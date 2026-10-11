<?php

namespace App\Policies;

use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionDecision;

/** Izin kegiatan kolaboratif mengikuti unit objek; penugasan PJ indikator bukan prasyarat. */
class KegiatanPolicy
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    public function viewAny(User $user): bool
    {
        return $this->resolver->allows($user, 'kegiatan:read');
    }

    public function view(User $user, Kegiatan $kegiatan): bool
    {
        return $this->resolver->allows($user, 'kegiatan:read', (string) $kegiatan->unit_id);
    }

    public function create(User $user, string $unitId): bool
    {
        return $this->resolver->allows($user, 'kegiatan:create', $unitId);
    }

    public function update(User $user, Kegiatan $kegiatan): bool
    {
        return $this->resolver->allows($user, 'kegiatan:update', (string) $kegiatan->unit_id);
    }

    /** Perubahan unit memerlukan izin asal dan tujuan; payload tidak dapat menyamarkan unit objek. */
    public function keputusanSimpan(User $user, ?Kegiatan $kegiatan, string $unitId): PermissionDecision
    {
        $code = $kegiatan ? 'kegiatan:update' : 'kegiatan:create';
        $source = $this->resolver->resolve($user, $code, $kegiatan ? (string) $kegiatan->unit_id : $unitId);
        if (! $source->allowed || ! $kegiatan || (string) $kegiatan->unit_id === $unitId) {
            return $source;
        }

        $target = $this->resolver->resolve($user, $code, $unitId);

        return new PermissionDecision($target->allowed, $code, ['asal' => $source->toAuditBasis(), 'tujuan' => $target->toAuditBasis()]);
    }
}
