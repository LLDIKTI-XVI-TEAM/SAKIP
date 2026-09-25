<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Models\IndikatorKinerja;
use Database\Seeders\IndikatorKomponenFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorKomponenFixtureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST-5: IKU 3 fixture hanya memiliki tepat dua komponen efektif sakip dan zi_wbk dengan bobot 0.5.
     */
    public function test_iku_3_fixture_hanya_memiliki_sakip_dan_zi_wbk(): void
    {
        $this->seed(IndikatorKomponenFixtureSeeder::class);

        $iku3 = IndikatorKinerja::where('kode', 'IKU-3')->first();
        $this->assertNotNull($iku3, 'Indikator IKU-3 harus tersedia di fixture seeder.');

        $komponen = $iku3->komponen()->where('aktif', true)->get();
        $this->assertCount(2, $komponen, 'IKU 3 harus memiliki tepat dua komponen efektif.');

        $kodes = $komponen->pluck('kode')->all();
        $this->assertEqualsCanonicalizing(['sakip', 'zi_wbk'], $kodes);

        $sakip = $komponen->firstWhere('kode', 'sakip');
        $ziWbk = $komponen->firstWhere('kode', 'zi_wbk');

        $this->assertNotNull($sakip);
        $this->assertNotNull($ziWbk);

        $this->assertEquals(0.5, (float) $sakip->bobot);
        $this->assertEquals(0.5, (float) $ziWbk->bobot);
        $this->assertSame('penjumlah', $sakip->peran);
        $this->assertSame('penjumlah', $ziWbk->peran);
    }

    /**
     * TEST-7: Empat komponen interpretasi lama tidak dibuat untuk IKU 3.
     */
    public function test_empat_komponen_lama_tidak_dibuat_untuk_iku_3(): void
    {
        $this->seed(IndikatorKomponenFixtureSeeder::class);

        $iku3 = IndikatorKinerja::where('kode', 'IKU-3')->first();
        $this->assertNotNull($iku3);

        $forbiddenCodes = [
            'perencanaan_kinerja',
            'pengukuran_kinerja',
            'pelaporan_kinerja',
            'evaluasi_internal',
        ];

        foreach ($forbiddenCodes as $code) {
            $this->assertDatabaseMissing('indikator_komponen', [
                'indikator_id' => $iku3->id,
                'kode' => $code,
            ]);
        }
    }

    /**
     * TEST-8: IKU 8 menggunakan n/t, tidak ada angka 84 yang ditanam, dan label penyebut benar.
     */
    public function test_iku_8_menggunakan_n_t_dan_tidak_ada_angka_84(): void
    {
        $this->seed(IndikatorKomponenFixtureSeeder::class);

        $iku8 = IndikatorKinerja::where('kode', 'IKU-8')->first();
        $this->assertNotNull($iku8, 'Indikator IKU-8 harus tersedia di fixture seeder.');
        $this->assertSame('rasio_persen', $iku8->tipe_perhitungan);

        $komponen = $iku8->komponen()->where('aktif', true)->get();
        $this->assertCount(2, $komponen);

        $pembilang = $komponen->firstWhere('kode', 'n');
        $penyebut = $komponen->firstWhere('kode', 't');

        $this->assertNotNull($pembilang);
        $this->assertNotNull($penyebut);

        $this->assertSame('pembilang', $pembilang->peran);
        $this->assertSame('penyebut', $penyebut->peran);

        // Label penyebut harus persis sesuai requirement
        $this->assertSame('total publikasi seluruh PTS wilayah kerja', $penyebut->label);

        // Angka 84 tidak boleh menjadi konstanta atau bobot atau default penyebut
        $this->assertNotEquals(84, (float) $penyebut->bobot);
        $this->assertEquals(1.0, (float) $penyebut->bobot);
    }
}
