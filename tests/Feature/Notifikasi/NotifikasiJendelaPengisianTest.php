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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/**
 * Item "jendela pengisian dibuka" hanya untuk transisi tertutup → terbuka oleh aktivasi jadwal atau revisi jendela PIC
 * pada tanggal bisnis aksi, ke PJ efektif indikator yang wajib diisi pada periode itu.
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
        $this->assertSame([$this->pjA->id, 'jadwal.jendela_dibuka'], [$item->notifiable_id, $item->type]);
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
        // Jendela yang sudah lewat diperpanjang sehingga terbuka lagi → item.
        $this->revisi($this->jendelaTw1('2026-04-01', '2026-04-07'))->assertSessionHasNoErrors();
        $this->revisi($this->jendelaTw1('2026-04-01', '2026-04-30'))->assertSessionHasNoErrors();
        $this->assertCount(2, $this->items());
    }

    public function test_sesi_koreksi_memicu_hanya_pj_indikator_lingkup_per_periode_yang_berlaku(): void
    {
        $tw2 = $this->calendarMaster(2, false);
        $tw3 = $this->calendarMaster(3, false);
        $this->simpanDraf([[$tw2, '2026-07-01', '2026-07-07'], [$tw3, '2026-10-01', '2026-10-07']]);
        // Indikator B berlaku sejak TW III; C di luar lingkup sesi.
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

        $dalamSesi = fn (): array => [
            ['periode_id' => $tw2->id, 'pengisian_mulai' => '2027-01-25', 'pengisian_selesai' => '2027-02-10'],
            ['periode_id' => $tw3->id, 'pengisian_mulai' => '2027-01-25', 'pengisian_selesai' => '2027-02-10'],
            ['periode_id' => $this->ready['periode']->id, 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11'],
        ];
        $this->revisi($dalamSesi())->assertSessionHasNoErrors();

        $perPenerima = $this->items()->groupBy('notifiable_id')->map(fn (Collection $rows): array => $rows->pluck('data.judul')->sort()->values()->all())->all();
        $this->assertEquals([
            $this->pjA->id => ['Jendela pengisian Triwulan 2 2026 dibuka', 'Jendela pengisian Triwulan 3 2026 dibuka'],
            $pjB->id => ['Jendela pengisian Triwulan 3 2026 dibuka'],
        ], $perPenerima);
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
}
