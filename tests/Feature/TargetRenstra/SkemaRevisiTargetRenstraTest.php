<?php

namespace Tests\Feature\TargetRenstra;

use App\Models\Renstra;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class SkemaRevisiTargetRenstraTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_kolom_penyimpanan_terisi_jika_dan_hanya_jika_revisi_disimpan(): void
    {
        $this->insertRevisi(['status' => 'draf']);
        $this->insertRevisi(['status' => 'draf', 'renstra_id' => $this->renstraLain(), 'justifikasi' => 'Draf justifikasi ikut tersimpan otomatis.']);
        $this->insertRevisi($this->penyimpanan(1));
        $this->assertDatabaseCount('revisi_target_renstra', 3);

        foreach ([
            array_replace($this->penyimpanan(2), ['nomor' => null]),
            array_replace($this->penyimpanan(2), ['nomor' => 0]),
            array_replace($this->penyimpanan(2), ['justifikasi' => null]),
            array_replace($this->penyimpanan(2), ['justifikasi' => "  \n "]),
            array_replace($this->penyimpanan(2), ['disimpan_oleh' => null]),
            array_replace($this->penyimpanan(2), ['disimpan_pada' => null]),
            ['status' => 'draf', 'renstra_id' => $this->renstraLain('R-DUA'), 'nomor' => 1],
            ['status' => 'draf', 'renstra_id' => $this->renstraLain('R-TIGA'), 'disimpan_oleh' => $this->actor->id],
            ['status' => 'draf', 'renstra_id' => $this->renstraLain('R-EMPAT'), 'versi' => 0],
            ['status' => 'batal', 'renstra_id' => $this->renstraLain('R-LIMA')],
        ] as $invalid) {
            $this->assertSqlState('23514', fn () => $this->insertRevisi($invalid));
        }
        $this->assertDatabaseCount('revisi_target_renstra', 3);
    }

    public function test_satu_draf_per_renstra_dan_nomor_revisi_unik_per_renstra(): void
    {
        $this->insertRevisi(['status' => 'draf']);
        $this->assertSqlState('23505', fn () => $this->insertRevisi(['status' => 'draf']));

        $this->insertRevisi($this->penyimpanan(1));
        $this->assertSqlState('23505', fn () => $this->insertRevisi($this->penyimpanan(1)));
        $this->insertRevisi($this->penyimpanan(2));
        $lain = $this->renstraLain();
        $this->insertRevisi(['status' => 'draf', 'renstra_id' => $lain]);
        $this->insertRevisi(array_replace($this->penyimpanan(1), ['renstra_id' => $lain]));

        $this->assertDatabaseCount('revisi_target_renstra', 5);
    }

    #[DataProvider('nilaiTidakSah')]
    public function test_nilai_lama_dan_baru_mengikuti_domain_q33(string $kolom, string $nilai, string $sqlState): void
    {
        $revisi = $this->insertRevisi(['status' => 'draf']);

        try {
            DB::transaction(fn () => $this->insertNilai($revisi, 2026, [$kolom => $nilai]));
            $this->fail('Basis data harus menolak nilai negatif dan non-finite.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        }
        $this->assertSame(0, DB::table('revisi_target_renstra_nilai')->count());
    }

    /** @return list<array{string, string, string}> */
    public static function nilaiTidakSah(): array
    {
        $kasus = [];
        foreach (['nilai_lama', 'nilai_baru'] as $kolom) {
            array_push($kasus, [$kolom, '-1', '23514'], [$kolom, 'NaN', '23514'], [$kolom, 'Infinity', '22003']);
        }

        return $kasus;
    }

    public function test_nilai_menerima_null_nol_dan_satu_baris_per_indikator_tahun_dalam_satu_revisi(): void
    {
        $revisi = $this->insertRevisi(['status' => 'draf']);
        $this->insertNilai($revisi, 2026, ['nilai_lama' => null, 'nilai_baru' => '0']);
        $this->insertNilai($revisi, 2027, ['nilai_lama' => '150.5', 'nilai_baru' => null]);
        $this->assertSqlState('23505', fn () => $this->insertNilai($revisi, 2026, ['nilai_baru' => '1']));

        $revisiLain = $this->insertRevisi($this->penyimpanan(1));
        $this->insertNilai($revisiLain, 2026, ['nilai_lama' => '0', 'nilai_baru' => '1']);

        $this->assertDatabaseCount('revisi_target_renstra_nilai', 3);
    }

    public function test_membuang_draf_ikut_membuang_nilainya(): void
    {
        $draf = $this->insertRevisi(['status' => 'draf']);
        $tersimpan = $this->insertRevisi($this->penyimpanan(1));
        $this->insertNilai($draf, 2026, ['nilai_baru' => '80']);
        $this->insertNilai($tersimpan, 2026, ['nilai_lama' => '70', 'nilai_baru' => '75']);

        DB::table('revisi_target_renstra')->where('id', $draf)->delete();

        $this->assertSame([$tersimpan], DB::table('revisi_target_renstra_nilai')->pluck('revisi_id')->all());
    }

    public function test_rollback_ditolak_bila_revisi_sudah_tercatat(): void
    {
        $this->insertRevisi(['status' => 'draf']);
        $migration = require database_path('migrations/2026_10_25_000001_create_revisi_target_renstra_table.php');

        try {
            $migration->down();
            $this->fail('Rollback tidak boleh menghapus revisi target Renstra yang sudah tercatat.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('revisi target Renstra', $exception->getMessage());
        }
        $this->assertDatabaseCount('revisi_target_renstra', 1);
    }

    /** @return array<string, mixed> */
    private function penyimpanan(int $nomor): array
    {
        return ['status' => 'disimpan', 'nomor' => $nomor, 'justifikasi' => 'Target terlampaui sebelum akhir periode Renstra.',
            'disimpan_oleh' => $this->actor->id, 'disimpan_pada' => now()];
    }

    private function renstraId(): string
    {
        return DB::table('indikator_kinerjas as i')->join('sasaran_strategis as s', 's.id', '=', 'i.sasaran_strategis_id')
            ->where('i.id', $this->pengukuran->indikator_id)->value('s.renstra_id');
    }

    private function renstraLain(string $kode = 'R-LAIN'): string
    {
        return Renstra::create(['kode' => $kode, 'nama' => "Renstra {$kode}", 'tahun_mulai' => 2030, 'tahun_selesai' => 2034, 'created_by' => $this->actor->id])->id;
    }

    /** @param  array<string, mixed>  $override */
    private function insertRevisi(array $override): string
    {
        $id = (string) Str::uuid();
        DB::table('revisi_target_renstra')->insert(array_replace([
            'id' => $id, 'renstra_id' => $this->renstraId(), 'dibuat_oleh' => $this->actor->id, 'created_at' => now(), 'updated_at' => now(),
        ], $override));

        return $id;
    }

    /** @param  array<string, mixed>  $override */
    private function insertNilai(string $revisiId, int $tahun, array $override): void
    {
        DB::table('revisi_target_renstra_nilai')->insert(array_replace([
            'id' => (string) Str::uuid(), 'revisi_id' => $revisiId, 'indikator_id' => $this->pengukuran->indikator_id, 'tahun' => $tahun,
        ], $override));
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
