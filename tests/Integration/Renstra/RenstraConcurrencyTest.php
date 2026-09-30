<?php

namespace Tests\Integration\Renstra;

use App\Actions\Renstra\ChangeRenstraStatus;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\AuditLogger;
use App\Services\Authorization\RolePermissionPresets;
use App\Services\PermissionResolver;
use App\Services\RenstraService;
use App\Support\PermissionDecision;
use Database\Seeders\RegulasiPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RenstraConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private bool $workersStopped = true;

    /** Fixture committed diperlukan dua koneksi; audit append-only tidak boleh di-rollback lewat migration down. */
    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            try {
                if (! $this->workersStopped || DB::transactionLevel() !== 0) {
                    throw new RuntimeException('Worker atau transaksi masih aktif.');
                }
                $this->assertDisposableDatabase($this->app);
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            } finally {
                RefreshDatabaseState::$migrated = false;
            }
        });
    }

    /** @return list<User> */
    private function actors(): array
    {
        $this->seed(RegulasiPermissionSeeder::class);
        $role = Role::query()->where('kode', 'perencanaan')->sole();
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('kode', RolePermissionPresets::forRole('perencanaan'))->pluck('id')
            ->mapWithKeys(fn (string $id): array => [$id => ['id' => (string) Str::uuid(), 'created_at' => now()]])->all());
        $actors = [];
        for ($index = 0; $index < 2; $index++) {
            $actor = User::factory()->create(['status' => 'aktif']);
            $actor->roles()->attach($role->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
            $actors[] = $actor;
        }

        return $actors;
    }

    private function master(User $actor, string $code, string $status = 'draft'): Renstra
    {
        return Renstra::query()->create(['kode' => $code, 'nama' => 'Renstra race', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'status' => $status, 'dasar_hukum' => 'Kepmen fixture', 'created_by' => $actor->id]);
    }

    public function test_dua_aktivasi_overlap_memiliki_satu_pemenang_di_constraint_postgresql(): void
    {
        $actors = $this->actors();
        $masters = [$this->master($actors[0], 'RACE-A'), $this->master($actors[1], 'RACE-B')];
        $payloads = [];
        foreach ($masters as $index => $master) {
            $payloads[] = ['actor_id' => $actors[$index]->id, 'renstra_id' => $master->id,
                'operation' => 'activate', 'expected_state' => $master->stateToken(), 'pause_before_save' => true];
        }
        $this->assertEqualsCanonicalizing(['changed', 'tahun_mulai'], $this->race($payloads));
        $this->assertSame(1, Renstra::query()->where('status', 'aktif')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra.activate')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra.activate_ditolak')->count());
    }

    public function test_dua_revisi_kode_sama_menghasilkan_satu_denial_setelah_rollback(): void
    {
        $actors = $this->actors();
        $masters = [$this->master($actors[0], 'KODE-A'), $this->master($actors[1], 'KODE-B')];
        $payloads = [];
        foreach ($masters as $index => $master) {
            $payloads[] = ['actor_id' => $actors[$index]->id, 'renstra_id' => $master->id,
                'operation' => 'revision', 'pause_before_save' => true, 'data' => ['nama' => 'Renstra race baru', 'kode' => 'KODE-BERSAMA', 'expected_state' => $master->stateToken()]];
        }
        $this->assertEqualsCanonicalizing(['changed', 'kode'], $this->race($payloads));
        $this->assertSame(1, Renstra::query()->where('kode', 'KODE-BERSAMA')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra.ubah')->count());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra.ubah_ditolak')->count());
    }

    public function test_revisi_dan_nonaktivasi_master_sama_memeriksa_state_setelah_menunggu_lock(): void
    {
        $actors = $this->actors();
        $master = $this->master($actors[0], 'RACE-STATE', 'aktif');
        $payloads = [
            ['actor_id' => $actors[0]->id, 'renstra_id' => $master->id, 'operation' => 'revision', 'pause_before_save' => true,
                'data' => ['nama' => 'Nama revisi', 'alasan' => 'Kebijakan baru', 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20', 'expected_state' => $master->stateToken()]],
            ['actor_id' => $actors[1]->id, 'renstra_id' => $master->id, 'operation' => 'deactivate', 'pause_before_save' => true, 'expected_state' => $master->stateToken()],
        ];
        $this->assertEqualsCanonicalizing(['changed', 'expected_state'], $this->race($payloads, sameMaster: true));
        $this->assertSame(1, DB::table('audit_log')->whereIn('tindakan', ['renstra.ubah', 'renstra.deactivate'])->count());
        $this->assertSame(1, DB::table('audit_log')->whereIn('tindakan', ['renstra.ubah_ditolak', 'renstra.deactivate_ditolak'])->count());
    }

    public function test_deny_yang_commit_saat_worker_menunggu_lock_aktor_diperiksa_ulang(): void
    {
        $actors = $this->actors();
        $master = $this->master($actors[0], 'RACE-DENY');
        $prepare = function () use ($actors): void {
            User::query()->whereKey($actors[0]->id)->lockForUpdate()->firstOrFail();
            UserPermissionDeny::query()->create(['user_id' => $actors[0]->id,
                'permission_id' => Permission::query()->where('kode', 'renstra:update')->sole()->id,
                'ditetapkan_oleh' => $actors[1]->id, 'alasan' => 'Fixture revoke bersamaan']);
        };
        $result = $this->race([['actor_id' => $actors[0]->id, 'renstra_id' => $master->id, 'operation' => 'activate', 'expected_state' => $master->stateToken()]], prepare: $prepare);
        $this->assertSame(['denied'], $result);
        $this->assertSame('draft', $master->fresh()->status);
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra.activate_ditolak')->count());
    }

    public function test_regulasi_yang_dinonaktifkan_saat_menunggu_lock_tidak_dapat_dipilih(): void
    {
        $actors = $this->actors();
        $master = $this->master($actors[0], 'RACE-REGULASI');
        $regulasi = Regulasi::query()->create(['jenis' => 'kepmen', 'nomor' => '123/M/2026', 'tahun' => 2026, 'tentang' => 'Fixture Regulasi', 'aktif' => true, 'created_by' => $actors[0]->id]);
        $prepare = function () use ($regulasi): void {
            Regulasi::query()->whereKey($regulasi->id)->lockForUpdate()->firstOrFail()->update(['aktif' => false]);
        };
        $result = $this->race([['actor_id' => $actors[0]->id, 'renstra_id' => $master->id, 'operation' => 'revision',
            'data' => ['nama' => 'Nama baru', 'regulasi_id' => $regulasi->id, 'expected_state' => $master->stateToken()]]], prepare: $prepare);
        $this->assertSame(['regulasi_id'], $result);
        $this->assertNull($master->fresh()->regulasi_id);
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra.ubah_ditolak')->count());
    }

    public function test_retry_deadlock_revisi_membersihkan_berkas_attempt_gagal_dan_mengaudit_sekali(): void
    {
        [$actor] = $this->actors();
        $master = $this->master($actor, 'RETRY-LAMPIRAN');
        Storage::fake('local');
        $realAudit = app(AuditLogger::class);
        $attempt = 0;
        $this->mock(AuditLogger::class)->shouldReceive('catat')->andReturnUsing(function (...$arguments) use ($realAudit, &$attempt) {
            $event = $arguments['tindakan'] ?? $arguments[1] ?? null;
            if ($event === 'renstra.ubah' && ++$attempt === 1) {
                $cause = new \PDOException('deadlock detected', 40001);
                $cause->errorInfo = ['40P01', 7, 'deadlock detected'];
                throw new QueryException('pgsql', 'fixture deadlock', [], $cause);
            }

            return $realAudit->catat(...$arguments);
        });
        app(RenstraService::class)->update($master, [
            'nama' => 'Renstra setelah retry', 'expected_state' => $master->stateToken(),
            'lampiran' => [['mode' => 'file', 'file' => UploadedFile::fake()->create('naskah.pdf', 1, 'application/pdf')]],
        ], $actor);
        $this->assertSame(2, $attempt);
        $this->assertSame('Renstra setelah retry', $master->fresh()->nama);
        $this->assertSame(1, $master->berkas()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra.ubah')->count());
        $this->assertSame(0, DB::table('audit_log')->where('tindakan', 'renstra.ubah_ditolak')->count());
    }

    public function test_retry_lifecycle_mengaudit_penolakan_izin_dari_attempt_terakhir(): void
    {
        [$actor] = $this->actors();
        $master = $this->master($actor, 'RETRY-IZIN-LIFECYCLE');
        $allowed = app(PermissionResolver::class)->resolve($actor, 'renstra:update');
        $denied = new PermissionDecision(false, 'renstra:update', ['alasan' => 'fixture_deny']);
        $this->mock(PermissionResolver::class)->shouldReceive('resolve')->times(3)->andReturn($allowed, $allowed, $denied);
        $realAudit = app(AuditLogger::class);
        $attempt = 0;
        $this->mock(AuditLogger::class)->shouldReceive('catat')->andReturnUsing(function (...$arguments) use ($realAudit, &$attempt) {
            $event = $arguments['tindakan'] ?? $arguments[1] ?? null;
            if ($event === 'renstra.activate' && ++$attempt === 1) {
                $cause = new \PDOException('deadlock detected', 40001);
                $cause->errorInfo = ['40P01', 7, 'deadlock detected'];
                throw new QueryException('pgsql', 'fixture deadlock', [], $cause);
            }

            return $realAudit->catat(...$arguments);
        });
        try {
            app(ChangeRenstraStatus::class)->execute($master, $actor, 'activate', $master->stateToken());
            $this->fail('Izin attempt terakhir harus menolak aktivasi.');
        } catch (AuthorizationException) {
            $this->assertSame('draft', $master->fresh()->status);
        }
        $denial = DB::table('audit_log')->where('tindakan', 'renstra.activate_ditolak')->sole();
        $this->assertSame('izin_ditolak', json_decode($denial->nilai_baru, true, flags: JSON_THROW_ON_ERROR)['alasan_penolakan']);
        $this->assertSame('ditolak', json_decode($denial->dasar_izin, true, flags: JSON_THROW_ON_ERROR)['keputusan']);
        $this->assertSame(0, DB::table('audit_log')->where('tindakan', 'renstra.activate')->count());
    }

    public function test_retry_revisi_tidak_mewarisi_flag_izin_dari_attempt_gagal(): void
    {
        [$actor] = $this->actors();
        $master = $this->master($actor, 'RETRY-IZIN-REVISI');
        $allowed = app(PermissionResolver::class)->resolve($actor, 'renstra:update');
        $denied = new PermissionDecision(false, 'renstra:update', ['alasan' => 'fixture_deny']);
        $this->mock(PermissionResolver::class)->shouldReceive('resolve')->times(3)->andReturn($allowed, $denied, $allowed);
        $realAudit = app(AuditLogger::class);
        $attempt = 0;
        $this->mock(AuditLogger::class)->shouldReceive('catat')->andReturnUsing(function (...$arguments) use ($realAudit, &$attempt) {
            $event = $arguments['tindakan'] ?? $arguments[1] ?? null;
            if ($event === 'renstra.ubah_ditolak' && ++$attempt === 1) {
                $cause = new \PDOException('deadlock detected', 40001);
                $cause->errorInfo = ['40P01', 7, 'deadlock detected'];
                throw new QueryException('pgsql', 'fixture deadlock', [], $cause);
            }

            return $realAudit->catat(...$arguments);
        });
        try {
            app(RenstraService::class)->update($master, ['nama' => '', 'expected_state' => $master->stateToken()], $actor);
            $this->fail('Nama kosong harus menghasilkan validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nama', $exception->errors());
        }
        $this->assertSame(2, $attempt);
        $this->assertSame('Renstra race', $master->fresh()->nama);
        $denial = DB::table('audit_log')->where('tindakan', 'renstra.ubah_ditolak')->sole();
        $this->assertSame('validasi_revisi', json_decode($denial->nilai_baru, true, flags: JSON_THROW_ON_ERROR)['alasan_penolakan']);
        $this->assertSame('diizinkan', json_decode($denial->dasar_izin, true, flags: JSON_THROW_ON_ERROR)['keputusan']);
    }

    /** Barrier save memaksa guard overlap lolos pada kedua koneksi; barrier lock membuktikan recheck state/izin live. */
    private function race(array $payloads, bool $sameMaster = false, ?callable $prepare = null): array
    {
        $connection = DB::connection();
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => (string) $connection->getConfig('host'), 'DB_PORT' => (string) $connection->getConfig('port'),
            'DB_DATABASE' => (string) $connection->getConfig('database'), 'DB_USERNAME' => (string) $connection->getConfig('username'),
            'DB_PASSWORD' => (string) $connection->getConfig('password'), 'SAKIP_TEST_ALLOW_DATABASE_RESET' => '1',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        $processes = $inputs = [];
        $this->workersStopped = false;
        try {
            if ($prepare !== null) {
                DB::beginTransaction();
                $prepare();
            }
            foreach ($payloads as $payload) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/renstra-concurrency-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), $environment, $input, 30);
                $process->start();
                $processes[] = $process;
                $inputs[] = $input;
            }
            $this->until(fn (): bool => collect($processes)->every(fn (Process $p): bool => preg_match('/READY:(\d+)/', $p->getOutput()) === 1), $processes);
            $pids = [];
            foreach ($processes as $index => $process) {
                preg_match('/READY:(\d+)/', $process->getOutput(), $match);
                $pids[] = (int) $match[1];
                $inputs[$index]->write("GO\n");
            }
            $this->assertCount(count($pids), array_unique($pids));
            if ($prepare !== null || $sameMaster) {
                $expectedBlocked = $prepare !== null ? count($pids) : 1;
                $this->until(function () use ($pids, $expectedBlocked): bool {
                    DB::select('select pg_stat_clear_snapshot()');

                    return DB::table('pg_stat_activity')->whereIn('pid', $pids)->where('wait_event_type', 'Lock')->count() === $expectedBlocked;
                }, $processes);
                if ($prepare !== null) {
                    $parentPid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
                    foreach ($pids as $pid) {
                        $this->assertTrue((bool) DB::selectOne('select ? = any(pg_blocking_pids(?)) as blocked', [$parentPid, $pid])->blocked);
                    }
                    DB::commit();
                }
            } else {
                $this->until(fn (): bool => collect($processes)->every(fn (Process $p): bool => str_contains($p->getOutput(), 'SAVING')), $processes);
            }
            foreach ($inputs as $input) {
                $input->write("SAVE\n");
                $input->close();
            }
            // InputStream baru mengirim buffer saat Process dipompa; tunggu kedua worker bersama.
            do {
                $running = false;
                foreach ($processes as $process) {
                    $process->checkTimeout();
                    $running = $process->isRunning() || $running;
                }
                if ($running) {
                    usleep(10000);
                }
            } while ($running);
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
            $this->workersStopped = DB::transactionLevel() === 0 && collect($processes)->every(fn (Process $p): bool => ! $p->isRunning());
        }
    }

    /** @param list<Process> $processes */
    private function until(callable $ready, array $processes): void
    {
        $deadline = microtime(true) + 15;
        do {
            if ($ready()) {
                return;
            }
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Worker berhenti sebelum barrier: '.$process->getOutput().$process->getErrorOutput());
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Worker tidak mencapai barrier PostgreSQL.');
    }
}
