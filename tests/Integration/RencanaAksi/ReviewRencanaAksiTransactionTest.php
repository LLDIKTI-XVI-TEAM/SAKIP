<?php

namespace Tests\Integration\RencanaAksi;

use App\Actions\RencanaAksi\KembalikanRencanaAksi;
use App\Events\RencanaAksiDikembalikan;
use App\Models\AuditLog;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class ReviewRencanaAksiTransactionTest extends TestCase
{
    use CreatesRencanaAksiFixture, DatabaseMigrations;

    private bool $workersStopped = true;

    /** Audit dan versi immutable tidak di-rollback migrasinya; rebuild hanya pada database disposable. */
    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            try {
                if (! $this->workersStopped || DB::transactionLevel() !== 0) {
                    throw new \RuntimeException('Rebuild ditolak: worker atau transaksi masih aktif.');
                }
                $this->assertDisposableDatabase($this->app);
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            } finally {
                RefreshDatabaseState::$migrated = false;
            }
        });
    }

    public function test_event_hanya_setelah_commit_memakai_pj_efektif_dan_mempertahankan_pengaju_historis(): void
    {
        $this->setUpRencanaAksiFixture();
        $versi = $this->createRencanaAksiSubmission();
        $reviewer = $this->createUserWithRole('perencanaan');
        $data = ['expected_versi' => 1, 'versi_pengajuan_id' => $versi->id, 'alasan' => '  Perbaiki narasi.  '];
        $events = [];
        Event::listen(RencanaAksiDikembalikan::class, function ($event) use (&$events): void {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('dikembalikan', RencanaAksi::findOrFail($event->rencanaAksiId)->status_alur);
            $events[] = $event;
        });
        DB::beginTransaction();
        try {
            app(KembalikanRencanaAksi::class)->handle($reviewer, $this->rencanaAksi->id, $data);
            $this->assertCount(0, $events);
        } finally {
            DB::rollBack();
        }
        $this->assertCount(0, $events);
        $this->assertSame('diajukan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(0, AuditLog::where('tindakan', 'rencana_aksi.kembalikan')->count());
        $pjBaru = $this->createPicPegawai();
        app(KembalikanRencanaAksi::class)->handle($reviewer, $this->rencanaAksi->id, $data);
        $this->assertCount(1, $events);
        $this->assertSame($pjBaru->id, $events[0]->penerimaId);
        $this->assertSame($this->actor->id, $events[0]->pengajuHistorisId);
        $this->assertSame($this->actor->id, $versi->fresh()->diajukan_by);
        $audit = AuditLog::where('tindakan', 'rencana_aksi.kembalikan')->sole();
        $this->assertSame('Perbaiki narasi.', $audit->alasan);
        $this->assertSame($pjBaru->id, $audit->nilai_baru['penerima_id']);
    }

    public function test_verifikasi_dan_pengembalian_bersaing_hanya_satu_transisi_commit(): void
    {
        $this->setUpRencanaAksiFixture();
        $versi = $this->createRencanaAksiSubmission();
        $reviewer = $this->createUserWithRole('perencanaan');
        $lain = $this->createUserWithRole('perencanaan');
        $data = ['expected_versi' => 1, 'versi_pengajuan_id' => $versi->id, 'alasan' => 'Perbaikan.'];
        $hasil = $this->race([
            ['operation' => 'ra-verifikasi', 'actor_id' => $reviewer->id, 'id' => $this->rencanaAksi->id, 'data' => $data],
            ['operation' => 'ra-kembalikan', 'actor_id' => $lain->id, 'id' => $this->rencanaAksi->id, 'data' => $data],
        ], fn () => RencanaAksi::whereKey($this->rencanaAksi->id)->lockForUpdate()->firstOrFail());
        $this->assertContains('conflict', $hasil);
        $this->assertCount(1, array_intersect($hasil, ['diverifikasi', 'dikembalikan']));
        $this->assertSame(2, $this->rencanaAksi->fresh()->versi);
        $this->assertSame(1, AuditLog::whereIn('tindakan', ['rencana_aksi.verifikasi', 'rencana_aksi.kembalikan'])->count());
        $this->assertSame(1, AuditLog::whereIn('tindakan', ['rencana_aksi.verifikasi_ditolak', 'rencana_aksi.kembalikan_ditolak'])->count());
    }

    public function test_deny_yang_commit_saat_menunggu_lock_tetap_menang(): void
    {
        $this->setUpRencanaAksiFixture();
        $versi = $this->createRencanaAksiSubmission();
        $reviewer = $this->createUserWithRole('perencanaan');
        $hasil = $this->race([
            ['operation' => 'ra-verifikasi', 'actor_id' => $reviewer->id, 'id' => $this->rencanaAksi->id,
                'data' => ['expected_versi' => 1, 'versi_pengajuan_id' => $versi->id]],
        ], fn () => User::whereKey($reviewer->id)->lockForUpdate()->firstOrFail(),
            fn () => $this->denyPermission($reviewer, 'rencana_aksi:verifikasi', $this->unit->id));
        $this->assertSame(['denied'], $hasil);
        $this->assertSame('diajukan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame('explicit_deny', AuditLog::where('tindakan', 'rencana_aksi.verifikasi_ditolak')->sole()->dasar_izin['alasan']);
    }

    /** @param list<array<string, mixed>> $jobs
     * @return list<string>
     */
    private function race(array $jobs, callable $lock, ?callable $beforeCommit = null): array
    {
        $processes = [];
        $inputs = [];
        $this->workersStopped = false;
        DB::beginTransaction();
        try {
            $lock();
            foreach ($jobs as $job) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrency-worker.php'), $job['operation'], $job['id'], json_encode($job, JSON_THROW_ON_ERROR)], base_path(), null, $input, 30);
                $process->start();
                $processes[] = $process;
                $inputs[] = $input;
            }
            $this->until(fn () => collect($processes)->every(fn ($p) => preg_match('/READY:(\d+)/', $p->getOutput()) === 1), $processes);
            $pids = [];
            foreach ($processes as $i => $process) {
                preg_match('/READY:(\d+)/', $process->getOutput(), $match);
                $pids[] = (int) $match[1];
                $inputs[$i]->write("GO\n");
                $inputs[$i]->close();
            }
            $this->assertCount(count($jobs), array_unique($pids));
            $this->until(function () use ($pids): bool {
                DB::select('select pg_stat_clear_snapshot()');

                return DB::table('pg_stat_activity')->whereIn('pid', $pids)->where('wait_event_type', 'Lock')->count() === count($pids);
            }, $processes);
            $beforeCommit?->__invoke();
            DB::commit();
            $hasil = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertSame(1, preg_match('/RESULT:(.+)/', $process->getOutput(), $match));
                $hasil[] = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
            }

            return $hasil;
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
        $deadline = microtime(true) + 10;
        do {
            if ($ready()) {
                return;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->isRunning(), 'Worker selesai sebelum barrier: '.$process->getErrorOutput());
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Worker tidak mencapai barrier lock PostgreSQL.');
    }
}
