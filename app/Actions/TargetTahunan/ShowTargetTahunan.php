<?php

namespace App\Actions\TargetTahunan;

use App\Models\User;
use App\Policies\TargetKinerjaPolicy;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ShowTargetTahunan
{
    public function __construct(private readonly TargetKinerjaPolicy $policy, private readonly ResolveLockedActor $lockedActor) {}

    /** Satu pasangan, metadata/capability dan token berasal dari state terkunci yang sama. @return array<string, mixed> */
    public function handle(User $actor, string $indikatorId, int $tahun): array
    {
        $this->policy->view($actor)->authorize();

        return DB::transaction(function () use ($actor, $indikatorId, $tahun): array {
            $read = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_READ);
            if (! $read['keputusan']->allowed) {
                throw new AuthorizationException('Izin membaca indikator tidak lagi berlaku.');
            }
            $write = $this->lockedActor->handle($actor, PermissionCodes::TARGET_UPDATE);
            $state = TargetTahunanState::lock($indikatorId, $tahun);
            $reason = TargetTahunanState::readOnlyReason($state) ?? (! $write['keputusan']->allowed ? 'Anda tidak memiliki izin mengubah target.' : null);

            return ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, 'baseline_year' => $tahun - 1,
                'indikator' => $state['indikator']->only(['id', 'kode', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'status', 'tahun_mulai_berlaku']),
                'renstra' => $state['renstra']->only(['id', 'nama', 'status', 'tahun_mulai', 'tahun_selesai']),
                ...TargetTahunanState::values($state['target']), 'has_snapshot' => $state['has_snapshot'], 'expected_state' => TargetTahunanState::token($state),
                'can' => ['update' => $reason === null], 'read_only_reason' => $reason];
        });
    }
}
