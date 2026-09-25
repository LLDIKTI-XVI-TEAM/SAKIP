<?php

namespace Database\Seeders;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\RoleCatalog;
use App\Services\Authorization\RolePermissionPresets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncRolePermissionPresetsSeeder extends Seeder
{
    public function __construct(private ?WriteAuditLog $audit = null)
    {
        $this->audit = $audit ?? app(WriteAuditLog::class);
    }

    /**
     * Menyelaraskan role_permissions dengan RolePermissionPresets secara idempoten.
     * Sesuai PRD §7.3, perubahan preset peran dilakukan melalui rilis kode + seeder dengan audit delta.
     * Hanya dieksekusi bila sistem sudah melewati bootstrap pertama (role_permissions tidak kosong).
     */
    public function run(): void
    {
        // Pastikan katalog permission dan roles terbaru sudah ada
        $this->call(AccessCatalogSeeder::class);

        // Jika sistem belum pernah di-bootstrap, jangan pasang hak akses agar bootstrap awal tetap bersih
        $hasBootstrapped = DB::table('auth_bootstraps')->where('id', 'initial')->exists()
            || DB::table('role_permissions')->exists();

        if (! $hasBootstrapped) {
            return;
        }

        $allPermissions = Permission::where('aktif', true)->pluck('id', 'kode');

        foreach (RoleCatalog::codes() as $roleKode) {
            if (! RolePermissionPresets::hasDefinedPreset($roleKode)) {
                continue;
            }

            $role = Role::where('kode', $roleKode)->first();
            if (! $role) {
                continue;
            }

            $expectedCodes = RolePermissionPresets::forRole($roleKode);
            sort($expectedCodes);

            $currentPermissions = DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role_id', $role->id)
                ->pluck('permissions.kode')
                ->all();
            sort($currentPermissions);

            $toAdd = array_values(array_diff($expectedCodes, $currentPermissions));
            $toRemove = array_values(array_diff($currentPermissions, $expectedCodes));

            if ($toAdd === [] && $toRemove === []) {
                continue;
            }

            DB::transaction(function () use ($role, $allPermissions, $currentPermissions, $expectedCodes, $toAdd, $toRemove) {
                foreach ($toAdd as $addCode) {
                    if (isset($allPermissions[$addCode])) {
                        DB::table('role_permissions')->insert([
                            'id' => (string) Str::uuid(),
                            'role_id' => $role->id,
                            'permission_id' => $allPermissions[$addCode],
                            'created_at' => now(),
                        ]);
                    }
                }

                if ($toRemove !== []) {
                    $removePermissionIds = Permission::whereIn('kode', $toRemove)->pluck('id')->all();
                    DB::table('role_permissions')
                        ->where('role_id', $role->id)
                        ->whereIn('permission_id', $removePermissionIds)
                        ->delete();
                }

                $this->audit->handle([
                    'actor_type' => 'operator',
                    'sumber' => 'bootstrap',
                    'operator_reference' => 'system:seeder',
                    'runtime_identity' => 'artisan:db:seed:sync-role-presets',
                    'tindakan' => 'role_permissions.ubah',
                    'objek_tipe' => 'roles',
                    'objek_id' => $role->id,
                    'nilai_lama' => ['permissions' => $currentPermissions],
                    'nilai_baru' => ['permissions' => $expectedCodes],
                    'alasan' => 'Sinkronisasi preset peran sesuai rilis kode (PRD §7.3)',
                ]);
            });
        }
    }
}
