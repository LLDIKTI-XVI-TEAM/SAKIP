<?php

namespace Tests\Unit;

use App\Actions\Pengukuran\CalculatePengukuran;
use PHPUnit\Framework\TestCase;

class CalculatePenyebutNolTest extends TestCase
{
    private const RASIO = [['komponen_id' => 'n', 'peran' => 'pembilang', 'bobot' => 1], ['komponen_id' => 't', 'peran' => 'penyebut', 'bobot' => 1]];

    public function test_penyebut_efektif_nol_dengan_komponen_lengkap_tidak_dapat_dihitung(): void
    {
        $engine = new CalculatePengukuran;
        $tdd = ['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung', 'sumber_nilai' => 'komponen'];
        $this->assertSame($tdd, $engine->handle('rasio_persen', 2, self::RASIO, ['n' => 5, 't' => '0.000'], null));
        $this->assertSame($tdd, $engine->handle('rasio_persen', 2, self::RASIO, ['n' => 0, 't' => 0], null));
        $berbobot = [self::RASIO[0], [...self::RASIO[1], 'bobot' => 2]];
        $this->assertSame($tdd, $engine->handle('rasio_persen', 2, $berbobot, ['n' => 5, 't' => 0], null));
        $this->assertSame('terhitung', $engine->handle('rasio_persen', 2, self::RASIO, ['n' => 1, 't' => '0.000000000001'], null)['status_perhitungan']);
    }

    public function test_komponen_belum_lengkap_tetap_belum_diisi_meski_penyebut_nol(): void
    {
        foreach ([['t' => 0], ['n' => null, 't' => 0]] as $values) {
            $this->assertSame(['nilai' => null, 'status_perhitungan' => 'belum_diisi', 'sumber_nilai' => 'komponen'],
                (new CalculatePengukuran)->handle('rasio_persen', 2, self::RASIO, $values, null));
        }
    }

    public function test_nol_faktual_pada_indikator_terhitung_bukan_null(): void
    {
        $engine = new CalculatePengukuran;
        $nol = ['nilai' => '0.00', 'status_perhitungan' => 'terhitung', 'sumber_nilai' => 'komponen'];
        $this->assertSame($nol, $engine->handle('rasio_persen', 2, self::RASIO, ['n' => 0, 't' => 10], null));
        $jumlah = [['komponen_id' => 'a', 'peran' => 'penjumlah', 'bobot' => 1], ['komponen_id' => 'b', 'peran' => 'penjumlah', 'bobot' => 1]];
        $this->assertSame($nol, $engine->handle('penjumlahan', 2, $jumlah, ['a' => 0, 'b' => 0], null));
    }
}
