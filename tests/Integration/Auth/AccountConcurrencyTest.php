<?php

namespace Tests\Integration\Auth;

use App\Actions\Auth\ProvisionKeycloakUser;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
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
        $this->assertDatabaseCount('role_permissions', 162);
        $this->assertDatabaseCount('user_roles', 1);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame(5, DB::table('audit_log')->where('tindakan', 'role_permissions.ubah')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'user_roles.ubah')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'pengguna.aktivasi')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'auth.bootstrap')->count());
    }

    /**
     * Kedua proses harus terbukti menunggu lock PostgreSQL yang sama sebelum dilepas.
     * Fixture sudah committed; ini tidak memakai transaksi luar RefreshDatabase.
     *
     * @return list<string|bool>
     */
    private function race(string $operation, string $subject, string $lock): array
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
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$lock]);
            for ($index = 0; $index < 2; $index++) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/account-concurrency-worker.php'), $operation, $subject], base_path(), $environment, $input, 20);
                $process->start();
                $processes[] = $process;
                $inputs[] = $input;
            }
            $this->until(function () use ($processes): bool {
                return preg_match('/READY:(\d+)/', $processes[0]->getOutput()) === 1
                    && preg_match('/READY:(\d+)/', $processes[1]->getOutput()) === 1;
            }, $processes);
            $pids = [];
            foreach ($processes as $index => $process) {
                preg_match('/READY:(\d+)/', $process->getOutput(), $match);
                $pids[] = (int) $match[1];
                $inputs[$index]->write("GO\n");
                $inputs[$index]->close();
            }
            $this->assertNotSame($pids[0], $pids[1]);
            $this->until(function () use ($pids): bool {
                DB::select('select pg_stat_clear_snapshot()');

                return DB::table('pg_stat_activity')->whereIn('pid', $pids)->where('wait_event_type', 'Lock')->count() === 2;
            }, $processes);
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
                $this->assertTrue($process->isRunning(), $process->getErrorOutput());
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Dua worker tidak mencapai barrier PostgreSQL dalam batas waktu.');
    }
}
