<?php

namespace Tests\Integration\Auth;

use App\Actions\Access\AssignRole;
use App\Actions\Access\ChangeRolePermission;
use App\Actions\Access\CreateDeny;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionReceipt;
use App\Services\Authorization\RolePermissionState;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_callbacks_create_one_identity_assignment_and_audit(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $results = $this->race('provision', 'concurrent-subject', 'sakip:sso:concurrent-subject');
        $this->assertSame($results[0], $results[1]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_roles', 1);
        $this->assertDatabaseCount('audit_log', 1);
        $this->assertDatabaseHas('users', ['keycloak_id' => 'concurrent-subject', 'is_active' => false]);
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
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame(5, DB::table('audit_log')->where('tindakan', 'role_permissions.ubah')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'user_roles.ubah')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'pengguna.aktivasi')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'auth.bootstrap')->count());
    }

    public function test_two_first_assignments_have_one_winner_and_one_stale_conflict(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $actor = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create();
        $admin = Role::where('kode', 'admin')->sole();
        $actor->roles()->attach($admin->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        foreach (Permission::whereIn('kode', ['pengguna:read', 'akses:update'])->get() as $permission) {
            $admin->permissions()->attach($permission->id, ['id' => Str::uuid(), 'created_at' => now()]);
        }
        $roles = [Role::where('kode', 'pic')->value('id'), Role::where('kode', 'pegawai')->value('id')];
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
        $actor = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create(['is_active' => true]);
        $role = Role::where('kode', 'admin')->sole();
        $actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        $role->permissions()->attach(Permission::where('kode', 'akses:update')->value('id'), ['id' => Str::uuid(), 'created_at' => now()]);
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
        $this->assertDatabaseCount('audit_log', 2);
        $this->assertSame(1, DB::table('audit_log')->where('objek_id', $deny->id)->where('tindakan', 'user_permission_denied.hapus')->count());
    }

    public static function denyScopes(): array
    {
        return [[false], [true]];
    }

    public function test_role_permission_concurrent_actors_have_one_delta_and_one_stale_conflict(): void
    {
        [$first, $second, $source, $target] = $this->roleEditors();
        $a = $this->rolePayload($first, $target);
        $b = $this->rolePayload($second, $target);
        $b['permission_id'] = Permission::where('kode', 'audit:read')->value('id');
        $results = $this->race('change-role-permission', $target->id, '', [$a, $b], barrierTable: 'roles');
        $this->assertEqualsCanonicalizing(['added', 'conflict'], $results);
        $this->assertSame(1, DB::table('role_permissions')->where('role_id', $target->id)->count());
        $this->assertSame(1, DB::table('audit_log')->where('objek_id', $target->id)->where('tindakan', 'role_permissions.ubah')->count());
    }

    #[DataProvider('authorizationRaces')]
    public function test_role_permission_rechecks_after_source_assignment_or_deny_commits(string $case): void
    {
        [$first, $second, $source, $target] = $this->roleEditors();
        $payload = $this->rolePayload($second, $target);
        $sourceToken = $this->rolePayload($first, $source)['expected_state'];
        $expected = (array) DB::table('user_roles')->where('user_id', $second->id)->first(['id', 'role_id', 'audit_id']);
        $prepare = function () use ($case, $first, $second, $source, $target, $sourceToken, $expected): void {
            $accessId = Permission::where('kode', 'akses:update')->value('id');
            match ($case) {
                'source' => app(ChangeRolePermission::class)->handle($first, $source->id, $accessId, 'revoke', 'Cabut sumber', $sourceToken),
                'assignment' => app(AssignRole::class)->handle($first, $second->id, $target->id, 'Ganti role aktor', $expected),
                'deny' => app(CreateDeny::class)->handle($first, $second->id, $accessId, null, 'Cabut izin aktor'),
            };
            // Action bersarang tidak melepas lock sebelum transaksi parent commit.
            $this->assertSame(1, DB::transactionLevel());
        };
        $this->assertSame(['denied'], $this->race('change-role-permission', $target->id, '', [$payload], $prepare));
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $target->id)->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'role_permissions.ditolak')->count());
    }

    public static function authorizationRaces(): array
    {
        return [['source'], ['assignment'], ['deny']];
    }

    public function test_role_permission_waits_for_target_lock_without_writing_pivot_or_audit(): void
    {
        [$actor, , $source, $target] = $this->roleEditors();
        $this->assertNotSame($source->id, $target->id);
        $payload = $this->rolePayload($actor, $target);
        $prepare = function () use ($target): void {
            // KEY SHARE dari FK pivot tetap boleh lewat; hanya lock eksplisit target yang ditahan.
            $locked = DB::selectOne('select id from roles where id = ? for no key update', [$target->id]);
            $this->assertSame($target->id, $locked->id);
        };
        $assertBlocked = function (array $pids, int $parentPid) use ($target): void {
            $this->assertCount(1, $pids);
            $this->assertNotContains($parentPid, $pids);
            $query = DB::table('pg_stat_activity')->where('pid', $pids[0])->value('query');
            $this->assertStringContainsString('from "roles"', $query);
            $this->assertStringEndsWith('for update', $query);
            $this->assertSame(0, DB::table('role_permissions')->where('role_id', $target->id)->count());
            $this->assertDatabaseCount('audit_log', 0);
        };
        $this->assertSame(['added'], $this->race('change-role-permission', $target->id, '', [$payload], prepare: $prepare, assertBlocked: $assertBlocked));
        $this->assertSame(1, DB::table('role_permissions')->where('role_id', $target->id)->count());
        $this->assertDatabaseHas('role_permissions', ['role_id' => $target->id, 'permission_id' => $payload['permission_id']]);
        $this->assertDatabaseCount('audit_log', 1);
        $this->assertDatabaseHas('audit_log', ['objek_id' => $target->id, 'actor_id' => $actor->id, 'tindakan' => 'role_permissions.ubah']);
    }

    public function test_role_permission_receipt_lock_is_nonblocking_and_consumes_only_once(): void
    {
        // DatabaseMigrations tanpa outer transaction: DatabaseLock menangani unique collision
        // PostgreSQL dalam autocommit, sama dengan request receipt setelah domain commit.
        $receipts = app(RolePermissionReceipt::class);
        $actor = (string) Str::uuid();
        $ref = $receipts->issue($actor, 'receipt-session', 'added');
        $this->assertNotNull($ref);
        $key = 'role-permission:receipt:'.hash('sha256', 'receipt-session').':'.$actor.':'.$ref;
        $lock = Cache::store('database')->lock($key.':consume', 300);
        $this->assertTrue($lock->get());
        try {
            $this->assertNull($receipts->consume($actor, 'receipt-session', $ref));
        } finally {
            $lock->release();
        }
        $this->assertSame(['receipt_id' => $ref, 'status' => 'added'], $receipts->consume($actor, 'receipt-session', $ref));
        $this->assertNull($receipts->consume($actor, 'receipt-session', $ref));
    }

    private function roleEditors(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $source = Role::where('kode', 'superadmin')->sole();
        foreach (Permission::whereIn('kode', ['akses:update', 'pengguna:read'])->get() as $permission) {
            $source->permissions()->attach($permission->id, ['id' => Str::uuid(), 'created_at' => now()]);
        }
        $actors = User::factory()->count(2)->create(['is_active' => true]);
        foreach ($actors as $actor) {
            $actor->roles()->attach($source->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        }

        return [$actors[0], $actors[1], $source, Role::where('kode', 'pic')->sole()];
    }

    private function rolePayload(User $actor, Role $role): array
    {
        $state = DB::transaction(fn () => app(RolePermissionState::class)->capture(Role::whereKey($role->id)->sharedLock()->firstOrFail()));

        return ['actor_id' => $actor->id, 'role_id' => $role->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
            'operation' => 'add', 'alasan' => 'Konkurensi izin peran', 'expected_state' => $state['token']];
    }

    /**
     * Kedua proses harus terbukti menunggu lock PostgreSQL yang sama sebelum dilepas.
     * Fixture sudah committed; ini tidak memakai transaksi luar RefreshDatabase.
     *
     * @return list<string|bool>
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
                $arguments = [PHP_BINARY, base_path('tests/Support/account-concurrency-worker.php'), $operation, $subject];
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
