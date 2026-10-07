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
        $this->assertSame('nonaktif', $user->status);
        $this->assertSame(0, $user->roles()->count());
        $user->update(['status' => 'aktif']);
        $action->handle(['subject' => 'first', 'nama' => 'Nama Baru', 'email' => 'same@example.test']);
        $action->handle(['subject' => 'second', 'nama' => 'Berbeda', 'email' => 'same@example.test']);
        $this->assertSame('Nama Baru', $user->fresh()->nama);
        $this->assertSame('aktif', $user->fresh()->status);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertSame(2, AuditLog::where('sumber', 'sso_onboarding')->count());
        $this->assertSame(2, AuditLog::where('tindakan', 'pengguna.terdaftar')->count());
        $this->assertDatabaseCount('role_permissions', 167);
    }

    public function test_onboarding_does_not_require_pegawai_and_audits_only_safe_registration_state(): void
    {
        $role = Role::where('kode', 'pegawai')->sole();
        DB::table('role_permissions')->where('role_id', $role->id)->delete();
        DB::table('roles')->where('id', $role->id)->delete();
        $user = $this->pending('no-catalog');
        $this->assertSame('nonaktif', $user->status);
        $this->assertDatabaseCount('user_roles', 0);
        $audit = AuditLog::where('sumber', 'sso_onboarding')->sole();
        $this->assertSame('pengguna.terdaftar', $audit->tindakan);
        $this->assertSame('system', $audit->actor_type);
        $this->assertSame('users', $audit->objek_tipe);
        $this->assertSame($user->id, $audit->objek_id);
        $this->assertNull($audit->nilai_lama);
        $this->assertSame(['status' => 'nonaktif'], $audit->nilai_baru);
    }

    public function test_relogin_preserves_manual_role_grant_deny_and_pending_state(): void
    {
        $admin = $this->pending('admin');
        app(BootstrapSuperadmin::class)->handle($admin->id, 'Operator QA', 'Inisialisasi uji', 'qa-runtime');
        $target = $this->pending('manual-pimpinan');
        app(AssignRole::class)->handle($admin, $target->id, Role::where('kode', 'pimpinan')->value('id'), 'Penetapan peran', null);
        foreach (['user_permission_granted' => 'diberikan_oleh', 'user_permission_denied' => 'ditetapkan_oleh'] as $table => $actorColumn) {
            DB::table($table)->insert(['id' => Str::uuid(), 'user_id' => $target->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'), 'unit_id' => null, $actorColumn => $admin->id, 'alasan' => 'Fixture preservasi', 'created_at' => now()]);
        }
        $tables = ['user_roles', 'user_permission_granted', 'user_permission_denied', 'audit_log'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        app(ProvisionKeycloakUser::class)->handle(['subject' => 'manual-pimpinan', 'nama' => 'Profil terbaru', 'email' => 'pic-new@example.test']);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $this->assertSame('nonaktif', $target->fresh()->status);
        $this->assertSame('Profil terbaru', $target->fresh()->nama);
        $this->assertSame('pimpinan', $target->roles()->value('kode'));
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

    public function test_bootstrap_validates_installed_presets_once_and_replay_cannot_restore_revoked_access(): void
    {
        $user = $this->pending('bootstrap');
        $action = app(BootstrapSuperadmin::class);
        $this->assertTrue($action->handle($user->id, 'Operator Uji / otorisasi QA', 'Inisialisasi pengujian', 'qa-runtime'));
        $this->assertSame('aktif', $user->fresh()->status);
        $this->assertSame('superadmin', $user->roles()->first()->kode);
        $this->assertDatabaseCount('role_permissions', 167);
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
        $this->assertCount(3, $bootstrapAudits);
        $assignmentAudit = $bootstrapAudits->sole('tindakan', 'user_roles.tambah');
        $this->assertNull($assignmentAudit->nilai_lama);
        $this->assertSame($assignmentAudit->id, DB::table('user_roles')->where('user_id', $user->id)->value('audit_id'));
        foreach ($bootstrapAudits as $audit) {
            $this->assertSame('operator', $audit->actor_type);
            $this->assertSame('Operator Uji / otorisasi QA', $audit->operator_reference);
            $this->assertSame('Inisialisasi pengujian', $audit->alasan);
            $this->assertSame('qa-runtime', $audit->runtime_identity);
        }
        $this->assertSame(AuditLog::where('tindakan', 'auth.bootstrap')->sole()->id, DB::table('auth_bootstraps')->value('audit_id'));
        $this->assertDatabaseCount('roles', 5);
        $this->assertSame(5, DB::table('audit_log')->where('tindakan', 'role_permissions.ubah')->where('sumber', 'preset_release')->count());
        $auditCount = DB::table('audit_log')->count();
        DB::table('user_roles')->where('user_id', $user->id)->update(['role_id' => Role::where('kode', 'pegawai')->value('id')]);
        $user->refresh()->update(['status' => 'nonaktif']);
        $this->assertFalse($action->handle($user->id, 'Operator Uji / otorisasi QA', 'Percobaan ulang', 'qa-runtime'));
        $this->assertSame('nonaktif', $user->fresh()->status);
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
        $this->assertSame(0, $target->roles()->count());
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
        $role = Role::where('kode', 'pimpinan')->sole();
        $role->permissions()->detach();
        $before = DB::table('role_permissions')->orderBy('id')->get()->toJson();
        try {
            app(BootstrapSuperadmin::class)->handle($user->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
            $this->fail('Bootstrap tidak boleh menandai preset parsial sebagai selesai.');
        } catch (\DomainException) {
            $this->assertSame('nonaktif', $user->fresh()->status);
            $this->assertDatabaseCount('auth_bootstraps', 0);
            $this->assertSame($before, DB::table('role_permissions')->orderBy('id')->get()->toJson());
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
        $this->assertSame('nonaktif', $user->fresh()->status);
        $this->assertSame(0, $user->roles()->count());
        $this->assertDatabaseCount('role_permissions', 167);
        $this->assertDatabaseCount('auth_bootstraps', 0);
        $this->assertSame(1, AuditLog::where('sumber', 'sso_onboarding')->count());
    }

    public function test_bootstrap_rejects_inactive_official_role_without_reactivating_it(): void
    {
        $user = $this->pending('inactive-catalog');
        Role::where('kode', 'pimpinan')->update(['aktif' => false]);
        try {
            app(BootstrapSuperadmin::class)->handle($user->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
            $this->fail('Seluruh role resmi wajib aktif sebelum bootstrap.');
        } catch (\DomainException) {
            $this->assertSame('nonaktif', $user->fresh()->status);
            $this->assertDatabaseCount('auth_bootstraps', 0);
            $this->assertDatabaseHas('roles', ['kode' => 'pimpinan', 'aktif' => false]);
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
        $this->assertSame('nonaktif', $target->fresh()->status);
        $this->assertSame($count, DB::table('audit_log')->count());
    }

    public function test_bootstrap_rejects_active_assigned_or_unproven_candidates_without_overwrite(): void
    {
        $active = $this->pending('already-active');
        $active->update(['status' => 'aktif']);
        $assigned = $this->pending('already-assigned');
        $assigned->roles()->attach(Role::where('kode', 'pegawai')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $active->id, 'created_at' => now()]);
        $unproven = User::factory()->create();
        $before = DB::table('audit_log')->count();
        foreach ([$active, $assigned, $unproven] as $candidate) {
            try {
                app(BootstrapSuperadmin::class)->handle($candidate->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
                $this->fail('Kandidat tidak sah tidak boleh memperoleh privilege.');
            } catch (\DomainException) {
                $this->assertDatabaseCount('auth_bootstraps', 0);
                $this->assertSame($before, DB::table('audit_log')->count());
            }
        }
        $this->assertDatabaseCount('user_roles', 1);
        $this->assertSame('pegawai', $assigned->roles()->value('kode'));
        $this->assertSame('nonaktif', $assigned->fresh()->status);
    }
}
