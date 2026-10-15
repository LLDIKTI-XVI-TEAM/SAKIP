<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Gerbang minimal satu kegiatan tahap RA saat pengajuan (Q42 §42.1 butir 3),
 * pengecualian Q46 §46.1, dan pembekuan klaim ke snapshot versi.
 */
class RencanaAksiKegiatanPengajuanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    private const PESAN = 'Rencana aksi wajib memiliki minimal satu kegiatan yang diklaim pada tahap rencana aksi.';

    public function test_pic_tanpa_kegiatan_ditolak_dan_halaman_menampilkan_alasan(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');

        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.pengajuan.siap', false)
                ->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => collect($alasan)->contains(self::PESAN)));
    }

    public function test_klaim_dibekukan_ke_snapshot_versi_dan_tidak_ikut_berubah(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $fixture['periode2']->id,
            'nama' => 'Bimbingan teknis', 'tujuan' => 'Tujuan beku', 'created_by' => $fixture['pic']->id]);
        $klaim = KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'mengurangi',
            'catatan' => 'Catatan klaim', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $fixture['pic']->id, 'created_at' => now()]);

        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $kegiatan->update(['nama' => 'Nama berubah setelah diajukan']);

        $this->assertEquals([[
            'id' => $klaim->id, 'kegiatan_id' => $kegiatan->id, 'komponen_id' => null, 'arah_dampak' => 'mengurangi', 'catatan' => 'Catatan klaim',
            'periode_id' => $fixture['periode2']->id,
            'kegiatan' => ['nama' => 'Bimbingan teknis', 'tujuan' => 'Tujuan beku', 'anggaran' => null, 'status' => 'rencana'],
        ]], RencanaAksiVersi::sole()->snapshot['klaim']);
    }

    public function test_data_awal_2026_lewat_jalur_perencanaan_dikecualikan(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');

        $this->ajukan($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $versi = RencanaAksiVersi::sole();
        $this->assertSame('perencanaan', $versi->jalur_pengajuan);
        $this->assertSame([], $versi->snapshot['klaim']);
    }

    public function test_ra_dengan_seluruh_periode_efektif_lampau_dikecualikan(): void
    {
        // Seluruh pengisian selesai 31 Maret, sebelum aktivasi 5 April: semua periode lampau saat aktivasi.
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-04-05 09:00:00');

        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $this->assertSame('pic', RencanaAksiVersi::sole()->jalur_pengajuan);
    }

    public function test_klaim_yang_tidak_cocok_dengan_konteks_beku_terbaru_menolak_pengajuan(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        // Periode di luar jadwal RA: klaim tidak lagi sah terhadap periode efektif beku.
        $asing = Periode::create(['nama' => 'Periode Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $asing->id,
            'nama' => 'Kegiatan basi', 'created_by' => $fixture['pic']->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $fixture['pic']->id, 'created_at' => now()]);

        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors('pengajuan');
        $this->assertStringContainsString('Kegiatan basi', session('errors')->first('pengajuan'));
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_kesiapan_halaman_menampilkan_klaim_yang_tidak_cocok_dan_review_menampilkan_klaim_beku(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $asing = Periode::create(['nama' => 'Periode Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);
        $basi = $this->klaimKegiatan($fixture, $header, $asing->id, 'Kegiatan basi');
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.siap', false)
            ->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => collect($alasan)->contains(fn ($teks) => str_contains($teks, 'Kegiatan basi'))));

        KlaimKegiatan::whereKey($basi->id)->delete();
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Bimtek terklaim');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}/review")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.klaim.0.nama', 'Bimtek terklaim')
            ->where('rencanaAksi.klaim.0.periode_nama', 'Triwulan I'));
    }

    public function test_pic_tidak_dapat_mengajukan_perubahan_daftar_kegiatan_periode_terkunci_dibanding_versi_sah(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        DB::table('rencana_aksi_versi')->where('rencana_aksi_id', $header->id)->update(['disahkan_by' => $fixture['perencanaan']->id, 'disahkan_at' => now()]);
        $header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);

        // Periode I terkunci bagi PIC; tanpa perubahan daftar kegiatan pengajuan PIC tetap lolos gerbang kunci.
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Ditambah Perencanaan');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors('jendela');

        $this->ajukan($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame(2, RencanaAksiVersi::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_klaim_ditolak_bila_unit_snapshot_terbaru_tidak_selaras_dengan_header(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $unitBaru = Unit::create(['nama' => 'Unit Pindahan', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $this->terbitkanSnapshotKoreksi($fixture, ['unit_id' => $unitBaru->id]);
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $fixture['periode1']->id,
            'nama' => 'Kegiatan unit lama', 'created_by' => $fixture['perencanaan']->id]);

        $this->actingAs($fixture['perencanaan'])->post("/kegiatan/{$kegiatan->id}/klaim", ['rencana_aksi_id' => $header->id, 'arah_dampak' => 'menambah'])
            ->assertSessionHasErrors('rencana_aksi_id');
        $this->assertSame(0, KlaimKegiatan::where('rencana_aksi_id', $header->id)->count());
    }

    /**
     * Draf manual lengkap tanpa klaim tahap RA, dengan waktu aktivasi jadwal
     * ditetapkan eksplisit agar status periode lampau tidak bergantung tanggal run.
     *
     * @return array{0: array<string, mixed>, 1: RencanaAksi}
     */
    private function drafTanpaKegiatan(string $aktivasi): array
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => $aktivasi]);
        $header = $this->drafManual($fixture);
        KlaimKegiatan::where('rencana_aksi_id', $header->id)->delete();

        return [$fixture, $header];
    }

    /** @param array<string, mixed> $fixture */
    private function klaimKegiatan(array $fixture, RencanaAksi $header, string $periodeId, string $nama): KlaimKegiatan
    {
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $periodeId, 'nama' => $nama, 'created_by' => $fixture['pic']->id]);

        return KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $fixture['pic']->id, 'created_at' => now()]);
    }

    private function ajukan(User $aktor, RencanaAksi $header): TestResponse
    {
        return $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $header->fresh()->versi]);
    }
}
