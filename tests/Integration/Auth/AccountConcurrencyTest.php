<?php

namespace Tests\Integration\Auth;

use App\Actions\Access\SyncRolePermissionPresets;
use App\Actions\Auth\ActivateUser;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
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

class AccountConcurrencyTest extends TestCase
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
                $arguments = [PHP_BINARY, base_path('tests/Support/account-concurrency-worker.php'), $assignments[$index]['worker_operation'] ?? $operation, $subject];
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
