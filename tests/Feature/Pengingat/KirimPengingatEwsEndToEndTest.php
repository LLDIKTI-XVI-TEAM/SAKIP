<?php

namespace Tests\Feature\Pengingat;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\JadwalSnapshot;
use App\Models\NotifikasiPengiriman;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** @return array<string, array{0:string, 1:int}> */
    public static function hariPengingatTenggat(): array
    {
        return [
            'H-7' => ['2026-03-08 08:00', 7],
            'H-3' => ['2026-03-12 08:00', 3],
            'H-1' => ['2026-03-14 08:00', 1],
        ];
    }

    #[DataProvider('hariPengingatTenggat')]
    public function test_h_minus_tenggat_mengantre_per_kanal_lalu_sender_menerima_provider_simulasi(string $waktu, int $h): void
    {
        $tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $this->travelKe($waktu);

        $this->jalankanEws();

        $baris = NotifikasiPengiriman::all();
        $this->assertCount(2, $baris);
        $this->assertEqualsCanonicalizing(['email', 'whatsapp'], $baris->pluck('kanal')->all());
        foreach ($baris as $b) {
            $this->assertSame('jadwal_periode', $b->objek_tipe);
            $this->assertSame($tw1->id, $b->objek_id);
            $this->assertSame('ews:h'.$h, $b->kejadian);
            $this->assertSame($tw1->id.':2026-03-15', $b->konteks_periode);
            $this->assertSame($this->actor->id, $b->penerima_user_id);
            $this->assertSame('antre', $b->status);
            $this->assertTrue($b->simulasi);
            $this->assertSame($h, $b->parameter['h']);
            $this->assertSame('2026-03-15', $b->parameter['jatuh_tempo']);
            $this->assertSame('15 Maret 2026', $b->parameter['jatuh_tempo_tampil']);
            $this->assertSame(1, $b->parameter['jumlah']);
            $this->assertSame(route('pengukuran.index'), $b->parameter['url']);
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
        $ids = NotifikasiPengiriman::orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertTrue(Cache::has('notifikasi:ews:diproses:2026-03-14'));

        $this->travelKe('2026-03-14 08:30');
        $this->jalankanEws();

        // Baris yang sama (identitas id identik), bukan hanya jumlah yang kebetulan sama.
        $this->assertSame($ids, NotifikasiPengiriman::orderBy('id')->pluck('id')->all());
    }

    public function test_kegagalan_antrean_berhenti_sekali_per_hari_tanpa_penanda_diproses(): void
    {
        // Driver kanal non-log belum didukung fondasi producer, sehingga antrean gagal pasti.
        config(['notifikasi.kanal.email.driver' => 'http']);
        $this->travelKe('2026-03-14 08:00');
        Log::spy();

        // Dua eksekusi scheduler tiap menit: log sekali per hari, tanpa evaluasi ulang.
        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('Pengingat EWS gagal diantrekan.')
            ->assertFailed();
        $this->artisan('notifikasi:ews')->assertFailed();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertFalse(Cache::has('notifikasi:ews:diproses:2026-03-14'));
        $this->assertTrue(Cache::has('notifikasi:ews:gagal:2026-03-14'));
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $pesan): bool => str_contains($pesan, 'Notifikasi EWS gagal diantrekan'));
    }

    public function test_jam_kirim_kosong_tidak_mengantrekan_walau_dijalankan_dua_kali(): void
    {
        $this->atur('notifikasi.jam_kirim', '');
        $this->travelKe('2026-03-14 09:00');

        $this->jalankanEws();
        $this->jalankanEws();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    /** @return array<string, array{0:string}> */
    public static function hariTanpaPengingat(): array
    {
        return [
            'H-6' => ['2026-03-09 08:00'],
            'H-5' => ['2026-03-10 08:00'],
            'H-4' => ['2026-03-11 08:00'],
            'H-2' => ['2026-03-13 08:00'],
        ];
    }

    #[DataProvider('hariTanpaPengingat')]
    public function test_h_minus_di_luar_daftar_tetap_dievaluasi_tanpa_outbox(string $waktu): void
    {
        $this->travelKe($waktu);

        // AC-2: pengecekan tetap berjalan (periode diperiksa) meskipun tidak ada dispatch.
        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('1 jadwal diperiksa, tidak ada kandidat')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    /** @return array<string, array{0:string}> */
    public static function statusTuntas(): array
    {
        return [
            'diajukan' => ['diajukan'],
            'diverifikasi' => ['diverifikasi'],
            'disahkan' => ['disahkan'],
        ];
    }

    #[DataProvider('statusTuntas')]
    public function test_header_tuntas_bukan_kandidat_dan_tidak_mengantrekan(string $status): void
    {
        $this->pengukuran->update(['status_alur' => $status]);
        $this->travelKe('2026-03-14 08:00');

        // AC-3: tanggal H-1 tetap dievaluasi, tetapi PIC dengan pekerjaan tuntas dikeluarkan.
        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('1 jadwal diperiksa, tidak ada kandidat')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_header_dikembalikan_belum_diajukan_ulang_tetap_mengantre_dan_terkirim(): void
    {
        $this->pengukuran->update(['status_alur' => 'dikembalikan']);
        $this->travelKe('2026-03-14 08:00');

        $this->jalankanEws();

        $baris = NotifikasiPengiriman::all();
        $this->assertCount(2, $baris);
        foreach ($baris as $b) {
            $this->assertSame('ews:h1', $b->kejadian);
            $this->assertSame($this->actor->id, $b->penerima_user_id);
            $this->assertSame('antre', $b->status);
        }

        // AC-4: pekerjaan yang dikembalikan tetap layak sampai diajukan ulang.
        $email = $baris->firstWhere('kanal', 'email');
        $this->kirim($email);
        $this->assertSame('diterima_provider', $email->fresh()->status);
    }

    public function test_jam_kirim_mengikuti_pengaturan_bukan_jam_tetap(): void
    {
        $this->atur('notifikasi.jam_kirim', '13:45');
        $this->travelKe('2026-03-14 09:00');
        $this->jalankanEws();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        $this->travelKe('2026-03-14 13:45');
        $this->jalankanEws();
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
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

    public function test_periode_lampau_2026_tidak_dievaluasi_dan_tidak_mengantrekan(): void
    {
        // Periode lampau (Q46): tenggat 2026-03-10 sebelum tanggal aktivasi jadwal (2026-03-15).
        $lampau = $this->tambahPeriode(2, '2026-02-01', '2026-03-10');
        $this->picDenganIndikator('I-EWS-LAMPAU', $this->actor, ['periode_mulai_id' => $lampau->periode_id]);

        // Tanggal evaluasi 2026-03-03 adalah H-7 dari tenggat 2026-03-10, sehingga periode lampau
        // akan menjadi kandidat bila aturan Q46 tidak diterapkan.
        $this->travelKe('2026-03-03 08:00');

        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('1 jadwal diperiksa, tidak ada kandidat')
            ->assertSuccessful();

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

    /** @param  array<string, mixed>  $snapshotOpsi */
    private function picDenganIndikator(string $kode, User $pic, array $snapshotOpsi = []): JadwalSnapshot
    {
        $snapshot = $this->indikatorBaru($kode, [], $snapshotOpsi);
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
