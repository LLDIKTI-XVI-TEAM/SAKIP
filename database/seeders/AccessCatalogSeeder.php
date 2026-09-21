<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\PermissionCatalog;
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
        foreach (['superadmin' => 'Superadmin', 'admin' => 'Administrator', 'perencanaan' => 'Perencanaan', 'pimpinan' => 'Pimpinan', 'pegawai' => 'Pegawai'] as $code => $label) {
            // Rerun tidak mengaktifkan ulang peran/permission atau menimpa izin yang dikelola.
            Role::firstOrCreate(['kode' => $code], ['nama' => $label, 'urutan' => match ($code) {
                'superadmin' => 1, 'admin' => 2, 'perencanaan' => 3, 'pimpinan' => 4, 'pegawai' => 5
            }, 'is_sistem' => true, 'aktif' => true]);
        }
    }
}
