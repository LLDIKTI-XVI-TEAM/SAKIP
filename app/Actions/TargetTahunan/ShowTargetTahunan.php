<?php

namespace App\Actions\TargetTahunan;

use App\Models\TargetRenstra;
use App\Models\User;
use App\Policies\TargetKinerjaPolicy;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\DeviasiTargetRenstra;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanDecimal;
use App\Support\TargetTahunanState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ShowTargetTahunan
{
    public function __construct(private readonly TargetKinerjaPolicy $policy, private readonly ResolveLockedActor $lockedActor) {}

    /** Satu pasangan, metadata/capability dan token berasal dari state terkunci yang sama. @return array<string, mixed> */
    public function handle(User $actor, string $indikatorId, int $tahun, ?string $renstraId = null): array
    {
        $this->policy->view($actor)->authorize();

        return DB::transaction(function () use ($actor, $indikatorId, $tahun, $renstraId): array {
            $read = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_READ);
            if (! $read['keputusan']->allowed) {
                throw new AuthorizationException('Izin membaca indikator tidak lagi berlaku.');
            }
            $write = $this->lockedActor->handle($actor, PermissionCodes::TARGET_UPDATE);
            $state = TargetTahunanState::lock($indikatorId, $tahun, renstraId: $renstraId);
            $reason = TargetTahunanState::readOnlyReason($state) ?? (! $write['keputusan']->allowed ? 'Anda tidak memiliki izin mengubah target.' : null);
            $values = TargetTahunanState::values($state['target']);
            $targetRenstra = TargetTahunanDecimal::normalize(TargetRenstra::where('indikator_id', $state['indikator']->id)->where('tahun', $tahun)->value('nilai'));

            return ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, 'baseline_year' => $tahun - 1,
                'indikator' => $state['indikator']->only(['id', 'kode', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'status', 'tahun_mulai_berlaku', 'arah']),
                'renstra' => $state['renstra']->only(['id', 'nama', 'status', 'tahun_mulai', 'tahun_selesai']),
                ...$values, 'has_snapshot' => $state['has_snapshot'], 'expected_state' => TargetTahunanState::token($state),
                'target_renstra' => $targetRenstra, 'alasan_deviasi_renstra' => $state['target']?->alasan_deviasi_renstra,
                'status_deviasi' => DeviasiTargetRenstra::status($state['indikator']->arah, $values['target_tahunan'], $targetRenstra),
                'can' => ['update' => $reason === null], 'read_only_reason' => $reason];
        });
    }
}
