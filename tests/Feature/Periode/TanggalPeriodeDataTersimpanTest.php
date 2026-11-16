<?php

namespace Tests\Feature\Periode;

use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

class TanggalPeriodeDataTersimpanTest extends TestCase
{
    use CreatesPengukuranFixture, JadwalFixtures, RefreshDatabase;

    private const TABEL = ['jadwal_tahunan', 'jadwal_periode', 'kegiatan', 'pengukuran_kinerjas'];

    /** @return array<string, list<array<string, mixed>>> */
    private function dataTersimpan(): array
    {
        return collect(self::TABEL)->mapWithKeys(fn (string $tabel): array => [
            $tabel => DB::table($tabel)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ])->all();
    }

    public function test_ubah_tanggal_periode_tidak_mengubah_jadwal_kegiatan_dan_pengukuran_tersimpan(): void
    {
        // Fixture memuat jadwal aktif 2026 dan pengukuran Triwulan I; ditambah draf 2027, kegiatan, dan periode final.
        $tw1 = Periode::findOrFail($this->pengukuran->periode_id);
        Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true,
            'bulan_mulai' => 10, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31]);
        Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $tw1->id, 'nama' => 'Kegiatan Triwulan I',
            'tanggal_rencana' => '2026-02-15', 'anggaran' => 0, 'created_by' => $this->actor->id]);
        $draf = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2027, 'penutupan' => '2028-01-19', 'status' => 'draft']);
        PeriodeJadwal::create(['jadwal_id' => $draf->id, 'periode_id' => $tw1->id, 'pengisian_mulai' => '2027-03-01', 'pengisian_selesai' => '2027-03-20']);
        $sebelum = $this->dataTersimpan();
        $this->assertSame([2, 2, 1, 1], array_map('count', array_values($sebelum)));

        // Triwulan I diperpanjang sampai April: batas awal pengisian bergeser ke 1 April, sehingga jendela 1 Maret yang tersimpan kini di luar batas.
        $this->actingAs($this->calendarActor(['periode:update']))->put("/periode/$tw1->id", [
            'nama' => $tw1->nama, 'urutan' => $tw1->urutan, 'aktif' => true, 'is_nilai_akhir' => false, 'revisi' => $tw1->revisi,
            'bulan_mulai' => 1, 'hari_mulai' => 1, 'bulan_selesai' => 4, 'hari_selesai' => 30,
        ])->assertSessionHasNoErrors()->assertRedirect('/periode');

        $this->assertEquals(['bulan_mulai' => 1, 'hari_mulai' => 1, 'bulan_selesai' => 4, 'hari_selesai' => 30],
            (array) DB::table('periode')->where('id', $tw1->id)->first(['bulan_mulai', 'hari_mulai', 'bulan_selesai', 'hari_selesai']));
        $this->assertEquals($sebelum, $this->dataTersimpan());
    }
}
