<?php

namespace Tests\Integration\Notifikasi;

use App\Models\NotifikasiPengiriman;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class NotifikasiConcurrencyTest extends TestCase
{
    use CreatesRencanaAksiFixture, DatabaseMigrations;

    /** Migrasi audit tidak dibalik; rebuild hanya setelah seluruh proses dihentikan. */
    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            $this->assertDisposableDatabase($this->app);
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_dua_koneksi_mengantrekan_kejadian_sama_dan_mengirim_hanya_sekali(): void
    {
        $this->setUpRencanaAksiFixture();
        $data = ['operation' => 'antre', 'objek' => $this->rencanaAksi->id, 'user' => $this->actor->id];
        $ids = $this->race($data);
        $this->assertSame($ids[0], $ids[1]);
        $this->assertDatabaseCount('notifikasi_pengiriman', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->race(['operation' => 'kirim', 'id' => $ids[0]]);
        $row = NotifikasiPengiriman::findOrFail($ids[0]);
        $this->assertSame('diterima_provider', $row->status);
        $this->assertSame(1, $row->percobaan);
    }

    private function race(array $data): array
    {
        $processes = [];
        $inputs = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $input = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/notifikasi-worker.php'), json_encode($data, JSON_THROW_ON_ERROR)], base_path(), null, $input, 60);
                $process->start();
                $inputs[] = $input;
                $processes[] = $process;
            }
            $deadline = microtime(true) + 50;
            while (collect($processes)->contains(fn ($p) => ! str_contains($p->getOutput(), 'READY:'))) {
                foreach ($processes as $process) {
                    $this->assertTrue($process->isRunning(), $process->getErrorOutput());
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Barrier dua koneksi tidak tercapai.');
                }
                usleep(10000);
            }
            $pids = [];
            foreach ($processes as $i => $process) {
                $this->assertSame(1, preg_match('/READY:(\d+)/', $process->getOutput(), $m), 'Worker tidak mengirim identitas koneksi.');
                $pids[] = $m[1];
                $inputs[$i]->write("GO\n");
                $inputs[$i]->close();
            }
            $this->assertCount(2, array_unique($pids));

            return array_map(function ($process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertSame(1, preg_match('/RESULT:([\w-]+)/', $process->getOutput(), $m), 'Worker tidak mengirim hasil: '.$process->getOutput());

                return $m[1];
            }, $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }
    }
}
