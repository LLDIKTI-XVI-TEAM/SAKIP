<?php

namespace Tests\Integration\Notifikasi;

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Models\PenugasanIndikator;
use App\Models\User;
use App\Services\Notifikasi\PenerimaNotifikasi;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/**
 * Perintah lonceng pembukaan menulis item per penerima dengan galat terisolasi. Memakai commit nyata karena insert
 * yang gagal di dalam transaksi test PostgreSQL membatalkan seluruh transaksi.
 */
class LoncengPembukaanTanggalTest extends TestCase
{
    use DatabaseMigrations, JadwalActivationFixtures;

    /** Tabel audit tidak di-rollback migrasinya; rebuild hanya pada database disposable. */
    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            try {
                if (DB::transactionLevel() !== 0) {
                    throw new RuntimeException('Rebuild ditolak: transaksi masih aktif.');
                }
                $this->assertDisposableDatabase($this->app);
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            } finally {
                RefreshDatabaseState::$migrated = false;
            }
        });
    }

    public function test_galat_satu_penerima_tidak_menghentikan_penerima_lain(): void
    {
        $this->travelTo(Carbon::parse('2026-04-10 09:00:00', 'Asia/Makassar'));
        $this->readyJadwal(withIndicator: false);
        $tw1 = $this->calendarMaster(1, false);
        $indikator = $this->eligibleIndicator(target: '80');
        $pjA = $this->activator([]);
        PenugasanIndikator::create(['indikator_id' => $indikator->id, 'user_id' => $pjA->id, 'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $this->ready['owner']->id, 'created_at' => now()]);
        $base = $this->calendarPayload($this->ready['renstra'], $this->ready['periode']);
        app(SaveJadwalDraft::class)->handle($this->ready['owner'], [...$base, 'revisi' => $this->ready['jadwal']->fresh()->revisi, 'periode' => [...$base['periode'],
            ['periode_revisi' => $tw1->revisi, 'periode_id' => $tw1->id, 'pengisian_mulai' => '2026-05-10', 'pengisian_selesai' => '2026-05-30']]], $this->ready['jadwal']);
        // Aktivasi 10 April belum membuka jendela Mei, jadi belum ada item sebelum perintah.
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $this->travelTo(Carbon::parse('2026-05-10 00:05:00', 'Asia/Makassar'));
        $this->assertDatabaseCount('notifications', 0);
        // Penerima pertama tidak tersimpan, sehingga insert-nya melanggar FK.
        $hantu = new User(['nama' => 'Hantu', 'email' => 'hantu@example.test', 'status' => 'aktif']);
        $hantu->id = (string) Str::uuid();
        $this->mock(PenerimaNotifikasi::class)->shouldReceive('pjJendela')->andReturn([$hantu, $pjA]);
        Log::spy();

        $this->artisan('notifikasi:lonceng-pembukaan')->assertSuccessful();

        $this->assertSame([$pjA->id], DB::table('notifications')->pluck('notifiable_id')->all());
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $pesan, array $konteks): bool => $pesan === 'Notifikasi in-app gagal ditulis.'
            && $konteks['jenis'] === 'jadwal.jendela_dibuka' && $konteks['penerima_id'] === $hantu->id && is_string($konteks['galat']));
    }
}
