<?php

namespace Tests\Feature\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Konsumen klaim tahap pengukuran kegiatan lanjutan (Q41 §41.4 butir 2): klaim yang dibentuk transisi `ditunda`
 * ikut dibekukan saat pengukuran periode tujuan diajukan dan tampil pada halaman pengukuran.
 */
class KlaimLanjutanPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_klaim_lanjutan_dibekukan_dan_tampil_pada_pengukuran_periode_tujuan(): void
    {
        $awal = Periode::create(['nama' => 'Periode Awal', 'urutan' => 0, 'aktif' => true, 'is_nilai_akhir' => false,
            'bulan_mulai' => 1, 'hari_mulai' => 1, 'bulan_selesai' => 1, 'hari_selesai' => 31]);
        $asal = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $awal->id, 'nama' => 'Bimtek asal',
            'tujuan' => 'Peningkatan kapasitas', 'anggaran' => 0, 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $asal->id, 'sumber_klaim' => 'rencana_aksi',
            'arah_dampak' => 'menambah', 'created_by' => $this->actor->id, 'created_at' => now()]);

        $this->actingAs($this->actor)->post("/kegiatan/{$asal->id}/status", ['status' => 'ditunda', 'justifikasi' => 'Narasumber berhalangan.',
            'periode_tujuan_id' => $this->pengukuran->periode_id, 'expected_state' => $asal->fresh()->versionToken()])->assertSessionHasNoErrors();
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $asal->id)->sole();
        $this->assertSame([$this->pengukuran->id, 'pengukuran'],
            [KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->sole()->pengukuran_id, KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->sole()->sumber_klaim]);

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();

        // Asal berada di periode lain sehingga tidak ikut; lanjutan dibekukan sebagai klaim tahap pengukuran.
        $beku = collect(KinerjaSnapshot::where('pengukuran_id', $this->pengukuran->id)->sole()->snapshot['klaim']);
        $this->assertSame([[$lanjutan->id, 'pengukuran']], $beku->map(fn (array $klaim): array => [$klaim['kegiatan_id'], $klaim['sumber_klaim']])->all());

        $this->actingAs($this->actor)->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertOk()
            ->assertInertia(fn ($page) => $page->has('pengukuran.klaim', 1)
                ->where('pengukuran.klaim.0.sumber_klaim', 'pengukuran')
                ->where('pengukuran.klaim.0.kegiatan.id', $lanjutan->id)
                ->where('pengukuran.klaim.0.kegiatan.nama', 'Bimtek asal'));
    }
}
