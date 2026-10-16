<?php

namespace Tests\Feature\Rekomendasi;

use App\Models\Periode;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class SkemaRekomendasiPimpinanTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_kolom_persetujuan_terisi_jika_dan_hanya_jika_disetujui(): void
    {
        $this->insertRekomendasi(['status' => 'draf']);
        $this->insertRekomendasi($this->persetujuan());
        $this->assertDatabaseCount('rekomendasi_pimpinan', 2);

        foreach ([
            ['status' => 'disetujui'],
            array_replace($this->persetujuan(), ['disetujui_oleh_role' => null]),
            array_replace($this->persetujuan(), ['disetujui_pada' => null]),
            ['status' => 'draf', 'disetujui_oleh' => $this->actor->id],
            ['status' => 'draf', 'disetujui_pada' => now()],
            ['status' => 'ditolak'],
        ] as $invalid) {
            $this->assertSqlState('23514', fn () => $this->insertRekomendasi($invalid));
        }
        $this->assertDatabaseCount('rekomendasi_pimpinan', 2);
    }

    public function test_hanya_satu_draf_terbuka_per_indikator_tahun_periode_sedangkan_riwayat_disetujui_boleh_banyak(): void
    {
        $this->insertRekomendasi(['status' => 'draf']);
        $this->assertSqlState('23505', fn () => $this->insertRekomendasi(['status' => 'draf']));

        $this->insertRekomendasi($this->persetujuan());
        $this->insertRekomendasi($this->persetujuan());
        $periodeLain = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->insertRekomendasi(['status' => 'draf', 'periode_id' => $periodeLain->id]);
        $this->insertRekomendasi(['status' => 'draf', 'tahun' => 2027]);

        $this->assertDatabaseCount('rekomendasi_pimpinan', 5);
    }

    public function test_draf_dapat_diubah_dan_disetujui_tetapi_baris_disetujui_final(): void
    {
        $id = $this->insertRekomendasi(['status' => 'draf']);
        $rows = DB::table('rekomendasi_pimpinan')->where('id', $id);
        $rows->update(['isi' => 'Isi draf diperbarui.', 'versi' => 2]);
        $rows->update($this->persetujuan());
        $this->assertDatabaseHas('rekomendasi_pimpinan', ['id' => $id, 'status' => 'disetujui', 'isi' => 'Isi draf diperbarui.']);

        $this->assertSqlState('23514', fn () => DB::table('rekomendasi_pimpinan')->where('id', $id)->update(['isi' => 'Diubah setelah disetujui.']));
        $this->assertSqlState('23514', fn () => DB::table('rekomendasi_pimpinan')->where('id', $id)->delete());
        $this->assertDatabaseHas('rekomendasi_pimpinan', ['id' => $id, 'isi' => 'Isi draf diperbarui.']);
    }

    public function test_rollback_ditolak_bila_rekomendasi_sudah_tercatat(): void
    {
        $this->insertRekomendasi(['status' => 'draf']);
        $migration = require database_path('migrations/2026_10_16_000001_create_rekomendasi_pimpinan_table.php');

        try {
            $migration->down();
            $this->fail('Rollback tidak boleh menghapus rekomendasi yang sudah tercatat.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rekomendasi', $exception->getMessage());
        }
        $this->assertDatabaseCount('rekomendasi_pimpinan', 1);
    }

    /** @return array<string, mixed> */
    private function persetujuan(): array
    {
        return ['status' => 'disetujui', 'disetujui_oleh' => $this->actor->id, 'disetujui_oleh_role' => 'pimpinan', 'disetujui_pada' => now()];
    }

    /** @param  array<string, mixed>  $override */
    private function insertRekomendasi(array $override): string
    {
        $id = (string) Str::uuid();
        DB::table('rekomendasi_pimpinan')->insert(array_replace([
            'id' => $id, 'indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'isi' => 'Perkuat monitoring bulanan.', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
        ], $override));

        return $id;
    }

    private function assertSqlState(string $state, Closure $statement): void
    {
        try {
            DB::transaction($statement);
            $this->fail('Basis data harus menolak pernyataan ini dengan SQLSTATE '.$state.'.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->getCode());
        }
    }
}
