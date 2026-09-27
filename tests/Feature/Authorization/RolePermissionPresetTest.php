<?php

namespace Tests\Feature\Authorization;

use App\Actions\Access\SyncRolePermissionPresets;
use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RegulasiPermissionSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RolePermissionPresetTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_installs_presets_and_all_entrypoints_are_idempotent(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $this->assertSame(5, AuditLog::where('tindakan', 'roles.tambah')->count());
        foreach (Role::all() as $role) {
            $creation = AuditLog::where('tindakan', 'roles.tambah')->where('objek_id', $role->id)->sole();
            $this->assertSame('preset_release', $creation->sumber);
            $this->assertNull($creation->nilai_lama);
            $this->assertEquals($role->only(['kode', 'nama', 'is_sistem', 'urutan', 'aktif']), $creation->nilai_baru);
        }
        $this->assertDatabaseCount('role_permissions', 165);
        $this->assertSame(5, AuditLog::where('tindakan', 'role_permissions.ubah')->count());
        $audit = AuditLog::where('tindakan', 'role_permissions.ubah')->firstOrFail();
        $this->assertSame(['permissions' => []], $audit->nilai_lama);
        $this->assertSame('system', $audit->actor_type);
        $this->assertSame('preset_release', $audit->sumber);
        $this->assertNull($audit->actor_id);
        $this->assertNull($audit->operator_reference);
        $this->assertNotEmpty($audit->runtime_identity);
        $this->assertStringContainsString('q32-2026-09-24', $audit->alasan);
        $before = $this->snapshot();
        $this->travel(10)->minutes();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RegulasiPermissionSeeder::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_release_corrects_drift_and_metadata_preserving_existing_identity_and_inactive_permission(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $role = Role::where('kode', 'pegawai')->sole();
        $role->update(['nama' => 'Label lokal', 'urutan' => 90]);
        $retained = DB::table('role_permissions')->where('role_id', $role->id)->orderBy('permission_id')->get();
        $extra = Permission::where('kode', 'akses:update')->sole();
        $role->permissions()->attach($extra->id, ['id' => Str::uuid(), 'created_at' => now()]);
        $extra->update(['keterangan' => 'Grant dan editor lama', 'aktif' => false]);
        Permission::where('kode', 'rencana_aksi:read')->update(['butuh_scope' => 'unit', 'keterangan' => 'Baca per unit']);
        Permission::where('kode', 'komponen:create')->update(['keterangan' => null]);
        Permission::where('kode', 'komponen:read')->update(['keterangan' => 'Keterangan lokal']);
        Permission::where('kode', 'renstra:read')->update(['keterangan' => '']);
        $this->travel(10)->minutes();
        $this->seed(AccessCatalogSeeder::class);
        $this->assertSame($retained->toJson(), DB::table('role_permissions')->where('role_id', $role->id)->orderBy('permission_id')->get()->toJson());
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'nama' => 'Label lokal', 'urutan' => 90, 'aktif' => true]);
        $this->assertFalse($extra->fresh()->aktif);
        $this->assertDatabaseHas('permissions', ['kode' => 'akses:update', 'keterangan' => 'Menetapkan peran pengguna dan mengelola pembatasan izin eksplisit.']);
        $this->assertDatabaseHas('permissions', ['kode' => 'delegasi:update', 'keterangan' => 'Memberikan dan mencabut grant izin tambahan per unit.', 'butuh_scope' => 'global']);
        $this->assertDatabaseHas('permissions', ['kode' => 'rencana_aksi:read', 'butuh_scope' => 'global', 'keterangan' => 'Membaca rencana aksi sesuai izin efektif dan aturan akses data.']);
        $this->assertDatabaseHas('permissions', ['kode' => 'kegiatan:read', 'keterangan' => 'Membaca kegiatan sesuai izin efektif dan aturan akses data.']);
        $this->assertDatabaseHas('permissions', ['kode' => 'komponen:create', 'keterangan' => 'Menambah komponen indikator']);
        $this->assertDatabaseHas('permissions', ['kode' => 'komponen:read', 'keterangan' => 'Keterangan lokal']);
        $this->assertDatabaseHas('permissions', ['kode' => 'renstra:read', 'keterangan' => 'Membaca data Renstra']);
        $this->assertDatabaseHas('permissions', ['kode' => 'sasaran:create', 'keterangan' => null]);
        $this->seed(PermissionCatalogSeeder::class);
        $audit = AuditLog::where('tindakan', 'role_permissions.ubah')->where('objek_id', $role->id)->orderByDesc('waktu')->firstOrFail();
        $this->assertSame(['akses:update', 'dashboard:read', 'jenis_berkas:read', 'kegiatan:read', 'komponen:read', 'pengukuran:read', 'regulasi:read', 'rencana_aksi:read'], $audit->nilai_lama['permissions']);
        $this->assertSame(['dashboard:read', 'jenis_berkas:read', 'kegiatan:read', 'komponen:read', 'pengukuran:read', 'regulasi:read', 'rencana_aksi:read'], $audit->nilai_baru['permissions']);
    }

    public function test_partial_catalog_audits_only_new_roles_and_reports_exact_event_count(): void
    {
        $existing = Role::create(['kode' => 'admin', 'nama' => 'Admin lokal', 'urutan' => 2]);
        $before = $existing->fresh()->getRawOriginal();
        $sync = app(SyncRolePermissionPresets::class);
        $events = $sync->handle('test-release', 'Lengkapi katalog parsial', 'test-cli');
        $this->assertSame(80, $events); // 4 role + 71 permission + 5 membership.
        $this->assertSame($events, AuditLog::where('sumber', 'preset_release')->count());
        $this->assertSame(4, AuditLog::where('tindakan', 'roles.tambah')->count());
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'roles.tambah', 'objek_id' => $existing->id]);
        $this->assertSame($before, $existing->fresh()->getRawOriginal());
        $snapshot = $this->snapshot();
        $this->assertSame(0, $sync->handle('test-release', 'Lengkapi katalog parsial', 'test-cli'));
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_release_previews_and_audits_sensitive_reclassification_in_both_directions(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $sensitive = Permission::where('kode', 'akses:update')->sole();
        $ordinary = Permission::where('kode', 'renstra:delete')->sole();
        $sensitive->update(['sensitif' => false, 'aktif' => false]);
        $ordinary->update(['sensitif' => true]);
        $before = $this->snapshot();
        $historical = AuditLog::pluck('id');
        $sync = app(SyncRolePermissionPresets::class);

        $this->assertEqualsCanonicalizing(['akses:update', 'renstra:delete'], $sync->preview()['metadata']);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(2, $sync->handle('test-release', 'Koreksi metadata sensitif', 'test-cli'));
        $this->assertDatabaseHas('permissions', ['id' => $sensitive->id, 'sensitif' => true, 'aktif' => false]);
        $this->assertDatabaseHas('permissions', ['id' => $ordinary->id, 'sensitif' => false, 'aktif' => true]);
        foreach ([$sensitive->id => true, $ordinary->id => false] as $id => $value) {
            $audit = AuditLog::whereNotIn('id', $historical)->where('objek_id', $id)->sole();
            $this->assertSame('permissions.ubah', $audit->tindakan);
            $this->assertSame('preset_release', $audit->sumber);
            $this->assertSame(['sensitif' => ! $value], $audit->nilai_lama);
            $this->assertSame(['sensitif' => $value], $audit->nilai_baru);
        }
        $this->assertSame(array_diff_key($before, ['permissions' => 1, 'audit_log' => 1]), array_diff_key($this->snapshot(), ['permissions' => 1, 'audit_log' => 1]));
        $after = $this->snapshot();
        $this->assertSame([], $sync->preview()['metadata']);
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RegulasiPermissionSeeder::class);
        $this->assertSame($after, $this->snapshot());
    }

    public function test_inactive_official_role_blocks_release_without_any_writes_or_reactivation(): void
    {
        // Katalog belum lengkap: penolakan juga harus mencegah pembuatan role/preset baru.
        $role = Role::create(['kode' => 'pegawai', 'nama' => 'Pegawai', 'urutan' => 5, 'aktif' => false]);
        $pic = Role::create(['kode' => 'pic', 'nama' => 'Legacy', 'urutan' => 6]);
        $before = $this->snapshot();
        $this->assertSame(['pegawai'], app(SyncRolePermissionPresets::class)->preview()['inactive_roles']);
        $this->assertSame(['role_id' => $pic->id, 'will_delete_role' => false, 'permissions_to_remove' => [], 'user_references' => 0], app(SyncRolePermissionPresets::class)->preview()['pic_cleanup']);
        foreach ([AccessCatalogSeeder::class, PermissionCatalogSeeder::class, RegulasiPermissionSeeder::class] as $seeder) {
            try {
                $this->seed($seeder);
                $this->fail('Role resmi nonaktif harus membatalkan sinkronisasi.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('pegawai', $exception->getMessage());
                $this->assertStringContainsString('nonaktif', $exception->getMessage());
            }
            $this->assertSame($before, $this->snapshot());
            $this->assertFalse($role->fresh()->aktif);
        }
    }

    public function test_audit_failure_rolls_back_catalog_membership_and_pic_cleanup_together(): void
    {
        $pic = Role::create(['kode' => 'pic', 'nama' => 'Legacy', 'urutan' => 6]);
        $before = $this->snapshot();
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            $this->seed(AccessCatalogSeeder::class);
            $this->fail('Release tanpa audit harus gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit-unavailable', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseHas('roles', ['id' => $pic->id]);
    }

    public function test_persisted_pic_assignment_blocks_release_without_automap_or_partial_writes(): void
    {
        $pic = Role::create(['kode' => 'pic', 'nama' => 'Legacy', 'urutan' => 6]);
        $user = User::factory()->create();
        $user->roles()->attach($pic->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        $before = $this->snapshot();
        $this->assertSame(['role_id' => $pic->id, 'will_delete_role' => false, 'permissions_to_remove' => [], 'user_references' => 1], app(SyncRolePermissionPresets::class)->preview()['pic_cleanup']);
        $this->assertSame($before, $this->snapshot());
        try {
            $this->seed(AccessCatalogSeeder::class);
            $this->fail('Pengguna PIC membutuhkan keputusan pengganti.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('PIC', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role_id' => $pic->id]);
    }

    public function test_unassigned_pic_cleanup_is_audited_and_historical_audit_is_immutable(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $pic = Role::create(['kode' => 'pic', 'nama' => 'Legacy', 'urutan' => 6, 'aktif' => false]);
        $pic->permissions()->attach(Permission::where('kode', 'dashboard:read')->value('id'), ['id' => Str::uuid(), 'created_at' => now()]);
        $historical = DB::table('audit_log')->orderBy('id')->get();
        $before = $this->snapshot();
        $this->assertSame(['role_id' => $pic->id, 'will_delete_role' => true, 'permissions_to_remove' => ['dashboard:read'], 'user_references' => 0], app(SyncRolePermissionPresets::class)->preview()['pic_cleanup']);
        $this->assertSame($before, $this->snapshot());
        $this->seed(AccessCatalogSeeder::class);
        $this->assertDatabaseMissing('roles', ['id' => $pic->id]);
        $this->assertSame(['role_id' => null, 'will_delete_role' => false, 'permissions_to_remove' => [], 'user_references' => 0], app(SyncRolePermissionPresets::class)->preview()['pic_cleanup']);
        $this->assertSame($historical->toJson(), DB::table('audit_log')->whereIn('id', $historical->pluck('id'))->orderBy('id')->get()->toJson());
        $audit = AuditLog::where('objek_id', $pic->id)->where('tindakan', 'role_permissions.ubah')->sole();
        $this->assertSame(['permissions' => ['dashboard:read']], $audit->nilai_lama);
        $this->assertSame(['permissions' => []], $audit->nilai_baru);
        $this->assertDatabaseHas('audit_log', ['objek_id' => $pic->id, 'tindakan' => 'roles.hapus']);
    }

    public function test_down_refuses_to_erase_release_provenance(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $before = $this->snapshot();
        foreach (['2026_09_27_000002_allow_role_creation_release_audit.php', '2026_09_27_000001_allow_preset_release_audit.php'] as $file) {
            $migration = require database_path('migrations/'.$file);
            try {
                $migration->down();
                $this->fail('Rollback tidak boleh menghapus audit rilis.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('preset_release', $exception->getMessage());
            }
            $this->assertSame($before, $this->snapshot());
        }
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['roles', 'permissions', 'role_permissions', 'audit_log', 'users', 'user_roles', 'user_permission_granted', 'user_permission_denied', 'penanggung_jawab'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    public function test_application_and_database_reject_forged_release_provenance(): void
    {
        $valid = ['actor_id' => null, 'actor_type' => 'system', 'sumber' => 'preset_release', 'operator_reference' => null,
            'runtime_identity' => 'test-cli', 'tindakan' => 'role_permissions.ubah', 'objek_tipe' => 'roles',
            'objek_id' => (string) Str::uuid(), 'alasan' => 'test-release: perubahan fixture'];
        foreach ([['runtime_identity' => ' '], ['operator_reference' => 'palsu'], ['tindakan' => 'pengguna.aktivasi'], ['objek_tipe' => 'users'], ['alasan' => ' '], ['tindakan' => 'roles.tambah', 'objek_tipe' => 'permissions'], ['tindakan' => 'roles.tambah', 'runtime_identity' => ' ']] as $override) {
            $attributes = array_replace($valid, $override);
            try {
                app(WriteAuditLog::class)->handle($attributes);
                $this->fail('Provenance rilis tidak sah harus ditolak aplikasi.');
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseCount('audit_log', 0);
            }
            try {
                DB::transaction(fn () => DB::table('audit_log')->insert($attributes + ['id' => Str::uuid(), 'waktu' => now()]));
                $this->fail('SQL langsung tidak boleh melewati constraint provenance.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->getCode());
            }
        }
    }

    public function test_migration_preserves_old_audit_and_release_does_not_globalize_legacy_unit_grants(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $historical = app(WriteAuditLog::class)->handle(['actor_type' => 'system', 'sumber' => 'sso_onboarding', 'tindakan' => 'user_roles.tambah', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'alasan' => 'Histori onboarding']);
        $before = $historical->fresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_09_27_000001_allow_preset_release_audit.php');
        $creationMigration = require database_path('migrations/2026_09_27_000002_allow_role_creation_release_audit.php');
        $creationMigration->down();
        $migration->down();
        $migration->up();
        $creationMigration->up();
        $this->assertSame($before, $historical->fresh()->getRawOriginal());
        $permission = Permission::create(['kode' => 'rencana_aksi:read', 'entitas' => 'rencana_aksi', 'aksi' => 'read', 'butuh_scope' => 'unit']);
        $unit = Unit::create(['nama' => 'Unit lama', 'created_by' => $user->id]);
        $role = Role::create(['kode' => 'admin', 'nama' => 'Admin', 'urutan' => 2]);
        $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        foreach (['user_permission_granted' => 'diberikan_oleh', 'user_permission_denied' => 'ditetapkan_oleh'] as $table => $actor) {
            DB::table($table)->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => $unit->id, 'alasan' => 'Histori izin unit', $actor => $user->id, 'created_at' => now()]);
        }
        $preserved = array_intersect_key($this->snapshot(), array_flip(['users', 'user_roles', 'user_permission_granted', 'user_permission_denied']));
        $this->seed(AccessCatalogSeeder::class);
        $this->assertSame($preserved, array_intersect_key($this->snapshot(), $preserved));
        $this->assertSame($before, $historical->fresh()->getRawOriginal());
        $resolver = app(PermissionResolver::class);
        $this->assertFalse($resolver->allows($user, 'rencana_aksi:read'));
        $decision = $resolver->decide($user, 'rencana_aksi:read', $unit->id);
        $this->assertFalse($decision['allowed']);
        $this->assertSame([], $decision['grants']);
        $this->assertCount(1, $decision['denies']);
    }
}
