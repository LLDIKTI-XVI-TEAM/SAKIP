<?php

namespace Tests\Feature\Notifikasi;

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\BukaKembaliJadwal;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Jadwal\TutupJadwal;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\AntrekanPembukaanJendela;
use App\Services\Notifikasi\KonfigurasiNotifikasi;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/**
 * Broadcast pembukaan jendela pengisian ke outbox eksternal: satu baris per PJ efektif × kanal dengan kejadian
 * bertanggal bisnis, sehingga jalur tanggal dan jalur aksi pada hari yang sama berbagi kunci idempoten.
 */
class NotifikasiPembukaanJendelaTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    private Periode $tw1;

    private IndikatorKinerja $indikatorA;

    private User $pjA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-04-10 09:00:00', 'Asia/Makassar'));
        $this->readyJadwal(withIndicator: false);
        $this->tw1 = $this->calendarMaster(1, false);
        $this->indikatorA = $this->eligibleIndicator(target: '80');
        $this->pjA = $this->pj($this->indikatorA);
        $this->pjA->forceFill(['nomor_telepon' => '081234567890'])->save();
        $this->aturKanal(true);
    }

    /** PJ efektif sejak awal tahun: pegawai aktif berperan aktif tanpa grant. */
    private function pj(IndikatorKinerja $indikator): User
    {
        $user = $this->activator([]);
        PenugasanIndikator::create(['indikator_id' => $indikator->id, 'user_id' => $user->id, 'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $this->ready['owner']->id, 'created_at' => now()]);

        return $user;
    }

    /** Saklar kanal dibaca dari pengaturan lebih dulu, lalu konfigurasi; keduanya disetel agar tidak bergantung seeder. */
    private function aturKanal(bool $aktif): void
    {
        config(['notifikasi.kanal.email.aktif' => $aktif, 'notifikasi.kanal.whatsapp.aktif' => $aktif]);
        DB::table('pengaturan')->whereIn('kunci', ['notifikasi.kanal_email_aktif', 'notifikasi.kanal_whatsapp_aktif'])->update(['nilai' => $aktif ? '1' : '0']);
    }

    /** @param list<array{0: Periode, 1: string, 2: string}> $jendela */
    private function simpanDraf(array $jendela): void
    {
        $base = $this->calendarPayload($this->ready['renstra'], $this->ready['periode']);
        $rows = array_map(fn (array $j): array => ['periode_revisi' => $j[0]->revisi, 'periode_id' => $j[0]->id, 'pengisian_mulai' => $j[1], 'pengisian_selesai' => $j[2]], $jendela);
        app(SaveJadwalDraft::class)->handle($this->ready['owner'], [...$base, 'revisi' => $this->ready['jadwal']->fresh()->revisi,
            'periode' => [...$base['periode'], ...$rows]], $this->ready['jadwal']);
    }

    private function aktivasi(): void
    {
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
    }

    /** Jadwal aktif dengan jendela TW I yang baru terbuka sebulan setelah aktivasi. */
    private function jadwalAktifJendelaMei(): PeriodeJadwal
    {
        $this->simpanDraf([[$this->tw1, '2026-05-10', '2026-05-30']]);
        $this->aktivasi();
        $this->travelTo(Carbon::parse('2026-05-10 08:00:00', 'Asia/Makassar'));

        return PeriodeJadwal::where('jadwal_id', $this->ready['jadwal']->id)->where('periode_id', $this->tw1->id)->firstOrFail();
    }

    /** Baris mentah outbox, terurut kanal, dengan parameter terurai. */
    private function baris(): Collection
    {
        return DB::table('notifikasi_pengiriman')->orderBy('kejadian')->orderBy('kanal')->get()
            ->map(fn ($row) => (object) [...(array) $row, 'parameter' => json_decode($row->parameter, true)]);
    }

    private function antre(PeriodeJadwal $jendela, ?array $indikatorIds = null, string $tanggal = '2026-05-10'): Collection
    {
        return app(AntrekanPembukaanJendela::class)->antre($jendela, $indikatorIds, $tanggal);
    }

    /** @param list<array{periode_id: string, pengisian_mulai: string, pengisian_selesai: string}> $periode */
    private function revisi(array $periode): TestResponse
    {
        $jadwal = $this->ready['jadwal']->fresh();

        return $this->actingAs($this->activator(['jadwal:update']))->post("/jadwal/{$jadwal->id}/revisi-jendela",
            ['expected_revisi' => $jadwal->revisi, 'alasan' => 'Revisi jendela pengujian.', 'periode' => $periode]);
    }

    /** Baris periode lengkap untuk revisi: TW I diubah, TW IV tetap. @return list<array{periode_id: string, pengisian_mulai: string, pengisian_selesai: string}> */
    private function jendelaTw1(string $mulai, string $selesai): array
    {
        return [['periode_id' => $this->tw1->id, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => $selesai],
            ['periode_id' => $this->ready['periode']->id, 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11']];
    }

    public function test_aktivasi_saat_jendela_berjalan_langsung_mengantre_walau_jam_kirim_kosong(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-04-01', '2026-04-30']]);
        // Jam kirim hanya mengatur jalur terjadwal; jalur aksi tidak menunggunya.
        $this->assertNull(app(KonfigurasiNotifikasi::class)->jamKirim());

        $this->aktivasi();

        $this->assertSame([['email', 'jendela_dibuka:2026-04-10', $this->pjA->id, 'antre'], ['whatsapp', 'jendela_dibuka:2026-04-10', $this->pjA->id, 'antre']],
            $this->baris()->map(fn ($r) => [$r->kanal, $r->kejadian, $r->penerima_user_id, $r->status])->all());
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_aktivasi_30_hari_sebelum_pengisian_mulai_tidak_mengantre(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-10', '2026-05-30']]);

        $this->aktivasi();

        $this->assertDatabaseHas('jadwal_tahunan', ['id' => $this->ready['jadwal']->id, 'status' => 'aktif']);
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_revisi_jendela_tertutup_ke_terbuka_mengantre_dan_perpanjangan_tidak(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-01', '2026-05-30']]);
        $this->aktivasi();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        // Maju ke hari ini: tertutup menjadi terbuka.
        $this->revisi($this->jendelaTw1('2026-04-10', '2026-05-30'))->assertSessionHasNoErrors();
        $this->assertSame(['jendela_dibuka:2026-04-10', 'jendela_dibuka:2026-04-10'], $this->baris()->pluck('kejadian')->all());

        // Memperpanjang jendela yang sedang berjalan bukan pembukaan.
        $this->revisi($this->jendelaTw1('2026-04-10', '2026-06-30'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
        $this->assertDatabaseCount('jobs', 2);
    }

    /**
     * Sesi koreksi 25 Januari sampai 10 Februari 2027 atas TW II dan TW III: indikator A dan B dalam lingkup
     * (B berlaku sejak TW III), indikator C di luar lingkup. Jendela PIC belum dibuka.
     *
     * @return array{0: Periode, 1: Periode, 2: User, 3: User} TW II, TW III, PJ B, PJ C
     */
    private function sesiKoreksi(): array
    {
        $tw2 = $this->calendarMaster(2, false);
        $tw3 = $this->calendarMaster(3, false);
        $this->simpanDraf([[$tw2, '2026-07-01', '2026-07-07'], [$tw3, '2026-10-01', '2026-10-07']]);
        $b = $this->eligibleIndicator(target: '90');
        JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $b->id, 'periode_mulai_id' => $tw3->id, 'unit_id' => $b->unit_id,
            'nama' => $b->nama, 'definisi' => 'Definisi', 'satuan' => 'persen', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 90]);
        $pjB = $this->pj($b);
        $pjC = $this->pj($this->eligibleIndicator(target: '70'));
        $this->aktivasi();
        $jadwal = $this->ready['jadwal']->fresh();

        $this->travelTo(Carbon::parse('2027-01-25 09:00:00', 'Asia/Makassar'));
        app(TutupJadwal::class)->handle($this->activator(['jadwal:tutup']), $jadwal->id, ['expected_revisi' => $jadwal->revisi, 'alasan' => 'Tahun ditutup.']);
        app(BukaKembaliJadwal::class)->handle($this->activator(['jadwal:buka_kembali']), $jadwal->id, [
            'expected_revisi' => $jadwal->fresh()->revisi, 'alasan' => 'Koreksi laporan.',
            'koreksi_mulai' => '2027-01-25T08:00', 'koreksi_sampai' => '2027-02-10T17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->indikatorA->id, $b->id], 'periode_ids' => [$tw2->id, $tw3->id]],
        ]);
        // Buka-kembali jadwal tidak membuka jendela PIC.
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        return [$tw2, $tw3, $pjB, $pjC];
    }

    /** Revisi jendela PIC TW II dan TW III di dalam sesi koreksi, mulai pada tanggal yang diberikan. */
    private function bukaJendelaSesi(Periode $tw2, Periode $tw3, string $mulai): void
    {
        $this->revisi([
            ['periode_id' => $tw2->id, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => '2027-02-10'],
            ['periode_id' => $tw3->id, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => '2027-02-10'],
            ['periode_id' => $this->ready['periode']->id, 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11'],
        ])->assertSessionHasNoErrors();
    }

    /** Periode yang diumumkan lewat Email per penerima. @return array<string, list<string>> */
    private function periodePerPenerima(): array
    {
        return $this->baris()->where('kanal', 'email')->groupBy('penerima_user_id')
            ->map(fn (Collection $rows): array => $rows->pluck('parameter.periode')->sort()->values()->all())->all();
    }

    public function test_sesi_koreksi_hanya_mengantre_pj_indikator_lingkup(): void
    {
        [$tw2, $tw3, $pjB, $pjC] = $this->sesiKoreksi();

        $this->bukaJendelaSesi($tw2, $tw3, '2027-01-25');

        $this->assertSame(['jendela_dibuka:2027-01-25'], $this->baris()->pluck('kejadian')->unique()->values()->all());
        $this->assertEquals([$this->pjA->id => ['Triwulan 2', 'Triwulan 3'], $pjB->id => ['Triwulan 3']], $this->periodePerPenerima());
        $this->assertDatabaseMissing('notifikasi_pengiriman', ['penerima_user_id' => $pjC->id]);
    }

    /** Pengaturan bawaan (kanal mati, jam kirim kosong) dimuat lebih dulu, lalu jam kirim dan kanal disetel untuk test. */
    private function aturJamKirim(string $nilai): void
    {
        $this->seed(PengaturanSeeder::class);
        DB::table('pengaturan')->where('kunci', 'notifikasi.jam_kirim')->update(['nilai' => $nilai]);
        $this->aturKanal(true);
        Cache::flush();
    }

    private function jalankanPerintah(): PendingCommand
    {
        return $this->artisan('notifikasi:pembukaan');
    }

    public function test_tanggal_pengisian_mulai_mengantrekan_email_dan_whatsapp_per_pj(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');

        $this->jalankanPerintah()->assertSuccessful();

        $this->assertSame([['email', 'jendela_dibuka:2026-05-10', $this->pjA->id, 'antre'], ['whatsapp', 'jendela_dibuka:2026-05-10', $this->pjA->id, 'antre']],
            $this->baris()->map(fn ($r) => [$r->kanal, $r->kejadian, $r->penerima_user_id, $r->status])->all());
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_sebelum_tanggal_pengisian_mulai_tidak_mengantrekan(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');
        $this->travelTo(Carbon::parse('2026-05-09 08:00:00', 'Asia/Makassar'));

        $this->jalankanPerintah()->assertSuccessful();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        $this->travelTo(Carbon::parse('2026-05-10 08:00:00', 'Asia/Makassar'));
        $this->jalankanPerintah()->assertSuccessful();
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
    }

    public function test_hari_setelah_pengisian_mulai_tidak_dibroadcast(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');
        // Scheduler tidak berjalan pada 10 Mei; pembukaan itu tidak disusulkan esok harinya.
        $this->travelTo(Carbon::parse('2026-05-11 08:00:00', 'Asia/Makassar'));

        $this->jalankanPerintah()->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_perintah_diulang_tidak_menambah_baris_walau_penanda_harian_hilang(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');

        $this->jalankanPerintah()->assertSuccessful();
        $this->jalankanPerintah()->assertSuccessful();
        // Tanpa penanda harian pun kunci idempoten outbox mencegah pesan ganda.
        Cache::flush();
        $this->jalankanPerintah()->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_listener_dan_perintah_di_hari_sama_menghasilkan_satu_set(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-10', '2026-05-30']]);
        $this->aktivasi();
        $this->aturJamKirim('07:00');
        $this->travelTo(Carbon::parse('2026-05-05 06:00:00', 'Asia/Makassar'));
        $this->revisi($this->jendelaTw1('2026-05-05', '2026-05-30'))->assertSessionHasNoErrors();
        $this->assertSame(['jendela_dibuka:2026-05-05', 'jendela_dibuka:2026-05-05'], $this->baris()->pluck('kejadian')->all());

        $this->travelTo(Carbon::parse('2026-05-05 08:00:00', 'Asia/Makassar'));
        // Keluaran membuktikan perintah memang memilih jendela itu; tanpa itu jumlah baris tetap 2 karena listener.
        $this->jalankanPerintah()->expectsOutputToContain('1 jendela mulai hari ini, 2 baris outbox')->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_jam_kirim_kosong_tidak_mengantre_dan_mencatat_alasan_sekali(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('');
        Log::spy();

        // Scheduler berjalan tiap menit: dua eksekusi pada hari bisnis yang sama tidak boleh memenuhi log.
        foreach ([1, 2] as $_) {
            $this->jalankanPerintah()->expectsOutputToContain('Jam kirim belum ditetapkan')->assertSuccessful();
        }

        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $pesan): bool => str_contains($pesan, 'jam kirim belum ditetapkan'));
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_jam_kirim_tidak_valid_tidak_mengantre(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('7 pagi');

        $this->jalankanPerintah()->expectsOutputToContain('Format jam kirim tidak valid')->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_sebelum_jam_kirim_belum_mengantre(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');
        $this->travelTo(Carbon::parse('2026-05-10 06:59:00', 'Asia/Makassar'));

        $this->jalankanPerintah()->assertSuccessful();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        $this->travelTo(Carbon::parse('2026-05-10 07:00:00', 'Asia/Makassar'));
        $this->jalankanPerintah()->assertSuccessful();
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
    }

    public function test_template_kosong_tidak_mengantre_dan_boleh_dicoba_lagi(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');
        $template = config('notifikasi.template_pembukaan');
        config(['notifikasi.template_pembukaan' => '']);

        $this->jalankanPerintah()->expectsOutputToContain('Template pembukaan belum ditetapkan')->assertSuccessful();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        // Tidak ada penanda harian, sehingga menit berikutnya mengantre setelah template diisi.
        config(['notifikasi.template_pembukaan' => $template]);
        $this->jalankanPerintah()->assertSuccessful();
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
    }

    public function test_gangguan_antre_mengembalikan_gagal_dan_boleh_dicoba_lagi(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');
        $this->mock(AntrekanNotifikasiEksternal::class, fn (MockInterface $mock) => $mock->shouldReceive('antre')->once()
            ->andThrow(new RuntimeException('notifikasi.gangguan_penyimpanan')));

        $this->jalankanPerintah()->assertFailed();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        $this->app->forgetInstance(AntrekanNotifikasiEksternal::class);
        $this->jalankanPerintah()->assertSuccessful();
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
    }

    public function test_jadwal_ditutup_tidak_dibroadcast(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->aturJamKirim('07:00');
        DB::table('jadwal_tahunan')->where('id', $this->ready['jadwal']->id)->update(['status' => 'ditutup']);

        $this->jalankanPerintah()->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_jendela_sesi_koreksi_yang_mulai_esok_diantrekan_perintah_hanya_untuk_lingkup(): void
    {
        [$tw2, $tw3, $pjB, $pjC] = $this->sesiKoreksi();
        $this->aturJamKirim('07:00');
        // Jendela PIC baru mulai besok: revisi hari ini bukan pembukaan.
        $this->bukaJendelaSesi($tw2, $tw3, '2027-01-26');
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        $this->travelTo(Carbon::parse('2027-01-26 08:00:00', 'Asia/Makassar'));
        $this->jalankanPerintah()->assertSuccessful();

        $this->assertSame(['jendela_dibuka:2027-01-26'], $this->baris()->pluck('kejadian')->unique()->values()->all());
        $this->assertEquals([$this->pjA->id => ['Triwulan 2', 'Triwulan 3'], $pjB->id => ['Triwulan 3']], $this->periodePerPenerima());
        $this->assertDatabaseMissing('notifikasi_pengiriman', ['penerima_user_id' => $pjC->id]);
    }

    public function test_satu_jendela_gagal_tidak_menghentikan_jendela_lain(): void
    {
        [$tw2, $tw3, $pjB] = $this->sesiKoreksi();
        $this->aturJamKirim('07:00');
        $this->bukaJendelaSesi($tw2, $tw3, '2027-01-26');
        $this->travelTo(Carbon::parse('2027-01-26 08:00:00', 'Asia/Makassar'));
        // Dua jendela mulai hari ini; yang pertama diproses gagal, yang kedua tetap harus dicoba.
        $panggilan = 0;
        $this->mock(AntrekanNotifikasiEksternal::class, fn (MockInterface $mock) => $mock->shouldReceive('antre')->twice()
            ->andReturnUsing(function () use (&$panggilan): Collection {
                if ($panggilan++ === 0) {
                    throw new RuntimeException('notifikasi.gangguan_penyimpanan');
                }

                return collect();
            }));

        $this->jalankanPerintah()->assertFailed();

        // Tanpa penanda harian, menit berikutnya mengantrekan seluruh jendela.
        $this->app->forgetInstance(AntrekanNotifikasiEksternal::class);
        $this->jalankanPerintah()->assertSuccessful();
        $this->assertEquals([$this->pjA->id => ['Triwulan 2', 'Triwulan 3'], $pjB->id => ['Triwulan 3']], $this->periodePerPenerima());
    }

    public function test_sesi_koreksi_khusus_rencana_aksi_tidak_diantrekan_perintah(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-04-01', '2026-04-07']]);
        $this->aktivasi();
        $jadwal = $this->ready['jadwal']->fresh();
        $this->travelTo(Carbon::parse('2027-01-25 09:00:00', 'Asia/Makassar'));
        app(TutupJadwal::class)->handle($this->activator(['jadwal:tutup']), $jadwal->id, ['expected_revisi' => $jadwal->revisi, 'alasan' => 'Tahun ditutup.']);
        app(BukaKembaliJadwal::class)->handle($this->activator(['jadwal:buka_kembali']), $jadwal->id, [
            'expected_revisi' => $jadwal->fresh()->revisi, 'alasan' => 'Koreksi rencana aksi.',
            'koreksi_mulai' => '2027-01-25T08:00', 'koreksi_sampai' => '2027-02-10T17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$this->indikatorA->id], 'periode_ids' => [$this->tw1->id]],
        ]);
        $this->aturJamKirim('07:00');
        // Sesi tanpa pengukuran tidak membuka pengisian bagi PIC, sama dengan jalur aksi.
        $this->revisi($this->jendelaTw1('2027-01-26', '2027-02-10'))->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2027-01-26 08:00:00', 'Asia/Makassar'));

        $this->jalankanPerintah()->expectsOutputToContain('0 jendela mulai hari ini')->assertSuccessful();

        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_perintah_terdaftar_di_scheduler(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('notifikasi:pembukaan')->assertSuccessful();
    }

    public function test_producer_gagal_tidak_menggagalkan_aktivasi(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-04-01', '2026-04-30']]);
        // `once()` memastikan jalur eksternal benar-benar dipanggil, sehingga test tidak lulus tanpa listener.
        $this->mock(AntrekanNotifikasiEksternal::class, fn (MockInterface $mock) => $mock->shouldReceive('antre')->once()
            ->andThrow(new RuntimeException('notifikasi.gangguan_penyimpanan')));

        $this->aktivasi();

        $this->assertSame('aktif', DB::table('jadwal_tahunan')->where('id', $this->ready['jadwal']->id)->value('status'));
        $this->assertDatabaseHas('audit_log', ['tindakan' => 'jadwal.aktivasi', 'objek_id' => $this->ready['jadwal']->id]);
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        // Item in-app dari event yang sama tetap tertulis.
        $this->assertSame([$this->pjA->id], DB::table('notifications')->pluck('notifiable_id')->all());
    }

    /** Periode lampau 2026 selalu berakhir sebelum hari aktivasi, jadi tidak pernah berjalan saat jadwal diaktifkan. */
    public function test_aktivasi_setelah_jendela_lewat_tidak_mengantre(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-03-01', '2026-03-31']]);

        $this->aktivasi();

        $this->assertDatabaseHas('jadwal_tahunan', ['id' => $this->ready['jadwal']->id, 'status' => 'aktif']);
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
    }

    public function test_producer_mengantrekan_email_dan_whatsapp_per_pj_efektif(): void
    {
        $jendela = $this->jadwalAktifJendelaMei();
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        $this->antre($jendela);

        $baris = $this->baris();
        $this->assertSame(['email', 'whatsapp'], $baris->pluck('kanal')->all());
        foreach ($baris as $row) {
            $this->assertSame(['jendela_dibuka:2026-05-10', 'jadwal_periode', $jendela->id, $this->tw1->id, $this->pjA->id, 'antre', null],
                [$row->kejadian, $row->objek_tipe, $row->objek_id, $row->konteks_periode, $row->penerima_user_id, $row->status, $row->kode_hasil]);
            $this->assertEquals(['periode' => 'Triwulan 1', 'tahun' => 2026, 'selesai' => '30 Mei 2026', 'tautan' => route('pengukuran.index')], $row->parameter);
            $this->assertSame('Jendela pengisian {{periode}} {{tahun}} di SAKIP dibuka sampai {{selesai}}. Masuk ke SAKIP untuk mengisi: {{tautan}}', $row->template);
        }
        $this->assertSame([$this->pjA->email, '081234567890'], $baris->pluck('alamat')->all());
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_producer_diulang_di_tanggal_sama_tidak_menambah_baris(): void
    {
        $jendela = $this->jadwalAktifJendelaMei();

        $this->antre($jendela);
        $this->antre($jendela);

        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
        $this->assertDatabaseCount('jobs', 2);

        // Pembukaan ulang pada tanggal bisnis lain adalah kejadian baru.
        $this->antre($jendela, null, '2026-05-11');

        $this->assertSame(['jendela_dibuka:2026-05-10', 'jendela_dibuka:2026-05-10', 'jendela_dibuka:2026-05-11', 'jendela_dibuka:2026-05-11'],
            $this->baris()->pluck('kejadian')->all());
        $this->assertDatabaseCount('jobs', 4);
    }

    public function test_pj_indikator_yang_belum_berlaku_atau_di_luar_lingkup_tidak_diantrekan(): void
    {
        // Indikator B berlaku sejak periode terakhir (snapshot sudah ada sebelum aktivasi), bukan TW I.
        $b = $this->eligibleIndicator(target: '90');
        JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $b->id, 'periode_mulai_id' => $this->ready['periode']->id, 'unit_id' => $b->unit_id,
            'nama' => $b->nama, 'definisi' => 'Definisi', 'satuan' => 'persen', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 90]);
        $pjB = $this->pj($b);
        $jendela = $this->jadwalAktifJendelaMei();

        // Lingkup sesi koreksi kosong atau hanya indikator yang belum berlaku: tidak ada penerima.
        $this->antre($jendela, []);
        $this->antre($jendela, [$b->id]);
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);

        // Kontrol positif: tanpa lingkup, hanya PJ indikator yang wajib diisi pada TW I.
        $this->antre($jendela);
        $this->assertSame([$this->pjA->id], $this->baris()->pluck('penerima_user_id')->unique()->values()->all());
        $this->assertDatabaseMissing('notifikasi_pengiriman', ['penerima_user_id' => $pjB->id]);
    }

    public function test_kanal_mati_dicatat_dibatalkan_tanpa_job(): void
    {
        $jendela = $this->jadwalAktifJendelaMei();
        $this->aturKanal(false);

        $this->antre($jendela);

        $this->assertSame([['dibatalkan', 'kanal_nonaktif'], ['dibatalkan', 'kanal_nonaktif']], $this->baris()->map(fn ($r) => [$r->status, $r->kode_hasil])->all());
        $this->assertDatabaseCount('jobs', 0);

        // Menyalakan kanal di hari yang sama tidak mengirim ulang kejadian yang sudah tercatat.
        $this->aturKanal(true);
        $this->antre($jendela);

        $this->assertSame([['dibatalkan', 'kanal_nonaktif'], ['dibatalkan', 'kanal_nonaktif']], $this->baris()->map(fn ($r) => [$r->status, $r->kode_hasil])->all());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_pj_tanpa_nomor_telepon_gagal_di_whatsapp_saja(): void
    {
        $this->pjA->forceFill(['nomor_telepon' => null])->save();
        $jendela = $this->jadwalAktifJendelaMei();

        $this->antre($jendela);

        $this->assertSame([['email', 'antre', null], ['whatsapp', 'gagal', 'kontak_tidak_tersedia']], $this->baris()->map(fn ($r) => [$r->kanal, $r->status, $r->kode_hasil])->all());
        $this->assertDatabaseCount('jobs', 1);
    }
}
