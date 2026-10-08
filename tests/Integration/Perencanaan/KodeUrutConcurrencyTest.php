<?php

namespace Tests\Integration\Perencanaan;

use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Bukti serialisasi deret kode pada dua koneksi PostgreSQL: dua pembuatan
 * Sasaran yang benar-benar bersamaan tidak boleh menghasilkan kode yang sama.
 *
 * Barrier memakai advisory lock deret yang sama dengan jalur produksi
 * (`sakip:kode:sasaran`), sehingga test membuktikan worker memang menunggu
 * lock tersebut — bukan sekadar berjalan berurutan.
 */
class KodeUrutConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private bool $workersStopped = true;

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

    public function test_dua_pembuatan_bersamaan_memperoleh_kode_berurutan_tanpa_duplikat(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $this->pasangPresetRole('perencanaan');

        $first = $this->buatUserDenganRole('perencanaan', 'kode-urut-pertama@sakip.test');
        $second = $this->buatUserDenganRole('perencanaan', 'kode-urut-kedua@sakip.test');

        $this->assertTrue(app(PermissionResolver::class)->resolve($first, 'sasaran:create')->allowed);
        $this->assertTrue(app(PermissionResolver::class)->resolve($second, 'sasaran:create')->allowed);

        $renstra = Renstra::create([
            'kode' => 'REN-'.Str::random(8),
            'nama' => 'Renstra Fixture Kode Urut',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'status' => 'draft',
            'dasar_hukum' => 'Kepmen fixture',
            'created_by' => $first->id,
        ]);

        $hasil = $this->race($renstra, [$first->id, $second->id]);

        $this->assertEqualsCanonicalizing(['SS-01', 'SS-02'], $hasil);
        $this->assertSame(2, DB::table('sasaran_strategis')->count());
        $this->assertSame(2, DB::table('sasaran_strategis')->distinct()->count('kode'));
    }

    /**
     * Menjalankan operasi store Sasaran yang sama pada dua proses worker,
     * keduanya tertahan advisory lock deret kode milik transaksi induk.
     *
     * @param  list<string>  $actorIds
     * @return list<string>
     */
    private function race(Renstra $renstra, array $actorIds): array
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
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:kode:sasaran']);

            foreach ($actorIds as $index => $actorId) {
                $assignment = [
                    'actor_id' => $actorId,
                    'permission' => 'sasaran:create',
                    'data' => ['renstra_id' => $renstra->id, 'deskripsi' => 'Sasaran pembuatan bersamaan '.$index],
                ];
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/concurrency-worker.php'),
                    'sasaran-create',
                    'kode-urut-'.$index,
                    json_encode($assignment, JSON_THROW_ON_ERROR),
                ], base_path(), $environment, $input, 20);
                $process->start();
                $processes[] = $process;
                $inputs[] = $input;
            }

            $this->until(
                fn (): bool => collect($processes)->every(fn (Process $process): bool => preg_match('/READY:(\d+)/', $process->getOutput()) === 1),
                $processes,
            );

            $pids = [];
            foreach ($processes as $index => $process) {
                preg_match('/READY:(\d+)/', $process->getOutput(), $match);
                $pids[] = (int) $match[1];
                $inputs[$index]->write("GO\n");
                $inputs[$index]->close();
            }

            $this->assertCount(count($pids), array_unique($pids));

            $parentPid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
            $this->until(function () use ($pids, $parentPid): bool {
                DB::select('select pg_stat_clear_snapshot()');

                return collect($pids)->every(fn (int $pid): bool => (bool) DB::selectOne('select ? = any(pg_blocking_pids(?)) as blocked', [$parentPid, $pid])->blocked);
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
            $this->workersStopped = DB::transactionLevel() === 0 && collect($processes)->every(fn (Process $process): bool => ! $process->isRunning());
        }
    }

    /** @param  list<Process>  $processes */
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

    private function buatUserDenganRole(string $roleName, string $email): User
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => $email,
            'status' => 'aktif',
        ]);

        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function pasangPresetRole(string $roleName): void
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $permissionCodes = RolePermissionPresets::forRole($roleName);

        $permissionIds = Permission::whereIn('kode', $permissionCodes)->pluck('id');

        $role->permissions()->syncWithoutDetaching(
            $permissionIds->mapWithKeys(fn (string $id) => [
                $id => [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                ],
            ])->all()
        );
    }
}
