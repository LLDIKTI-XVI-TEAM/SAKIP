<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Jadwal\TutupJadwal;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class JadwalRevisiJendelaTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    private const ALASAN = 'Tenggat TW I diperpanjang karena gangguan jaringan unit.';

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
            'periode' => [...$base['periode'], ['periode_revisi' => $this->tw1->revisi, ...$this->jendela()[0]]]], $this->ready['jadwal']);
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $this->jadwal = $this->ready['jadwal']->fresh();
    }

    /**
     * Jendela tersimpan saat ini, urut tampilan (TW I lalu TW IV); `$ubah` memakai notasi titik `0.pengisian_selesai`.
     *
     * @param  array<string, string>  $ubah
     * @return list<array<string, string>>
     */
    private function jendela(array $ubah = []): array
    {
        $rows = [
            ['periode_id' => $this->tw1->id, 'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-07', 'reviu_mulai' => '2026-04-08', 'reviu_selesai' => '2026-04-15'],
            ['periode_id' => $this->ready['periode']->id, 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11', 'reviu_mulai' => '2027-01-12', 'reviu_selesai' => '2027-01-18'],
        ];
        foreach ($ubah as $key => $value) {
            data_set($rows, $key, $value);
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $overrides */
    private function revisi(User $actor, array $overrides = [], ?string $jadwalId = null): TestResponse
    {
        return $this->actingAs($actor)->post('/jadwal/'.($jadwalId ?? $this->jadwal->id).'/revisi-jendela',
            [...['expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => self::ALASAN, 'periode' => $this->jendela()], ...$overrides]);
    }

    private function penolakan(string $alasan): int
    {
        return AuditLog::where('tindakan', 'jadwal.revisi_jendela_ditolak')->where('nilai_baru->alasan_penolakan', $alasan)->count();
    }

    /** @return array<string, array<string, string>> */
    private function jendelaTersimpan(): array
    {
        return PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->get()->mapWithKeys(fn (PeriodeJadwal $row): array => [$row->periode_id => [
            'pengisian_mulai' => $row->pengisian_mulai->toDateString(), 'pengisian_selesai' => $row->pengisian_selesai->toDateString(),
            'reviu_mulai' => $row->reviu_mulai->toDateString(), 'reviu_selesai' => $row->reviu_selesai->toDateString(),
        ]])->all();
    }

    public function test_perpanjangan_memperbarui_jendela_menaikkan_revisi_dan_mengaudit_batas_lama_baru(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $snapshot = $this->snapshotFingerprint();

        // Kiriman tanpa perubahan tanggal tidak menaikkan revisi dan tidak menulis audit.
        $this->revisi($perencana)->assertSessionHasNoErrors();
        $this->assertSame($this->jadwal->revisi, $this->jadwal->fresh()->revisi);
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());

        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_selesai' => '2026-04-12', '0.reviu_mulai' => '2026-04-13', '0.reviu_selesai' => '2026-04-20'])])
            ->assertSessionHasNoErrors()->assertRedirect("/jadwal/{$this->jadwal->id}");

        $fresh = $this->jadwal->fresh();
        $this->assertSame('aktif', $fresh->status);
        $this->assertSame($this->jadwal->revisi + 1, $fresh->revisi);
        $this->assertSame($this->jadwal->penutupan->toDateString(), $fresh->penutupan->toDateString());
        $this->assertSame(['pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-12', 'reviu_mulai' => '2026-04-13', 'reviu_selesai' => '2026-04-20'], $this->jendelaTersimpan()[$this->tw1->id]);
        $this->assertSame(array_diff_key($this->jendela()[1], ['periode_id' => true]), $this->jendelaTersimpan()[$this->ready['periode']->id]);
        $this->assertSame($snapshot, $this->snapshotFingerprint());

        $audit = AuditLog::where('tindakan', 'jadwal.revisi_jendela')->sole();
        $this->assertSame($perencana->id, $audit->actor_id);
        $this->assertSame($this->jadwal->id, $audit->objek_id);
        $this->assertSame('jadwal:update', $audit->dasar_izin['permission']);
        $this->assertSame(self::ALASAN, $audit->alasan);
        $this->assertSame($this->jadwal->revisi, $audit->nilai_lama['revisi']);
        $this->assertSame($fresh->revisi, $audit->nilai_baru['revisi']);
        $lama = collect($audit->nilai_lama['periode'])->firstWhere('periode_id', $this->tw1->id);
        $baru = collect($audit->nilai_baru['periode'])->firstWhere('periode_id', $this->tw1->id);
        $this->assertSame('2026-04-07', $lama['pengisian_selesai']);
        $this->assertSame('2026-04-12', $baru['pengisian_selesai']);
        $this->assertCount(2, $audit->nilai_baru['periode']);
    }

    public function test_revisi_menerima_pengisian_pertama_awal_tahun(): void
    {
        // Jadwal tidak lagi memuat jendela Rencana Aksi, jadi pengisian pertama boleh dimulai di awal tahun.
        $this->revisi($this->activator(['jadwal:update']), ['periode' => $this->jendela(['0.pengisian_mulai' => '2026-01-10'])])->assertSessionHasNoErrors();

        $this->assertSame('2026-01-10', $this->jendelaTersimpan()[$this->tw1->id]['pengisian_mulai']);
    }

    public function test_pelanggaran_kalender_ditolak_per_kolom_tanpa_perubahan_parsial(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $sebelum = $this->jendelaTersimpan();

        // Perubahan sah TW I ikut dikirim agar terbukti tidak tersimpan sebagian.
        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_selesai' => '2026-04-06', '1.pengisian_mulai' => '2026-04-05'])])
            ->assertSessionHasErrors('periode.1.pengisian_mulai');
        $this->revisi($perencana, ['periode' => $this->jendela(['0.pengisian_selesai' => '2026-03-31'])])
            ->assertSessionHasErrors('periode.0.pengisian_selesai');
        $this->revisi($perencana, ['periode' => $this->jendela(['1.reviu_selesai' => '2027-01-19'])])
            ->assertSessionHasErrors('penutupan');

        $this->assertSame($sebelum, $this->jendelaTersimpan());
        $this->assertSame($this->jadwal->revisi, $this->jadwal->fresh()->revisi);
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());
        $this->assertSame(3, $this->penolakan('aturan_jadwal'));
    }

    public function test_daftar_periode_wajib_sama_dengan_anggota_jadwal(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $asing = Periode::create(['nama' => 'Semester Asing', 'urutan' => 7, 'aktif' => true, 'is_nilai_akhir' => false]);

        $this->revisi($perencana, ['periode' => [$this->jendela()[0]]])->assertSessionHasErrors('periode');
        $this->revisi($perencana, ['periode' => [...$this->jendela(), [...$this->jendela()[0], 'periode_id' => $asing->id]]])->assertSessionHasErrors('periode');
        $this->revisi($perencana, ['periode' => $this->jendela(['1.periode_id' => $asing->id])])->assertSessionHasErrors('periode');

        $this->assertEqualsCanonicalizing([$this->tw1->id, $this->ready['periode']->id], array_keys($this->jendelaTersimpan()));
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());
    }

    public function test_alasan_kosong_revisi_basi_dan_field_asing_ditolak_dengan_audit(): void
    {
        $perencana = $this->activator(['jadwal:update']);

        $this->revisi($perencana, ['alasan' => '   '])->assertSessionHasErrors('alasan');
        $this->revisi($perencana, ['expected_revisi' => $this->jadwal->revisi - 1])->assertSessionHasErrors(['jadwal' => 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum merevisi jendela.']);
        $this->revisi($perencana, ['penutupan' => '2027-02-01'])->assertSessionHasErrors('jadwal');

        $this->assertSame($this->jadwal->revisi, $this->jadwal->fresh()->revisi);
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());
        $this->assertSame(2, $this->penolakan('input_tidak_valid'));
        $this->assertSame(1, $this->penolakan('aturan_jadwal'));
    }

    public function test_jadwal_draft_ditutup_atau_lewat_penutupan_ditolak_dengan_audit(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $draft = JadwalTahunan::create(['renstra_id' => $this->ready['renstra']->id, 'tahun' => 2027, 'penutupan' => '2028-01-31', 'status' => 'draft']);
        $pesan = 'Revisi jendela PIC hanya untuk jadwal aktif yang belum melewati penutupan.';

        $this->revisi($perencana, ['expected_revisi' => 1], $draft->id)->assertSessionHasErrors(['jadwal' => $pesan]);
        $this->travelTo(now()->setDate(2027, 1, 20));
        $this->revisi($perencana)->assertSessionHasErrors(['jadwal' => $pesan]);
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));
        app(TutupJadwal::class)->handle($this->activator(['jadwal:tutup']), $this->jadwal->id, ['expected_revisi' => $this->jadwal->revisi, 'alasan' => 'Tahun ditutup.']);
        $this->revisi($perencana)->assertSessionHasErrors(['jadwal' => $pesan]);

        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());
        $this->assertSame(3, $this->penolakan('aturan_jadwal'));
    }

    public function test_tanpa_izin_dan_deny_ditolak_403_dengan_audit(): void
    {
        $this->revisi($this->activator(['jadwal:aktivasi']))->assertForbidden();
        $denied = $this->activator(['jadwal:update']);
        $this->denyPermission($denied, 'jadwal:update');
        $this->revisi($denied)->assertForbidden();

        $this->assertSame($this->jadwal->revisi, $this->jadwal->fresh()->revisi);
        $this->assertSame(2, $this->penolakan('izin_ditolak'));
        // Kontrol positif: pemegang izin yang sah tetap dapat merevisi.
        $this->revisi($this->activator(['jadwal:update']), ['periode' => $this->jendela(['0.reviu_selesai' => '2026-04-16'])])->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.revisi_jendela')->count());
    }

    public function test_editor_menawarkan_revisi_hanya_untuk_pemegang_izin_pada_jadwal_aktif(): void
    {
        $perencana = $this->activator(['jadwal:update']);
        $url = "/jadwal/{$this->jadwal->id}";

        $this->actingAs($perencana)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.revise_windows', true));
        $this->actingAs($this->activator(['jadwal:aktivasi']))->get($url)->assertInertia(fn (Assert $page) => $page->where('can.revise_windows', false));
        app(TutupJadwal::class)->handle($this->activator(['jadwal:tutup']), $this->jadwal->id, ['expected_revisi' => $this->jadwal->revisi, 'alasan' => 'Tahun ditutup.']);
        $this->actingAs($perencana)->get($url)->assertInertia(fn (Assert $page) => $page->where('can.revise_windows', false));
    }
}
