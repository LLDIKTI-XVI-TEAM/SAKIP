<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UserPermissionGrant;
use App\Services\Authorization\PermissionCatalog;
use Illuminate\Support\Str;

class IndexGrant
{
    /**
     * Daftar grant izin tambahan ber-unit beserta pilihan unit aktif dan permission yang boleh didelegasikan.
     * Filter unit yang bukan UUID unit aktif jatuh kembali ke "all" agar query tidak membocorkan keberadaan unit nonaktif.
     *
     * @return array<string, mixed>
     */
    public function handle(string $search, mixed $unitId): array
    {
        $grantsQuery = UserPermissionGrant::with([
            'user:id,nama,email,status',
            'user.roles:id,nama,kode,aktif',
            'permission:id,kode,keterangan,butuh_scope,aktif',
            'unit:id,nama,status',
            'diberikanOleh:id,nama',
        ])
            ->whereNotNull('unit_id')
            ->whereHas('permission', fn ($q) => $q->where('butuh_scope', Permission::SCOPE_UNIT));

        if ($search !== '') {
            $kodeBerlabel = array_keys(array_filter(PermissionCatalog::LABEL_IZIN_UNIT, fn (string $label) => mb_stripos($label, $search) !== false));
            $grantsQuery->where(function ($q) use ($search, $kodeBerlabel) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                })
                    ->orWhereHas('permission', function ($pq) use ($search, $kodeBerlabel) {
                        $pq->where('kode', 'ilike', "%{$search}%")
                            ->orWhere('keterangan', 'ilike', "%{$search}%")
                            ->orWhereIn('kode', $kodeBerlabel);
                    })
                    ->orWhereHas('unit', function ($uq) use ($search) {
                        $uq->where('nama', 'ilike', "%{$search}%");
                    });
            });
        }

        if (! empty($unitId) && $unitId !== 'all') {
            if (is_string($unitId) && Str::isUuid($unitId) && Unit::where('id', $unitId)->where('status', 'aktif')->exists()) {
                $grantsQuery->where('unit_id', $unitId);
            } else {
                $unitId = 'all';
            }
        }

        $grants = $grantsQuery->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (UserPermissionGrant $grant) {
                return [
                    'id' => $grant->id,
                    'user_id' => $grant->user_id,
                    'user_name' => $grant->user?->nama ?? '-',
                    'user_email' => $grant->user?->email ?? '-',
                    'user_roles' => $grant->user?->roles->map(fn (Role $role) => $role->aktif ? $role->nama : "{$role->nama} (nonaktif)")->all() ?? [],
                    'permission_id' => $grant->permission_id,
                    'permission_kode' => $grant->permission?->kode,
                    'permission_label' => PermissionCatalog::LABEL_IZIN_UNIT[$grant->permission?->kode] ?? $grant->permission?->keterangan ?? $grant->permission?->kode,
                    'unit_id' => $grant->unit_id,
                    'unit_nama' => $grant->unit?->nama,
                    'alasan' => $grant->alasan,
                    'diberikan_oleh_nama' => $grant->diberikanOleh?->nama ?? '-',
                    'created_at' => $grant->created_at?->toISOString(),
                    // Kriteria sama dengan PermissionResolver: akun, izin katalog, dan unit harus aktif agar grant berlaku.
                    'tidak_berlaku' => array_keys(array_filter([
                        'Akun nonaktif' => $grant->user?->status !== 'aktif',
                        'Izin nonaktif' => ! $grant->permission?->aktif,
                        'Unit nonaktif' => $grant->unit?->status !== 'aktif',
                    ])),
                ];
            });

        $units = Unit::where('status', 'aktif')
            ->select('id', 'nama')
            ->orderBy('nama')
            ->get();

        $unitPermissions = Permission::where('butuh_scope', Permission::SCOPE_UNIT)
            ->where('aktif', true)
            ->whereIn('kode', PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS)
            ->select('id', 'kode')
            ->get()
            ->sortBy(fn (Permission $p) => array_search($p->kode, PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS, true))
            ->values()
            ->map(fn (Permission $p) => [
                'id' => $p->id,
                'kode' => $p->kode,
                'label' => PermissionCatalog::LABEL_IZIN_UNIT[$p->kode],
            ]);

        return [
            'grants' => $grants,
            'filters' => [
                'search' => $search,
                'unit_id' => $unitId ?? 'all',
            ],
            'units' => $units,
            'unitPermissions' => $unitPermissions,
            'can' => [
                'create_grant' => true,
                'revoke_grant' => true,
            ],
        ];
    }
}
