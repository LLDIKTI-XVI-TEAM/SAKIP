<?php

namespace App\Actions\TargetTahunan;

use App\Http\Requests\TargetTahunan\SaveTargetTahunanRequest;
use App\Models\TargetKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\AuditReason;
use App\Support\DeviasiTargetRenstra;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanDecimal;
use App\Support\TargetTahunanState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveTargetTahunan
{
    /** Q72 §72.4 butir 6: arah, tahun, dan target Renstra tersimpan tanpa pembulatan tampilan. */
    private const PESAN_DEVIASI = 'Target PK %s target Renstra %d (%s). Revisi target Renstra dulu.';

    private const PESAN_ALASAN_KOSONG = 'Isi alasan perubahan karena target PK yang tersimpan diubah.';

    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /** ACL → indikator → sasaran → Renstra → target; lock indikator juga menserialkan pasangan absent. @param array<string, mixed> $data @return array<string, mixed> */
    public function handle(User $actor, string $indikatorId, int $tahun, array $data, ?string $renstraId = null): array
    {
        $basis = [];
        $penolakan = null;
        try {
            return DB::transaction(function () use ($actor, $indikatorId, $tahun, $data, $renstraId, &$basis, &$penolakan): array {
                foreach ([PermissionCodes::INDIKATOR_READ, PermissionCodes::TARGET_UPDATE] as $permission) {
                    $locked = $this->lockedActor->handle($actor, $permission);
                    $basis[$permission] = $locked['keputusan']->toAuditBasis();
                }
                if ($basis[PermissionCodes::INDIKATOR_READ]['keputusan'] !== 'diizinkan' || $basis[PermissionCodes::TARGET_UPDATE]['keputusan'] !== 'diizinkan') {
                    throw new AuthorizationException('Menyimpan target memerlukan kedua izin yang efektif.');
                }
                $rules = SaveTargetTahunanRequest::inputRules();
                if (array_diff(array_keys($data), array_keys($rules)) !== []) {
                    throw ValidationException::withMessages(['target_tahunan' => 'Permintaan memuat field yang tidak didukung.']);
                }
                $data = Validator::make($data, $rules, SaveTargetTahunanRequest::inputMessages())->validate();
                $next = ['baseline' => TargetTahunanDecimal::normalize($data['baseline'], 'baseline'), 'target_tahunan' => TargetTahunanDecimal::normalize($data['target_tahunan'])];
                $state = TargetTahunanState::lock($indikatorId, $tahun, writing: true, renstraId: $renstraId);
                if (($reason = TargetTahunanState::readOnlyReason($state)) !== null) {
                    throw ValidationException::withMessages(['target_tahunan' => $reason]);
                }
                if (! hash_equals(TargetTahunanState::token($state), $data['expected_state'])) {
                    throw ValidationException::withMessages(['expected_state' => 'Data sudah berubah. Muat kembali data terbaru sebelum menyimpan.'])->status(409);
                }
                $current = $state['target'];
                $before = TargetTahunanState::values($current);
                $outcome = ['target_id' => $current?->id, 'indikator_id' => $state['indikator']->id, 'tahun' => $tahun, 'operation_id' => strtolower($data['operation_id'])];
                if ($next === $before) {
                    return [...$outcome, 'changed' => false];
                }
                $targetBerubah = $next['target_tahunan'] !== $before['target_tahunan'];
                if ($targetBerubah) {
                    // Nilai existing yang tidak berubah tidak dipaksa mengikuti presisi yang baru diturunkan.
                    TargetTahunanDecimal::assertPrecision($next['target_tahunan'], $state['indikator']->presisi);
                    // Q72 §72.4 butir 1: deviasi hanya dinilai bila target berubah, agar baris lama yang kini menyimpang tetap dapat menyimpan baseline.
                    // Target Renstra dibaca sesudah lock indikator; penulisnya mengunci indikator yang sama lebih dulu.
                    $arah = $state['indikator']->arah;
                    $targetRenstra = TargetTahunanDecimal::normalize(TargetRenstra::where('indikator_id', $state['indikator']->id)->where('tahun', $tahun)->value('nilai'));
                    if (DeviasiTargetRenstra::status($arah, $next['target_tahunan'], $targetRenstra) === DeviasiTargetRenstra::MENYIMPANG) {
                        $penolakan = 'target_menyimpang_renstra';
                        throw ValidationException::withMessages(['deviasi_renstra' => sprintf(self::PESAN_DEVIASI,
                            $arah === 'turun_baik' ? 'di atas' : 'di bawah', $tahun, str_replace('.', ',', $targetRenstra))]);
                    }
                }
                $reason = trim(AuditReason::sanitize($data['alasan'] ?? null));
                // Q72 §72.2 butir 1–2 dan §72.4 butir 2: alasan wajib hanya bila target tersimpan berubah, termasuk dikosongkan
                // dan setelah snapshot (snapshot tidak ikut berubah). Pembanding `''` agar alasan "0" tetap sah.
                if ($targetBerubah && $before['target_tahunan'] !== null && $reason === '') {
                    $penolakan = 'alasan_perubahan_kosong';
                    throw ValidationException::withMessages(['alasan' => self::PESAN_ALASAN_KOSONG]);
                }
                $source = trim(AuditReason::sanitize($data['rujukan_sumber'] ?? null));
                $target = $current ?? new TargetKinerja(['indikator_kinerja_id' => $state['indikator']->id, 'tahun' => $tahun]);
                // Q72 §72.2 butir 4: `alasan_deviasi_renstra` tidak lagi diisi; nilai lama kolom tetap untuk histori.
                $target->fill([...$next, 'updated_by' => $actor->id])->save();
                $this->audit->catat(actor: $actor, tindakan: 'target_tahunan.simpan', objekTipe: 'target_tahunan', objekId: $target->id,
                    nilaiLama: $current ? ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, ...$before] : null,
                    nilaiBaru: ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, ...$next,
                        'rujukan_sumber' => $source !== '' ? $source : null], alasan: $reason !== '' ? $reason : null, dasarIzin: $basis);

                return [...$outcome, 'target_id' => $target->id, 'changed' => true];
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Penolakan di luar rollback; tidak menyimpan payload mentah, token, nilai yang dicoba, atau snapshot nilai objek.
            $this->audit->catat(actor: $actor, tindakan: 'target_tahunan.simpan_ditolak', objekTipe: 'indikator', objekId: $indikatorId,
                nilaiBaru: ['tahun' => $tahun, 'alasan_penolakan' => $penolakan
                    ?? ($exception instanceof AuthorizationException ? 'izin_ditolak' : 'input_atau_state_tidak_valid')], dasarIzin: $basis);
            throw $exception;
        }
    }
}
