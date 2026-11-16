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
use App\Models\User;
use App\Services\Notifikasi\PenerimaNotifikasi;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/**
 * Item "jendela pengisian dibuka" ke PJ efektif indikator yang wajib diisi pada periode itu, dari dua jalur: transisi
 * tertutup → terbuka oleh aktivasi jadwal atau revisi jendela PIC pada tanggal bisnis aksi, dan perintah lonceng harian
 * untuk jendela yang `pengisian_mulai`-nya hari ini. Paling banyak satu item per jendela, tanggal bisnis, dan penerima.
 */
class NotifikasiJendelaPengisianTest extends TestCase
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
    }

    /** PJ efektif sejak awal tahun: pegawai aktif berperan aktif tanpa grant. */
    private function pj(IndikatorKinerja $indikator): User
    {
        $user = $this->activator([]);
        PenugasanIndikator::create(['indikator_id' => $indikator->id, 'user_id' => $user->id, 'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $this->ready['owner']->id, 'created_at' => now()]);

        return $user;
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

    private function items(): Collection
    {
        return DB::table('notifications')->orderBy('created_at')->get()->map(fn ($row) => (object) [...(array) $row, 'data' => json_decode($row->data, true)]);
    }

    /** @return array<string, list<string>> judul item per penerima, terurut */
    private function judulPerPenerima(): array
    {
        return $this->items()->groupBy('notifiable_id')->map(fn (Collection $rows): array => $rows->pluck('data.judul')->sort()->values()->all())->all();
    }

    private function jalankanLonceng(): PendingCommand
    {
        return $this->artisan('notifikasi:lonceng-pembukaan');
    }

    /** Jendela TW I 10 sampai 30 Mei; aktivasi 10 April belum membukanya sehingga belum ada item. */
    private function jadwalAktifJendelaMei(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-10', '2026-05-30']]);
        $this->aktivasi();
    }

    /**
     * Tahun 2026 ditutup lalu dibuka kembali untuk koreksi pengukuran indikator A dan B (B berlaku sejak TW III; C di luar
     * lingkup), kemudian jendela TW II dan TW III di dalam sesi direvisi agar mulai `$mulaiDalamSesi`. Mengembalikan PJ B.
     */
    private function sesiKoreksiLingkupAB(string $mulaiDalamSesi): User
    {
        $tw2 = $this->calendarMaster(2, false);
        $tw3 = $this->calendarMaster(3, false);
        $this->simpanDraf([[$tw2, '2026-07-01', '2026-07-07'], [$tw3, '2026-10-01', '2026-10-07']]);
        $b = $this->eligibleIndicator(target: '90');
        JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $b->id, 'periode_mulai_id' => $tw3->id, 'unit_id' => $b->unit_id,
            'nama' => $b->nama, 'definisi' => 'Definisi', 'satuan' => 'persen', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 90]);
        $pjB = $this->pj($b);
        $this->pj($this->eligibleIndicator(target: '70'));
        $this->aktivasi();
        $jadwal = $this->ready['jadwal']->fresh();

        $this->travelTo(Carbon::parse('2027-01-25 09:00:00', 'Asia/Makassar'));
        app(TutupJadwal::class)->handle($this->activator(['jadwal:tutup']), $jadwal->id, ['expected_revisi' => $jadwal->revisi, 'alasan' => 'Tahun ditutup.']);
        app(BukaKembaliJadwal::class)->handle($this->activator(['jadwal:buka_kembali']), $jadwal->id, [
            'expected_revisi' => $jadwal->fresh()->revisi, 'alasan' => 'Koreksi laporan.',
            'koreksi_mulai' => '2027-01-25T08:00', 'koreksi_sampai' => '2027-02-10T17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->indikatorA->id, $b->id], 'periode_ids' => [$tw2->id, $tw3->id]],
        ]);
        // Buka-kembali jadwal tidak membuka akses PIC → tanpa item.
        $this->assertCount(0, $this->items());

        $this->revisi([
            ['periode_id' => $tw2->id, 'pengisian_mulai' => $mulaiDalamSesi, 'pengisian_selesai' => '2027-02-10'],
            ['periode_id' => $tw3->id, 'pengisian_mulai' => $mulaiDalamSesi, 'pengisian_selesai' => '2027-02-10'],
            ['periode_id' => $this->ready['periode']->id, 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11'],
        ])->assertSessionHasNoErrors();

        return $pjB;
    }

    public function test_aktivasi_saat_jendela_berjalan_menulis_item_ke_pj_indikator_yang_wajib_diisi_saja(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-04-01', '2026-04-30']]);
        // Indikator B berlaku sejak periode terakhir (snapshot sudah ada sebelum aktivasi), bukan TW I.
        $b = $this->eligibleIndicator(target: '90');
        JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $b->id, 'periode_mulai_id' => $this->ready['periode']->id, 'unit_id' => $b->unit_id,
            'nama' => $b->nama, 'definisi' => 'Definisi', 'satuan' => 'persen', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 90]);
        $this->pj($b);

        $this->aktivasi();

        $item = $this->items()->sole();
        $this->assertSame([$this->pjA->id, (new User)->getMorphClass(), 'jadwal.jendela_dibuka'], [$item->notifiable_id, $item->notifiable_type, $item->type]);
        $this->assertSame(['judul' => 'Jendela pengisian Triwulan 1 2026 dibuka', 'ringkasan' => 'Pengisian sampai 30 April 2026',
            'route_name' => 'pengukuran.index', 'route_params' => []], $item->data);
        // Tanpa parameter pun tersimpan sebagai objek JSON, bukan array kosong.
        $this->assertMatchesRegularExpression('/"route_params":\s*\{\}/', DB::table('notifications')->value('data'));

        // Replay aktivasi adalah no-op.
        $this->aktivasi();
        $this->assertCount(1, $this->items());
    }

    public function test_aktivasi_saat_jendela_belum_tiba_tidak_menulis_item(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-01', '2026-05-30']]);

        $this->aktivasi();

        $this->assertCount(0, $this->items());
    }

    public function test_aktivasi_jadwal_2026_dengan_periode_lampau_tidak_menulis_item_untuk_periode_itu(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-03-01', '2026-03-31']]);

        $this->aktivasi();

        $this->assertCount(0, $this->items());
    }

    public function test_revisi_jendela_normal_hanya_memicu_transisi_tertutup_ke_terbuka(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-01', '2026-05-30']]);
        $this->aktivasi();

        // Maju ke hari ini → terbuka.
        $this->revisi($this->jendelaTw1('2026-04-10', '2026-05-30'))->assertSessionHasNoErrors();
        $this->assertSame([$this->pjA->id], $this->items()->pluck('notifiable_id')->all());
        // Memperpanjang jendela yang sedang berjalan → tidak ada item baru.
        $this->revisi($this->jendelaTw1('2026-04-10', '2026-06-30'))->assertSessionHasNoErrors();
        $this->assertCount(1, $this->items());
        // Revisi tanpa perubahan → tidak ada item.
        $this->revisi($this->jendelaTw1('2026-04-10', '2026-06-30'))->assertSessionHasNoErrors();
        $this->assertCount(1, $this->items());
        // Ke tanggal mendatang → tidak ada item.
        $this->revisi($this->jendelaTw1('2026-06-01', '2026-06-30'))->assertSessionHasNoErrors();
        $this->assertCount(1, $this->items());
        // Ditutup lalu dibuka lagi pada tanggal bisnis yang sama: tetap satu item per penerima.
        $this->revisi($this->jendelaTw1('2026-04-01', '2026-04-07'))->assertSessionHasNoErrors();
        $this->revisi($this->jendelaTw1('2026-04-01', '2026-04-30'))->assertSessionHasNoErrors();
        $this->assertCount(1, $this->items());
    }

    public function test_buka_ulang_tanggal_lain_menulis_item_baru(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-01', '2026-05-30']]);
        $this->aktivasi();
        $this->revisi($this->jendelaTw1('2026-04-10', '2026-05-30'))->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-04-11 09:00:00', 'Asia/Makassar'));
        $this->revisi($this->jendelaTw1('2026-04-01', '2026-04-07'))->assertSessionHasNoErrors();
        $this->revisi($this->jendelaTw1('2026-04-01', '2026-04-30'))->assertSessionHasNoErrors();

        $this->assertSame([$this->pjA->id, $this->pjA->id], $this->items()->pluck('notifiable_id')->all());
        $this->assertCount(2, $this->items()->pluck('id')->unique());
    }

    public function test_sesi_koreksi_memicu_hanya_pj_indikator_lingkup_per_periode_yang_berlaku(): void
    {
        $pjB = $this->sesiKoreksiLingkupAB('2027-01-25');

        $this->assertEquals([
            $this->pjA->id => ['Jendela pengisian Triwulan 2 2026 dibuka', 'Jendela pengisian Triwulan 3 2026 dibuka'],
            $pjB->id => ['Jendela pengisian Triwulan 3 2026 dibuka'],
        ], $this->judulPerPenerima());
    }

    public function test_sesi_koreksi_hanya_rencana_aksi_tidak_memicu_item(): void
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

        $this->revisi($this->jendelaTw1('2027-01-25', '2027-02-10'))->assertSessionHasNoErrors();

        $this->assertCount(0, $this->items());
    }

    public function test_perintah_lonceng_menulis_item_pada_tanggal_pengisian_mulai(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-05-10', '2026-05-30']]);
        // Indikator B wajib diisi tetapi PJ-nya nonaktif, dan satu akun aktif bukan PJ: keduanya tidak menerima.
        $this->pj($this->eligibleIndicator(target: '90'))->forceFill(['status' => 'nonaktif'])->save();
        $this->activator([]);
        $this->aktivasi();
        $this->travelTo(Carbon::parse('2026-05-10 00:05:00', 'Asia/Makassar'));

        $this->jalankanLonceng()->expectsOutputToContain('1 jendela mulai hari ini')->assertSuccessful();

        $item = $this->items()->sole();
        $this->assertSame([$this->pjA->id, (new User)->getMorphClass(), 'jadwal.jendela_dibuka'], [$item->notifiable_id, $item->notifiable_type, $item->type]);
        $this->assertSame(['judul' => 'Jendela pengisian Triwulan 1 2026 dibuka', 'ringkasan' => 'Pengisian sampai 30 Mei 2026',
            'route_name' => 'pengukuran.index', 'route_params' => []], $item->data);
    }

    public function test_perintah_lonceng_sebelum_0005_tidak_menulis(): void
    {
        $this->jadwalAktifJendelaMei();
        $penanda = 'notifikasi:lonceng_pembukaan:diproses:2026-05-10';

        $this->travelTo(Carbon::parse('2026-05-10 00:04:00', 'Asia/Makassar'));
        $this->jalankanLonceng()->doesntExpectOutputToContain('jendela mulai hari ini')->assertSuccessful();
        $this->assertCount(0, $this->items());
        $this->assertFalse(Cache::has($penanda));

        $this->travelTo(Carbon::parse('2026-05-10 00:05:00', 'Asia/Makassar'));
        $this->jalankanLonceng()->assertSuccessful();
        $this->assertCount(1, $this->items());
        $this->assertTrue(Cache::has($penanda));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function jamKirimDanKanal(): array
    {
        return ['jam kirim kosong, kanal mati' => ['', false], 'jam kirim terisi, kanal aktif' => ['00:00', true]];
    }

    #[DataProvider('jamKirimDanKanal')]
    public function test_perintah_lonceng_menulis_tanpa_outbox_apa_pun_jam_kirim_dan_kanalnya(string $jamKirim, bool $kanalAktif): void
    {
        $this->seed(PengaturanSeeder::class);
        DB::table('pengaturan')->where('kunci', 'notifikasi.jam_kirim')->update(['nilai' => $jamKirim]);
        DB::table('pengaturan')->whereIn('kunci', ['notifikasi.kanal_email_aktif', 'notifikasi.kanal_whatsapp_aktif'])->update(['nilai' => $kanalAktif ? '1' : '0']);
        config(['notifikasi.kanal.email.aktif' => $kanalAktif, 'notifikasi.kanal.whatsapp.aktif' => $kanalAktif]);
        $this->jadwalAktifJendelaMei();
        $this->travelTo(Carbon::parse('2026-05-10 00:05:00', 'Asia/Makassar'));

        $this->jalankanLonceng()->assertSuccessful();

        $this->assertCount(1, $this->items());
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_aktivasi_hari_pengisian_mulai_lalu_perintah_tetap_satu_item(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-04-10', '2026-04-30']]);
        $this->aktivasi();
        $this->assertCount(1, $this->items());

        $this->jalankanLonceng()->expectsOutputToContain('1 jendela mulai hari ini')->assertSuccessful();
        $this->assertCount(1, $this->items());
        // Penanda harian terpasang: run berikutnya hari itu tidak menilai ulang.
        $this->jalankanLonceng()->doesntExpectOutputToContain('jendela mulai hari ini')->assertSuccessful();
        $this->assertCount(1, $this->items());
        // Penanda hilang: jendela dinilai ulang, tetapi item yang sama tidak ditulis dua kali.
        Cache::flush();
        $this->jalankanLonceng()->expectsOutputToContain('1 jendela mulai hari ini')->assertSuccessful();
        $this->assertCount(1, $this->items());
    }

    public function test_perintah_lalu_buka_ulang_hari_sama_tetap_satu_item(): void
    {
        $this->jadwalAktifJendelaMei();
        $this->travelTo(Carbon::parse('2026-05-10 00:05:00', 'Asia/Makassar'));
        $this->jalankanLonceng()->assertSuccessful();
        $id = $this->items()->sole()->id;

        $this->travelTo(Carbon::parse('2026-05-10 09:00:00', 'Asia/Makassar'));
        $this->revisi($this->jendelaTw1('2026-05-01', '2026-05-09'))->assertSessionHasNoErrors();
        $this->revisi($this->jendelaTw1('2026-05-01', '2026-05-30'))->assertSessionHasNoErrors();

        $this->assertSame([$id], $this->items()->pluck('id')->all());
    }

    public function test_perintah_lonceng_periode_lampau_tanpa_item(): void
    {
        $this->simpanDraf([[$this->tw1, '2026-03-01', '2026-03-31']]);
        $this->aktivasi();
        // Mundur ke 1 Maret agar `pengisian_mulai` jatuh hari ini; jendela tetap disaring karena selesai sebelum aktivasi 10 April (periode lampau 2026).
        $this->travelTo(Carbon::parse('2026-03-01 00:05:00', 'Asia/Makassar'));

        $this->jalankanLonceng()->expectsOutputToContain('0 jendela mulai hari ini')->assertSuccessful();

        $this->assertCount(0, $this->items());
    }

    public function test_perintah_lonceng_sesi_koreksi_hanya_pj_lingkup(): void
    {
        $pjB = $this->sesiKoreksiLingkupAB('2027-01-26');
        $this->assertCount(0, $this->items());
        $this->travelTo(Carbon::parse('2027-01-26 00:05:00', 'Asia/Makassar'));

        $this->jalankanLonceng()->assertSuccessful();

        $this->assertEquals([
            $this->pjA->id => ['Jendela pengisian Triwulan 2 2026 dibuka', 'Jendela pengisian Triwulan 3 2026 dibuka'],
            $pjB->id => ['Jendela pengisian Triwulan 3 2026 dibuka'],
        ], $this->judulPerPenerima());
    }

    public function test_perintah_lonceng_satu_jendela_gagal_tidak_menghentikan_jendela_lain(): void
    {
        $this->sesiKoreksiLingkupAB('2027-01-26');
        $this->travelTo(Carbon::parse('2027-01-26 00:05:00', 'Asia/Makassar'));
        // Dipasang sesudah susunan agar aksi di susunan memakai penerima asli. Dua jendela mulai hari ini; yang pertama gagal.
        $panggilan = 0;
        $this->mock(PenerimaNotifikasi::class, fn (MockInterface $mock) => $mock->shouldReceive('pjJendela')->twice()
            ->andReturnUsing(function () use (&$panggilan): array {
                if ($panggilan++ === 0) {
                    throw new RuntimeException('penerima.gangguan');
                }

                return [$this->pjA];
            }));
        Log::spy();

        $this->jalankanLonceng()->assertSuccessful();

        $this->assertSame([$this->pjA->id], $this->items()->pluck('notifiable_id')->all());
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $pesan, array $konteks): bool => $pesan === 'Notifikasi in-app gagal ditulis.'
            && $konteks['jenis'] === 'jadwal.jendela_dibuka' && $konteks['galat'] === RuntimeException::class);
    }

    public function test_perintah_lonceng_terdaftar_tiap_menit_wita_dengan_kunci_sepuluh_menit(): void
    {
        $event = collect(app(Schedule::class)->events())->sole(fn (Event $event): bool => str_contains((string) $event->command, 'notifikasi:lonceng-pembukaan'));

        $this->assertSame(['* * * * *', 'Asia/Makassar', true, 10, true],
            [$event->expression, $event->timezone, $event->withoutOverlapping, $event->expiresAt, $event->onOneServer]);
    }
}
