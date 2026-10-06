<?php

namespace App\Actions\TargetTahunan;

use App\Http\Requests\TargetTahunan\SaveTargetTahunanRequest;
use App\Models\TargetKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanDecimal;
use App\Support\TargetTahunanState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveTargetTahunan
{
    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /** ACL → indikator → sasaran → Renstra → target; lock indikator juga menserialkan pasangan absent. @param array<string, mixed> $data @return array<string, mixed> */
    public function handle(User $actor, string $indikatorId, int $tahun, array $data): array
    {
        $basis = [];
        try {
            return DB::transaction(function () use ($actor, $indikatorId, $tahun, $data, &$basis): array {
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
                $state = TargetTahunanState::lock($indikatorId, $tahun, writing: true);
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
                // Nilai existing yang tidak berubah tidak dipaksa mengikuti presisi yang baru diturunkan.
                if ($next['target_tahunan'] !== $before['target_tahunan']) {
                    TargetTahunanDecimal::assertPrecision($next['target_tahunan'], $state['indikator']->presisi);
                }
                $reason = trim(AuditReason::sanitize($data['alasan'] ?? null));
                $source = trim(AuditReason::sanitize($data['rujukan_sumber'] ?? null));
                if ($state['has_snapshot']) {
                    Validator::make(['alasan' => $reason, 'rujukan_sumber' => $source], ['alasan' => 'required', 'rujukan_sumber' => 'required'], SaveTargetTahunanRequest::inputMessages())->validate();
                }
                $target = $current ?? new TargetKinerja(['indikator_kinerja_id' => $state['indikator']->id, 'tahun' => $tahun]);
                $target->fill([...$next, 'updated_by' => $actor->id])->save();
                $this->audit->catat(actor: $actor, tindakan: 'target_tahunan.simpan', objekTipe: 'target_tahunan', objekId: $target->id,
                    nilaiLama: $current ? ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, ...$before] : null,
                    nilaiBaru: ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, ...$next, 'rujukan_sumber' => $source !== '' ? $source : null], alasan: $reason !== '' ? $reason : null, dasarIzin: $basis);

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
