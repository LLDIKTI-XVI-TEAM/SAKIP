<?php

namespace Tests\Feature\Pengukuran;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DaftarKewajibanPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw1;

    private PeriodeJadwal $tw2;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $periode = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->tw2 = PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id,
            'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15']);
    }

    /** Baris satu indikator, dikunci per periode. */
    private function baris(string $indikatorId): Collection
    {
        return app(DaftarKewajibanPengukuran::class)
            ->query([$this->tw1->id, $this->tw2->id], DB::table('unit')->whereRaw('false')->select('id'), '2026-06-20')->get()
            ->where('indikator_id', $indikatorId)->keyBy('periode_id');
    }

    public function test_satu_baris_per_indikator_untuk_setiap_periode_yang_diminta(): void
    {
        $rows = $this->baris($this->pengukuran->indikator_id);

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing([$this->tw1->periode_id, $this->tw2->periode_id], $rows->keys()->all());
        $this->assertSame($this->pengukuran->id, $rows[$this->tw1->periode_id]->pengukuran_id);
        $this->assertNull($rows[$this->tw2->periode_id]->pengukuran_id);
    }

    public function test_periode_sebelum_periode_mulai_snapshot_berlaku_false(): void
    {
        $indikator = IndikatorKinerja::create([...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'tipe_perhitungan', 'created_by_role']),
            'kode' => 'I-TW2', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id]);
        JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'indikator_id' => $indikator->id, 'periode_mulai_id' => $this->tw2->periode_id]);

        $rows = $this->baris($indikator->id);

        $this->assertCount(2, $rows);
        $this->assertFalse((bool) $rows[$this->tw1->periode_id]->berlaku);
        $this->assertTrue((bool) $rows[$this->tw2->periode_id]->berlaku);
    }

    public function test_indikator_arsip_tanpa_header_tidak_ada_dan_dengan_header_tetap_ada(): void
    {
        IndikatorKinerja::whereKey($this->pengukuran->indikator_id)->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        $rows = $this->baris($this->pengukuran->indikator_id);

        $this->assertSame([$this->tw1->periode_id], $rows->keys()->all());
    }

    public function test_versi_snapshot_koreksi_dipilih_per_periode(): void
    {
        $koreksi = $this->context->replicate();
        $koreksi->fill(['nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'periode_mulai_id' => $this->tw2->periode_id])->save();
        $this->pengukuran->delete();

        $rows = $this->baris($this->pengukuran->indikator_id);

        $this->assertCount(2, $rows);
        $this->assertSame($this->context->id, $rows[$this->tw1->periode_id]->jadwal_snapshot_id);
        $this->assertSame($koreksi->id, $rows[$this->tw2->periode_id]->jadwal_snapshot_id);
    }
}
