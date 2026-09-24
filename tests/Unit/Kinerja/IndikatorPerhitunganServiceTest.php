<?php

namespace Tests\Unit\Kinerja;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Services\Kinerja\IndikatorPerhitunganService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class IndikatorPerhitunganServiceTest extends TestCase
{
    private IndikatorPerhitunganService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IndikatorPerhitunganService;
    }

    private function createIndikator(string $tipe, int $presisi = 2): IndikatorKinerja
    {
        $indikator = new IndikatorKinerja([
            'kode' => 'IKU-TEST',
            'nama' => 'Indikator Test',
            'satuan' => '%',
            'tipe_perhitungan' => $tipe,
            'presisi' => $presisi,
            'desimal_tampilan' => $presisi,
            'is_aktif' => true,
        ]);

        return $indikator;
    }

    private function createKomponen(string $kode, string $peran, float $bobot = 1.0, bool $aktif = true, int $urutan = 1, string $label = ''): IndikatorKomponen
    {
        return new IndikatorKomponen([
            'kode' => $kode,
            'label' => $label ?: "Label {$kode}",
            'peran' => $peran,
            'bobot' => $bobot,
            'urutan' => $urutan,
            'aktif' => $aktif,
        ]);
    }

    /**
     * TEST-1: Rasio tanpa penyebut ditolak.
     */
    public function test_rasio_tanpa_penyebut_ditolak(): void
    {
        $indikator = $this->createIndikator('rasio_persen');
        $komponen = new Collection([
            $this->createKomponen('n', 'pembilang', 1.0, true),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Indikator bertipe rasio persen wajib memiliki tepat satu penyebut aktif.', $result['messages']);
    }

    /**
     * TEST-2: Rasio dengan >1 penyebut aktif ditolak.
     */
    public function test_rasio_dengan_lebih_dari_satu_penyebut_aktif_ditolak(): void
    {
        $indikator = $this->createIndikator('rasio_persen');
        $komponen = new Collection([
            $this->createKomponen('n', 'pembilang', 1.0, true),
            $this->createKomponen('t1', 'penyebut', 1.0, true),
            $this->createKomponen('t2', 'penyebut', 1.0, true),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Indikator bertipe rasio persen tidak boleh memiliki lebih dari satu penyebut aktif.', $result['messages']);
    }

    /**
     * TEST-3: Penjumlahan tanpa penjumlah ditolak.
     */
    public function test_penjumlahan_tanpa_penjumlah_ditolak(): void
    {
        $indikator = $this->createIndikator('penjumlahan');
        $komponen = new Collection([
            $this->createKomponen('x', 'pembilang', 1.0, true),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Indikator bertipe penjumlahan wajib memiliki minimal satu komponen penjumlah aktif.', $result['messages']);
    }

    /**
     * TEST-4: Kombinasi generic valid berhasil.
     */
    public function test_kombinasi_generic_valid_berhasil(): void
    {
        $indikator = $this->createIndikator('rasio_persen', 2);
        $komponen = new Collection([
            $this->createKomponen('a', 'pembilang', 1.0, true, 1),
            $this->createKomponen('b', 'pembilang', 2.0, true, 2),
            $this->createKomponen('t', 'penyebut', 1.0, true, 3),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $validation = $this->service->validateDefinisiKomponen($indikator);
        $this->assertTrue($validation['is_valid']);
        $this->assertEmpty($validation['messages']);

        // Nilai a = 10, b = 20, t = 100
        // (10 * 1 + 20 * 2) / (100 * 1) * 100 = 50 / 100 * 100 = 50.00
        $hasil = $this->service->evaluate($indikator, [
            'a' => 10,
            'b' => 20,
            't' => 100,
        ]);
        $this->assertSame(50.0, $hasil);
    }

    /**
     * TEST-6: IKU 3 menghitung nilai rata-rata dua skor dengan benar: (sakip + zi_wbk) / 2.
     */
    public function test_iku_3_menghitung_rata_rata_dua_skor_dengan_benar(): void
    {
        $indikator = $this->createIndikator('penjumlahan', 2);
        $komponen = new Collection([
            $this->createKomponen('sakip', 'penjumlah', 0.5, true, 1, 'Skor SAKIP'),
            $this->createKomponen('zi_wbk', 'penjumlah', 0.5, true, 2, 'Skor ZI-WBK'),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $validation = $this->service->validateDefinisiKomponen($indikator);
        $this->assertTrue($validation['is_valid']);

        // Misal Skor SAKIP = 75, Skor ZI-WBK = 85
        // (75 * 0.5) + (85 * 0.5) = 37.5 + 42.5 = 80.0
        // (75 + 85) / 2 = 80.0
        $hasil = $this->service->evaluate($indikator, [
            'sakip' => 75.0,
            'zi_wbk' => 85.0,
        ]);

        $this->assertSame(80.0, $hasil);
    }

    /**
     * Pembagian nol menghasilkan null (tidak dapat dihitung) sesuai PRD §17.4.
     */
    public function test_pembagian_nol_menghasilkan_null(): void
    {
        $indikator = $this->createIndikator('rasio_persen', 2);
        $komponen = new Collection([
            $this->createKomponen('n', 'pembilang', 1.0, true, 1),
            $this->createKomponen('t', 'penyebut', 1.0, true, 2),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $hasil = $this->service->evaluate($indikator, [
            'n' => 50,
            't' => 0,
        ]);

        $this->assertNull($hasil);
    }

    /**
     * AC-11: Kontrak formula dihasilkan oleh server.
     */
    public function test_formula_contract_berasal_dari_server(): void
    {
        $indikator = $this->createIndikator('penjumlahan', 2);
        $komponen = new Collection([
            $this->createKomponen('sakip', 'penjumlah', 0.5, true, 1, 'Skor SAKIP'),
            $this->createKomponen('zi_wbk', 'penjumlah', 0.5, true, 2, 'Skor ZI-WBK'),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $contract = $this->service->getFormulaContract($indikator);

        $this->assertTrue($contract['is_valid']);
        $this->assertStringContainsString('sakip', $contract['formula_text']);
        $this->assertStringContainsString('zi_wbk', $contract['formula_text']);
        $this->assertCount(2, $contract['komponen_list']);
    }
}
