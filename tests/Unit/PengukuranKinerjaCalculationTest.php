<?php

namespace Tests\Unit;

use App\Models\PengukuranKinerja;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PengukuranKinerjaCalculationTest extends TestCase
{
    /**
     * Karakterisasi kalkulasi server existing, bukan bukti seluruh mesin indikator PRD selesai.
     */
    #[DataProvider('capaianCases')]
    public function test_existing_server_calculation(float $target, float $realisasi, string $tipe, float $expected): void
    {
        $this->assertSame($expected, PengukuranKinerja::hitungCapaian($target, $realisasi, $tipe));
    }

    /** @return array<string, array{float, float, string, float}> */
    public static function capaianCases(): array
    {
        return [
            'naik mencapai target' => [70.0, 70.0, 'naik_baik', 100.0],
            'naik melebihi target' => [70.0, 84.0, 'naik_baik', 120.0],
            'naik di bawah target' => [70.0, 35.0, 'naik_baik', 50.0],
            'turun lebih baik' => [8.0, 4.0, 'turun_baik', 150.0],
            'turun mencapai target' => [8.0, 8.0, 'turun_baik', 100.0],
            'turun lebih buruk' => [8.0, 10.0, 'turun_baik', 75.0],
            'turun dibatasi nol' => [8.0, 20.0, 'turun_baik', 0.0],
        ];
    }
}
