<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCodes;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RegulasiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $definitions = [
            PermissionCodes::REGULASI_CREATE => ['aksi' => 'create', 'sensitif' => false, 'keterangan' => 'Membuat dasar aturan'],
            PermissionCodes::REGULASI_READ => ['aksi' => 'read', 'sensitif' => false, 'keterangan' => 'Membaca dasar aturan'],
            PermissionCodes::REGULASI_UPDATE => ['aksi' => 'update', 'sensitif' => true, 'keterangan' => 'Mengubah dasar aturan'],
            PermissionCodes::REGULASI_DELETE => ['aksi' => 'delete', 'sensitif' => true, 'keterangan' => 'Menghapus dasar aturan'],
            PermissionCodes::BERKAS_DELETE => ['aksi' => 'delete', 'sensitif' => true, 'keterangan' => 'Menghapus bukti dukung atau lampiran'],
        ];

        foreach ($definitions as $name => $metadata) {
            Permission::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'entitas' => $name === PermissionCodes::BERKAS_DELETE ? 'berkas' : 'regulasi',
                    'aksi' => $metadata['aksi'],
                    'butuh_scope' => 'global',
                    'sensitif' => $metadata['sensitif'],
                    'aktif' => true,
                    'keterangan' => $metadata['keterangan'],
                ]
            );
        }

        $allPermissions = Permission::query()
            ->whereIn('name', PermissionCodes::regulasi())
            ->get();
        $readPermission = $allPermissions->firstWhere('name', PermissionCodes::REGULASI_READ);
        $berkasDeletePermission = Permission::findByName(PermissionCodes::BERKAS_DELETE, 'web');

        foreach (['superadmin', 'perencanaan'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo($allPermissions);

            if ($berkasDeletePermission !== null) {
                $role->givePermissionTo($berkasDeletePermission);
            }
        }

        foreach (['admin', 'pimpinan', 'pegawai'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($readPermission !== null) {
                $role->givePermissionTo($readPermission);
            }
        }

        // Q31: PIC adalah role resmi, tetapi preset permission-nya tetap OPEN.
        Role::firstOrCreate(['name' => 'pic', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
