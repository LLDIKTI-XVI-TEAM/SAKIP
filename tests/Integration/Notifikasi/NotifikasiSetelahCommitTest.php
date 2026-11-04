<?php

namespace Tests\Integration\Notifikasi;

use App\Actions\Pengukuran\ReturnPengukuran;
use App\Actions\RencanaAksi\AjukanRencanaAksi;
use App\Events\PengukuranDikembalikan;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\User;
use App\Services\Notifikasi\PenerimaNotifikasi;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Item in-app ditulis hanya setelah transaksi bisnis commit, dan galat penulisan
 * tidak membatalkan mutasi maupun menghentikan penerima lain. Memakai commit nyata
 * karena insert yang gagal di dalam transaksi test PostgreSQL membatalkan seluruh transaksi.
 */
class NotifikasiSetelahCommitTest extends TestCase
{
    use CreatesRencanaAksiFixture, DatabaseMigrations, RencanaAksiFixtures;

    private const BATAL = 'batal';

    private User $reviewer;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->reviewer = $this->createUserWithRole('perencanaan');
    }

    /** Header diajukan beserta satu versi pengajuan sintetis agar gerbang review terbuka. */
    private function pengukuran(): PengukuranKinerja
    {
        $p = PengukuranKinerja::create(['indikator_id' => $this->indikator->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id,
            'jadwal_snapshot_id' => $this->snapshot->id, 'sumber_nilai' => 'manual', 'status_alur' => 'diajukan', 'created_by' => $this->actor->id]);
        KinerjaSnapshot::create(['pengukuran_id' => $p->id, 'jadwal_snapshot_id' => $this->snapshot->id, 'nomor' => 1, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'snapshot' => ['fixture' => 'sintetis']]);

        return $p;
    }

    private function kembalikan(PengukuranKinerja $p, string $alasan = 'Perbaiki.'): PengukuranKinerja
    {
        return app(ReturnPengukuran::class)->handle($this->reviewer, $p->id, ['versi' => $p->fresh()->versi, 'catatan' => $alasan]);
    }

    public function test_item_pengembalian_pengukuran_ditulis_hanya_setelah_transaksi_luar_commit(): void
    {
        $this->createPicPegawai();
        $p = $this->pengukuran();

        try {
            DB::transaction(function () use ($p): void {
                $this->kembalikan($p);
                $this->assertSame('dikembalikan', $p->fresh()->status_alur);
                // Belum ada item selama transaksi luar berjalan: bukti item tidak ditulis di dalam transaksi.
                $this->assertSame(0, DB::table('notifications')->count());
                throw new RuntimeException(self::BATAL);
            });
        } catch (RuntimeException $e) {
            // Hanya sentinel pembatal yang ditelan; kegagalan asersi PHPUnit (turunan RuntimeException) harus tetap keluar.
            if ($e->getMessage() !== self::BATAL) {
                throw $e;
            }
        }
        $this->assertSame('diajukan', $p->fresh()->status_alur);
        $this->assertSame(0, DB::table('notifications')->count());

        $this->kembalikan($p);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_item_pengajuan_rencana_aksi_ditulis_hanya_setelah_transaksi_luar_commit(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $ajukan = fn () => app(AjukanRencanaAksi::class)->handle($fixture['pic'], $header->id, ['expected_versi' => $header->fresh()->versi]);

        try {
            DB::transaction(function () use ($ajukan, $header): void {
                $ajukan();
                $this->assertSame('diajukan', $header->fresh()->status_alur);
                $this->assertSame(0, DB::table('notifications')->count());
                throw new RuntimeException(self::BATAL);
            });
        } catch (RuntimeException $e) {
            // Hanya sentinel pembatal yang ditelan; kegagalan asersi PHPUnit (turunan RuntimeException) harus tetap keluar.
            if ($e->getMessage() !== self::BATAL) {
                throw $e;
            }
        }
        $this->assertSame('draft', $header->fresh()->status_alur);
        $this->assertSame(0, DB::table('notifications')->count());

        $ajukan();
        // Dua Perencanaan aktif: reviewer fixture kelas ini dan Perencanaan fixture RA.
        $this->assertEqualsCanonicalizing([$this->reviewer->id, $fixture['perencanaan']->id], DB::table('notifications')->where('type', 'rencana_aksi.diajukan')->pluck('notifiable_id')->all());
    }

    public function test_event_pengembalian_membawa_versi_header_sesudah_mutasi(): void
    {
        $p = $this->pengukuran();
        Event::fake([PengukuranDikembalikan::class]);

        $this->kembalikan($p);

        Event::assertDispatched(PengukuranDikembalikan::class, fn (PengukuranDikembalikan $e): bool => $e->pengukuranId === $p->id
            && $e->versi === $p->fresh()->versi && $e->versi === 2 && $e->aktorId === $this->reviewer->id && $e->alasan === 'Perbaiki.');
    }

    public function test_galat_penentuan_penerima_tidak_mengubah_respons_dan_dicatat_tanpa_isi(): void
    {
        $this->createPicPegawai();
        $p = $this->pengukuran();
        $this->mock(PenerimaNotifikasi::class)->shouldReceive('aktif')->andThrow(new RuntimeException('penerima gagal'));
        Log::spy();

        $this->actingAs($this->reviewer)->post("/verifikasi/{$p->id}/kembalikan", ['versi' => $p->fresh()->versi, 'catatan' => 'Alasan rahasia.'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('dikembalikan', $p->fresh()->status_alur);
        $this->assertSame(0, DB::table('notifications')->count());
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $pesan, array $konteks): bool => $pesan === 'Notifikasi in-app gagal ditulis.'
            && $konteks['jenis'] === 'pengukuran.dikembalikan' && $konteks['objek_id'] === $p->id && $konteks['galat'] === RuntimeException::class
            && ! str_contains(json_encode($konteks), 'rahasia'));
    }

    public function test_galat_satu_penerima_tidak_menghentikan_penerima_lain(): void
    {
        $b = $this->createPicPegawai();
        $hantu = new User(['nama' => 'Hantu', 'email' => 'hantu@example.test', 'status' => 'aktif']);
        $hantu->id = (string) Str::uuid();
        $p = $this->pengukuran();
        $this->partialMock(PenerimaNotifikasi::class)->shouldReceive('aktif')->andReturn([$hantu, $b]);
        Log::spy();

        $this->actingAs($this->reviewer)->post("/verifikasi/{$p->id}/kembalikan", ['versi' => $p->fresh()->versi, 'catatan' => 'Alasan rahasia.'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('dikembalikan', $p->fresh()->status_alur);
        $this->assertSame([$b->id], DB::table('notifications')->pluck('notifiable_id')->all());
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $pesan, array $konteks): bool => $konteks['jenis'] === 'pengukuran.dikembalikan'
            && $konteks['objek_id'] === $p->id && $konteks['penerima_id'] === $hantu->id && is_string($konteks['galat'])
            && ! str_contains(json_encode($konteks), 'rahasia') && ! str_contains(json_encode($konteks), 'Pengukuran IKU'));
    }
}
