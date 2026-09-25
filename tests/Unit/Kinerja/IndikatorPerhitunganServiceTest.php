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
     * Penjumlahan dengan komponen aktif bukan penjumlah (pembilang/penyebut) ditolak.
     */
    public function test_penjumlahan_dengan_komponen_non_penjumlah_ditolak(): void
    {
        $indikator = $this->createIndikator('penjumlahan');
        $komponen = new Collection([
            $this->createKomponen('x', 'penjumlah', 1.0, true),
            $this->createKomponen('y', 'pembilang', 1.0, true),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Seluruh komponen aktif pada indikator bertipe penjumlahan wajib berperan sebagai penjumlah.', $result['messages']);
    }

    /**
     * Rasio persen dengan komponen penjumlah aktif ditolak.
     */
    public function test_rasio_persen_dengan_penjumlah_ditolak(): void
    {
        $indikator = $this->createIndikator('rasio_persen');
        $komponen = new Collection([
            $this->createKomponen('n', 'pembilang', 1.0, true),
            $this->createKomponen('t', 'penyebut', 1.0, true),
            $this->createKomponen('extra', 'penjumlah', 1.0, true),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Indikator bertipe rasio persen tidak boleh memiliki komponen berperan penjumlah.', $result['messages']);
    }

    /**
     * Rasio persen dengan penyebut berbobot 0 ditolak.
     */
    public function test_rasio_persen_dengan_penyebut_bobot_nol_ditolak(): void
    {
        $indikator = $this->createIndikator('rasio_persen');
        $komponen = new Collection([
            $this->createKomponen('n', 'pembilang', 1.0, true),
            $this->createKomponen('t', 'penyebut', 0.0, true),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Komponen penyebut pada indikator bertipe rasio persen wajib memiliki bobot lebih besar dari 0 agar formula dapat dihitung.', $result['messages']);
    }

    /**
     * Evaluate mengembalikan null jika belum semua komponen aktif diisi.
     */
    public function test_evaluate_mengembalikan_null_jika_komponen_aktif_belum_lengkap(): void
    {
        $indikator = $this->createIndikator('penjumlahan', 2);
        $komponen = new Collection([
            $this->createKomponen('a', 'penjumlah', 0.5, true, 1),
            $this->createKomponen('b', 'penjumlah', 0.5, true, 2),
        ]);
        $indikator->setRelation('komponen', $komponen);

        // Hanya komponen 'a' yang diisi, 'b' kosong
        $hasil = $this->service->evaluate($indikator, [
            'a' => 10,
        ]);

        $this->assertNull($hasil);
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

    /**
     * Indikator manual dengan komponen aktif ditolak.
     */
    public function test_manual_dengan_komponen_aktif_ditolak(): void
    {
        $indikator = $this->createIndikator('manual');
        $komponen = new Collection([
            $this->createKomponen('x', 'penjumlah', 1.0, true),
        ]);
        $indikator->setRelation('komponen', $komponen);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Indikator bertipe manual tidak menggunakan komponen angka perhitungan.', $result['messages']);
    }

    /**
     * Indikator manual tanpa komponen valid secara definisi.
     */
    public function test_manual_tanpa_komponen_valid(): void
    {
        $indikator = $this->createIndikator('manual');
        $indikator->setRelation('komponen', new Collection);

        $result = $this->service->validateDefinisiKomponen($indikator);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['messages']);

        $contract = $this->service->getFormulaContract($indikator);
        $this->assertSame('Input Manual Langsung', $contract['formula_text']);
    }

    /**
     * Indikator manual mengevaluasi nilai langsung dan membulatkan sesuai presisi.
     */
    public function test_manual_evaluate_menggunakan_nilai_langsung(): void
    {
        $indikator = $this->createIndikator('manual', 2);
        $indikator->setRelation('komponen', new Collection);

        $hasil = $this->service->evaluate($indikator, ['nilai' => 88.5432]);
        $this->assertSame(88.54, $hasil);

        $nullHasil = $this->service->evaluate($indikator, []);
        $this->assertNull($nullHasil);
    }

    /**
     * Evaluasi membulatkan hasil perhitungan ke presisi indikator.
     */
    public function test_evaluate_membulatkan_ke_presisi_indikator(): void
    {
        $indikator = $this->createIndikator('penjumlahan', 2);
        $komponen = new Collection([
            $this->createKomponen('k1', 'penjumlah', 1.0, true, 1),
            $this->createKomponen('k2', 'penjumlah', 1.0, true, 2),
        ]);
        $indikator->setRelation('komponen', $komponen);

        // 0.234 + 1.0006 = 1.2346 -> dibulatkan ke presisi 2 menjadi 1.23
        $hasil = $this->service->evaluate($indikator, [
            'k1' => 0.234,
            'k2' => 1.0006,
        ]);

        $this->assertSame(1.23, $hasil);
    }
}
