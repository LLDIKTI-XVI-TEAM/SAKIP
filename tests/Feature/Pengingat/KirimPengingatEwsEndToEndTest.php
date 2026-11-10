<?php

namespace Tests\Feature\Pengingat;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\JadwalSnapshot;
use App\Models\NotifikasiPengiriman;
use App\Models\PenugasanIndikator;
use App\Models\User;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * End-to-end command EWS -> outbox -> sender memakai producer asli (tanpa mock).
 *
 * Job difake pada bus, bukan `Queue`, karena fondasi `AntreanNotifikasi` mensyaratkan
 * koneksi `database` nyata di dalam transaksi outbox. Bus fake mencegah worker memproses
 * job tanpa mengganti QueueManager, sehingga gerbang transaksi fondasi tetap teruji.
 */
class KirimPengingatEwsEndToEndTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->seed(PengaturanSeeder::class);
        Cache::flush();

        $this->atur('notifikasi.jam_kirim', '08:00');
        $this->atur('notifikasi.kanal_email_aktif', '1');
        $this->atur('notifikasi.kanal_whatsapp_aktif', '1');
        $this->actor->update(['email' => 'pic.ews@example.test', 'nomor_telepon' => '6281234567890']);

        // Worker tidak dijalankan; pengirim dipanggil eksplisit per skenario.
        Bus::fake([KirimNotifikasiEksternal::class]);
    }

    public function test_h_minus_satu_mengantre_per_kanal_lalu_sender_menerima_provider_simulasi(): void
    {
        $this->travelKe('2026-03-14 08:00');

        $this->jalankanEws();

        $baris = NotifikasiPengiriman::all();
        $this->assertCount(2, $baris);
        $this->assertEqualsCanonicalizing(['email', 'whatsapp'], $baris->pluck('kanal')->all());
        foreach ($baris as $b) {
            $this->assertSame('jadwal_periode', $b->objek_tipe);
            $this->assertSame('ews:h1', $b->kejadian);
            $this->assertSame($this->actor->id, $b->penerima_user_id);
            $this->assertSame('antre', $b->status);
            $this->assertTrue($b->simulasi);
        }

        $email = $baris->firstWhere('kanal', 'email');
        $this->kirim($email);
        $email->refresh();

        $this->assertSame('diterima_provider', $email->status);
        $this->assertNull($email->kode_hasil);
        $this->assertTrue($email->simulasi);
        $this->assertSame('log:'.$email->id, $email->rujukan_provider);
    }

    public function test_sebelum_jam_kirim_tidak_mengantrekan(): void
    {
        $this->travelKe('2026-03-14 07:59');

        $this->jalankanEws();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertFalse(Cache::has('notifikasi:ews:diproses:2026-03-14'));
    }

    public function test_dua_kali_jalan_pada_hari_yang_sama_tidak_menambah_baris(): void
    {
        $this->travelKe('2026-03-14 08:00');
        $this->jalankanEws();
        $awal = NotifikasiPengiriman::count();
        $this->assertSame(2, $awal);
        $this->assertTrue(Cache::has('notifikasi:ews:diproses:2026-03-14'));

        $this->travelKe('2026-03-14 08:30');
        $this->jalankanEws();

        $this->assertSame($awal, NotifikasiPengiriman::count());
    }

    public function test_jam_kirim_kosong_tidak_mengantrekan_walau_dijalankan_dua_kali(): void
    {
        $this->atur('notifikasi.jam_kirim', '');
        $this->travelKe('2026-03-14 09:00');

        $this->jalankanEws();
        $this->jalankanEws();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_h_minus_dua_dan_enam_tidak_mengantrekan(): void
    {
        foreach (['2026-03-13 08:00', '2026-03-09 08:00'] as $waktu) {
            $this->travelKe($waktu);
            $this->jalankanEws();
        }

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_pic_tanpa_hak_isi_tetap_diantrekan_tetapi_dibatalkan_gerbang_kirim(): void
    {
        $this->travelKe('2026-03-14 08:00');
        $pic = $this->userWithRole('pegawai');
        $pic->update(['email' => 'pic.tanpa.hak@example.test', 'nomor_telepon' => '6281234567891']);
        // Header belum ada sehingga hak isi memerlukan `pengukuran:create`; grant update tidak cukup.
        $this->grant($pic, 'pengukuran:update');
        $this->picDenganIndikator('I-EWS-TANPA-CREATE', $pic);

        $this->jalankanEws();

        $baris = NotifikasiPengiriman::where('penerima_user_id', $pic->id)->get();
        $this->assertCount(2, $baris);
        $this->assertSame(['antre'], $baris->pluck('status')->unique()->values()->all());

        foreach ($baris as $b) {
            $this->kirim($b);
            $b->refresh();

            $this->assertSame('dibatalkan', $b->status);
            $this->assertSame('penerima_tidak_berhak', $b->kode_hasil);
            // Penyedia log tidak dipanggil: tidak ada rujukan provider.
            $this->assertNull($b->rujukan_provider);
        }
    }

    public function test_periode_lampau_2026_tidak_mengantrekan(): void
    {
        $this->travelKe('2026-06-01 08:00');

        $this->jalankanEws();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_scheduler_mendaftarkan_notifikasi_ews(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('notifikasi:ews')
            ->assertSuccessful();
    }

    private function atur(string $kunci, string $nilai): void
    {
        DB::table('pengaturan')->where('kunci', $kunci)->update(['nilai' => $nilai]);
    }

    private function travelKe(string $waktu): void
    {
        $this->travelTo(CarbonImmutable::parse($waktu, 'Asia/Makassar'));
    }

    private function jalankanEws(): void
    {
        $this->artisan('notifikasi:ews')->assertSuccessful();
    }

    private function kirim(NotifikasiPengiriman $baris): void
    {
        app(PengirimNotifikasiEksternal::class)->kirim($baris->id, 4);
    }

    private function picDenganIndikator(string $kode, User $pic): JadwalSnapshot
    {
        $snapshot = $this->indikatorBaru($kode);
        PenugasanIndikator::create([
            'indikator_id' => $snapshot->indikator_id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        return $snapshot;
    }
}
