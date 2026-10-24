<?php

namespace Tests\Unit\Laporan;

use App\Actions\Laporan\HitungCapaianPeriode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HitungCapaianPeriodeTest extends TestCase
{
    #[DataProvider('contoh')]
    public function test_capaian_memakai_target_periode_positif_tanpa_pembatasan(?string $realisasi, ?string $target, string $arah, ?string $hasil): void
    {
        $this->assertSame($hasil, (new HitungCapaianPeriode)->handle($realisasi, $target, $arah));
    }

    public static function contoh(): array
    {
        return [
            'naik tepat target' => ['10', '10', 'naik_baik', '100.00'],
            'naik melebihi target' => ['15', '10', 'naik_baik', '150.00'],
            'realisasi nol sah' => ['0', '10', 'naik_baik', '0.00'],
            'realisasi negatif sah' => ['-5', '10', 'naik_baik', '-50.00'],
            'turun tepat target' => ['10', '10', 'turun_baik', '100.00'],
            'turun realisasi nol' => ['0', '10', 'turun_baik', '200.00'],
            'turun hasil negatif' => ['25', '10', 'turun_baik', '-50.00'],
            'turun realisasi negatif' => ['-5', '10', 'turun_baik', '250.00'],
            'target nol' => ['10', '0.000', 'naik_baik', null],
            'target negatif naik' => ['-5', '-10', 'naik_baik', null],
            'target negatif turun' => ['-15', '-10', 'turun_baik', null],
            'target kosong' => ['0', null, 'naik_baik', null],
            'realisasi kosong' => [null, '10', 'naik_baik', null],
            'half up positif' => ['2.01', '200', 'naik_baik', '1.01'],
            'half up negatif' => ['402.01', '200', 'turun_baik', '-1.01'],
            'desimal penuh' => ['0.000000000001', '0.000000000003', 'naik_baik', '33.33'],
            'melewati presisi float' => ['9007199254740993', '1', 'naik_baik', '900719925474099300.00'],
        ];
    }
}
