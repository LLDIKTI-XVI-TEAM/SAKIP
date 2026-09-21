<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCodes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegulasiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccessCatalogSeeder::class);

        $kodeRegulasi = [...PermissionCodes::regulasi(), ...PermissionCodes::berkas()];
        $permissionIds = Permission::query()
            ->whereIn('kode', $kodeRegulasi)
            ->pluck('id', 'kode');

        foreach ([
            'superadmin' => $kodeRegulasi,
            'perencanaan' => $kodeRegulasi,
            'admin' => [PermissionCodes::REGULASI_READ],
            'pimpinan' => [PermissionCodes::REGULASI_READ],
            'pegawai' => [PermissionCodes::REGULASI_READ],
        ] as $roleCode => $codes) {
            $role = Role::query()->where('kode', $roleCode)->firstOrFail();
            $pivot = [];

            foreach ($codes as $code) {
                $permissionId = $permissionIds->get($code);

                if (is_string($permissionId)) {
                    $pivot[$permissionId] = ['id' => (string) Str::uuid(), 'created_at' => now()];
                }
            }

            $role->permissions()->syncWithoutDetaching($pivot);
        }
    }
}
