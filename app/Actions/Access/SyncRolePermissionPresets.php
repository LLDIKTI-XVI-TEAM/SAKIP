<?php

namespace App\Actions\Access;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\RoleCatalog;
use App\Services\Authorization\RolePermissionPresets;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncRolePermissionPresets
{
    public function __construct(private WriteAuditLog $audit) {}

    /** Pratinjau CLI tidak menulis data; apply tetap memeriksa ulang di bawah lock. */
    public function preview(): array
    {
        $roles = Role::whereIn('kode', [...RoleCatalog::codes(), 'pic'])->get()->keyBy('kode');
        $permissions = Permission::whereIn('kode', PermissionCatalog::codes())->get()->keyBy('kode');
        $changes = [];
        foreach (RoleCatalog::codes() as $code) {
            $before = isset($roles[$code]) ? $this->membership($roles[$code]->id) : [];
            $after = RolePermissionPresets::forRole($code);
            $changes[$code] = ['add' => array_values(array_diff($after, $before)), 'remove' => array_values(array_diff($before, $after))];
        }
        $metadata = [];
        foreach (PermissionCatalog::codes() as $code) {
            $permission = $permissions->get($code);
            $attributes = $this->attributes($code, $permission);
            if (! $permission || $permission->fill($attributes)->isDirty()) {
                $metadata[] = $code;
            }
        }

        $pic = $roles->get('pic');
        $picReferences = $pic ? DB::table('user_roles')->where('role_id', $pic->id)->count() : 0;
        $willDeletePic = $pic !== null && $picReferences === 0
            && ! $roles->contains(fn (Role $role) => in_array($role->kode, RoleCatalog::codes(), true) && ! $role->aktif);

        return [
            'memberships' => $changes,
            'metadata' => $metadata,
            'pic_references' => $picReferences,
            'pic_cleanup' => [
                'role_id' => $pic?->id,
                'will_delete_role' => $willDeletePic,
                'permissions_to_remove' => $willDeletePic ? $this->membership($pic->id) : [],
                'user_references' => $picReferences,
            ],
            'inactive_roles' => $roles->filter(fn (Role $role) => ! $role->aktif)->keys()->all(),
            'legacy_read_grants' => DB::table('user_permission_granted')->join('permissions', 'permissions.id', '=', 'user_permission_granted.permission_id')
                ->whereIn('permissions.kode', ['rencana_aksi:read', 'kegiatan:read'])->whereNotNull('unit_id')->count(),
        ];
    }

    /** Katalog, preset, cleanup terbatas dan audit harus berhasil atau batal bersama. */
    public function handle(string $release, string $reason, string $runtimeIdentity): int
    {
        if (! app()->runningInConsole() || trim($release) === '' || trim($reason) === '' || trim($runtimeIdentity) === '') {
            throw new DomainException('Sinkronisasi preset memerlukan CLI, identitas rilis, alasan, dan konteks eksekusi.');
        }

        return DB::transaction(function () use ($release, $reason, $runtimeIdentity): int {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:initial-bootstrap']);
            $roles = Role::whereIn('kode', [...RoleCatalog::codes(), 'pic'])->orderBy('id')->lockForUpdate()->get()->keyBy('kode');
            $inactiveRoles = $roles->filter(fn (Role $role) => in_array($role->kode, RoleCatalog::codes(), true) && ! $role->aktif)->keys();
            if ($inactiveRoles->isNotEmpty()) {
                throw new DomainException('Sinkronisasi dibatalkan: role resmi masih nonaktif ('.$inactiveRoles->implode(', ').'). Tidak ada data yang diubah.');
            }
            $pic = $roles->get('pic');
            if ($pic && DB::table('user_roles')->where('role_id', $pic->id)->exists()) {
                throw new DomainException('Referensi pengguna role PIC masih ada; tetapkan pengganti per pengguna sebelum rilis.');
            }
            $provenance = ['actor_type' => 'system', 'sumber' => 'preset_release', 'runtime_identity' => $runtimeIdentity, 'alasan' => $release.': '.$reason];
            $events = 0;
            foreach (RoleCatalog::ROLES as $code => $definition) {
                if (! isset($roles[$code])) {
                    $roles[$code] = Role::create(['kode' => $code, 'nama' => $definition['nama'], 'urutan' => $definition['seed_urutan'], 'is_sistem' => true, 'aktif' => true]);
                    $this->audit->handle($provenance + ['tindakan' => 'roles.tambah', 'objek_tipe' => 'roles', 'objek_id' => $roles[$code]->id,
                        'nilai_lama' => null, 'nilai_baru' => $roles[$code]->only(['kode', 'nama', 'is_sistem', 'urutan', 'aktif'])]);
                    $events++;
                }
            }
            // Selaras dengan writer akses lain: role dahulu, lalu permission berurutan UUID.
            $permissions = Permission::whereIn('kode', PermissionCatalog::codes())->orderBy('id')->lockForUpdate()->get()->keyBy('kode');
            foreach (PermissionCatalog::codes() as $code) {
                $permission = $permissions->get($code);
                $before = $permission?->only(['entitas', 'aksi', 'butuh_scope', 'sensitif', 'keterangan']) ?? [];
                $attributes = $this->attributes($code, $permission);
                if (! $permission) {
                    $permission = Permission::create(['kode' => $code] + $attributes);
                    $changed = $attributes;
                } else {
                    $permission->fill($attributes);
                    $changed = $permission->getDirty();
                    if ($changed === []) {
                        continue;
                    }
                    $permission->save();
                }
                $permissions[$code] = $permission;
                $this->audit->handle($provenance + ['tindakan' => 'permissions.ubah', 'objek_tipe' => 'permissions', 'objek_id' => $permission->id,
                    'nilai_lama' => array_intersect_key($before, $changed), 'nilai_baru' => $permission->only(array_keys($changed))]);
                $events++;
            }
            foreach ($roles->sortBy('id') as $role) {
                $before = $this->membership($role->id);
                $after = $role->kode === 'pic' ? [] : RolePermissionPresets::forRole($role->kode);
                sort($after);
                if ($before !== $after) {
                    $ids = array_map(fn (string $code) => $permissions[$code]->id, $after);
                    DB::table('role_permissions')->where('role_id', $role->id)->whereNotIn('permission_id', $ids)->delete();
                    foreach (array_diff($after, $before) as $code) {
                        DB::table('role_permissions')->insert(['id' => Str::uuid(), 'role_id' => $role->id, 'permission_id' => $permissions[$code]->id, 'created_at' => now()]);
                    }
                    $this->audit->handle($provenance + ['tindakan' => 'role_permissions.ubah', 'objek_tipe' => 'roles', 'objek_id' => $role->id,
                        'nilai_lama' => ['permissions' => $before], 'nilai_baru' => ['permissions' => $after]]);
                    $events++;
                }
            }
            if ($pic) {
                $this->audit->handle($provenance + ['tindakan' => 'roles.hapus', 'objek_tipe' => 'roles', 'objek_id' => $pic->id,
                    'nilai_lama' => $pic->only(['kode', 'nama', 'is_sistem', 'urutan', 'aktif']), 'nilai_baru' => null]);
                // Pengecualian rilis hanya untuk PIC tanpa referensi; guard model role lain tetap berlaku.
                DB::table('roles')->where('id', $pic->id)->where('kode', 'pic')->delete();
                $events++;
            }

            return $events;
        });
    }

    /** @return list<string> */
    private function membership(string $roleId): array
    {
        return DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_id', $roleId)->orderBy('permissions.kode')->pluck('permissions.kode')->all();
    }

    private function attributes(string $code, ?Permission $permission): array
    {
        [$entity, $action] = explode(':', $code);

        return [
            'entitas' => $entity, 'aksi' => $action,
            'butuh_scope' => in_array($code, PermissionCatalog::UNIT_SCOPED, true) ? 'unit' : 'global',
            'sensitif' => in_array($code, PermissionCatalog::SENSITIVE, true),
            'keterangan' => PermissionCatalog::DESCRIPTION_OVERRIDES[$code]
                ?? ($permission && filled($permission->keterangan) ? $permission->keterangan : (PermissionCatalog::DEFAULT_DESCRIPTIONS[$code] ?? null)),
        ];
    }
}
