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

    /**
     * Perubahan unit memerlukan izin asal dan tujuan; payload tidak dapat menyamarkan unit objek.
     * Izin baca unit asal dan tujuan diperiksa terakhir (Q54 §54.3) agar alasan tulis tetap didahulukan.
     */
    public function keputusanSimpan(User $user, ?Kegiatan $kegiatan, string $unitId): PermissionDecision
    {
        $code = $kegiatan ? 'kegiatan:update' : 'kegiatan:create';
        $decision = $this->resolver->resolve($user, $code, $kegiatan ? (string) $kegiatan->unit_id : $unitId);
        if ($decision->allowed && $kegiatan && (string) $kegiatan->unit_id !== $unitId) {
            $target = $this->resolver->resolve($user, $code, $unitId);
            $decision = new PermissionDecision($target->allowed, $code, ['asal' => $decision->toAuditBasis(), 'tujuan' => $target->toAuditBasis()]);
        }
        if (! $decision->allowed) {
            return $decision;
        }
        foreach (array_unique([(string) ($kegiatan?->unit_id ?? $unitId), $unitId]) as $unit) {
            $baca = $this->resolver->resolve($user, 'kegiatan:read', $unit);
            if (! $baca->allowed) {
                return $baca;
            }
        }

        return $decision;
    }
}
