<?php

namespace Tests\Feature\Rekomendasi;

use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RenstraPk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class RekomendasiBerlakuTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_draf_tidak_tampil_dan_hanya_rekomendasi_disetujui_terbaru_yang_berlaku_pada_periode_berikutnya(): void
    {
        $tw2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw2->id, 'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15', 'reviu_mulai' => '2026-06-15', 'reviu_selesai' => '2026-07-15']);
        $berikutnya = PengukuranKinerja::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $tw2->id, 'jadwal_snapshot_id' => $this->context->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);
        $tw1 = $this->pengukuran->periode_id;

        $this->rekomendasi(2026, $tw1, 'Draf belum berlaku.');
        $this->assertRekomendasiSebelumnya($berikutnya, null);

        $this->rekomendasi(2026, $tw1, 'Arahan lama.', now()->subDay());
        $baru = $this->rekomendasi(2026, $tw1, 'Arahan terbaru.', now());
        $this->rekomendasi(2026, $tw2->id, 'Rekomendasi periode yang sama tidak dibawa.', now());
        $this->assertRekomendasiSebelumnya($berikutnya, ['id' => $baru, 'isi' => 'Arahan terbaru.', 'tahun' => 2026, 'periode_id' => $tw1]);
        $this->assertRekomendasiSebelumnya($this->pengukuran, null);
    }

    public function test_rekomendasi_periode_terakhir_tahun_sebelumnya_berlaku_pada_periode_pertama_jadwal_berikutnya(): void
    {
        $tw4 = Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        $pk = RenstraPk::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2025, 'nomor_pk' => 'PK-2025', 'tanggal_pk' => '2025-01-01', 'created_by' => $this->actor->id]);
        $lalu = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2025, 'renstra_pk_id' => $pk->id, 'penutupan' => '2025-12-31', 'status' => 'ditutup', 'activated_at' => now()->subYear()]);
        foreach ([$this->pengukuran->periode_id => '2025-03-01', $tw4->id => '2025-12-01'] as $periodeId => $mulai) {
            PeriodeJadwal::create(['jadwal_id' => $lalu->id, 'periode_id' => $periodeId, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => $mulai, 'reviu_mulai' => $mulai, 'reviu_selesai' => $mulai]);
        }
        JadwalSnapshot::create([...$this->context->only(['indikator_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'jadwal_id' => $lalu->id, 'periode_mulai_id' => $this->pengukuran->periode_id]);

        $this->rekomendasi(2025, $this->pengukuran->periode_id, 'Rekomendasi TW I 2025 bukan periode sebelumnya.', now());
        $tw4Lalu = $this->rekomendasi(2025, $tw4->id, 'Rekomendasi TW IV 2025.', now()->subDay());

        $this->assertRekomendasiSebelumnya($this->pengukuran, ['id' => $tw4Lalu, 'isi' => 'Rekomendasi TW IV 2025.', 'tahun' => 2025, 'periode_id' => $tw4->id]);
    }

    /** @param  array<string, mixed>|null  $expected */
    private function assertRekomendasiSebelumnya(PengukuranKinerja $pengukuran, ?array $expected): void
    {
        $this->actingAs($this->actor)->get('/pengukuran/'.$pengukuran->id.'/edit')->assertOk()->assertInertia(fn (AssertableInertia $page) => $expected === null
            ? $page->where('pengukuran.rekomendasi_sebelumnya', null)
            : $page->where('pengukuran.rekomendasi_sebelumnya', fn ($actual) => collect($expected)->every(fn ($value, $key) => $actual[$key] === $value) && is_string($actual['disetujui_pada'])));
    }

    /** Tanpa waktu persetujuan berarti draf. */
    private function rekomendasi(int $tahun, string $periodeId, string $isi, mixed $disetujuiPada = null): string
    {
        $id = (string) Str::uuid();
        DB::table('rekomendasi_pimpinan')->insert(['id' => $id, 'indikator_id' => $this->pengukuran->indikator_id, 'tahun' => $tahun, 'periode_id' => $periodeId, 'isi' => $isi,
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(), ...($disetujuiPada === null ? [] : ['status' => 'disetujui', 'disetujui_oleh' => $this->actor->id, 'disetujui_oleh_role' => 'pimpinan', 'disetujui_pada' => $disetujuiPada])]);

        return $id;
    }
}
