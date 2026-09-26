<?php

namespace Tests\Feature\Authorization;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\SyncRolePermissionPresetsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncRolePermissionPresetsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_skips_when_system_not_yet_bootstrapped(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $this->assertDatabaseCount('role_permissions', 0);

        $this->seed(SyncRolePermissionPresetsSeeder::class);

        // Harus tetap 0 agar tidak mengganggu bootstrap awal
        $this->assertDatabaseCount('role_permissions', 0);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_seeder_syncs_permissions_and_writes_audit_when_system_bootstrapped(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $audit = AuditLog::create([
            'actor_type' => 'operator',
            'sumber' => 'bootstrap',
            'operator_reference' => 'operator-test',
            'runtime_identity' => 'test@phpunit',
            'waktu' => now(),
            'tindakan' => 'auth.bootstrap',
            'objek_tipe' => 'users',
            'objek_id' => $user->id,
            'alasan' => 'Initial bootstrap',
        ]);

        DB::table('auth_bootstraps')->insert([
            'id' => 'initial',
            'user_id' => $user->id,
            'audit_id' => $audit->id,
            'created_at' => now(),
        ]);

        $this->seed(SyncRolePermissionPresetsSeeder::class);

        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $adminRole = Role::where('kode', 'admin')->firstOrFail();
        $perencanaanRole = Role::where('kode', 'perencanaan')->firstOrFail();
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();

        // delegasi:update harus terpasang di superadmin, admin, perencanaan
        $delegasiPerm = Permission::where('kode', 'delegasi:update')->firstOrFail();
        $this->assertDatabaseHas('role_permissions', ['role_id' => $superadminRole->id, 'permission_id' => $delegasiPerm->id]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $adminRole->id, 'permission_id' => $delegasiPerm->id]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $perencanaanRole->id, 'permission_id' => $delegasiPerm->id]);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $pegawaiRole->id, 'permission_id' => $delegasiPerm->id]);

        // Audit log harus tercatat untuk role yang mengalami perubahan
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'role_permissions.ubah',
            'objek_id' => $superadminRole->id,
        ]);

        $auditCountBefore = AuditLog::count();

        // Rerun seeder harus idempoten (tidak menambah role_permissions baru atau audit log baru)
        $this->seed(SyncRolePermissionPresetsSeeder::class);
        $this->assertSame($auditCountBefore, AuditLog::count());
    }

    public function test_seeder_does_not_audit_inactive_permissions_as_added_and_remains_idempotent(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $audit = AuditLog::create([
            'actor_type' => 'operator',
            'sumber' => 'bootstrap',
            'operator_reference' => 'operator-test',
            'runtime_identity' => 'test@phpunit',
            'waktu' => now(),
            'tindakan' => 'auth.bootstrap',
            'objek_tipe' => 'users',
            'objek_id' => $user->id,
            'alasan' => 'Initial bootstrap',
        ]);

        DB::table('auth_bootstraps')->insert([
            'id' => 'initial',
            'user_id' => $user->id,
            'audit_id' => $audit->id,
            'created_at' => now(),
        ]);

        // Nonaktifkan salah satu permission yang ada di preset pegawai, misalnya 'kegiatan:read'
        $kegiatanRead = Permission::where('kode', 'kegiatan:read')->firstOrFail();
        $kegiatanRead->update(['aktif' => false]);

        $this->seed(SyncRolePermissionPresetsSeeder::class);

        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();

        // Permission nonaktif tidak boleh terpasang di role_permissions
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $pegawaiRole->id,
            'permission_id' => $kegiatanRead->id,
        ]);

        // Audit log nilai_baru tidak boleh mencantumkan permission nonaktif
        $pegawaiAudit = AuditLog::where('tindakan', 'role_permissions.ubah')
            ->where('objek_id', $pegawaiRole->id)
            ->firstOrFail();

        $this->assertNotContains('kegiatan:read', $pegawaiAudit->nilai_baru['permissions'] ?? []);

        // Rerun seeder harus idempoten (tidak menambah audit log baru)
        $auditCountBefore = AuditLog::count();
        $this->seed(SyncRolePermissionPresetsSeeder::class);
        $this->assertSame($auditCountBefore, AuditLog::count());
    }
}
