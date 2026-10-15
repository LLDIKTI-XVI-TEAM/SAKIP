<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Validation\ValidationException;

class GetRolePermissionPage
{
    /**
     * Katalog peran resmi dan, bila satu peran dipilih, membership permission aktualnya secara read-only.
     * Peran di luar katalog ditolak sebagai validasi, bukan 404, agar halaman tetap menampilkan daftar peran.
     *
     * @return array<string, mixed>
     */
    public function handle(?string $roleId, string $search, int $page): array
    {
        $catalog = Role::whereIn('kode', RoleCatalog::codes())->get(['id', 'kode', 'nama', 'aktif'])->keyBy('kode');
        $roles = [];
        foreach (RoleCatalog::codes() as $code) {
            if ($role = $catalog->get($code)) {
                $roles[] = $role->only(['id', 'kode', 'nama', 'aktif']);
            }
        }
        $filters = ['role' => $roleId, 'q' => $search];
        $props = ['roles' => $roles, 'selectedRole' => null, 'permissions' => [],
            'pagination' => ['links' => [], 'from' => null, 'to' => null, 'total' => 0],
            'filters' => $filters, 'can' => ['viewRolePermissions' => true]];
        if ($filters['role'] !== null) {
            $role = $catalog->firstWhere('id', $filters['role']);
            if (! $role) {
                throw ValidationException::withMessages(['role' => 'Pilih peran resmi.']);
            }
            // `%`/`_` dari pengguna dicari sebagai teks, bukan wildcard SQL.
            $pattern = '%'.addcslashes($filters['q'], '\\%_').'%';
            // Baca membership aktual; metadata nonaktif/legacy bukan klaim izin efektif pengguna.
            $pageResult = Permission::whereIn('id', function ($query) use ($role) {
                $query->select('permission_id')->from('role_permissions')->where('role_id', $role->id);
            })->when($filters['q'] !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('kode', 'ilike', $pattern)->orWhere('keterangan', 'ilike', $pattern)))
                ->orderBy('kode')->paginate(20, ['id', 'kode', 'keterangan', 'butuh_scope', 'aktif'], 'page', $page)
                ->withPath(route('role-permission.index'))->appends($filters);
            $props['selectedRole'] = $role->only(['id', 'kode', 'nama', 'aktif']);
            $props['permissions'] = $pageResult->getCollection()->map(fn (Permission $permission) => $permission->only(['id', 'kode', 'keterangan', 'butuh_scope', 'aktif'])
                + ['in_catalog' => in_array($permission->kode, PermissionCatalog::codes(), true)])->all();
            $props['pagination'] = ['links' => $pageResult->linkCollection()->all(), 'from' => $pageResult->firstItem(), 'to' => $pageResult->lastItem(), 'total' => $pageResult->total()];
        }

        return $props;
    }
}
