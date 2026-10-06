<?php

namespace Tests\Integration\Jadwal;

use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Jadwal\ShowJadwalEditor;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

class PeriodeJadwalConcurrencyTest extends TestCase
{
    use DatabaseMigrations, JadwalFixtures;

    private bool $workersStopped = true;

    /** Fixture committed untuk dua koneksi; cleanup tetap guard disposable, tanpa rollback audit append-only. */
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

    public function test_concurrent_initial_final_creation_has_one_winner(): void
    {
        $actors = [$this->calendarActor(), $this->calendarActor()];
        $data = ['nama' => 'Final', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true];
        $this->assertSame(['changed', 'is_nilai_akhir'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'master', 'data' => $data],
            ['actor_id' => $actors[1]->id, 'operation' => 'master', 'data' => $data],
        ], serialized: true));
        $this->assertSame(1, Periode::where('aktif', true)->where('is_nilai_akhir', true)->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'periode.tambah')->count());
    }

    public function test_concurrent_seeders_create_one_complete_configuration(): void
    {
        $actor = $this->calendarActor();
        $this->assertSame(['changed', 'changed'], $this->race([
            ['actor_id' => $actor->id, 'operation' => 'seed'],
            ['actor_id' => $actor->id, 'operation' => 'seed'],
        ], serialized: true));
        $this->assertSame(4, Periode::count());
        $this->assertSame(['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV'], Periode::orderBy('urutan')->pluck('nama')->all());
        $this->assertSame('Triwulan IV', Periode::where('aktif', true)->where('is_nilai_akhir', true)->sole()->nama);
    }

    public function test_waiting_seeder_preserves_configuration_committed_by_manager(): void
    {
        $actor = $this->calendarActor();
        $this->assertSame(['changed', 'changed'], $this->race([
            ['actor_id' => $actor->id, 'operation' => 'master', 'data' => ['nama' => 'Semester', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]],
            ['actor_id' => $actor->id, 'operation' => 'seed'],
        ], serialized: true));
        $this->assertSame('Semester', Periode::sole()->nama);
        $this->assertSame(1, AuditLog::where('tindakan', 'periode.tambah')->count());
    }

    public function test_waiting_manager_rechecks_final_after_seed_commits(): void
    {
        $actor = $this->calendarActor();
        $this->assertSame(['changed', 'is_nilai_akhir'], $this->race([
            ['actor_id' => $actor->id, 'operation' => 'seed'],
            ['actor_id' => $actor->id, 'operation' => 'master', 'data' => ['nama' => 'Semester', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]],
        ], serialized: true));
        $this->assertSame(4, Periode::count());
        $this->assertSame('Triwulan IV', Periode::where('aktif', true)->where('is_nilai_akhir', true)->sole()->nama);
        $this->assertSame(1, AuditLog::where('tindakan', 'periode.tambah_ditolak')->count());
    }

    public function test_concurrent_final_replacement_has_one_winner_and_read_sees_current_singleton(): void
    {
        $actors = [$this->calendarActor(), $this->calendarActor()];
        $old = $this->calendarMaster();
        $next = $this->calendarMaster(5, false);
        $other = $this->calendarMaster(6, false);
        $swap = ['periode_lama_id' => $old->id, 'revisi_lama' => 1, 'periode_pengganti_id' => $next->id, 'revisi_pengganti' => 1];
        $this->assertSame(['changed', 'periode'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'swap', 'data' => $swap],
            ['actor_id' => $actors[1]->id, 'operation' => 'swap', 'data' => [...$swap, 'periode_pengganti_id' => $other->id]],
        ], serialized: true));
        $this->assertSame(1, Periode::where('aktif', true)->where('is_nilai_akhir', true)->count());
        $this->assertTrue($old->fresh()->is_nilai_akhir);
        $this->assertSame(1, AuditLog::where('tindakan', 'periode.ganti_nilai_akhir')->count());
        $results = $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'swap', 'data' => ['periode_lama_id' => $next->id, 'revisi_lama' => 2, 'periode_pengganti_id' => $other->id, 'revisi_pengganti' => 1]],
            ['actor_id' => $actors[1]->id, 'operation' => 'list'],
        ], serialized: true);
        $this->assertSame(['id' => $other->id, 'nama' => $other->nama, 'revisi' => 2], $results[1]);
    }

    public function test_concurrent_same_pair_create_has_one_calendar(): void
    {
        $actors = [$this->calendarActor(), $this->calendarActor()];
        $data = $this->calendarPayload($this->calendarRenstra($actors[0]), $this->calendarMaster());
        $this->assertEqualsCanonicalizing(['changed', 'jadwal'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'save', 'data' => $data],
            ['actor_id' => $actors[1]->id, 'operation' => 'save', 'data' => $data],
        ]));
        $this->assertSame(1, JadwalTahunan::count());
        $this->assertSame(1, PeriodeJadwal::count());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tambah')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tambah_ditolak')->count());
    }

    public function test_concurrent_draft_updates_reject_stale_writer_and_editor_is_coherent(): void
    {
        $actors = [$this->calendarActor(), $this->calendarActor()];
        $data = $this->calendarPayload($this->calendarRenstra($actors[0]), $this->calendarMaster());
        $jadwal = app(SaveJadwalDraft::class)->handle($actors[0], $data);
        $changed = [...$data, 'penutupan' => '2027-01-20', 'revisi' => 1];
        $this->assertSame(['changed', 'jadwal'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'save', 'id' => $jadwal->id, 'data' => $changed],
            ['actor_id' => $actors[1]->id, 'operation' => 'save', 'id' => $jadwal->id, 'data' => [...$changed, 'penutupan' => '2027-01-21']],
        ], serialized: true));
        $results = $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'save', 'id' => $jadwal->id, 'data' => [...$changed, 'revisi' => 2, 'penutupan' => '2027-01-22']],
            ['actor_id' => $actors[1]->id, 'operation' => 'show', 'id' => $jadwal->id],
        ], serialized: true);
        $this->assertSame(['revisi' => 3, 'penutupan' => '2027-01-22'], $results[1]);
        $this->assertSame(2, AuditLog::where('tindakan', 'jadwal.ubah')->count());
    }

    public function test_master_change_cannot_invalidate_concurrent_draft_save(): void
    {
        $actors = [$this->calendarActor(), $this->calendarActor()];
        $final = $this->calendarMaster();
        $early = $this->calendarMaster(1, false);
        $renstra = $this->calendarRenstra($actors[0]);
        $data = $this->calendarPayload($renstra, $final);
        $data['periode'][] = ['periode_id' => $early->id, 'periode_revisi' => 1, 'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-07', 'reviu_mulai' => '2026-04-08', 'reviu_selesai' => '2026-04-15'];
        $this->assertSame(['changed', 'urutan'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'save', 'data' => $data],
            ['actor_id' => $actors[1]->id, 'operation' => 'master', 'id' => $final->id, 'data' => ['nama' => $final->nama, 'urutan' => 0, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1]],
        ], serialized: true));
        $this->assertSame(4, $final->fresh()->urutan);
        $this->assertSame(2, PeriodeJadwal::count());
    }

    public function test_different_calendars_reach_save_together_with_shared_catalog_lock(): void
    {
        $actors = [$this->calendarActor(), $this->calendarActor()];
        $renstra = $this->calendarRenstra($actors[0]);
        $periode = $this->calendarMaster();
        $this->assertSame(['changed', 'changed'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'save', 'data' => $this->calendarPayload($renstra, $periode)],
            ['actor_id' => $actors[1]->id, 'operation' => 'save', 'data' => $this->calendarPayload($renstra, $periode, 2027)],
        ]));
        $this->assertSame(2, JadwalTahunan::count());
    }

    public function test_master_commits_first_and_waiting_draft_rejects_stale_period_revision(): void
    {
        $actors = [$this->calendarActor(), $this->calendarActor()];
        $periode = $this->calendarMaster();
        $data = $this->calendarPayload($this->calendarRenstra($actors[1]), $periode);
        $this->assertSame(['changed', 'periode.0.periode_revisi'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'master', 'id' => $periode->id,
                'data' => ['nama' => 'Final diperbarui', 'urutan' => 5, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1]],
            ['actor_id' => $actors[1]->id, 'operation' => 'save', 'data' => $data],
        ], serialized: true));
        $this->assertSame('Final diperbarui', $periode->fresh()->nama);
        $this->assertSame(5, $periode->fresh()->urutan);
        $this->assertSame(2, $periode->fresh()->revisi);
        $this->assertSame(0, JadwalTahunan::count());
        $this->assertSame(0, PeriodeJadwal::count());
        $this->assertSame(1, AuditLog::where('tindakan', 'periode.ubah')->where('objek_id', $periode->id)->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.tambah')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tambah_ditolak')->where('actor_id', $actors[1]->id)->count());
    }

    public function test_nonactivation_commits_first_and_draft_save_rechecks_parent(): void
    {
        $actors = [$this->calendarActor(['periode:create', 'periode:update', 'jadwal:create', 'jadwal:update', 'renstra:update']), $this->calendarActor(['periode:create', 'periode:update', 'jadwal:create', 'jadwal:update', 'renstra:update'])];
        $renstra = $this->calendarRenstra($actors[0], 'aktif');
        $data = $this->calendarPayload($renstra, $this->calendarMaster());
        $this->assertSame(['changed', 'renstra_id'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'deactivate', 'id' => $renstra->id, 'expected_state' => $renstra->stateToken()],
            ['actor_id' => $actors[1]->id, 'operation' => 'save', 'data' => $data],
        ], serialized: true));
        $this->assertSame(0, JadwalTahunan::count());
        $this->assertSame('nonaktif', $renstra->fresh()->status);
    }

    public function test_draft_save_commits_first_and_nonactivation_can_then_make_it_readonly(): void
    {
        $permissions = ['periode:create', 'periode:update', 'jadwal:create', 'jadwal:update', 'renstra:update'];
        $actors = [$this->calendarActor($permissions), $this->calendarActor($permissions)];
        $renstra = $this->calendarRenstra($actors[0], 'aktif');
        $data = $this->calendarPayload($renstra, $this->calendarMaster());
        $this->assertSame(['changed', 'changed'], $this->race([
            ['actor_id' => $actors[0]->id, 'operation' => 'save', 'data' => $data],
            ['actor_id' => $actors[1]->id, 'operation' => 'deactivate', 'id' => $renstra->id, 'expected_state' => $renstra->stateToken()],
        ], serialized: true));
        $this->assertFalse(app(ShowJadwalEditor::class)->handle($actors[0], JadwalTahunan::sole())['can']['update']);
        $this->assertSame('nonaktif', $renstra->fresh()->status);
    }

    /** Barrier STDIN/STDOUT dan pg_blocking_pids membuktikan overlap, bukan perkiraan durasi sleep. */
    private function race(array $payloads, bool $serialized = false): array
    {
        $connection = DB::connection();
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => (string) $connection->getConfig('host'), 'DB_PORT' => (string) $connection->getConfig('port'),
            'DB_DATABASE' => (string) $connection->getConfig('database'), 'DB_USERNAME' => (string) $connection->getConfig('username'),
            'DB_PASSWORD' => (string) $connection->getConfig('password'), 'SAKIP_TEST_ALLOW_DATABASE_RESET' => '1',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        // Process mewarisi cache/storage QA runner; tidak membangun cache atau log di checkout lain.
        $processes = $inputs = [];
        $this->workersStopped = false;
        try {
            foreach ($payloads as $payload) {
                $input = new InputStream;
                $payload['pause_before_save'] = ! in_array($payload['operation'], ['show', 'list'], true);
                $process = new Process([PHP_BINARY, '-d', 'variables_order=EGPCS', base_path('tests/Support/periode-jadwal-concurrency-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), $environment, $input, 30);
                $process->start();
                $processes[] = $process;
                $inputs[] = $input;
            }
            $this->until(fn (): bool => collect($processes)->every(fn (Process $process): bool => preg_match('/READY:(\d+)/', $process->getOutput()) === 1), $processes);
            $pids = array_map(function (Process $process): int {
                preg_match('/READY:(\d+)/', $process->getOutput(), $match);

                return (int) $match[1];
            }, $processes);
            $this->assertCount(2, array_unique($pids));
            $inputs[0]->write("GO\n");
            if ($serialized) {
                $this->until(fn (): bool => str_contains($processes[0]->getOutput(), 'SAVING'), $processes);
            }
            $inputs[1]->write("GO\n");
            if ($serialized) {
                $this->until(function () use ($pids): bool {
                    DB::select('select pg_stat_clear_snapshot()');

                    return (bool) DB::selectOne('select ? = any(pg_blocking_pids(?)) as blocked', [$pids[0], $pids[1]])->blocked;
                }, $processes);
            } else {
                $this->until(fn (): bool => collect($processes)->every(fn (Process $process): bool => str_contains($process->getOutput(), 'SAVING')), $processes);
            }
            foreach ($inputs as $input) {
                $input->write("SAVE\n");
                $input->close();
            }
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
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
            $this->workersStopped = collect($processes)->every(fn (Process $process): bool => ! $process->isRunning());
        }
    }

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
