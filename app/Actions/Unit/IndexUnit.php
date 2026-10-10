<?php

namespace App\Actions\Unit;

use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;

class IndexUnit
{
    public function __construct(private PermissionResolver $resolver) {}

    /**
     * Daftar seluruh unit beserta hitungan relasi dan capability per baris bagi aktor yang sudah lolos Gate viewAny.
     * Hapus hanya untuk Superadmin dengan izin dan unit tanpa relasi; daftar sengaja tidak dipaginasi (kontrak existing).
     *
     * @return array<string, mixed>
     */
    public function handle(User $actor): array
    {
        $canCreate = $this->resolver->allows($actor, 'unit:create');
        $canUpdate = $this->resolver->allows($actor, 'unit:update');
        $canDeleteUnit = $actor->hasRole('superadmin') && $this->resolver->allows($actor, 'unit:delete');

        $units = Unit::withCount(['indikators', 'rencanaAksis', 'kegiatans', 'permissionGrants', 'permissionDenies', 'jadwalSnapshots'])
            ->orderBy('nama')
            ->get()
            ->map(function (Unit $unit) use ($canUpdate, $canDeleteUnit) {
                $isDeletable = $unit->isDeletable();

                return [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'status' => $unit->status,
                    'is_active' => $unit->status === 'aktif',
                    'version_token' => $unit->getVersionToken(),
                    'snapshot' => $unit->toSnapshot(),
                    'expected_nama' => $unit->nama,
                    'expected_status' => $unit->status,
                    'indikators_count' => $unit->indikators_count,
                    'rencana_aksis_count' => $unit->rencana_aksis_count,
                    'kegiatans_count' => $unit->kegiatans_count,
                    'grants_count' => $unit->permission_grants_count,
                    'denies_count' => $unit->permission_denies_count,
                    'jadwal_snapshots_count' => $unit->jadwal_snapshots_count,
                    'is_deletable' => $isDeletable,
                    'can' => [
                        'update' => $canUpdate,
                        'delete' => $canDeleteUnit && $isDeletable,
                    ],
                ];
            });

        return [
            'units' => $units,
            'can' => [
                'create' => $canCreate,
            ],
        ];
    }
}
