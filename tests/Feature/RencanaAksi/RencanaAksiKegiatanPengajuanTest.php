<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function ajukan(User $aktor, RencanaAksi $header): TestResponse
    {
        return $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $header->fresh()->versi]);
    }
}
