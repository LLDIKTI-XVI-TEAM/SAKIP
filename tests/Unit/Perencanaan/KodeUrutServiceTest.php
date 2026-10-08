<?php

namespace Tests\Unit\Perencanaan;

use App\Services\Perencanaan\KodeUrutService;
use Tests\TestCase;

/**
 * Kontrak pembangkit kode berurutan: deret global, padding dua digit,
 * dan kode legacy di luar pola tidak menggeser deret.
 */
class KodeUrutServiceTest extends TestCase
{
    private KodeUrutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KodeUrutService;
    }

    public function test_deret_kosong_mulai_dari_satu_dengan_padding_dua_digit(): void
    {
        $hasil = $this->service->berikutnya(KodeUrutService::PREFIX_SASARAN, collect([]));

        $this->assertSame('SS-01', $hasil['kode']);
        $this->assertSame(1, $hasil['nomor']);
    }

    public function test_nomor_mengikuti_kode_tertinggi_yang_sudah_ada(): void
    {
        $hasil = $this->service->berikutnya(KodeUrutService::PREFIX_SASARAN, collect(['SS-01', 'SS-02', 'SS-03']));

        $this->assertSame('SS-04', $hasil['kode']);
        $this->assertSame(4, $hasil['nomor']);
    }

    public function test_kode_legacy_di_luar_pola_diabaikan(): void
    {
        $hasil = $this->service->berikutnya(
            KodeUrutService::PREFIX_SASARAN,
            collect(['SS-RA-FIXTURE', 'SS-', 'SASARAN-01', 'SS-2A', null, 5]),
        );

        $this->assertSame('SS-01', $hasil['kode']);
        $this->assertSame(1, $hasil['nomor']);
    }

    public function test_prefix_indikator_tidak_menghitung_kode_iku_resmi(): void
    {
        $hasil = $this->service->berikutnya(KodeUrutService::PREFIX_INDIKATOR, collect(['IKU-3', 'IKU-8']));

        $this->assertSame('IK-01', $hasil['kode']);
    }

    public function test_deret_sasaran_dan_indikator_saling_independen(): void
    {
        $sasaran = $this->service->berikutnya(KodeUrutService::PREFIX_SASARAN, collect(['IK-07', 'SS-05']));
        $indikator = $this->service->berikutnya(KodeUrutService::PREFIX_INDIKATOR, collect(['IK-07', 'SS-05']));

        $this->assertSame('SS-06', $sasaran['kode']);
        $this->assertSame('IK-08', $indikator['kode']);
    }

    public function test_nomor_melebar_tanpa_terpotong_setelah_99(): void
    {
        $hasil = $this->service->berikutnya(KodeUrutService::PREFIX_SASARAN, collect(['SS-99']));

        $this->assertSame('SS-100', $hasil['kode']);
        $this->assertSame(100, $hasil['nomor']);
        $this->assertSame('SS-100', $this->service->format(KodeUrutService::PREFIX_SASARAN, 100));
        $this->assertSame('SS-05', $this->service->format(KodeUrutService::PREFIX_SASARAN, 5));
    }

    public function test_nomor_dari_mengembalikan_null_untuk_kode_non_pola(): void
    {
        $this->assertSame(7, $this->service->nomorDari(KodeUrutService::PREFIX_INDIKATOR, 'IK-7'));
        $this->assertSame(7, $this->service->nomorDari(KodeUrutService::PREFIX_INDIKATOR, 'IK-007'));
        $this->assertNull($this->service->nomorDari(KodeUrutService::PREFIX_INDIKATOR, 'IKU-7'));
        $this->assertNull($this->service->nomorDari(KodeUrutService::PREFIX_INDIKATOR, 'IK-'));
        $this->assertNull($this->service->nomorDari(KodeUrutService::PREFIX_INDIKATOR, null));
    }
}
