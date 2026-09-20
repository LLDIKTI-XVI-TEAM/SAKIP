<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JenisBerkasPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_seeded_and_assigned_correctly(): void
    {
        $this->seed(DatabaseSeeder::class);

        $perencanaan = Role::findByName('perencanaan', 'web');
        $this->assertTrue($perencanaan->hasPermissionTo('jenis_berkas:create'));
        $this->assertTrue($perencanaan->hasPermissionTo('jenis_berkas:read'));
        $this->assertTrue($perencanaan->hasPermissionTo('jenis_berkas:update'));
        $this->assertTrue($perencanaan->hasPermissionTo('jenis_berkas:delete'));

        $admin = Role::findByName('admin', 'web');
        $this->assertTrue($admin->hasPermissionTo('jenis_berkas:read'));
        $this->assertFalse($admin->hasPermissionTo('jenis_berkas:create'));
        $this->assertFalse($admin->hasPermissionTo('jenis_berkas:update'));
        $this->assertFalse($admin->hasPermissionTo('jenis_berkas:delete'));

        $pegawai = Role::findByName('pegawai', 'web');
        $this->assertTrue($pegawai->hasPermissionTo('jenis_berkas:read'));
        $this->assertFalse($pegawai->hasPermissionTo('jenis_berkas:create'));

        $pimpinan = Role::findByName('pimpinan', 'web');
        $this->assertTrue($pimpinan->hasPermissionTo('jenis_berkas:read'));
        $this->assertFalse($pimpinan->hasPermissionTo('jenis_berkas:create'));
    }
}
