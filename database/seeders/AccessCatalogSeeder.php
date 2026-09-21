<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Database\Seeder;

class AccessCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::codes() as $code) {
            [$entity, $action] = explode(':', $code);
            Permission::updateOrCreate(['kode' => $code], [
                'entitas' => $entity,
                'aksi' => $action,
                'butuh_scope' => in_array($code, PermissionCatalog::UNIT_SCOPED, true) ? 'unit' : 'global',
                'sensitif' => in_array($code, PermissionCatalog::SENSITIVE, true),
            ]);
        }
        foreach (RoleCatalog::ROLES as $code => $definition) {
            // Rerun tidak mengaktifkan ulang peran/permission atau menimpa izin yang dikelola.
            Role::firstOrCreate(['kode' => $code], ['nama' => $definition['nama'], 'urutan' => $definition['seed_urutan'], 'is_sistem' => true, 'aktif' => true]);
        }
    }
}
