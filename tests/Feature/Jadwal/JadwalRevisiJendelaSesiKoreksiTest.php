<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\BukaKembaliJadwal;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Jadwal\TutupJadwal;
use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/** Revisi resmi jendela PIC di dalam sesi koreksi: batas wajib di dalam sesi dan hanya periode lingkup (Plan 3.12). */
class JadwalRevisiJendelaSesiKoreksiTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    private const ALASAN = 'Jendela PIC TW I dibuka untuk koreksi laporan.';

    private JadwalTahunan $jadwal;

    private Periode $tw1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));
        $this->readyJadwal();
        $this->tw1 = $this->calendarMaster(1, false);
        $base = $this->calendarPayload($this->ready['renstra'], $this->ready['periode']);
        app(SaveJadwalDraft::class)->handle($this->ready['owner'], [...$base, 'revisi' => $this->ready['jadwal']->fresh()->revisi,
            'periode' => [...$base['periode'], ['periode_revisi' => $this->tw1->revisi, 'periode_id' => $this->tw1->id, 'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-07']]], $this->ready['jadwal']);
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        // Setelah penutupan asli 19 Januari 2027: tutup, lalu buka sesi koreksi resmi untuk TW I saja.
        $this->travelTo(Carbon::parse('2027-01-25 09:00:00', 'Asia/Makassar'));
        $jadwal = $this->ready['jadwal']->fresh();
        app(TutupJadwal::class)->handle($this->activator(['jadwal:tutup']), $jadwal->id, ['expected_revisi' => $jadwal->revisi, 'alasan' => 'Tahun ditutup.']);
        app(BukaKembaliJadwal::class)->handle($this->activator(['jadwal:buka_kembali']), $jadwal->id, [
            'expected_revisi' => $jadwal->fresh()->revisi, 'alasan' => 'Koreksi laporan TW I.',
            'koreksi_mulai' => '2027-01-25T08:00', 'koreksi_sampai' => '2027-02-10T17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [JadwalSnapshot::where('jadwal_id', $jadwal->id)->value('indikator_id')],
                'periode_ids' => [$this->tw1->id]],
        ]);
        $this->jadwal = $jadwal->fresh();
    }

    /**
     * @param  array<string, string>  $ubah
     * @return list<array<string, string>>
     */
    private function jendela(array $ubah = []): array
    {
        $rows = [
            ['periode_id' => $this->tw1->id, 'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-07'],
            ['periode_id' => $this->ready['periode']->id, 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11'],
        ];
        foreach ($ubah as $key => $value) {
            data_set($rows, $key, $value);
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $overrides */
    private function revisi(User $actor, array $overrides = []): TestResponse
    {
        return $this->actingAs($actor)->post("/jadwal/{$this->jadwal->id}/revisi-jendela",
            [...['expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => self::ALASAN, 'periode' => $this->jendela()], ...$overrides]);
    }

    /** @return array<string, array<string, string>> */
    private function jendelaTersimpan(): array
    {
        return PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->get()->mapWithKeys(fn (PeriodeJadwal $row): array => [$row->periode_id => [
            'pengisian_mulai' => $row->pengisian_mulai->toDateString(), 'pengisian_selesai' => $row->pengisian_selesai->toDateString(),
        ]])->all();
    }

    public function test_revisi_dalam_sesi_membuka_jendela_periode_lingkup_tanpa_mengubah_penutupan_dan_sesi(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $tetap = $this->jadwal->only(['status', 'penutupan', 'closed_at', 'activated_at', 'koreksi_mulai', 'koreksi_sampai', 'lingkup_koreksi']);
        $this->actingAs($perencana)->get("/jadwal/{$this->jadwal->id}")->assertInertia(fn (Assert $page) => $page->where('can.revise_windows', true));

        // Batas sesi inklusif per tanggal bisnis: hari pembukaan sampai hari akhir sesi.
        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_mulai' => '2027-01-25', '0.pengisian_selesai' => '2027-02-10'])])
            ->assertSessionHasNoErrors()->assertRedirect("/jadwal/{$this->jadwal->id}");

        $fresh = $this->jadwal->fresh();
        $this->assertEquals($tetap, $fresh->only(array_keys($tetap)));
        $this->assertSame($this->jadwal->revisi + 1, $fresh->revisi);
        $this->assertSame(['pengisian_mulai' => '2027-01-25', 'pengisian_selesai' => '2027-02-10'], $this->jendelaTersimpan()[$this->tw1->id]);
        $audit = AuditLog::where('tindakan', 'jadwal.revisi_jendela')->sole();
        $this->assertSame(self::ALASAN, $audit->alasan);
        $this->assertSame('2026-04-07', collect($audit->nilai_lama['periode'])->firstWhere('periode_id', $this->tw1->id)['pengisian_selesai']);
        $this->assertSame('2027-02-10', collect($audit->nilai_baru['periode'])->firstWhere('periode_id', $this->tw1->id)['pengisian_selesai']);
        // Penutupan asli dan auditnya tetap; revisi tidak menutup atau membuka ulang jadwal.
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tutup')->count());
    }

    public function test_batas_di_luar_sesi_dan_periode_di_luar_lingkup_ditolak_tanpa_perubahan(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $sebelum = $this->jendelaTersimpan();

        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_mulai' => '2027-01-24', '0.pengisian_selesai' => '2027-02-01'])])
            ->assertSessionHasErrors('periode.0.pengisian_mulai');
        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_mulai' => '2027-01-26', '0.pengisian_selesai' => '2027-02-11'])])
            ->assertSessionHasErrors('periode.0.pengisian_selesai');
        // Perpanjangan tanggal selesai saja menyisakan mulai sebelum sesi: bukan jendela di dalam sesi.
        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_selesai' => '2027-02-01'])])
            ->assertSessionHasErrors('periode.0.pengisian_mulai');
        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_mulai' => '2027-02-05', '0.pengisian_selesai' => '2027-02-01'])])
            ->assertSessionHasErrors('periode.0.pengisian_selesai');
        $this->revisi($perencana, ['periode' => $this->jendela(['1.pengisian_mulai' => '2027-01-26', '1.pengisian_selesai' => '2027-02-01'])])
            ->assertSessionHasErrors('periode.1.periode_id');

        $this->assertSame($sebelum, $this->jendelaTersimpan());
        $this->assertSame($this->jadwal->revisi, $this->jadwal->fresh()->revisi);
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());
        $this->assertSame(5, AuditLog::where('tindakan', 'jadwal.revisi_jendela_ditolak')->where('nilai_baru->alasan_penolakan', 'aturan_jadwal')->count());
    }

    public function test_sesi_berakhir_atau_ditutup_ulang_menolak_revisi(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $baru = ['periode' => $this->jendela(['0.pengisian_mulai' => '2027-01-26', '0.pengisian_selesai' => '2027-02-01'])];
        $pesan = 'Revisi jendela PIC hanya untuk jadwal aktif yang belum melewati penutupan atau di dalam sesi koreksi yang berjalan.';

        $this->travelTo(Carbon::parse('2027-02-10 17:01:00', 'Asia/Makassar'));
        $this->revisi($perencana, $baru)->assertSessionHasErrors(['jadwal' => $pesan]);
        $this->actingAs($perencana)->get("/jadwal/{$this->jadwal->id}")->assertInertia(fn (Assert $page) => $page->where('can.revise_windows', false));

        $this->travelTo(Carbon::parse('2027-01-26 09:00:00', 'Asia/Makassar'));
        app(TutupJadwal::class)->handle($this->activator(['jadwal:tutup']), $this->jadwal->id, ['expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Koreksi selesai.']);
        $this->revisi($perencana, $baru)->assertSessionHasErrors(['jadwal' => $pesan]);

        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());
    }
}
