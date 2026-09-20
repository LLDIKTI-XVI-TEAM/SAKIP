<?php

namespace Tests\Unit;

use App\Actions\Pengukuran\CalculatePengukuran;
use PHPUnit\Framework\TestCase;

class CalculatePengukuranTest extends TestCase
{
    public function test_manual_preserves_null_and_zero_as_different_states(): void
    {
        $engine = new CalculatePengukuran;
        $this->assertSame(['nilai' => null, 'status_perhitungan' => 'belum_diisi', 'sumber_nilai' => 'manual'], $engine->handle('manual', 2, [], [], null));
        $this->assertSame(['nilai' => 0.0, 'status_perhitungan' => 'terhitung', 'sumber_nilai' => 'manual'], $engine->handle('manual', 2, [], [], 0));
    }

    public function test_ratio_uses_weighted_snapshot_components(): void
    {
        $definitions = [['komponen_id' => 'a', 'peran' => 'pembilang', 'bobot' => 2], ['komponen_id' => 'b', 'peran' => 'pembilang', 'bobot' => 1], ['komponen_id' => 't', 'peran' => 'penyebut', 'bobot' => 1]];
        $result = (new CalculatePengukuran)->handle('rasio_persen', 2, $definitions, ['a' => 10, 'b' => 5, 't' => 40], null);
        $this->assertSame(62.5, $result['nilai']);
    }

    public function test_complete_zero_denominator_is_not_incomplete_or_numeric_zero(): void
    {
        $definitions = [['komponen_id' => 'n', 'peran' => 'pembilang', 'bobot' => 1], ['komponen_id' => 't', 'peran' => 'penyebut', 'bobot' => 1]];
        $engine = new CalculatePengukuran;
        $this->assertSame('belum_diisi', $engine->handle('rasio_persen', 2, $definitions, ['n' => 5], null)['status_perhitungan']);
        $this->assertSame(['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung', 'sumber_nilai' => 'komponen'], $engine->handle('rasio_persen', 2, $definitions, ['n' => 5, 't' => 0], null));
    }

    public function test_iku_three_is_five_weighted_terms_without_double_weighting(): void
    {
        $keys = ['perencanaan_kinerja', 'pengukuran_kinerja', 'pelaporan_kinerja', 'evaluasi_internal', 'zi'];
        $definitions = array_map(fn ($key) => ['komponen_id' => $key, 'peran' => 'penjumlah', 'bobot' => 0.5], $keys);
        $engine = new CalculatePengukuran;
        $this->assertSame(76.25, $engine->handle('penjumlahan', 2, $definitions, array_combine($keys, [23, 24, 11.5, 19, 75]), null)['nilai']);
        $this->assertSame(66.395, $engine->handle('penjumlahan', 3, $definitions, array_combine($keys, [23.1, 24.6, 11.55, 20.5, 53.04]), null)['nilai']);
    }

    public function test_derived_value_exceeding_storage_is_rejected_before_database_write(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculatePengukuran)->handle('rasio_persen', 2, [['komponen_id' => 'n', 'peran' => 'pembilang', 'bobot' => 1], ['komponen_id' => 't', 'peran' => 'penyebut', 'bobot' => 1]], ['n' => 1e17, 't' => 1], null);
    }
}
