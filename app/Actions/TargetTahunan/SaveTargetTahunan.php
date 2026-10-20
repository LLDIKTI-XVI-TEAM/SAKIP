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
    private const PESAN_DEVIASI_NAIK = 'Target PK lebih rendah dari target Renstra tahun ini. Isi alasan deviasi untuk menyimpan.';

    private const PESAN_DEVIASI_TURUN = 'Target PK lebih tinggi dari target Renstra tahun ini, padahal indikator ini membaik bila nilainya turun. Isi alasan deviasi untuk menyimpan.';

    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /** ACL → indikator → sasaran → Renstra → target; lock indikator juga menserialkan pasangan absent. @param array<string, mixed> $data @return array<string, mixed> */
    public function handle(User $actor, string $indikatorId, int $tahun, array $data, ?string $renstraId = null): array
    {
        $basis = [];
        try {
            return DB::transaction(function () use ($actor, $indikatorId, $tahun, $data, $renstraId, &$basis): array {
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
                // Target Renstra dibaca sesudah lock indikator; penulisnya mengunci indikator yang sama lebih dulu.
                $arah = $state['indikator']->arah;
                $deviasi = DeviasiTargetRenstra::status($arah, $next['target_tahunan'], TargetTahunanDecimal::normalize(
                    TargetRenstra::where('indikator_id', $state['indikator']->id)->where('tahun', $tahun)->value('nilai')));
                $alasanDeviasi = $deviasi === DeviasiTargetRenstra::MENYIMPANG ? trim(AuditReason::sanitize($data['alasan_deviasi_renstra'] ?? null)) : '';
                $alasanDeviasi = $alasanDeviasi !== '' ? $alasanDeviasi : null;
                $alasanSebelum = $current?->alasan_deviasi_renstra;
                if ($next === $before && $alasanDeviasi === $alasanSebelum) {
                    return [...$outcome, 'changed' => false];
                }
                // Nilai existing yang tidak berubah tidak dipaksa mengikuti presisi yang baru diturunkan.
                if ($next['target_tahunan'] !== $before['target_tahunan']) {
                    TargetTahunanDecimal::assertPrecision($next['target_tahunan'], $state['indikator']->presisi);
                }
                // Q65 §65.2: nilai menyimpang tidak ditolak; yang ditolak adalah penyimpanan tanpa alasannya.
                if ($deviasi === DeviasiTargetRenstra::MENYIMPANG && $alasanDeviasi === null) {
                    throw ValidationException::withMessages(['alasan_deviasi_renstra' => $arah === 'turun_baik' ? self::PESAN_DEVIASI_TURUN : self::PESAN_DEVIASI_NAIK]);
                }
                // Q65 §65.1: alasan dan rujukan opsional juga setelah snapshot; snapshot tidak ikut berubah.
                $reason = trim(AuditReason::sanitize($data['alasan'] ?? null));
                $source = trim(AuditReason::sanitize($data['rujukan_sumber'] ?? null));
                $target = $current ?? new TargetKinerja(['indikator_kinerja_id' => $state['indikator']->id, 'tahun' => $tahun]);
                $target->fill([...$next, 'alasan_deviasi_renstra' => $alasanDeviasi, 'updated_by' => $actor->id])->save();
                $this->audit->catat(actor: $actor, tindakan: 'target_tahunan.simpan', objekTipe: 'target_tahunan', objekId: $target->id,
                    nilaiLama: $current ? ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, ...$before, 'alasan_deviasi_renstra' => $alasanSebelum] : null,
                    nilaiBaru: ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, ...$next, 'alasan_deviasi_renstra' => $alasanDeviasi,
                        'rujukan_sumber' => $source !== '' ? $source : null], alasan: $reason !== '' ? $reason : null, dasarIzin: $basis);

                return [...$outcome, 'target_id' => $target->id, 'changed' => true];
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Penolakan di luar rollback; tidak menyimpan payload mentah, token, atau snapshot nilai objek.
            $this->audit->catat(actor: $actor, tindakan: 'target_tahunan.simpan_ditolak', objekTipe: 'indikator', objekId: $indikatorId,
                nilaiBaru: ['tahun' => $tahun, 'alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'input_atau_state_tidak_valid'], dasarIzin: $basis);
            throw $exception;
        }
    }
}
