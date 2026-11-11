<?php

namespace Tests\Feature\Pengingat;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\NotifikasiPengiriman;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifikasi\HasilKirim;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use App\Services\Notifikasi\PenyediaKanalNotifikasi;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * End-to-end perintah rekap Perencanaan -> outbox -> pengirim memakai producer asli.
 * Fixture dasar: Triwulan I dengan batas pengisian 2026-03-15, satu header draf (I-UJI), aktor Superadmin.
 * Job difake pada bus agar transaksi outbox tetap memakai koneksi `database` nyata; pengirim dipanggil eksplisit.
 */
class KirimRekapPerencanaanTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw1;

    private User $perencana;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->seed(PengaturanSeeder::class);
        Cache::flush();

        $this->atur('notifikasi.jam_kirim', '08:00');
        $this->atur('notifikasi.kanal_email_aktif', '1');
        $this->atur('notifikasi.kanal_whatsapp_aktif', '1');
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $this->perencana = $this->akunPerencanaan('perencana.satu@example.test', '6281234567801');

        Bus::fake([KirimNotifikasiEksternal::class]);
    }

    /** @return array<string, array{0:string, 1:int}> */
    public static function hariRekap(): array
    {
        return ['H-3' => ['2026-03-12 08:00', 3], 'H-1' => ['2026-03-14 08:00', 1]];
    }

    #[DataProvider('hariRekap')]
    public function test_h3_dan_h1_mengantre_email_dan_whatsapp_per_penerima(string $waktu, int $h): void
    {
        $kedua = $this->akunPerencanaan('perencana.dua@example.test', '6281234567802');
        $this->indikatorBaru('I-KOSONG-1');
        $this->indikatorBaru('I-KOSONG-2');
        $this->travelKe($waktu);

        $this->jalankan();

        // Dibaca dari tabel mentah: yang dibuktikan adalah data tersimpan, bukan keluaran perintah.
        $baris = DB::table('notifikasi_pengiriman')->get();
        $this->assertCount(4, $baris);
        $this->assertEqualsCanonicalizing(
            [[$this->perencana->id, 'email'], [$this->perencana->id, 'whatsapp'], [$kedua->id, 'email'], [$kedua->id, 'whatsapp']],
            $baris->map(fn ($b): array => [$b->penerima_user_id, $b->kanal])->all(),
        );
        foreach ($baris as $b) {
            $this->assertSame(['jadwal_periode', $this->tw1->id, 'rekap:h'.$h, $this->tw1->id.':2026-03-15', 'antre'],
                [$b->objek_tipe, $b->objek_id, $b->kejadian, $b->konteks_periode, $b->status]);
            $this->assertSame(config('notifikasi.template_rekap'), $b->template);
            // jsonb mengurutkan ulang kunci, jadi isi dibandingkan tanpa urutan.
            $this->assertEquals([
                'h' => $h, 'jatuh_tempo' => '2026-03-15', 'jatuh_tempo_tampil' => '15 Maret 2026', 'periode' => 'Triwulan I', 'tahun' => 2026,
                'belum_mengisi' => 2, 'draf' => 1, 'dikembalikan' => 0, 'diajukan' => 0, 'belum_selesai' => 3, 'total' => 3,
                'rincian_unit' => 'Belum selesai per unit: Unit Pengujian 3.', 'url' => route('dashboard'),
                // Tanpa unit yang ditolak baca, sidik cakupan adalah hash daftar kosong.
                'cakupan_unit' => hash('sha256', ''),
            ], json_decode($b->parameter, true));
        }

        $email = NotifikasiPengiriman::where('penerima_user_id', $this->perencana->id)->where('kanal', 'email')->firstOrFail();
        $this->kirim($email);

        $this->assertSame(['diterima_provider', null, 'log:'.$email->id], [$email->fresh()->status, $email->fresh()->kode_hasil, $email->fresh()->rujukan_provider]);
    }

    /** @return array<string, array{0:string}> */
    public static function hariTanpaRekap(): array
    {
        return ['H-7' => ['2026-03-08 08:00'], 'H-2' => ['2026-03-13 08:00'], 'H-0' => ['2026-03-15 08:00']];
    }

    #[DataProvider('hariTanpaRekap')]
    public function test_hari_selain_h3_h1_tidak_mengantrekan(string $waktu): void
    {
        // Kontrol positif: test_h3_dan_h1_mengantre_email_dan_whatsapp_per_penerima pada fixture yang sama.
        $this->travelKe($waktu);

        $this->jalankan();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertTrue(Cache::has('notifikasi:rekap:diproses:'.substr($waktu, 0, 10)));
    }

    /** @return array<string, array{0:string}> */
    public static function bukanPenerima(): array
    {
        return ['akun nonaktif' => ['nonaktif'], 'peran bukan perencanaan' => ['peran_lain'], 'ditolak membuka dashboard' => ['deny']];
    }

    #[DataProvider('bukanPenerima')]
    public function test_bukan_penerima_efektif_tidak_diantrekan(string $kasus): void
    {
        $lain = $this->akunPerencanaan('perencana.lain@example.test', '6281234567803');
        match ($kasus) {
            'nonaktif' => $lain->update(['status' => 'nonaktif']),
            'peran_lain' => $this->gantiPeran($lain, 'pimpinan'),
            'deny' => DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $lain->id,
                'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'), 'unit_id' => null,
                'alasan' => 'Fixture pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]),
        };
        $this->travelKe('2026-03-12 08:00');

        $this->jalankan();

        // Kontrol positif: akun Perencanaan lain pada run yang sama tetap menerima dua kanal.
        $this->assertSame(2, DB::table('notifikasi_pengiriman')->where('penerima_user_id', $this->perencana->id)->count());
        $this->assertSame(0, DB::table('notifikasi_pengiriman')->where('penerima_user_id', $lain->id)->count());
        // Aktor fixture berperan Superadmin: memegang semua izin tetapi bukan Tim Perencanaan.
        $this->assertSame(0, DB::table('notifikasi_pengiriman')->where('penerima_user_id', $this->actor->id)->count());
    }

    public function test_peran_perencanaan_nonaktif_tidak_menghasilkan_penerima(): void
    {
        Role::where('kode', 'perencanaan')->update(['aktif' => false]);
        $this->travelKe('2026-03-12 08:00');

        $this->jalankan();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_penerima_dicabut_setelah_antre_dibatalkan_di_gerbang_kirim(): void
    {
        $dicabut = $this->akunPerencanaan('perencana.dicabut@example.test', '6281234567804');
        $this->travelKe('2026-03-12 08:00');
        $this->jalankan();
        $this->gantiPeran($dicabut, 'pimpinan');

        $tetap = NotifikasiPengiriman::where('penerima_user_id', $this->perencana->id)->where('kanal', 'email')->firstOrFail();
        $batal = NotifikasiPengiriman::where('penerima_user_id', $dicabut->id)->where('kanal', 'email')->firstOrFail();
        $this->kirim($tetap);
        $this->kirim($batal);

        // Akun tetap aktif, jadi pembatalan hanya dapat berasal dari gerbang penerima rekap.
        $this->assertSame('diterima_provider', $tetap->fresh()->status);
        $this->assertSame(['dibatalkan', 'penerima_tidak_berhak', null], [$batal->fresh()->status, $batal->fresh()->kode_hasil, $batal->fresh()->rujukan_provider]);
    }

    /** @return array<string, array{0:?string, 1:string}> */
    public static function pesanBasi(): array
    {
        return [
            'hari H sudah lewat' => [null, '2026-03-13 08:00'],
            'tenggat bergeser dengan H sama' => ['2026-03-16', '2026-03-13 08:00'],
            'tenggat bergeser pada hari yang sama' => ['2026-03-20', '2026-03-12 08:05'],
        ];
    }

    #[DataProvider('pesanBasi')]
    public function test_tenggat_berubah_atau_h_lewat_dibatalkan_di_gerbang_kirim(?string $tenggatBaru, string $waktuKirim): void
    {
        // Kontrol positif: test_h3_dan_h1_mengantre_email_dan_whatsapp_per_penerima mengirim baris yang tidak basi.
        $this->travelKe('2026-03-12 08:00');
        $this->jalankan();
        $baris = NotifikasiPengiriman::where('kanal', 'email')->firstOrFail();
        if ($tenggatBaru !== null) {
            DB::table('jadwal_periode')->where('id', $this->tw1->id)->update(['pengisian_selesai' => $tenggatBaru]);
        }
        $this->travelKe($waktuKirim);

        $this->kirim($baris);

        $this->assertSame(['dibatalkan', 'penerima_tidak_berhak'], [$baris->fresh()->status, $baris->fresh()->kode_hasil]);
    }

    public function test_jadwal_tidak_lagi_aktif_setelah_antre_dibatalkan_di_gerbang_kirim(): void
    {
        // Kontrol positif: test_h3_dan_h1_mengantre_email_dan_whatsapp_per_penerima mengirim pada jadwal aktif.
        $this->travelKe('2026-03-12 08:00');
        $this->jalankan();
        $baris = NotifikasiPengiriman::where('kanal', 'email')->firstOrFail();
        DB::table('jadwal_tahunan')->where('id', $this->jadwal->id)->update(['status' => 'ditutup']);

        $this->kirim($baris);

        $this->assertSame(['dibatalkan', 'penerima_tidak_berhak'], [$baris->fresh()->status, $baris->fresh()->kode_hasil]);
    }

    public function test_unit_yang_ditolak_baca_hanya_hilang_dari_rekap_penerima_itu(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $this->indikatorBaru('I-B-KOSONG', [], ['unit_id' => $unitB->id]);
        $terbatas = $this->akunPerencanaan('perencana.terbatas@example.test', '6281234567805');
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $terbatas->id,
            'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'), 'unit_id' => $unitB->id,
            'alasan' => 'Fixture pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->travelKe('2026-03-12 08:00');

        $this->jalankan();

        $total = fn (User $u): array => DB::table('notifikasi_pengiriman')->where('penerima_user_id', $u->id)->get()
            ->map(fn ($b): int => json_decode($b->parameter, true)['total'])->all();
        $this->assertSame([2, 2], $total($this->perencana));
        $this->assertSame([1, 1], $total($terbatas));
    }

    public function test_deny_unit_setelah_antre_dibatalkan_di_gerbang_kirim(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $this->indikatorBaru('I-B-KOSONG', [], ['unit_id' => $unitB->id]);
        $lain = $this->akunPerencanaan('perencana.lain@example.test', '6281234567806');
        $this->travelKe('2026-03-12 08:00');
        $this->jalankan();
        // Rekap yang sudah antre memuat Unit B; penolakan baca unit itu baru ditetapkan sesudahnya.
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $lain->id,
            'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'), 'unit_id' => $unitB->id,
            'alasan' => 'Fixture pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $tetap = NotifikasiPengiriman::where('penerima_user_id', $this->perencana->id)->where('kanal', 'email')->firstOrFail();
        $batal = NotifikasiPengiriman::where('penerima_user_id', $lain->id)->where('kanal', 'email')->firstOrFail();
        $this->kirim($tetap);
        $this->kirim($batal);

        $this->assertSame('diterima_provider', $tetap->fresh()->status);
        $this->assertSame(['dibatalkan', 'penerima_tidak_berhak', null], [$batal->fresh()->status, $batal->fresh()->kode_hasil, $batal->fresh()->rujukan_provider]);
    }

    public function test_dijalankan_ulang_tidak_menambah_baris(): void
    {
        $this->travelKe('2026-03-12 08:00');
        $this->jalankan();
        $ids = DB::table('notifikasi_pengiriman')->orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertTrue(Cache::has('notifikasi:rekap:diproses:2026-03-12'));

        // Penanda harian dihapus agar run kedua benar-benar mengantre ulang dan hanya ditahan kunci idempoten.
        Cache::forget('notifikasi:rekap:diproses:2026-03-12');
        $this->travelKe('2026-03-12 08:30');
        $this->jalankan();

        $this->assertSame($ids, DB::table('notifikasi_pengiriman')->orderBy('id')->pluck('id')->all());
    }

    public function test_provider_gagal_sementara_dicatat_dan_diulang_terbatas(): void
    {
        $provider = new class implements PenyediaKanalNotifikasi
        {
            public int $panggilan = 0;

            public function kirim(NotifikasiPengiriman $pengiriman): HasilKirim
            {
                $this->panggilan++;

                return HasilKirim::sementara(60);
            }
        };
        $this->app->instance(PenyediaKanalNotifikasi::class, $provider);
        $this->travelKe('2026-03-12 08:00');
        $this->jalankan();
        $baris = NotifikasiPengiriman::where('kanal', 'email')->firstOrFail();

        $this->kirim($baris);

        $baris->refresh();
        $this->assertSame(['antre', 'provider_sementara', 1], [$baris->status, $baris->kode_hasil, $baris->percobaan]);
        $this->assertTrue($baris->coba_lagi_pada->isFuture());

        // Jeda terpanjang 30 menit, sehingga empat upaya selesai pada hari H yang sama.
        foreach ([2, 3, 4] as $upaya) {
            $this->travel(31)->minutes();
            $this->kirim($baris);
            $this->assertSame($upaya, $baris->fresh()->percobaan);
        }

        $this->assertSame(['gagal', 'percobaan_habis'], [$baris->fresh()->status, $baris->fresh()->kode_hasil]);
        $this->travel(31)->minutes();
        $this->kirim($baris);
        $this->assertSame(4, $provider->panggilan);
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
    }

    public function test_jam_kirim_kosong_atau_belum_tiba_tidak_mengantrekan(): void
    {
        $this->travelKe('2026-03-12 07:59');
        $this->jalankan();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        $this->atur('notifikasi.jam_kirim', '');
        $this->travelKe('2026-03-12 09:00');
        $this->artisan('notifikasi:rekap')->expectsOutputToContain('Jam kirim belum ditetapkan')->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertFalse(Cache::has('notifikasi:rekap:diproses:2026-03-12'));
    }

    public function test_kegagalan_antrean_berhenti_sekali_per_hari_tanpa_penanda_diproses(): void
    {
        // Driver kanal selain log belum didukung producer, sehingga antrean pasti gagal.
        config(['notifikasi.kanal.email.driver' => 'http']);
        $this->travelKe('2026-03-12 08:00');

        $this->artisan('notifikasi:rekap')->expectsOutputToContain('Rekap Perencanaan gagal diantrekan.')->assertFailed();
        $this->artisan('notifikasi:rekap')->assertFailed();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertFalse(Cache::has('notifikasi:rekap:diproses:2026-03-12'));
        $this->assertTrue(Cache::has('notifikasi:rekap:gagal:2026-03-12'));
    }

    public function test_scheduler_mendaftarkan_notifikasi_rekap(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('notifikasi:rekap')->assertSuccessful();
    }

    private function akunPerencanaan(string $email, string $telepon): User
    {
        $user = $this->userWithRole('perencanaan');
        $user->update(['email' => $email, 'nomor_telepon' => $telepon]);

        return $user;
    }

    private function gantiPeran(User $user, string $kode): void
    {
        DB::table('user_roles')->where('user_id', $user->id)->update(['role_id' => Role::where('kode', $kode)->value('id')]);
    }

    private function atur(string $kunci, string $nilai): void
    {
        DB::table('pengaturan')->where('kunci', $kunci)->update(['nilai' => $nilai]);
    }

    private function travelKe(string $waktu): void
    {
        $this->travelTo(CarbonImmutable::parse($waktu, 'Asia/Makassar'));
    }

    private function jalankan(): void
    {
        $this->artisan('notifikasi:rekap')->assertSuccessful();
    }

    private function kirim(NotifikasiPengiriman $baris): void
    {
        app(PengirimNotifikasiEksternal::class)->kirim($baris->id, 4);
    }
}
