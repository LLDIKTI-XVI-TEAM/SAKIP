<?php

namespace Tests\Feature\Auth;

use App\Actions\Access\AssignRole;
use App\Actions\Audit\WriteAuditLog;
use App\Actions\Auth\ActivateUser;
use App\Actions\Auth\BootstrapSuperadmin;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
    }

    public function test_onboarding_and_relogin_preserve_local_access_and_do_not_link_email(): void
    {
        $action = app(ProvisionKeycloakUser::class);
        $user = $action->handle(['subject' => 'first', 'nama' => 'Pertama', 'email' => 'same@example.test']);
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertFalse($user->is_active);
        $this->assertSame('pegawai', $user->roles()->first()->kode);
        $user->update(['is_active' => true]);
        $action->handle(['subject' => 'first', 'nama' => 'Nama Baru', 'email' => 'same@example.test']);
        $action->handle(['subject' => 'second', 'nama' => 'Berbeda', 'email' => 'same@example.test']);
        $this->assertSame('Nama Baru', $user->fresh()->nama);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('user_roles', 2);
        $this->assertDatabaseCount('audit_log', 2);
        $this->assertDatabaseCount('role_permissions', 0);
    }

    public function test_relogin_preserves_manual_pic_role_grant_deny_and_pending_state(): void
    {
        $admin = $this->pending('admin');
        app(BootstrapSuperadmin::class)->handle($admin->id, 'Operator QA', 'Inisialisasi uji', 'qa-runtime');
        $target = $this->pending('manual-pic');
        $token = (array) DB::table('user_roles')->where('user_id', $target->id)->first(['id', 'role_id', 'audit_id']);
        app(AssignRole::class)->handle($admin, $target->id, Role::where('kode', 'pic')->value('id'), 'Penugasan PIC', $token);
        foreach (['user_permission_granted' => 'diberikan_oleh', 'user_permission_denied' => 'ditetapkan_oleh'] as $table => $actorColumn) {
            DB::table($table)->insert(['id' => Str::uuid(), 'user_id' => $target->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'), 'unit_id' => null, $actorColumn => $admin->id, 'alasan' => 'Fixture preservasi', 'created_at' => now()]);
        }
        $tables = ['user_roles', 'user_permission_granted', 'user_permission_denied', 'audit_log'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        app(ProvisionKeycloakUser::class)->handle(['subject' => 'manual-pic', 'nama' => 'Profil terbaru', 'email' => 'pic-new@example.test']);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame('Profil terbaru', $target->fresh()->nama);
        $this->assertSame('pic', $target->roles()->value('kode'));
    }

    public function test_failed_audit_rolls_back_onboarding(): void
    {
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            app(ProvisionKeycloakUser::class)->handle(['subject' => 'rollback', 'nama' => 'Uji', 'email' => 'test@example.test']);
            $this->fail('Audit failure harus membatalkan provisioning.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit-unavailable', $e->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_roles', 0);
    }

    public function test_bootstrap_initializes_presets_once_and_replay_cannot_restore_revoked_access(): void
    {
        $user = $this->pending('bootstrap');
        $action = app(BootstrapSuperadmin::class);
        $this->assertTrue($action->handle($user->id, 'Operator Uji / otorisasi QA', 'Inisialisasi pengujian', 'qa-runtime'));
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame('superadmin', $user->roles()->first()->kode);
        $this->assertDatabaseCount('role_permissions', 162);
        foreach (['superadmin', 'admin', 'perencanaan', 'pimpinan', 'pegawai'] as $code) {
            $role = Role::where('kode', $code)->sole();
            $installed = DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role_id', $role->id)
                ->pluck('permissions.kode')->all();
            $this->assertEqualsCanonicalizing(RolePermissionPresets::forRole($code), $installed);
            $audit = AuditLog::where('tindakan', 'role_permissions.ubah')->where('objek_id', $role->id)->sole();
            $this->assertEqualsCanonicalizing(RolePermissionPresets::forRole($code), $audit->nilai_baru['permissions']);
        }
        $bootstrapAudits = AuditLog::where('sumber', 'bootstrap')->get();
        $this->assertCount(8, $bootstrapAudits);
        foreach ($bootstrapAudits as $audit) {
            $this->assertSame('operator', $audit->actor_type);
            $this->assertSame('Operator Uji / otorisasi QA', $audit->operator_reference);
            $this->assertSame('Inisialisasi pengujian', $audit->alasan);
            $this->assertSame('qa-runtime', $audit->runtime_identity);
        }
        $this->assertSame(AuditLog::where('tindakan', 'auth.bootstrap')->sole()->id, DB::table('auth_bootstraps')->value('audit_id'));
        $this->assertDatabaseCount('roles', 6);
        $pic = Role::where('kode', 'pic')->sole();
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $pic->id]);
        $this->assertSame(5, DB::table('audit_log')->where('tindakan', 'role_permissions.ubah')->count());
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'role_permissions.ubah', 'objek_id' => $pic->id]);
        $auditCount = DB::table('audit_log')->count();
        DB::table('user_roles')->where('user_id', $user->id)->update(['role_id' => Role::where('kode', 'pegawai')->value('id')]);
        $user->refresh()->update(['is_active' => false]);
        $this->assertFalse($action->handle($user->id, 'Operator Uji / otorisasi QA', 'Percobaan ulang', 'qa-runtime'));
        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame('pegawai', $user->roles()->first()->kode);
        $this->assertSame($auditCount, DB::table('audit_log')->count());
        $other = $this->pending('other');
        $this->expectException(\DomainException::class);
        $action->handle($other->id, 'Operator Uji', 'Target berbeda', 'qa-runtime');
    }

    public function test_activation_is_atomic_idempotent_and_deny_overrides_superadmin(): void
    {
        $admin = $this->pending('admin');
        app(BootstrapSuperadmin::class)->handle($admin->id, 'Operator QA', 'Inisialisasi uji', 'qa-runtime');
        $target = $this->pending('target');
        $action = app(ActivateUser::class);
        $this->assertTrue($action->handle($admin->fresh(), $target->id, 'Disetujui admin'));
        $this->assertFalse($action->handle($admin->fresh(), $target->id, 'Submit ulang'));
        $this->assertSame('pegawai', $target->roles()->first()->kode);
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $admin->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'unit_id' => null, 'ditetapkan_oleh' => $admin->id, 'alasan' => 'Dibatasi', 'created_at' => now()]);
        $this->expectException(AuthorizationException::class);
        $action->handle($admin->fresh(), $target->id, 'Ditolak');
    }

    private function pending(string $subject): User
    {
        return app(ProvisionKeycloakUser::class)->handle(['subject' => $subject, 'nama' => 'Pengguna '.$subject, 'email' => $subject.'@example.test']);
    }

    public function test_bootstrap_rejects_incomplete_presets_without_committing_any_privilege(): void
    {
        $user = $this->pending('incomplete');
        Role::where('kode', 'pimpinan')->delete();
        try {
            app(BootstrapSuperadmin::class)->handle($user->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
            $this->fail('Bootstrap tidak boleh menandai preset parsial sebagai selesai.');
        } catch (\DomainException) {
            $this->assertFalse($user->fresh()->is_active);
            $this->assertDatabaseCount('auth_bootstraps', 0);
            $this->assertDatabaseCount('role_permissions', 0);
        }
    }

    public function test_bootstrap_audit_failure_rolls_back_presets_assignment_activation_and_marker(): void
    {
        $user = $this->pending('audit-failure');
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            app(BootstrapSuperadmin::class)->handle($user->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
            $this->fail('Audit gagal harus membatalkan bootstrap.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit-unavailable', $e->getMessage());
        }
        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame('pegawai', $user->roles()->first()->kode);
        $this->assertDatabaseCount('role_permissions', 0);
        $this->assertDatabaseCount('auth_bootstraps', 0);
        $this->assertDatabaseCount('audit_log', 1);
    }

    public function test_bootstrap_requires_pic_identity_active_even_without_a_defined_preset(): void
    {
        $user = $this->pending('pic-catalog');
        foreach (['inactive', 'missing'] as $state) {
            if ($state === 'inactive') {
                Role::where('kode', 'pic')->update(['aktif' => false]);
            } else {
                Role::where('kode', 'pic')->delete();
            }
            try {
                app(BootstrapSuperadmin::class)->handle($user->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
                $this->fail('PIC wajib ada dan aktif sebelum bootstrap.');
            } catch (\DomainException) {
                $this->assertFalse($user->fresh()->is_active);
                $this->assertDatabaseCount('auth_bootstraps', 0);
                $this->assertDatabaseCount('role_permissions', 0);
            }
        }
    }

    public function test_activation_audit_failure_leaves_account_pending(): void
    {
        $admin = $this->pending('admin-audit');
        app(BootstrapSuperadmin::class)->handle($admin->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
        $target = $this->pending('target-audit');
        $count = DB::table('audit_log')->count();
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            app(ActivateUser::class)->handle($admin->fresh(), $target->id, 'Disetujui');
            $this->fail('Audit gagal harus membatalkan aktivasi.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit-unavailable', $e->getMessage());
        }
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame($count, DB::table('audit_log')->count());
    }
}
