<?php

namespace Tests\Integration\Concurrency;

use App\Actions\Access\CreateDeny;
use App\Actions\Access\SyncRolePermissionPresets;
use App\Actions\Auth\ActivateUser;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Actions\PenanggungJawab\AssignPenanggungJawab;
use App\Actions\Pengukuran\ChangePengukuran;
use App\Actions\Perencanaan\ChangeIndicatorFormula;
use App\Actions\Regulasi\DeleteRegulasiAction;
use App\Actions\Regulasi\UpdateRegulasiAction;
use App\Actions\RencanaAksi\EnsureDraftRencanaAksi;
use App\Actions\Renstra\UpdateRenstraAction;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentReceipt;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MutationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private bool $workersStopped = true;

    /** Audit rilis menolak rollback; rebuild hanya untuk fixture disposable setelah worker berhenti. */
    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            try {
                if (! $this->workersStopped || DB::transactionLevel() !== 0) {
                    throw new RuntimeException('Rebuild ditolak: worker atau transaksi masih aktif.');
                }
                $this->assertDisposableDatabase($this->app);
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            } finally {
                RefreshDatabaseState::$migrated = false;
            }
        });
    }

    public function test_two_concurrent_callbacks_create_one_identity_without_assignment_and_one_audit(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $results = $this->race('provision', 'concurrent-subject', 'sakip:sso:concurrent-subject');
        $this->assertSame($results[0], $results[1]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertSame(1, DB::table('audit_log')->where('sumber', 'sso_onboarding')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'pengguna.terdaftar')->count());
        $this->assertDatabaseHas('users', ['keycloak_id' => 'concurrent-subject', 'status' => 'nonaktif']);
    }

    public function test_two_concurrent_bootstraps_install_privileges_and_audit_only_once(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = app(ProvisionKeycloakUser::class)->handle(['subject' => 'bootstrap-subject', 'nama' => 'Fixture Bootstrap', 'email' => 'bootstrap@example.test']);
        $results = $this->race('bootstrap', $user->id, 'sakip:initial-bootstrap');
        $this->assertEqualsCanonicalizing([true, false], $results);
        $this->assertDatabaseCount('auth_bootstraps', 1);
        $this->assertDatabaseCount('role_permissions', 165);
        $this->assertDatabaseCount('user_roles', 1);
        $this->assertSame('aktif', $user->fresh()->status);
        $this->assertSame(5, DB::table('audit_log')->where('tindakan', 'role_permissions.ubah')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'user_roles.tambah')->where('sumber', 'bootstrap')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'pengguna.aktivasi')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'auth.bootstrap')->count());
    }

    public function test_bootstrap_rechecks_candidate_after_waiting_for_activation_lock(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $actor->roles()->attach(Role::where('kode', 'admin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $target = app(ProvisionKeycloakUser::class)->handle(['subject' => 'activation-race', 'nama' => 'Fixture', 'email' => 'fixture@example.test']);
        $prepare = fn () => app(ActivateUser::class)->handle($actor, $target->id, 'Aktivasi sah lebih dahulu');
        $results = $this->race('bootstrap', $target->id, '', [['expect_ineligible' => true]], $prepare);
        $this->assertSame(['ineligible'], $results);
        $this->assertSame('aktif', $target->fresh()->status);
        $this->assertSame(0, $target->roles()->count());
        $this->assertDatabaseCount('auth_bootstraps', 0);
        $this->assertSame(0, DB::table('audit_log')->where('sumber', 'bootstrap')->count());
        $this->assertSame(1, DB::table('audit_log')->where('objek_id', $target->id)->where('tindakan', 'pengguna.aktivasi')->count());
    }

    public function test_two_first_assignments_have_one_winner_and_one_stale_conflict(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $target = User::factory()->create();
        $admin = Role::where('kode', 'admin')->sole();
        $actor->roles()->attach($admin->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $roles = [Role::where('kode', 'pimpinan')->value('id'), Role::where('kode', 'pegawai')->value('id')];
        $payloads = array_map(fn ($role) => ['actor_id' => $actor->id, 'target_id' => $target->id, 'role_id' => $role, 'alasan' => 'Fixture konkurensi', 'expected_assignment' => null], $roles);
        $results = $this->race('assign-role', $target->id, '', $payloads);
        $this->assertEqualsCanonicalizing(['assigned', 'conflict'], $results);
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $target->id)->count());
        $this->assertContains(DB::table('user_roles')->where('user_id', $target->id)->value('role_id'), $roles);
        $this->assertSame(1, DB::table('audit_log')->where('objek_id', $target->id)->where('tindakan', 'user_roles.tambah')->count());
        $this->assertSame(1, DB::table('audit_log')->where('objek_id', $target->id)->where('tindakan', 'user_roles.ditolak')->count());
    }

    public function test_assign_role_reauthorizes_after_preset_release_without_crossed_role_locks(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $target = User::factory()->create(['status' => 'aktif']);
        // Role aktor di atas role tujuan memeriksa risiko lock aktor-dahulu melawan urutan rilis.
        $actorRole = Role::whereIn('kode', ['pegawai', 'pimpinan'])->orderByDesc('id')->firstOrFail();
        $destination = Role::orderBy('id')->firstOrFail();
        $this->assertTrue($destination->id < $actorRole->id);
        $actor->roles()->attach($actorRole->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        foreach (['pengguna:read', 'akses:update'] as $code) {
            DB::table('role_permissions')->insert(['id' => Str::uuid(), 'role_id' => $actorRole->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'created_at' => now()]);
            $this->assertTrue(app(PermissionResolver::class)->allows($actor, $code));
        }
        $releaseWhileBlocked = function (array $pids) use ($actorRole): void {
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertStringContainsString('from "roles"', $query);
            $this->assertStringContainsString('for share', $query);
            // Writer produksi mengunci semua role terurut dan mencabut kedua izin fixture sebelum commit.
            $this->assertSame(1, app(SyncRolePermissionPresets::class)->handle('assign-role-race', 'Kembalikan preset resmi', 'test-parent'));
            $audit = AuditLog::where('objek_id', $actorRole->id)->where('alasan', 'assign-role-race: Kembalikan preset resmi')->sole();
            $this->assertContains('akses:update', $audit->nilai_lama['permissions']);
            $this->assertNotContains('akses:update', $audit->nilai_baru['permissions']);
        };

        $results = $this->race('assign-role', $destination->id, '', [[
            'actor_id' => $actor->id, 'target_id' => $target->id, 'role_id' => $destination->id,
            'alasan' => 'Fixture pencabutan preset', 'expected_assignment' => null,
        ]], barrierTable: 'roles', assertBlocked: $releaseWhileBlocked);

        $this->assertSame(['denied'], $results);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $target->id]);
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['user_roles.tambah', 'user_roles.ubah'])->count());
        $audit = AuditLog::where('tindakan', 'user_roles.ditolak')->sole();
        $this->assertSame($target->id, $audit->objek_id);
        foreach (['pengguna_read', 'akses_update'] as $key) {
            $this->assertFalse($audit->dasar_izin[$key]['allowed']);
            $this->assertSame('no_allow', $audit->dasar_izin[$key]['reason']);
        }
    }

    public function test_two_receipt_consumers_have_one_winner_on_database_cache(): void
    {
        $actor = User::factory()->create(['status' => 'aktif']);
        $reference = app(RoleAssignmentReceipt::class)->issue($actor->id, 'concurrent-session', ['status' => 'assigned', 'has_active_pj' => true]);
        $this->assertNotNull($reference);
        $key = DB::table('cache')->sole()->key.':consume';
        DB::table('cache_locks')->insert(['key' => $key, 'owner' => 'expired-fixture', 'expiration' => 0]);
        $prepare = fn () => DB::table('cache_locks')->where('key', $key)->lockForUpdate()->sole();
        $payload = ['actor_id' => $actor->id, 'session_id' => 'concurrent-session', 'reference' => $reference];

        $results = $this->race('receipt-consume', $actor->id, '', [$payload, $payload], $prepare);

        $this->assertEqualsCanonicalizing(['consumed', 'unknown'], $results);
        $this->assertDatabaseCount('cache', 0);
        $this->assertDatabaseCount('audit_log', 0);
    }

    #[DataProvider('denyScopes')]
    public function test_concurrent_deny_creates_and_revokes_have_one_winner(bool $scoped): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $target = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'admin')->sole();
        $actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $unitId = $scoped ? Unit::create(['nama' => 'Race unit', 'created_by' => $actor->id])->id : null;
        $payload = ['actor_id' => $actor->id, 'target_id' => $target->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'), 'unit_id' => $unitId, 'alasan' => 'Race deny'];
        $results = $this->race('create-deny', $target->id, '', [$payload, $payload]);
        $this->assertEqualsCanonicalizing(['created', 'duplicate'], $results);
        $deny = DB::table('user_permission_denied')->sole();
        $this->assertSame('Race deny', $deny->alasan);
        $this->assertSame($actor->id, $deny->ditetapkan_oleh);
        $this->assertSame($unitId, $deny->unit_id);
        $this->assertSame(1, DB::table('audit_log')->where('objek_id', $deny->id)->where('tindakan', 'user_permission_denied.tambah')->count());
        $payload = ['actor_id' => $actor->id, 'deny_id' => $deny->id, 'alasan' => 'Race revoke'];
        $results = $this->race('revoke-deny', $target->id, '', [$payload, $payload]);
        $this->assertEqualsCanonicalizing(['revoked', 'stale'], $results);
        $this->assertDatabaseCount('user_permission_denied', 0);
        $this->assertSame(2, DB::table('audit_log')->whereIn('tindakan', ['user_permission_denied.tambah', 'user_permission_denied.hapus'])->count());
        $this->assertSame(1, DB::table('audit_log')->where('objek_id', $deny->id)->where('tindakan', 'user_permission_denied.hapus')->count());
    }

    public static function denyScopes(): array
    {
        return [[false], [true]];
    }

    public function test_two_releases_wait_for_the_same_lock_and_emit_one_set_of_delta_audits(): void
    {
        $results = $this->race('sync-presets', '', 'sakip:initial-bootstrap');
        $this->assertEqualsCanonicalizing([81, 0], $results);
        $this->assertSame(5, DB::table('audit_log')->where('tindakan', 'roles.tambah')->count());
        $this->assertDatabaseCount('role_permissions', 165);
        $this->assertSame(5, DB::table('audit_log')->where('tindakan', 'role_permissions.ubah')->count());
        $this->assertSame(71, DB::table('audit_log')->where('tindakan', 'permissions.ubah')->count());
    }

    public function test_bootstrap_and_release_wait_until_preset_release_commits(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = app(ProvisionKeycloakUser::class)->handle(['subject' => 'release-bootstrap', 'nama' => 'Fixture', 'email' => 'release@example.test']);
        DB::table('role_permissions')->where('role_id', Role::where('kode', 'admin')->value('id'))->delete();
        $prepare = fn () => app(SyncRolePermissionPresets::class)->handle('test-release', 'Pasang ulang preset', 'test-parent');
        $payloads = [['worker_operation' => 'sync-presets'], ['worker_operation' => 'bootstrap']];
        $results = $this->race('sync-presets', $user->id, 'sakip:initial-bootstrap', $payloads, $prepare);
        $this->assertSame([0, true], $results);
        $this->assertSame('aktif', $user->fresh()->status);
        $this->assertDatabaseCount('auth_bootstraps', 1);
        $this->assertSame(6, DB::table('audit_log')->where('tindakan', 'role_permissions.ubah')->count());
    }

    public function test_disposable_guard_rejects_invalid_target_before_reset(): void
    {
        $connection = DB::connection();
        $original = $connection->getDatabaseName();
        $connection->setDatabaseName('unsafe-test-target');
        try {
            $this->assertDisposableDatabase($this->app);
            $this->fail('Target selain sakip_test harus ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sakip_test', $exception->getMessage());
        } finally {
            $connection->setDatabaseName($original);
        }
    }

    #[DataProvider('unitAndGrantMutations')]
    public function test_unit_and_grant_actions_reauthorize_after_waiting_for_actor_lock(string $operation, string $permission, string $denialEvent): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'superadmin')->sole();
        $actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $unit = Unit::create(['nama' => 'Unit sebelum pencabutan', 'status' => 'aktif', 'created_by' => $actor->id]);
        $payload = [
            'actor_id' => $actor->id, 'unit_id' => $unit->id, 'permission' => $permission,
            'data' => ['nama' => 'Mutasi yang harus ditolak', 'status' => 'aktif', 'version_token' => $unit->getVersionToken(), 'expected_nama' => null, 'expected_status' => null, 'snapshot' => null],
        ];
        $grant = null;
        if (in_array($operation, ['grant-create', 'grant-revoke'], true)) {
            $target = User::factory()->create(['status' => 'aktif']);
            $payload['data'] = [
                'user_id' => $target->id, 'permission_id' => Permission::where('kode', 'pengukuran:create')->value('id'),
                'unit_id' => $unit->id, 'alasan' => 'Alasan grant fixture',
            ];
            if ($operation === 'grant-revoke') {
                $grant = UserPermissionGrant::create([...$payload['data'], 'diberikan_oleh' => $actor->id]);
                $payload['grant_id'] = $grant->id;
            }
        }
        $denyId = null;
        $prepare = fn () => User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $revokeWhileBlocked = function (array $pids) use ($actor, $permission, &$denyId): void {
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertStringContainsString('users', $query);
            $this->assertStringContainsString('for share', $query);
            // Pencabutan baru dibuat setelah worker terbukti menunggu lock aktor.
            $denyId = UserPermissionDeny::create([
                'user_id' => $actor->id, 'permission_id' => Permission::where('kode', $permission)->value('id'),
                'unit_id' => null, 'alasan' => 'Pencabutan saat mutasi menunggu', 'ditetapkan_oleh' => $actor->id,
            ])->id;
        };

        $results = $this->race($operation, $actor->id, '', [$payload], $prepare, assertBlocked: $revokeWhileBlocked);

        $this->assertSame(['denied'], $results);
        $this->assertDatabaseCount('unit', 1);
        $this->assertDatabaseHas('unit', ['id' => $unit->id, 'nama' => 'Unit sebelum pencabutan', 'status' => 'aktif']);
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['unit.tambah', 'unit.ubah', 'unit.hapus'])->count());
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['user_permission_granted.tambah', 'user_permission_granted.hapus'])->count());
        $this->assertDatabaseCount('user_permission_granted', $grant === null ? 0 : 1);
        if ($grant !== null) {
            $this->assertDatabaseHas('user_permission_granted', ['id' => $grant->id, 'alasan' => 'Alasan grant fixture']);
        }
        $audit = AuditLog::where('tindakan', $denialEvent)->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertEquals([
            'permission' => $permission, 'keputusan' => 'ditolak', 'alasan' => 'explicit_deny',
            'sumber_allow' => ['roles' => [$role->id], 'grants' => []], 'deny' => [$denyId],
        ], $audit->dasar_izin);
    }

    public static function unitAndGrantMutations(): array
    {
        return [
            ['unit-create', 'unit:create', 'unit.tambah_ditolak'],
            ['unit-update', 'unit:update', 'unit.ubah_ditolak'],
            ['unit-delete', 'unit:delete', 'unit.hapus_ditolak'],
            ['grant-create', 'delegasi:update', 'user_permission_granted.ditolak'],
            ['grant-revoke', 'delegasi:update', 'user_permission_granted.ditolak'],
        ];
    }

    public function test_grant_creation_reauthorizes_after_waiting_for_active_role_lock(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $target = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'admin')->sole();
        $actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $permission = Permission::where('kode', 'delegasi:update')->sole();
        $unit = Unit::create(['nama' => 'Unit sebelum pencabutan permission role', 'status' => 'aktif', 'created_by' => $actor->id]);
        $revokeWhileBlocked = function (array $pids) use ($role, $permission): void {
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertStringContainsString('from "roles"', $query);
            $this->assertStringContainsString('for share', $query);
            // Cabut permission setelah worker terbukti menunggu lock role, sebelum transaksi parent dilepas.
            $this->assertSame(1, DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->delete());
        };

        $results = $this->race('grant-create', $role->id, '', [[
            'actor_id' => $actor->id, 'permission' => $permission->kode,
            'data' => [
                'user_id' => $target->id, 'permission_id' => Permission::where('kode', 'pengukuran:create')->value('id'),
                'unit_id' => $unit->id, 'alasan' => 'Alasan grant fixture role lock',
            ],
        ]], barrierTable: 'roles', assertBlocked: $revokeWhileBlocked);

        $this->assertSame(['denied'], $results);
        $this->assertDatabaseCount('user_permission_granted', 0);
        $this->assertSame(0, AuditLog::where('tindakan', 'user_permission_granted.tambah')->count());
        $audit = AuditLog::where('tindakan', 'user_permission_granted.ditolak')->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertEquals([
            'permission' => 'delegasi:update', 'keputusan' => 'ditolak', 'alasan' => 'no_allow',
            'sumber_allow' => ['roles' => [], 'grants' => []], 'deny' => [],
        ], $audit->dasar_izin);
    }

    public function test_revoke_grant_locks_users_before_grant_row(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $target = User::factory()->create(['status' => 'aktif']);
        $actor->roles()->attach(Role::where('kode', 'superadmin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $unit = Unit::create(['nama' => 'Unit lock grant', 'status' => 'aktif', 'created_by' => $actor->id]);
        $grant = UserPermissionGrant::create([
            'user_id' => $target->id, 'permission_id' => Permission::where('kode', 'pengukuran:create')->value('id'),
            'unit_id' => $unit->id, 'alasan' => 'Grant uji lock', 'diberikan_oleh' => $actor->id,
        ]);
        $prepare = fn () => User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $assertGrantUnlocked = function (array $pids) use ($grant): void {
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertStringContainsString('users', $query);
            $this->assertStringContainsString('for share', $query);
            // NOWAIT harus berhasil: worker yang menunggu pengguna belum boleh memegang grant.
            try {
                $row = DB::selectOne('select id from user_permission_granted where id = ? for update nowait', [$grant->id]);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '55P03') {
                    throw $exception;
                }
                $this->fail('Worker memegang grant sebelum memperoleh lock pengguna.');
            }
            $this->assertSame($grant->id, $row->id);
        };

        $results = $this->race('grant-revoke', $actor->id, '', [[
            'actor_id' => $actor->id, 'permission' => 'delegasi:update', 'grant_id' => $grant->id,
            'data' => ['alasan' => 'Alasan pencabutan fixture'],
        ]], $prepare, assertBlocked: $assertGrantUnlocked);

        $this->assertSame(['revoked'], $results);
        $this->assertDatabaseMissing('user_permission_granted', ['id' => $grant->id]);
        $this->assertSame(1, AuditLog::where('tindakan', 'user_permission_granted.hapus')->where('objek_id', $grant->id)->count());
    }

    #[DataProvider('jenisBerkasMutations')]
    public function test_jenis_berkas_reauthorizes_when_deny_commits_before_mutation(string $operation, string $permission, string $denialEvent, bool $technicalOnly = false): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $manager = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'superadmin')->sole();
        foreach ([$actor, $manager] as $user) {
            $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        }
        $setting = Pengaturan::create(['kunci' => 'berkas.unggahan_aktif', 'nilai' => 'true', 'tipe' => 'boolean', 'grup' => 'berkas']);
        $jenis = JenisBerkas::create([
            'nama' => 'Persyaratan sebelum pencabutan', 'tahap' => 'pengukuran', 'wajib' => false,
            'izinkan_file' => true, 'format_diizinkan' => 'pdf', 'ukuran_maks_kb' => 1024, 'created_by' => $actor->id,
        ]);
        $before = $jenis->fresh()->getAttributes();
        $payload = [
            'actor_id' => $actor->id, 'permission' => $permission, 'jenis_id' => $jenis->id,
            'data' => $operation === 'jenis-create'
                ? ['nama' => 'Persyaratan setelah pencabutan', 'tahap' => 'pengukuran', 'wajib' => false, 'izinkan_file' => true]
                : ['alasan' => 'Fixture pencabutan izin saat menunggu', 'expected_updated_at' => $jenis->updated_at->toISOString()],
        ];
        if ($operation === 'jenis-update') {
            $payload['data'] += ['nama' => 'Persyaratan setelah pencabutan', 'tahap' => 'pengukuran', 'wajib' => false, 'izinkan_file' => true];
        } elseif ($operation === 'jenis-technical') {
            $payload['data']['ukuran_maks_kb'] = 2048;
        }
        if ($technicalOnly) {
            $payload['data'] += ['format_diizinkan' => 'pdf,docx', 'ukuran_maks_kb' => 2048];
        }
        $prepare = function () use ($actor, $manager, $setting, $jenis): void {
            // Writer deny mengunci kedua pengguna; urutan sama mencegah inversi fixture.
            User::whereIn('id', [$actor->id, $manager->id])->orderBy('id')->lockForUpdate()->get();
            Pengaturan::whereKey($setting->id)->lockForUpdate()->firstOrFail();
            JenisBerkas::whereKey($jenis->id)->lockForUpdate()->firstOrFail();
        };
        $denyId = null;
        $denyWhileBlocked = function (array $pids) use ($actor, $manager, $permission, $technicalOnly, &$denyId): void {
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertMatchesRegularExpression('/(?:users|pengaturan|jenis_berkas).*for (?:share|update)/i', $query);
            // Jalur aplikasi aktual, setelah request worker terbukti menunggu lock PostgreSQL.
            $denyId = app(CreateDeny::class)->handle($manager, $actor->id, Permission::where('kode', $technicalOnly ? 'pengaturan:update' : $permission)->value('id'), null, 'Pencabutan sah sebelum mutasi')->id;
        };

        $results = $this->race($operation, $actor->id, '', [$payload], $prepare, assertBlocked: $denyWhileBlocked);

        if ($technicalOnly) {
            $this->assertSame(['mutated'], $results);
            $saved = JenisBerkas::where('nama', 'Persyaratan setelah pencabutan')->sole();
            $this->assertSame($operation === 'jenis-create' ? null : 'pdf', $saved->format_diizinkan);
            $this->assertSame($operation === 'jenis-create' ? null : 1024, $saved->ukuran_maks_kb);
            $audit = AuditLog::where('tindakan', $operation === 'jenis-create' ? 'jenis_berkas.buat' : 'jenis_berkas.ubah')->sole();
            $this->assertSame($permission, $audit->dasar_izin['permission']);
            $this->assertTrue($audit->dasar_izin['allowed']);
            $this->assertSame(0, AuditLog::where('tindakan', $denialEvent)->count());

            return;
        }

        $this->assertSame(['denied'], $results, 'Izin yang dicabut saat menunggu lock harus diperiksa kembali sebelum mutasi.');
        $this->assertDatabaseCount('jenis_berkas', 1);
        $this->assertSame($before, $jenis->fresh()->getAttributes());
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['jenis_berkas.buat', 'jenis_berkas.ubah', 'jenis_berkas.hapus', 'jenis_berkas.batas_teknis_ubah', 'berkas.tandai_tidak_dapat_dipenuhi', 'berkas.cabut_tidak_dapat_dipenuhi'])->count());
        $audit = AuditLog::where('tindakan', $denialEvent)->sole();
        $this->assertFalse($audit->dasar_izin['allowed']);
        $this->assertSame($permission, $audit->dasar_izin['permission']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['reason']);
        $this->assertContains($denyId, $audit->dasar_izin['denies']);
    }

    public static function jenisBerkasMutations(): array
    {
        return [
            'create' => ['jenis-create', 'jenis_berkas:create', 'jenis_berkas.buat_ditolak'],
            'update' => ['jenis-update', 'jenis_berkas:update', 'jenis_berkas.ubah_ditolak'],
            'delete' => ['jenis-delete', 'jenis_berkas:delete', 'jenis_berkas.hapus_ditolak'],
            'technical' => ['jenis-technical', 'pengaturan:update', 'jenis_berkas.batas_teknis_ubah_ditolak'],
            'create strips revoked technical fields' => ['jenis-create', 'jenis_berkas:create', 'jenis_berkas.buat_ditolak', true],
            'update strips revoked technical fields' => ['jenis-update', 'jenis_berkas:update', 'jenis_berkas.ubah_ditolak', true],
        ];
    }

    public function test_storage_policy_reauthorizes_when_deny_commits_before_mutation(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $manager = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'superadmin')->sole();
        foreach ([$actor, $manager] as $user) {
            $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        }
        // Tanpa default: penolakan setelah lock juga tidak boleh menginisialisasi pengaturan.
        $payload = [
            'actor_id' => $actor->id, 'permission' => 'pengaturan:update',
            'data' => [
                'berkas_unggahan_aktif' => false, 'berkas_ukuran_maks_kb' => 2048,
                'berkas_format_diizinkan' => 'pdf', 'berkas_tautan_selalu_diizinkan' => true,
                'expected_updated_at' => now()->toISOString(), 'expected_version' => 1,
                'alasan' => 'Fixture pencabutan izin kebijakan storage',
            ],
        ];
        $prepare = fn () => User::whereIn('id', [$actor->id, $manager->id])->orderBy('id')->lockForUpdate()->get();
        $denyId = null;
        $denyWhileBlocked = function (array $pids) use ($actor, $manager, &$denyId): void {
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertStringContainsString('users', $query);
            $this->assertStringContainsString('for share', $query);
            $denyId = app(CreateDeny::class)->handle($manager, $actor->id, Permission::where('kode', 'pengaturan:update')->value('id'), null, 'Pencabutan sah sebelum mutasi storage')->id;
        };

        $results = $this->race('storage-update', $actor->id, '', [$payload], $prepare, assertBlocked: $denyWhileBlocked);

        $this->assertSame(['denied'], $results);
        $this->assertDatabaseCount('pengaturan', 0);
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['pengaturan.ubah', 'berkas.tandai_tidak_dapat_dipenuhi', 'berkas.cabut_tidak_dapat_dipenuhi'])->count());
        $audit = AuditLog::where('tindakan', 'pengaturan.ubah_ditolak')->sole();
        $this->assertFalse($audit->dasar_izin['allowed']);
        $this->assertSame('pengaturan:update', $audit->dasar_izin['permission']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['reason']);
        $this->assertContains($denyId, $audit->dasar_izin['denies']);
    }

    #[DataProvider('regulasiMutations')]
    public function test_regulasi_reauthorizes_when_deny_commits_while_request_waits(string $operation, string $permission, string $denialEvent): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $manager = User::factory()->create(['status' => 'aktif']);
        foreach ([$actor, $manager] as $user) {
            $user->roles()->attach(Role::where('kode', 'superadmin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        }
        $regulasi = Regulasi::create(['jenis' => 'kepmen', 'nomor' => 'LOCK-REGULASI', 'tahun' => 2026, 'tentang' => 'Regulasi awal', 'aktif' => true, 'created_by' => $actor->id]);
        $berkas = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Lampiran awal', 'uploaded_by' => $actor->id]);
        $before = $regulasi->fresh()->getAttributes();
        $data = ['jenis' => 'kepmen', 'nomor' => $operation === 'regulasi-create' ? 'LOCK-CREATE' : $regulasi->nomor, 'tahun' => 2026, 'tentang' => 'Perubahan yang harus ditolak', 'aktif' => true, 'alasan' => 'Pembaruan fixture konkurensi'];
        if ($operation === 'regulasi-update') {
            $data['versi'] = $regulasi->versi;
        }
        $payload = ['actor_id' => $actor->id, 'permission' => $permission, 'regulasi_id' => $regulasi->id, 'berkas_id' => $berkas->id, 'data' => $data];
        $prepare = function () use ($actor, $manager, $regulasi): void {
            // Writer akses mengambil user lebih dahulu; barrier domain menahan perubahan induk.
            User::whereIn('id', [$actor->id, $manager->id])->orderBy('id')->lockForUpdate()->get();
            Regulasi::whereKey($regulasi->id)->lockForUpdate()->firstOrFail();
        };
        $denyId = null;
        $denyWhileBlocked = function () use ($actor, $manager, $permission, &$denyId): void {
            $denyId = app(CreateDeny::class)->handle($manager, $actor->id, Permission::where('kode', $permission)->value('id'), null, 'Pencabutan sah saat request regulasi menunggu')->id;
        };

        $results = $this->race($operation, $actor->id, '', [$payload], $prepare, assertBlocked: $denyWhileBlocked);

        $this->assertSame(['denied'], $results, 'Keputusan izin sebelum lock tidak boleh dipakai untuk mutasi setelah deny committed.');
        $this->assertDatabaseCount('regulasi', 1);
        $this->assertSame($before, $regulasi->fresh()->getAttributes());
        $this->assertFalse($berkas->fresh()->trashed());
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['regulasi.buat', 'regulasi.ubah', 'regulasi.hapus', 'berkas.hapus'])->count());
        $audit = AuditLog::where('tindakan', $denialEvent)->sole();
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertSame($permission, $audit->dasar_izin['permission']);
        $this->assertContains($denyId, $audit->dasar_izin['deny']);
    }

    public static function regulasiMutations(): array
    {
        return [
            'create' => ['regulasi-create', 'regulasi:create', 'regulasi.buat_ditolak'],
            'update' => ['regulasi-update', 'regulasi:update', 'regulasi.ubah_ditolak'],
            'delete' => ['regulasi-delete', 'regulasi:delete', 'regulasi.hapus_ditolak'],
            'attachment' => ['regulasi-attachment', 'berkas:delete', 'berkas.hapus_ditolak'],
        ];
    }

    #[DataProvider('renstraMutations')]
    public function test_renstra_reauthorizes_when_deny_commits_while_request_waits(string $operation, string $permission, string $variant = ''): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $manager = User::factory()->create(['status' => 'aktif']);
        foreach ([$actor, $manager] as $user) {
            $user->roles()->attach(Role::where('kode', 'superadmin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        }
        $renstra = Renstra::create(['kode' => 'LOCK-RENSTRA', 'nama' => 'Renstra awal', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $actor->id]);
        $berkas = $renstra->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Lampiran awal', 'uploaded_by' => $actor->id]);
        $before = $renstra->fresh()->getAttributes();
        $data = ['expected_state' => $renstra->stateToken(), 'kode' => $operation === 'renstra-create' ? 'LOCK-CREATE' : $renstra->kode, 'nama' => 'Perubahan yang harus ditolak', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'alasan' => 'Pembaruan fixture konkurensi'];
        if ($variant === 'upload') {
            $data['lampiran'] = [['mode' => 'teks', 'isi_teks' => 'Lampiran baru yang harus ditolak']];
        }
        if ($variant === 'regulasi-null') {
            $data['regulasi_id'] = null;
        }
        $parentDeny = null;
        if ($variant === 'parent-update') {
            $parentDeny = app(CreateDeny::class)->handle($manager, $actor->id, Permission::where('kode', 'renstra:delete')->value('id'), null, 'Hanya jalur update yang semula diizinkan');
        }
        $payload = ['actor_id' => $actor->id, 'permission' => $permission, 'renstra_id' => $renstra->id, 'berkas_id' => $berkas->id, 'data' => $data];
        $prepare = function () use ($actor, $manager, $renstra): void {
            // Writer izin mengambil user lebih dahulu; induk menahan jalur mutasi setelah otorisasi request.
            User::whereIn('id', [$actor->id, $manager->id])->orderBy('id')->lockForUpdate()->get();
            Renstra::whereKey($renstra->id)->lockForUpdate()->firstOrFail();
        };
        $denyId = null;
        $denyWhileBlocked = function () use ($actor, $manager, $permission, &$denyId): void {
            $denyId = app(CreateDeny::class)->handle($manager, $actor->id, Permission::where('kode', $permission)->value('id'), null, 'Pencabutan sah saat request Renstra menunggu')->id;
        };

        $results = $this->race($operation, $actor->id, '', [$payload], $prepare, assertBlocked: $denyWhileBlocked);

        $this->assertSame([$variant === 'parent-berkas' ? 'validation-denied' : 'denied'], $results);
        $this->assertDatabaseCount('renstras', 1);
        $this->assertDatabaseCount('berkas', 1);
        $this->assertSame($before, $renstra->fresh()->getAttributes());
        $this->assertFalse($berkas->fresh()->trashed());
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['renstra.buat', 'renstra.ubah', 'renstra.hapus', 'berkas.hapus', 'berkas.unggah'])->count());
        $event = match ($operation) {
            'renstra-create' => 'renstra.buat_ditolak',
            'renstra-update' => 'renstra.ubah_ditolak',
            'renstra-delete' => 'renstra.hapus_ditolak',
            default => 'berkas.hapus_ditolak',
        };
        $audit = AuditLog::where('tindakan', $event)->sole();
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertSame($parentDeny ? 'renstra:delete' : $permission, $audit->dasar_izin['permission']);
        $this->assertContains($parentDeny?->id ?? $denyId, $audit->dasar_izin['deny']);
    }

    public static function renstraMutations(): array
    {
        return [
            'create' => ['renstra-create', 'renstra:create'],
            'update' => ['renstra-update', 'renstra:update'],
            'delete' => ['renstra-delete', 'renstra:delete'],
            'attachment' => ['renstra-attachment', 'berkas:delete'],
            'upload create' => ['renstra-create', 'berkas:upload', 'upload'],
            'upload update' => ['renstra-update', 'berkas:upload', 'upload'],
            'regulasi null create' => ['renstra-create', 'regulasi:read', 'regulasi-null'],
            'regulasi null update' => ['renstra-update', 'regulasi:read', 'regulasi-null'],
            'attachment parent update' => ['renstra-attachment', 'renstra:update', 'parent-update'],
            'parent berkas delete' => ['renstra-delete', 'berkas:delete', 'parent-berkas'],
        ];
    }

    /**
     * Request lolos gerbang FormRequest lalu menunggu kunci aktor di Action;
     * deny yang commit selama menunggu wajib menolak mutasi lewat otorisasi
     * ulang di dalam transaksi, dengan tepat satu audit dari Action itu.
     */
    #[DataProvider('rencanaAksiMutations')]
    public function test_rencana_aksi_reauthorize_saat_deny_commit_selagi_request_menunggu(string $operation, string $permission, string $denialEvent, string $alasan): void
    {
        [$actor, $indicator] = $this->formulaFixture();
        $indicator->update(['tipe_perhitungan' => 'manual']);
        $manager = User::factory()->create(['status' => 'aktif']);
        $manager->roles()->attach(Role::where('kode', 'superadmin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $manager->id, 'created_at' => now()]);
        // Worker memakai jam nyata: jalur Perencanaan global hanya terikat
        // penutupan, sehingga jadwal tahun berjalan yang ditutup akhir tahun
        // tetap terbuka kapan pun test dijalankan.
        $tahun = (int) now()->year;
        $period = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $schedule = JadwalTahunan::create(['renstra_id' => $indicator->sasaranStrategis->renstra_id, 'tahun' => $tahun,
            'rencana_aksi_mulai' => "{$tahun}-01-01", 'rencana_aksi_selesai' => "{$tahun}-12-31", 'penutupan' => "{$tahun}-12-31", 'status' => 'aktif', 'activated_at' => now()]);
        PeriodeJadwal::create(['jadwal_id' => $schedule->id, 'periode_id' => $period->id, 'pengisian_mulai' => "{$tahun}-01-01",
            'pengisian_selesai' => "{$tahun}-06-30", 'reviu_mulai' => "{$tahun}-07-01", 'reviu_selesai' => "{$tahun}-12-31"]);
        $snapshot = JadwalSnapshot::create(['jadwal_id' => $schedule->id, 'indikator_id' => $indicator->id, 'periode_mulai_id' => $period->id,
            'unit_id' => $indicator->unit_id, 'nama' => 'Indikator Race', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2,
            'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        PenugasanIndikator::create(['indikator_id' => $indicator->id, 'user_id' => $actor->id,
            'tanggal_mulai_berlaku' => "{$tahun}-01-01", 'ditetapkan_oleh' => $manager->id, 'created_at' => now()]);
        $header = $operation === 'ra-simpan' ? app(EnsureDraftRencanaAksi::class)->handle($actor, $indicator->id, $tahun) : null;
        $data = $operation === 'ra-simpan'
            ? ['expected_versi' => 1, 'expected_snapshot_id' => $snapshot->id, 'expected_snapshot_versi' => 1,
                'targets' => [['periode_id' => $period->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null]]]
            : ['indikator_id' => $indicator->id, 'tahun' => $tahun];
        $payload = ['actor_id' => $actor->id, 'permission' => $permission, 'izin_unit_id' => $indicator->unit_id, 'rencana_aksi_id' => $header?->id, 'data' => $data];
        $prepare = function () use ($actor, $manager, $indicator, $header): void {
            // Writer izin mengambil user lebih dahulu; induk menahan Action setelah otorisasi request.
            User::whereIn('id', [$actor->id, $manager->id])->orderBy('id')->lockForUpdate()->get();
            $header !== null
                ? RencanaAksi::whereKey($header->id)->lockForUpdate()->firstOrFail()
                : IndikatorKinerja::whereKey($indicator->id)->lockForUpdate()->firstOrFail();
        };
        $denyId = null;
        $denyWhileBlocked = function () use ($actor, $manager, $permission, &$denyId): void {
            $denyId = app(CreateDeny::class)->handle($manager, $actor->id, Permission::where('kode', $permission)->value('id'), null, 'Pencabutan sah saat request Rencana Aksi menunggu')->id;
        };

        $results = $this->race($operation, $actor->id, '', [$payload], $prepare, assertBlocked: $denyWhileBlocked);

        $this->assertSame(['denied'], $results, 'Keputusan izin di request tidak boleh dipakai untuk mutasi setelah deny committed.');
        $this->assertDatabaseCount('rencana_aksi', $header === null ? 0 : 1);
        if ($header !== null) {
            $this->assertSame(1, $header->fresh()->versi);
            $this->assertDatabaseCount('rencana_aksi_target', 0);
        }
        $this->assertSame($header === null ? 0 : 1, AuditLog::where('tindakan', 'rencana_aksi.buat')->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'rencana_aksi.ubah')->count());
        $audit = AuditLog::where('tindakan', $denialEvent)->sole();
        $this->assertSame($alasan, $audit->alasan, 'Penolakan wajib berasal dari otorisasi ulang di dalam transaksi Action.');
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertSame($permission, $audit->dasar_izin['permission']);
        $this->assertContains($denyId, $audit->dasar_izin['deny']);
        if ($header === null) {
            $this->assertSame(['tahun' => $tahun, 'indikator_id' => $indicator->id], $audit->nilai_baru);
        }
    }

    public static function rencanaAksiMutations(): array
    {
        return [
            'ensure-draft' => ['ra-ensure-draft', 'rencana_aksi:create', 'rencana_aksi.buat_ditolak', 'Izin pembuatan rencana aksi tidak tersedia atau telah dicabut.'],
            'simpan' => ['ra-simpan', 'rencana_aksi:update', 'rencana_aksi.ubah_ditolak', 'Izin penyimpanan target rencana aksi tidak tersedia atau telah dicabut.'],
        ];
    }

    #[DataProvider('invalidatedRegulasiReferences')]
    public function test_renstra_create_rechecks_reference_after_concurrent_regulasi_change(bool $deleteRegulasi): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $actor->roles()->attach(Role::where('kode', 'superadmin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $regulasi = Regulasi::create(['jenis' => 'kepmen', 'nomor' => 'RUJUKAN-CREATE', 'tahun' => 2026, 'tentang' => 'Rujukan awal aktif', 'aktif' => true, 'created_by' => $actor->id]);
        $data = ['kode' => 'RENSTRA-RUJUKAN', 'nama' => 'Renstra dengan rujukan yang harus ditolak', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'regulasi_id' => $regulasi->id, 'lampiran' => [['mode' => 'teks', 'isi_teks' => 'Lampiran tidak boleh tersimpan']]];
        $payload = [
            'actor_id' => $actor->id, 'permission' => 'renstra:create', 'data' => $data,
            'expected_error_field' => 'regulasi_id',
            'expected_error_message' => 'Dasar aturan regulasi yang dipilih tidak ditemukan.',
        ];
        $prepare = fn () => User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $changeWhileBlocked = function (array $pids) use ($actor, $regulasi, $deleteRegulasi): void {
            // Lock pertama Action membuktikan FormRequest sudah menerima Regulasi yang masih aktif.
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertMatchesRegularExpression('/from "users".*for share/i', $query);
            if ($deleteRegulasi) {
                app(DeleteRegulasiAction::class)->handle($actor, $regulasi, 'Menghapus rujukan sebelum pembuatan Renstra');
                $this->assertModelMissing($regulasi);
            } else {
                app(UpdateRegulasiAction::class)->handle($actor, $regulasi, [
                    'jenis' => $regulasi->jenis, 'nomor' => $regulasi->nomor, 'tahun' => $regulasi->tahun,
                    'tentang' => $regulasi->tentang, 'aktif' => false, 'versi' => $regulasi->versi,
                    'alasan' => 'Menonaktifkan rujukan sebelum pembuatan Renstra',
                ]);
                $this->assertFalse($regulasi->fresh()->aktif);
            }
        };

        $results = $this->race('renstra-create', $actor->id, '', [$payload], $prepare, assertBlocked: $changeWhileBlocked);

        $this->assertSame(['validation-denied'], $results, 'Rujukan yang berubah sesudah validasi harus menjadi error regulasi_id, bukan sukses atau server error.');
        $this->assertDatabaseCount('renstras', 0);
        $this->assertDatabaseCount('berkas', 0);
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['renstra.buat', 'berkas.unggah'])->count());
        $this->assertSame($regulasi->id, AuditLog::where('tindakan', $deleteRegulasi ? 'regulasi.hapus' : 'regulasi.ubah')->sole()->objek_id);

        // Rujukan opsional tetap sah, baik omitted maupun null, dengan izin aktor yang sama.
        unset($data['regulasi_id'], $data['lampiran']);
        if ($deleteRegulasi) {
            $data['regulasi_id'] = null;
        }
        $this->actingAs($actor)->post('/renstra', $data)->assertRedirect('/renstra')->assertSessionHasNoErrors();
        $this->assertNull(Renstra::sole()->regulasi_id);
    }

    public static function invalidatedRegulasiReferences(): array
    {
        return ['deactivated' => [false], 'deleted' => [true]];
    }

    public function test_renstra_rechecks_inactive_reference_after_concurrent_reference_change(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $actor->roles()->attach(Role::where('kode', 'superadmin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $regulasiA = Regulasi::create(['jenis' => 'kepmen', 'nomor' => 'RUJUKAN-A', 'tahun' => 2026, 'tentang' => 'Rujukan lama nonaktif', 'aktif' => false, 'created_by' => $actor->id]);
        $regulasiB = Regulasi::create(['jenis' => 'kepmen', 'nomor' => 'RUJUKAN-B', 'tahun' => 2026, 'tentang' => 'Rujukan baru aktif', 'aktif' => true, 'created_by' => $actor->id]);
        $renstra = Renstra::create(['kode' => 'RUJUKAN-BERSAMA', 'nama' => 'Renstra awal', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'regulasi_id' => $regulasiA->id, 'created_by' => $actor->id]);
        $berkas = $renstra->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Lampiran awal', 'uploaded_by' => $actor->id]);
        $berkasBefore = $berkas->fresh()->getAttributes();
        $expectedRenstra = null;
        $payload = [
            'actor_id' => $actor->id, 'permission' => 'renstra:update', 'renstra_id' => $renstra->id,
            'expected_error_field' => 'regulasi_id',
            'expected_error_message' => 'Dasar aturan regulasi yang dipilih tidak ditemukan.',
            'data' => ['expected_state' => $renstra->stateToken(), 'nama' => 'Perubahan stale tidak boleh tersimpan', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'regulasi_id' => $regulasiA->id, 'lampiran' => [['mode' => 'teks', 'isi_teks' => 'Lampiran stale tidak boleh tersimpan']], 'alasan' => 'Mempertahankan rujukan dari formulir lama'],
        ];
        $prepare = fn () => Renstra::whereKey($renstra->id)->lockForUpdate()->firstOrFail();
        $changeWhileBlocked = function (array $pids) use ($actor, $renstra, $regulasiA, $regulasiB, &$expectedRenstra): void {
            // Query lock Action membuktikan request telah lolos FormRequest saat rujukannya masih A.
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertMatchesRegularExpression('/from "renstras".*for update/i', $query);
            // Lock target sudah dipegang sebelum menunggu Renstra; savepoint memulihkan transaksi probe.
            try {
                DB::transaction(fn () => Regulasi::whereKey($regulasiA->id)->lock('for update nowait')->firstOrFail());
                $this->fail('Worker harus mengunci Regulasi sebelum mencoba mengunci Renstra.');
            } catch (QueryException $exception) {
                $this->assertSame('55P03', $exception->errorInfo[0] ?? null);
            }
            $this->assertSame(1, DB::transactionLevel());
            $updated = app(UpdateRenstraAction::class)->handle($actor, $renstra, [
                'expected_state' => $renstra->stateToken(), 'nama' => 'Perubahan sah ke rujukan B', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
                'regulasi_id' => $regulasiB->id, 'alasan' => 'Mengganti rujukan dengan regulasi aktif',
            ]);
            $this->assertSame($regulasiB->id, $updated->regulasi_id);
            $expectedRenstra = $updated->fresh()->getAttributes();
        };

        $results = $this->race('renstra-update', $actor->id, '', [$payload], $prepare, assertBlocked: $changeWhileBlocked);

        $this->assertSame(['validation-denied'], $results, 'Pengecualian rujukan nonaktif harus memakai FK terkunci setelah perubahan konkuren.');
        $this->assertSame($expectedRenstra, $renstra->fresh()->getAttributes());
        $this->assertSame($berkasBefore, $berkas->fresh()->getAttributes());
        $this->assertDatabaseCount('berkas', 1);
        $this->assertSame(0, AuditLog::where('tindakan', 'berkas.unggah')->count());
        $this->assertSame($regulasiB->id, AuditLog::where('tindakan', 'renstra.ubah')->sole()->nilai_baru['regulasi_id']);
        $this->assertSame($regulasiB->id, AuditLog::where('tindakan', 'renstra.ubah_regulasi')->sole()->nilai_baru['regulasi_id']);
    }

    #[DataProvider('phaseCPresetMutations')]
    public function test_regulasi_and_renstra_reauthorize_after_actual_preset_release(string $domain): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'pegawai')->firstOrFail();
        $actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $permission = Permission::where('kode', $domain.':update')->firstOrFail();
        $role->permissions()->attach($permission->id, ['id' => Str::uuid(), 'created_at' => now()]);
        $record = $domain === 'regulasi'
            ? Regulasi::create(['jenis' => 'kepmen', 'nomor' => 'PRESET', 'tahun' => 2026, 'tentang' => 'Regulasi awal', 'aktif' => true, 'created_by' => $actor->id])
            : Renstra::create(['kode' => 'PRESET', 'nama' => 'Renstra awal', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $actor->id]);
        $before = $record->fresh()->getAttributes();
        $data = $domain === 'regulasi'
            ? ['jenis' => 'kepmen', 'nomor' => 'PRESET', 'tahun' => 2026, 'tentang' => 'Perubahan ditolak', 'aktif' => true, 'versi' => $record->versi, 'alasan' => 'Perubahan fixture preset']
            : ['expected_state' => $record->stateToken(), 'nama' => 'Perubahan ditolak', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'alasan' => 'Perubahan fixture preset'];
        $payload = ['actor_id' => $actor->id, 'permission' => $permission->kode, $domain.'_id' => $record->id, 'data' => $data];
        $prepare = function (): void {
            // Rilis preset memakai urutan role lalu permission; worker harus menunggu role yang sama.
            Role::orderBy('id')->lockForUpdate()->get();
        };
        $releaseWhileBlocked = function (): void {
            app(SyncRolePermissionPresets::class)->handle('fixture-phase-c', 'Pulihkan preset resmi saat mutasi menunggu', 'test-process:'.getmypid());
        };

        $results = $this->race($domain.'-update', $actor->id, '', [$payload], $prepare, 'roles', $releaseWhileBlocked);

        $this->assertSame(['denied'], $results);
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertFalse($role->permissions()->whereKey($permission->id)->exists());
        $this->assertSame(0, AuditLog::where('tindakan', $domain.'.ubah')->count());
        $audit = AuditLog::where('tindakan', $domain.'.ubah_ditolak')->sole();
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertSame('no_allow', $audit->dasar_izin['alasan']);
        $this->assertSame(['roles' => [], 'grants' => []], $audit->dasar_izin['sumber_allow']);
        $this->assertSame([], $audit->dasar_izin['deny']);
    }

    public static function phaseCPresetMutations(): array
    {
        return [['regulasi'], ['renstra']];
    }

    /** Dua writer aplikasi pada revisi sama: tepat satu commit, lainnya konflik. */
    public function test_phase_d_two_actual_writers_share_parent_revision(): void
    {
        [$actor, $parent, $child] = $this->formulaFixture();
        $payload = ['tipe_perhitungan' => 'penjumlahan', 'expected_updated_at' => $parent->updated_at->toISOString(),
            'komponen' => [array_merge($child->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']), ['label' => 'Revisi bersamaan'])],
            'alasan' => 'Perubahan bersamaan dari dua editor.'];
        $assignment = ['actor_id' => $actor->id, 'indikator_id' => $parent->id, 'data' => $payload];
        $results = $this->race('formula-update', $parent->id, '', [$assignment, $assignment],
            prepare: fn () => IndikatorKinerja::whereKey($parent->id)->lockForUpdate()->firstOrFail());
        $this->assertEqualsCanonicalizing(['saved', 'conflict'], $results);
        $this->assertSame('Revisi bersamaan', $child->fresh()->label);
        $this->assertSame(1, AuditLog::where('tindakan', 'komponen.ubah')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'indikator.ubah_ditolak')->count());
    }

    /** Reader menunggu writer nyata; data, formula dan token seluruhnya berasal dari commit baru. */
    public function test_phase_d_reader_cannot_mix_parent_and_child_revisions(): void
    {
        [$actor, $parent, $child] = $this->formulaFixture();
        $oldRevision = $parent->updated_at->toISOString();
        $newRevision = null;
        $results = $this->race('formula-read', $parent->id, '', [['actor_id' => $actor->id, 'indikator_id' => $parent->id]],
            prepare: fn () => IndikatorKinerja::whereKey($parent->id)->lockForUpdate()->firstOrFail(),
            assertBlocked: function () use ($actor, $parent, $child, $oldRevision, &$newRevision): void {
                $result = app(ChangeIndicatorFormula::class)->handle($actor, $parent, [
                    'tipe_perhitungan' => 'penjumlahan', 'presisi' => 4, 'expected_updated_at' => $oldRevision,
                    'komponen' => [array_merge($child->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']), ['bobot' => '2'])],
                    'alasan' => 'Perubahan definisi selama editor menunggu.',
                ]);
                $newRevision = $result['indikator']->updated_at->toISOString();
            });
        $editor = $results[0];
        $this->assertNotSame($oldRevision, $newRevision);
        $this->assertSame($newRevision, $editor['revision']);
        $this->assertSame(4, $editor['indikator']['presisi']);
        $this->assertSame('2.000000000000', $editor['komponen'][0]['bobot']);
        $this->assertStringContainsString('2', $editor['formulaContract']['formula_text']);
        $this->assertTrue($editor['validation']['is_valid']);
    }

    /** ACL writer nyata membuat deny saat editor menunggu kunci aktor. */
    public function test_phase_d_permission_revoked_before_commit_by_actual_acl_writer(): void
    {
        [$actor, $parent, $child] = $this->formulaFixture();
        $admin = User::factory()->create(['status' => 'aktif']);
        $admin->roles()->attach(Role::where('kode', 'admin')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $admin->id, 'created_at' => now()]);
        $permission = Permission::where('kode', 'komponen:create')->firstOrFail();
        $assignment = ['actor_id' => $actor->id, 'indikator_id' => $parent->id, 'data' => [
            'tipe_perhitungan' => 'penjumlahan', 'expected_updated_at' => $parent->updated_at->toISOString(),
            'komponen' => [['kode' => 'baru', 'label' => 'Baru', 'peran' => 'penjumlah', 'bobot' => '1', 'urutan' => 2, 'aktif' => true]],
        ]];
        $result = $this->race('formula-update', $actor->id, '', [$assignment],
            prepare: fn () => User::whereKey($actor->id)->lockForUpdate()->firstOrFail(),
            assertBlocked: fn () => app(CreateDeny::class)->handle($admin, $actor->id, $permission->id, null, 'Pencabutan hak sebelum simpan definisi.'));
        $this->assertSame(['denied'], $result);
        $this->assertSame($parent->updated_at->toISOString(), $parent->fresh()->updated_at->toISOString());
        $this->assertSame(1, $parent->komponen()->count());
        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->firstOrFail();
        $this->assertSame('komponen:create', $audit->dasar_izin['permission']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['alasan']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.buat']);
    }

    public function test_pj_two_appends_from_one_state_have_one_winner(): void
    {
        [$actor, $indicator] = $this->formulaFixture();
        $target = User::factory()->create(['status' => 'aktif']);
        $payload = ['actor_id' => $actor->id, 'indikator_id' => $indicator->id, 'data' => [
            'user_id' => $target->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'expected_state' => $indicator->assignmentStateToken(),
        ]];
        $results = $this->race('pj-assign', $indicator->id, '', [$payload, $payload],
            prepare: fn () => User::whereKey($actor->id)->lockForUpdate()->firstOrFail());
        $this->assertEqualsCanonicalizing(['assigned', 'conflict'], $results);
        $this->assertDatabaseCount('penanggung_jawab', 1);
        $this->assertSame(1, AuditLog::where('tindakan', 'penanggung_jawab.tetapkan')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
    }

    public function test_pj_reader_waits_for_append_and_returns_matching_token_and_history(): void
    {
        [$actor, $indicator] = $this->formulaFixture();
        $payload = ['indikator_id' => $indicator->id];
        $results = $this->race('pj-read', $indicator->id, '', [$payload],
            prepare: fn () => IndikatorKinerja::whereKey($indicator->id)->lockForUpdate()->firstOrFail(),
            assertBlocked: fn () => app(AssignPenanggungJawab::class)->handle($actor, $indicator, [
                'user_id' => $actor->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'expected_state' => $indicator->assignmentStateToken(),
            ]));
        $this->assertSame(1, $results[0]['count']);
        $this->assertSame($actor->id, $results[0]['pic_id']);
        $this->assertSame($indicator->fresh()->assignmentStateToken(), $results[0]['token']);
    }

    #[DataProvider('pjBoundaryCases')]
    public function test_pj_rechecks_locked_state_after_waiting(string $change, string $field): void
    {
        [$actor, $indicator] = $this->formulaFixture();
        $target = User::factory()->create(['status' => 'aktif']);
        $payload = ['actor_id' => $actor->id, 'indikator_id' => $indicator->id, 'expected_error_field' => $field, 'data' => [
            'user_id' => $target->id, 'tanggal_mulai_berlaku' => '2026-02-01', 'expected_state' => $indicator->assignmentStateToken(),
        ]];
        $results = $this->race('pj-assign', $indicator->id, '', [$payload],
            prepare: fn () => User::whereIn('id', [$actor->id, $target->id])->orderBy('id')->lockForUpdate()->get(),
            assertBlocked: function () use ($change, $actor, $target, $indicator): void {
                match ($change) {
                    'actor' => $actor->update(['status' => 'nonaktif']),
                    'target' => $target->update(['status' => 'nonaktif']),
                    'lifecycle' => $indicator->update(['status' => 'arsip']),
                    'unit' => $indicator->update(['unit_id' => Unit::create(['nama' => 'Unit Baru', 'created_by' => $actor->id])->id]),
                    'backdate' => app(AssignPenanggungJawab::class)->handle($actor, $indicator, [
                        'user_id' => $actor->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'expected_state' => $indicator->assignmentStateToken(),
                    ]),
                    default => throw new RuntimeException('Kasus race tidak dikenal.'),
                };
            });
        $this->assertSame([$change === 'actor' ? 'denied' : 'conflict'], $results);
        $this->assertDatabaseCount('penanggung_jawab', $change === 'backdate' ? 1 : 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
    }

    public static function pjBoundaryCases(): array
    {
        return [['actor', 'authorization'], ['target', 'user_id'], ['lifecycle', 'indikator'], ['unit', 'expected_state'], ['backdate', 'expected_state']];
    }

    public function test_pj_change_waits_until_submission_provenance_is_frozen(): void
    {
        [$operator, $indicator] = $this->formulaFixture();
        $indicator->update(['tipe_perhitungan' => 'manual']);
        $pic = User::factory()->create(['status' => 'aktif']);
        $pic->roles()->attach(Role::where('kode', 'pegawai')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $operator->id, 'created_at' => now()]);
        $target = User::factory()->create(['status' => 'aktif']);
        $permission = Permission::where('kode', 'pengukuran:update')->sole();
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $pic->id, 'permission_id' => $permission->id,
            'unit_id' => $indicator->unit_id, 'alasan' => 'Fixture', 'diberikan_oleh' => $operator->id, 'created_at' => now()]);
        PenugasanIndikator::create(['indikator_id' => $indicator->id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $operator->id, 'created_at' => now()]);
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));
        $renstra = $indicator->sasaranStrategis->renstra;
        $pk = RenstraPk::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'nomor_pk' => 'PK-PJ', 'tanggal_pk' => '2026-01-01', 'created_by' => $operator->id]);
        $period = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $schedule = JadwalTahunan::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31', 'status' => 'aktif', 'activated_at' => now()]);
        PeriodeJadwal::create(['jadwal_id' => $schedule->id, 'periode_id' => $period->id, 'pengisian_mulai' => '2026-03-01',
            'pengisian_selesai' => '2026-03-15', 'reviu_mulai' => '2026-03-15', 'reviu_selesai' => '2026-04-15']);
        $snapshot = JadwalSnapshot::create(['jadwal_id' => $schedule->id, 'indikator_id' => $indicator->id, 'periode_mulai_id' => $period->id,
            'unit_id' => $indicator->unit_id, 'nama' => 'Indikator PJ', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2,
            'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        $plan = RencanaAksi::create(['indikator_id' => $indicator->id, 'tahun' => 2026, 'unit_id' => $indicator->unit_id,
            'jadwal_tahunan_id' => $schedule->id, 'jadwal_snapshot_id' => $snapshot->id, 'penanggung_jawab_id' => $pic->id, 'created_by' => $pic->id,
            'status_alur' => 'disahkan', 'disahkan_by' => $operator->id, 'disahkan_at' => now()]);
        RencanaAksiVersi::create(['rencana_aksi_id' => $plan->id, 'jadwal_snapshot_id' => $snapshot->id, 'nomor' => 1, 'diajukan_by' => $pic->id,
            'diajukan_at' => now(), 'jalur_pengajuan' => 'pic', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'snapshot' => ['target_periode' => [['periode_id' => $period->id, 'nilai' => 70, 'status_perhitungan' => 'terhitung', 'komponen' => []]]],
            'disahkan_by' => $operator->id, 'disahkan_at' => now()]);
        $measurement = PengukuranKinerja::create(['indikator_id' => $indicator->id, 'tahun' => 2026, 'periode_id' => $period->id,
            'jadwal_snapshot_id' => $snapshot->id, 'sumber_nilai' => 'manual', 'created_by' => $pic->id]);
        $payload = ['actor_id' => $operator->id, 'indikator_id' => $indicator->id, 'data' => [
            'user_id' => $target->id, 'tanggal_mulai_berlaku' => '2026-02-01', 'expected_state' => $indicator->assignmentStateToken(), 'alasan' => 'Pergantian setelah pengajuan',
        ]];
        $results = $this->race('pj-change', $indicator->id, '', [$payload],
            prepare: fn () => app(ChangePengukuran::class)->handle($pic, $measurement->id, 'ajukan', ['versi' => 1, 'nilai' => 70]));
        $this->assertSame(['assigned'], $results);
        $version = $measurement->fresh()->latestVersion;
        $this->assertSame($pic->id, $version->diajukan_by);
        $this->assertSame($pic->id, $version->dasar_izin_pengajuan['pic_id']);
        $this->assertSame($pic->id, $version->snapshot['pic']['id']);
        $this->assertSame($target->id, $measurement->fresh()->effectivePic()->user_id);
    }

    private function formulaFixture(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['status' => 'aktif']);
        $actor->roles()->attach(Role::where('kode', 'perencanaan')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $renstra = Renstra::create(['kode' => 'D-RACE', 'nama' => 'Renstra Race', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $actor->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'D-RACE', 'deskripsi' => 'Sasaran Race', 'urutan' => 1]);
        $unit = Unit::create(['nama' => 'Unit Race', 'status' => 'aktif', 'created_by' => $actor->id]);
        $parent = IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'D-RACE', 'nama' => 'Indikator Race', 'satuan' => 'poin', 'tipe_perhitungan' => 'penjumlahan', 'arah' => 'naik_baik',
            'presisi' => 2, 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025, 'created_by' => $actor->id, 'created_by_role' => 'perencanaan']);
        $child = IndikatorKomponen::create(['indikator_id' => $parent->id, 'kode' => 'a', 'label' => 'A', 'peran' => 'penjumlah', 'bobot' => '1', 'urutan' => 1, 'aktif' => true, 'created_by' => $actor->id]);

        return [$actor, $parent, $child];
    }

    /**
     * Kedua proses harus terbukti menunggu lock PostgreSQL yang sama sebelum dilepas.
     * Fixture sudah committed; ini tidak memakai transaksi luar RefreshDatabase.
     *
     * @return list<string|bool|int>
     */
    private function race(string $operation, string $subject, string $lock, array $assignments = [], ?callable $prepare = null, string $barrierTable = 'users', ?callable $assertBlocked = null): array
    {
        $connection = DB::connection();
        $environment = [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => (string) $connection->getConfig('host'),
            'DB_PORT' => (string) $connection->getConfig('port'),
            'DB_DATABASE' => (string) $connection->getConfig('database'),
            'DB_USERNAME' => (string) $connection->getConfig('username'),
            'DB_PASSWORD' => (string) $connection->getConfig('password'),
            'SAKIP_TEST_ALLOW_DATABASE_RESET' => '1',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];
        $processes = [];
        $inputs = [];
        $this->workersStopped = false;
        DB::beginTransaction();
        try {
            if ($prepare !== null) {
                $prepare();
            } elseif ($barrierTable === 'roles') {
                Role::whereKey($subject)->lockForUpdate()->firstOrFail();
            } elseif (in_array($operation, ['assign-role', 'create-deny', 'revoke-deny'], true)) {
                User::whereKey($subject)->lockForUpdate()->firstOrFail();
            } else {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$lock]);
            }
            $workers = $assignments === [] ? 2 : count($assignments);
            for ($index = 0; $index < $workers; $index++) {
                $input = new InputStream;
                $arguments = [PHP_BINARY, base_path('tests/Support/concurrency-worker.php'), $assignments[$index]['worker_operation'] ?? $operation, $subject];
                if ($assignments !== []) {
                    $arguments[] = json_encode($assignments[$index], JSON_THROW_ON_ERROR);
                }
                $process = new Process($arguments, base_path(), $environment, $input, 20);
                $process->start();
                $processes[] = $process;
                $inputs[] = $input;
            }
            $this->until(function () use ($processes): bool {
                return collect($processes)->every(fn ($process) => preg_match('/READY:(\d+)/', $process->getOutput()) === 1);
            }, $processes);
            $pids = [];
            foreach ($processes as $index => $process) {
                preg_match('/READY:(\d+)/', $process->getOutput(), $match);
                $pids[] = (int) $match[1];
                $inputs[$index]->write("GO\n");
                $inputs[$index]->close();
            }
            $this->assertCount(count($pids), array_unique($pids));
            $this->until(function () use ($pids): bool {
                DB::select('select pg_stat_clear_snapshot()');

                return DB::table('pg_stat_activity')->whereIn('pid', $pids)->where('wait_event_type', 'Lock')->count() === count($pids);
            }, $processes);
            $parentPid = DB::selectOne('select pg_backend_pid() as pid')->pid;
            $directlyBlocked = 0;
            foreach ($pids as $pid) {
                $directlyBlocked += DB::selectOne('select ? = any(pg_blocking_pids(?)) as blocked', [$parentPid, $pid])->blocked ? 1 : 0;
            }
            $this->assertGreaterThan(0, $directlyBlocked, 'Worker harus benar-benar menunggu lock parent PostgreSQL.');
            if ($assertBlocked !== null) {
                $assertBlocked($pids, (int) $parentPid);
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertSame(1, preg_match('/RESULT:(.+)/', $process->getOutput(), $match));
                $results[] = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
            $this->workersStopped = DB::transactionLevel() === 0 && collect($processes)->every(fn (Process $process) => ! $process->isRunning());
        }
    }

    /** @param list<Process> $processes */
    private function until(callable $ready, array $processes): void
    {
        $deadline = microtime(true) + 10;
        do {
            if ($ready()) {
                return;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->isRunning(), 'Worker selesai sebelum barrier PostgreSQL: '.$process->getOutput().$process->getErrorOutput());
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Dua worker tidak mencapai barrier PostgreSQL dalam batas waktu.');
    }
}
