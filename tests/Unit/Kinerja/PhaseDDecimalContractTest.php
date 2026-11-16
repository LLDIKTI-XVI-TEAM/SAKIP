<?php

namespace Tests\Unit\Kinerja;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Services\Kinerja\IndikatorPerhitunganService;
use Tests\TestCase;

class PhaseDDecimalContractTest extends TestCase
{
    public function test_formula_tidak_menghapus_bobot_hampir_satu_dan_transport_eksak(): void
    {
        $indikator = new IndikatorKinerja(['tipe_perhitungan' => 'penjumlahan']);
        $indikator->setRelation('komponen', collect([new IndikatorKomponen([
            'kode' => 'a', 'label' => 'A', 'peran' => 'penjumlah', 'bobot' => '0.999999999999', 'urutan' => 1, 'aktif' => true,
        ])]));
        $contract = (new IndikatorPerhitunganService)->getFormulaContract($indikator);
        $this->assertSame('0.999999999999', $contract['komponen_list'][0]['bobot']);
        $this->assertStringContainsString('0.999999999999', $contract['formula_text']);
    }
}
