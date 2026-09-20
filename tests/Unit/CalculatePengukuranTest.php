<?php

namespace Tests\Unit;

use App\Actions\Pengukuran\CalculatePengukuran;
use PHPUnit\Framework\TestCase;

class CalculatePengukuranTest extends TestCase
{
    public function test_decimal_inputs_and_rounding_preserve_all_storable_digits(): void
    {
        $engine = new CalculatePengukuran;
        $this->assertSame('9007199254740993.00', $engine->handle('manual', 2, [], [], '9007199254740993')['nilai']);
        $this->assertSame('123456789012345678.123456789012', $engine->handle('manual', 12, [], [], '123456789012345678.123456789012')['nilai']);
        $this->assertSame('-1.01', $engine->handle('manual', 2, [], [], '-1.005')['nilai']);
        $this->assertSame('0.000000000001', $engine->handle('manual', 12, [], [], '0.0000000000005')['nilai']);
    }

    public function test_weighted_sum_and_ratio_do_not_round_intermediate_values(): void
    {
        $engine = new CalculatePengukuran;
        $sum = [['komponen_id' => 'a', 'peran' => 'penjumlah', 'bobot' => '1'], ['komponen_id' => 'b', 'peran' => 'penjumlah', 'bobot' => '1']];
        $this->assertSame('1.00', $engine->handle('penjumlahan', 2, $sum, ['a' => '9007199254740993', 'b' => '-9007199254740992'], null)['nilai']);
        $ratio = [['komponen_id' => 'n', 'peran' => 'pembilang', 'bobot' => '1'], ['komponen_id' => 't', 'peran' => 'penyebut', 'bobot' => '1']];
        $this->assertSame('9007199254740993.00', $engine->handle('rasio_persen', 2, $ratio, ['n' => '9007199254740993', 't' => '100'], null)['nilai']);
        $this->assertSame('16.67', $engine->handle('rasio_persen', 2, $ratio, ['n' => '1', 't' => '6'], null)['nilai']);
    }

    public function test_manual_preserves_null_and_zero_as_different_states(): void
    {
        $engine = new CalculatePengukuran;
        $this->assertSame(['nilai' => null, 'status_perhitungan' => 'belum_diisi', 'sumber_nilai' => 'manual'], $engine->handle('manual', 2, [], [], null));
        $this->assertSame(['nilai' => '0.00', 'status_perhitungan' => 'terhitung', 'sumber_nilai' => 'manual'], $engine->handle('manual', 2, [], [], 0));
    }

    public function test_ratio_uses_weighted_snapshot_components(): void
    {
        $definitions = [['komponen_id' => 'a', 'peran' => 'pembilang', 'bobot' => 2], ['komponen_id' => 'b', 'peran' => 'pembilang', 'bobot' => 1], ['komponen_id' => 't', 'peran' => 'penyebut', 'bobot' => 1]];
        $result = (new CalculatePengukuran)->handle('rasio_persen', 2, $definitions, ['a' => 10, 'b' => 5, 't' => 40], null);
        $this->assertSame('62.50', $result['nilai']);
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
        $this->assertSame('76.25', $engine->handle('penjumlahan', 2, $definitions, array_combine($keys, [23, 24, 11.5, 19, 75]), null)['nilai']);
        $this->assertSame('66.395', $engine->handle('penjumlahan', 3, $definitions, array_combine($keys, [23.1, 24.6, 11.55, 20.5, 53.04]), null)['nilai']);
    }

    public function test_derived_value_exceeding_storage_is_rejected_before_database_write(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculatePengukuran)->handle('rasio_persen', 2, [['komponen_id' => 'n', 'peran' => 'pembilang', 'bobot' => 1], ['komponen_id' => 't', 'peran' => 'penyebut', 'bobot' => 1]], ['n' => 1e17, 't' => 1], null);
    }

    public function test_rounding_cannot_overflow_the_stored_integer_digits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculatePengukuran)->handle('manual', 0, [], [], '999999999999999999.9');
    }

    public function test_raw_component_cannot_be_silently_rounded_by_storage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculatePengukuran)->handle('penjumlahan', 12, [['komponen_id' => 'a', 'peran' => 'penjumlah', 'bobot' => '1']], ['a' => '0.0000000000005'], null);
    }
}
