<?php

namespace App\Actions\TargetRenstra;

use App\Http\Requests\TargetRenstra\SimpanTargetRenstraRequest;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use App\Support\TargetRenstraState;
use App\Support\TargetTahunanDecimal;
use App\Support\TargetTahunanState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Satu simpan per indikator untuk semua tahun Renstra; tanpa alasan, tetapi setiap tahun yang berubah diaudit. */
class SimpanTargetRenstra
{
    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /**
     * ACL → indikator → sasaran → Renstra → baris target; lock indikator juga menserialkan insert pertama per tahun.
     *
     * @param  array<string, mixed>  $data
     * @return array{indikator_id: string, renstra_id: string, tahun: list<int>}
     */
    public function handle(User $actor, string $indikatorId, array $data): array
    {
        $basis = [];
        try {
            return DB::transaction(function () use ($actor, $indikatorId, $data, &$basis): array {
                foreach ([PermissionCodes::INDIKATOR_READ, PermissionCodes::TARGET_UPDATE] as $permission) {
                    $basis[$permission] = $this->lockedActor->handle($actor, $permission)['keputusan']->toAuditBasis();
                }
                if ($basis[PermissionCodes::INDIKATOR_READ]['keputusan'] !== 'diizinkan' || $basis[PermissionCodes::TARGET_UPDATE]['keputusan'] !== 'diizinkan') {
                    throw new AuthorizationException('Menyimpan target Renstra memerlukan kedua izin yang efektif.');
                }
                if (array_diff(array_keys($data), SimpanTargetRenstraRequest::FIELDS) !== []) {
                    throw ValidationException::withMessages(['nilai' => 'Permintaan memuat field yang tidak didukung.']);
                }
                $data = Validator::make($data, SimpanTargetRenstraRequest::inputRules(), SimpanTargetRenstraRequest::inputMessages())->validate();
                $state = TargetRenstraState::lock($indikatorId);
                if (($reason = TargetTahunanState::readOnlyReason($state)) !== null) {
                    throw ValidationException::withMessages(['nilai' => $reason]);
                }
                $indikator = $state['indikator'];
                $before = TargetRenstraState::values($state['rows']);
                if (! hash_equals(TargetRenstraState::token($indikator, $state['renstra'], $before), $data['expected_state'])) {
                    throw ValidationException::withMessages(['expected_state' => 'Data sudah berubah. Muat kembali data terbaru sebelum menyimpan.'])->status(409);
                }

                $changes = [];
                foreach ($data['nilai'] as $tahun => $raw) {
                    if (! is_int($tahun) || ! TargetTahunanState::tahunBerlaku($indikator, $state['renstra'], $tahun)) {
                        throw ValidationException::withMessages(['nilai' => 'Tahun harus berada dalam rentang Renstra dan tidak sebelum indikator mulai berlaku.']);
                    }
                    $next = TargetTahunanDecimal::normalize($raw, "nilai.{$tahun}");
                    // Nilai lama yang tidak berubah tidak dipaksa mengikuti presisi yang baru diturunkan.
                    if ($next !== ($before[$tahun] ?? null)) {
                        TargetTahunanDecimal::assertPrecision($next, $indikator->presisi, "nilai.{$tahun}");
                        $changes[$tahun] = $next;
                    }
                }
                ksort($changes);

                foreach ($changes as $tahun => $next) {
                    $current = $state['rows'][$tahun] ?? null;
                    $row = $current ?? new TargetRenstra(['indikator_id' => $indikator->id, 'tahun' => $tahun]);
                    $row->fill(['nilai' => $next, 'updated_by' => $actor->id])->save();
                    $this->audit->catat(actor: $actor, tindakan: 'target_renstra.simpan', objekTipe: 'target_renstra', objekId: $row->id,
                        nilaiLama: $current ? ['indikator_id' => $indikator->id, 'tahun' => $tahun, 'nilai' => $before[$tahun]] : null,
                        nilaiBaru: ['indikator_id' => $indikator->id, 'tahun' => $tahun, 'nilai' => $next], dasarIzin: $basis);
                }

                return ['indikator_id' => $indikator->id, 'renstra_id' => $state['renstra']->id, 'tahun' => array_keys($changes)];
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Penolakan di luar rollback; tanpa payload mentah, token, atau nilai objek.
            $this->audit->catat(actor: $actor, tindakan: 'target_renstra.simpan_ditolak', objekTipe: 'indikator', objekId: $indikatorId,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'input_atau_state_tidak_valid'], dasarIzin: $basis);
            throw $exception;
        }
    }
}
