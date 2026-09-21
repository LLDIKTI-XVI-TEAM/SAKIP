<?php

namespace Tests\Feature;

use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisBerkasPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_seeded_and_assigned_correctly(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $this->assertDatabaseHas('permissions', ['kode' => 'jenis_berkas:create']);
        $this->assertDatabaseHas('permissions', ['kode' => 'jenis_berkas:read']);
        $this->assertDatabaseHas('permissions', ['kode' => 'jenis_berkas:update']);
        $this->assertDatabaseHas('permissions', ['kode' => 'jenis_berkas:delete']);

        $perencanaanPermissions = RolePermissionPresets::forRole('perencanaan');
        $this->assertContains('jenis_berkas:create', $perencanaanPermissions);
        $this->assertContains('jenis_berkas:read', $perencanaanPermissions);
        $this->assertContains('jenis_berkas:update', $perencanaanPermissions);
        $this->assertContains('jenis_berkas:delete', $perencanaanPermissions);

        $adminPermissions = RolePermissionPresets::forRole('admin');
        $this->assertContains('jenis_berkas:read', $adminPermissions);
        $this->assertNotContains('jenis_berkas:create', $adminPermissions);
        $this->assertNotContains('jenis_berkas:update', $adminPermissions);
        $this->assertNotContains('jenis_berkas:delete', $adminPermissions);

        $pegawaiPermissions = RolePermissionPresets::forRole('pegawai');
        $this->assertContains('jenis_berkas:read', $pegawaiPermissions);
        $this->assertNotContains('jenis_berkas:create', $pegawaiPermissions);

        $pimpinanPermissions = RolePermissionPresets::forRole('pimpinan');
        $this->assertContains('jenis_berkas:read', $pimpinanPermissions);
        $this->assertNotContains('jenis_berkas:create', $pimpinanPermissions);
    }
}
