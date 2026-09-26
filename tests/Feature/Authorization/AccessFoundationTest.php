<?php

namespace Tests\Feature\Authorization;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Models\UserRole;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleCatalog;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_presets_match_the_agreed_contract_without_installing_privileges(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $this->seed(AccessCatalogSeeder::class);
        $this->assertDatabaseCount('permissions', 71);
        // Fixture kontrak terpisah dari generator produksi agar kode hilang/berlebih terdeteksi.
        $expected = 'renstra:create renstra:read renstra:update renstra:delete sasaran:create sasaran:update sasaran:delete indikator:create indikator:read indikator:update indikator:delete target:update pk:create pk:update regulasi:create regulasi:read regulasi:update regulasi:delete periode:create periode:update jadwal:create jadwal:update jadwal:aktivasi jadwal:tutup jadwal:buka_kembali penanggung_jawab:update rencana_aksi:read rencana_aksi:create rencana_aksi:update rencana_aksi:ajukan rencana_aksi:verifikasi rencana_aksi:kembalikan rencana_aksi:sahkan rencana_aksi:buka_kembali kegiatan:read kegiatan:create kegiatan:update kegiatan:delete komponen:create komponen:read komponen:update komponen:delete jenis_berkas:create jenis_berkas:read jenis_berkas:update jenis_berkas:delete berkas:read berkas:upload berkas:delete pengukuran:create pengukuran:update pengukuran:read pengukuran:verifikasi pengukuran:kembalikan pengukuran:sahkan pengukuran:buka_kembali pengukuran:setujui status_capaian:update rekomendasi:tetapkan unit:create unit:read unit:update unit:delete pengguna:read akses:update delegasi:update dashboard:read laporan:read laporan:ekspor audit:read pengaturan:update';
        $this->assertEqualsCanonicalizing(explode(' ', $expected), Permission::pluck('kode')->all());
        $this->assertDatabaseCount('roles', 6);
        $this->assertEqualsCanonicalizing(['superadmin', 'admin', 'perencanaan', 'pic', 'pimpinan', 'pegawai'], Role::pluck('kode')->all());
        $this->assertSame(6, Role::pluck('urutan')->unique()->count());
        $this->assertSame(['superadmin', 'admin', 'perencanaan', 'pic', 'pimpinan', 'pegawai'], RoleCatalog::codes());
        $this->assertDatabaseHas('roles', ['kode' => 'pic', 'aktif' => true, 'is_sistem' => true, 'urutan' => 6]);
        $this->assertDatabaseCount('role_permissions', 0);
        $this->assertSame(7, Permission::where('butuh_scope', 'unit')->count());
        $this->assertSame(23, Permission::where('sensitif', true)->count());
        $this->assertEqualsCanonicalizing(['pengukuran:read', 'rencana_aksi:read', 'kegiatan:read', 'komponen:read', 'jenis_berkas:read', 'regulasi:read', 'dashboard:read'], RolePermissionPresets::forRole('pegawai'));
        $this->assertEqualsCanonicalizing(['pengguna:read', 'akses:update', 'delegasi:update', 'unit:create', 'unit:read', 'unit:update', 'unit:delete', 'pengaturan:update', 'komponen:read', 'jenis_berkas:read', 'regulasi:read', 'audit:read', 'dashboard:read', 'laporan:read'], RolePermissionPresets::forRole('admin'));
        $this->assertCount(71, RolePermissionPresets::forRole('superadmin'));
        $this->assertCount(63, RolePermissionPresets::forRole('perencanaan'));
        $this->assertCount(12, RolePermissionPresets::forRole('pimpinan'));
        $this->assertFalse(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasColumn('role_permissions', 'unit_id'));
    }

    public function test_resolver_evaluates_live_roles_grants_scopes_and_deny_without_superadmin_bypass(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('kode', 'superadmin')->firstOrFail();
        $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        $permission = Permission::where('kode', 'pengukuran:update')->firstOrFail();
        $unit = Unit::create(['nama' => 'Unit A', 'created_by' => $user->id]);
        $otherUnit = Unit::create(['nama' => 'Unit B', 'created_by' => $user->id]);
        $resolver = app(PermissionResolver::class);
        $this->assertFalse($resolver->allows($user, 'tidak:ada'));
        $this->assertFalse($resolver->allows($user, 'pengukuran:update', $unit->id));
        $grant = ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => $unit->id, 'alasan' => 'Fixture delegasi', 'diberikan_oleh' => $user->id, 'created_at' => now()];
        DB::table('user_permission_granted')->insert($grant);
        $this->assertTrue($resolver->allows($user, 'pengukuran:update', $unit->id));
        $this->assertFalse($resolver->allows($user, 'pengukuran:update', $otherUnit->id));
        $this->assertFalse($resolver->allows($user, 'pengukuran:update'));
        $role->permissions()->attach($permission->id, ['id' => Str::uuid(), 'created_at' => now()]);
        $this->assertTrue($resolver->allows($user, 'pengukuran:update', $otherUnit->id));
        $denyId = (string) Str::uuid();
        DB::table('user_permission_denied')->insert(['id' => $denyId, 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => $unit->id, 'alasan' => 'Fixture pencabutan', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
        $decision = $resolver->decide($user, 'pengukuran:update', $unit->id);
        $this->assertFalse($decision['allowed']);
        $this->assertSame([$denyId], $decision['denies']);
        $this->assertTrue($resolver->allows($user, 'pengukuran:update', $otherUnit->id));
        DB::table('user_permission_denied')->where('id', $denyId)->update(['unit_id' => null]);
        $this->assertFalse($resolver->allows($user, 'pengukuran:update', $otherUnit->id));
        DB::table('user_permission_denied')->delete();
        $permission->update(['aktif' => false]);
        $this->assertFalse($resolver->allows($user, 'pengukuran:update', $otherUnit->id));
    }

    public function test_pic_identity_does_not_imply_a_defined_preset_and_unknown_roles_are_invalid(): void
    {
        $this->assertTrue(RoleCatalog::contains('pic'));
        $this->assertFalse(RolePermissionPresets::hasDefinedPreset('pic'));
        foreach (['superadmin', 'admin', 'perencanaan', 'pimpinan', 'pegawai'] as $code) {
            $this->assertTrue(RolePermissionPresets::hasDefinedPreset($code));
        }
        $this->assertFalse(RoleCatalog::contains('asing'));
        foreach (['pic', 'asing'] as $code) {
            try {
                RolePermissionPresets::forRole($code);
                $this->fail('Preset belum tersedia atau peran asing harus ditolak.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame($code === 'pic' ? 'Preset peran belum ditetapkan.' : 'Peran tidak dikenal.', $exception->getMessage());
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        RolePermissionPresets::hasDefinedPreset('asing');
    }

    public function test_catalog_rerun_preserves_existing_role_fields_and_managed_permissions(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $this->assertDatabaseHas('roles', ['kode' => 'pimpinan', 'urutan' => 4]);
        $this->assertDatabaseHas('roles', ['kode' => 'pegawai', 'urutan' => 5]);
        $role = Role::where('kode', 'pimpinan')->sole();
        $role->update(['nama' => 'Label tersimpan', 'urutan' => 20, 'aktif' => false]);
        $role->permissions()->attach(Permission::where('kode', 'dashboard:read')->value('id'), ['id' => Str::uuid(), 'created_at' => now()]);
        $before = DB::table('roles')->orderBy('kode')->get()->toJson();
        $permissions = DB::table('role_permissions')->get()->toJson();
        $this->seed(AccessCatalogSeeder::class);
        $this->assertSame($before, DB::table('roles')->orderBy('kode')->get()->toJson());
        $this->assertSame($permissions, DB::table('role_permissions')->get()->toJson());
    }

    public function test_pic_without_preset_uses_explicit_grant_and_deny(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('kode', 'pic')->sole();
        $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        $permission = Permission::where('kode', 'pengukuran:update')->sole();
        $unit = Unit::create(['nama' => 'Unit PIC', 'created_by' => $user->id]);
        $otherUnit = Unit::create(['nama' => 'Unit Lain', 'created_by' => $user->id]);
        $resolver = app(PermissionResolver::class);
        $this->assertFalse($resolver->allows($user, 'pengukuran:update', $unit->id));
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => $unit->id, 'alasan' => 'Fixture', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        $this->assertTrue($resolver->allows($user, 'pengukuran:update', $unit->id));
        $this->assertFalse($resolver->allows($user, 'pengukuran:update', $otherUnit->id));
        $this->assertFalse($resolver->allows($user, 'pengukuran:update'));
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => $unit->id, 'alasan' => 'Fixture', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
        $this->assertFalse($resolver->allows($user, 'pengukuran:update', $unit->id));
        $this->assertDatabaseCount('role_permissions', 0);
    }

    public function test_global_permission_is_checked_against_the_target_unit_deny(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $unit = Unit::create(['nama' => 'Unit A', 'created_by' => $user->id]);
        $permission = Permission::where('kode', 'pengukuran:read')->firstOrFail();
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => null, 'alasan' => 'Fixture', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => $unit->id, 'alasan' => 'Fixture', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($user, 'pengukuran:read'));
        $this->assertFalse($resolver->allows($user, 'pengukuran:read', $unit->id));
    }

    public function test_audit_rejects_missing_actor_and_is_append_only(): void
    {
        $user = User::factory()->create();
        $writer = app(WriteAuditLog::class);
        $audit = $writer->handle(['actor_type' => 'system', 'sumber' => 'sso_onboarding', 'tindakan' => 'user_roles.tambah', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'alasan' => 'Pendaftaran SSO', 'nilai_baru' => ['role' => 'pegawai']]);
        $this->assertNull($audit->actor_id);
        $this->assertDatabaseCount('audit_log', 1);
        $this->expectException(\LogicException::class);
        $audit->update(['alasan' => 'Tidak boleh diubah']);
    }

    public function test_manual_audit_requires_a_real_actor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(WriteAuditLog::class)->handle(['actor_type' => 'user', 'sumber' => 'manual', 'tindakan' => 'pengguna.aktivasi', 'objek_tipe' => 'users', 'objek_id' => (string) Str::uuid(), 'alasan' => 'Fixture']);
    }

    public function test_database_rejects_raw_update_and_delete_of_final_audit_records(): void
    {
        $user = User::factory()->create();
        $audit = app(WriteAuditLog::class)->handle(['actor_type' => 'system', 'sumber' => 'sso_onboarding', 'tindakan' => 'user_roles.tambah', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'alasan' => 'Pendaftaran SSO']);
        foreach ([
            fn () => DB::table('audit_log')->where('id', $audit->id)->update(['alasan' => 'Jejak diganti']),
            fn () => DB::table('audit_log')->where('id', $audit->id)->delete(),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Audit final tidak boleh berubah melalui query langsung.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', (string) $exception->getCode());
            }
        }
        $this->assertDatabaseHas('audit_log', ['id' => $audit->id, 'alasan' => 'Pendaftaran SSO']);
    }

    public function test_database_enforces_identity_role_and_nullable_scope_uniqueness(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = User::factory()->create();
        $this->assertTrue(Str::isUuid($user->id));
        $role = Role::where('kode', 'pegawai')->firstOrFail();
        $assignment = ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => $role->id, 'sumber_pemberian' => 'sso_onboarding', 'diberikan_oleh' => null, 'created_at' => now()];
        DB::table('user_roles')->insert($assignment);
        $permission = Permission::where('kode', 'dashboard:read')->firstOrFail();
        $grant = ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => null, 'alasan' => 'Fixture', 'diberikan_oleh' => $user->id, 'created_at' => now()];
        DB::table('user_permission_granted')->insert($grant);
        foreach ([
            fn () => User::factory()->create(['keycloak_id' => $user->keycloak_id]),
            fn () => DB::table('user_roles')->insert([...$assignment, 'id' => (string) Str::uuid()]),
            fn () => DB::table('user_permission_granted')->insert([...$grant, 'id' => (string) Str::uuid()]),
        ] as $duplicate) {
            try {
                DB::transaction($duplicate);
                $this->fail('Constraint unique tidak ditegakkan.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', (string) $exception->getCode());
            }
        }
        // Email bukan identitas: dua subject sah dapat memiliki profil email yang sama.
        User::factory()->create(['email' => $user->email]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_invalid_grant_scope_and_privileged_system_assignment_are_rejected(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = User::factory()->create();
        $permission = Permission::where('kode', 'pengukuran:update')->firstOrFail();
        try {
            UserPermissionGrant::create(['user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => null, 'alasan' => 'Fixture', 'diberikan_oleh' => $user->id]);
            $this->fail('Grant unit tanpa unit harus ditolak.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('user_permission_granted', 0);
        }
        $this->expectException(\InvalidArgumentException::class);
        UserRole::create(['user_id' => $user->id, 'role_id' => Role::where('kode', 'superadmin')->firstOrFail()->id, 'sumber_pemberian' => 'sso_onboarding']);
    }
}
