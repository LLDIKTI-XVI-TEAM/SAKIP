<?php

namespace Tests\Feature\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Services\Kegiatan\TotalAnggaranKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Penjumlahan anggaran kegiatan (Q42 §42.2 butir 2, 4, 6): anggaran kosong
 * tidak dihitung tetapi menandai total, kegiatan bersama tampil penuh per
 * IKU tetapi dihitung sekali per unit/tahun, dan kegiatan asal yang ditunda
 * tidak ikut dihitung setelah salah satu lanjutan dalam rantainya diklaim (Q69 §69.6).
 */
class TotalAnggaranKegiatanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RencanaAksi $header;

    private TotalAnggaranKegiatan $total;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->buatFixtureManual();
        // Draf berisi satu kegiatan tahap RA beranggaran 0 pada Triwulan I.
        $this->header = $this->buatDraf($this->fixture);
        $this->total = app(TotalAnggaranKegiatan::class);
    }

    public function test_total_per_periode_dan_ra_melewati_anggaran_kosong_dan_menandainya(): void
    {
        $this->klaim($this->kegiatan('Bimtek', 1500000));
        $this->klaim($this->kegiatan('Rapat koordinasi', null, $this->fixture['periode2']));
        $this->klaim($this->kegiatan('Monev', 2000000, $this->fixture['periode2']));

        $hasil = $this->total->perRencanaAksi($this->header);

        $this->assertSame(['total' => '3500000', 'belum_diisi' => 1], ['total' => $hasil['total'], 'belum_diisi' => $hasil['belum_diisi']]);
        $this->assertSame(['total' => '1500000', 'belum_diisi' => 0], $hasil['per_periode'][$this->fixture['periode1']->id]);
        $this->assertSame(['total' => '2000000', 'belum_diisi' => 1], $hasil['per_periode'][$this->fixture['periode2']->id]);
    }

    public function test_kegiatan_dua_iku_penuh_per_iku_sekali_per_unit_tahun_dan_ditandai_bersama(): void
    {
        $bersama = $this->kegiatan('Workshop bersama', 1000000);
        $tunggal = $this->kegiatan('Survei kepuasan', 250000);
        $this->klaim($bersama);
        $this->klaim($tunggal);
        $headerLain = $this->headerIkuLain();
        $this->klaim($bersama, $headerLain);

        $this->assertSame(['total' => '1250000', 'belum_diisi' => 0], $this->total->perIku((string) $this->fixture['indikator']->id, 2026));
        $this->assertSame(['total' => '1000000', 'belum_diisi' => 0], $this->total->perIku((string) $headerLain->indikator_id, 2026));
        $this->assertSame(['total' => '1250000', 'belum_diisi' => 0], $this->total->perUnitTahun((string) $this->fixture['unit']->id, 2026));
        $this->assertSame([(string) $bersama->id], $this->total->kegiatanBersama([(string) $bersama->id, (string) $tunggal->id]));
    }

    public function test_kegiatan_asal_ditunda_dengan_lanjutan_tidak_dihitung(): void
    {
        $asal = $this->kegiatan('Pelatihan asesor', 700000, null, ['status' => 'ditunda']);
        $this->klaim($asal);
        $this->klaim($this->kegiatan('Pelatihan asesor (lanjutan)', 700000, $this->fixture['periode2'], ['kegiatan_asal_id' => $asal->id]));
        // Kegiatan ditunda tanpa lanjutan tetap dihitung.
        $this->klaim($this->kegiatan('Sosialisasi', 300000, null, ['status' => 'ditunda']));

        $hasil = $this->total->perRencanaAksi($this->header);

        $this->assertSame('1000000', $hasil['total']);
        $this->assertSame('300000', $hasil['per_periode'][$this->fixture['periode1']->id]['total']);
        $this->assertSame('1000000', $this->total->perUnitTahun((string) $this->fixture['unit']->id, 2026)['total']);
    }

    public function test_asal_ditunda_tetap_dihitung_sampai_lanjutan_diklaim(): void
    {
        $asal = $this->kegiatan('Pelatihan asesor', 700000, null, ['status' => 'ditunda']);
        $this->klaim($asal);
        $lanjutan = $this->kegiatan('Pelatihan asesor', 700000, $this->fixture['periode2'], ['kegiatan_asal_id' => $asal->id]);
        $unit = (string) $this->fixture['unit']->id;

        // Lanjutan belum diklaim: anggaran tetap terhitung pada asal.
        $this->assertSame('700000', $this->total->perRencanaAksi($this->header)['total']);
        $this->assertSame('700000', $this->total->perUnitTahun($unit, 2026)['total']);

        // Klaim ke Rencana Aksi IKU lain pun memindahkan hitungan ke lanjutan.
        $this->klaim($lanjutan, $this->headerIkuLain());
        $this->assertSame('0', $this->total->perRencanaAksi($this->header)['total']);
        $this->assertSame('700000', $this->total->perUnitTahun($unit, 2026)['total']);
    }

    public function test_rantai_penundaan_menghitung_anggaran_sekali(): void
    {
        // A diklaim lalu ditunda ke B yang belum diklaim, B ditunda ke C: A tidak boleh terhitung bersama C (Q69 §69.6 butir 2).
        $a = $this->kegiatan('Pelatihan asesor', 700000, null, ['status' => 'ditunda']);
        $this->klaim($a);
        $b = $this->kegiatan('Pelatihan asesor', 800000, $this->fixture['periode2'], ['status' => 'ditunda', 'kegiatan_asal_id' => $a->id]);
        $c = $this->kegiatan('Pelatihan asesor', 900000, $this->periodeTiga(), ['kegiatan_asal_id' => $b->id]);
        $unit = (string) $this->fixture['unit']->id;

        $this->assertSame('700000', $this->total->perUnitTahun($unit, 2026)['total']);

        $this->klaim($c);
        $this->assertSame('900000', $this->total->perUnitTahun($unit, 2026)['total']);
        $this->assertSame('900000', $this->total->perRencanaAksi($this->header)['total']);
    }

    public function test_rantai_penundaan_dengan_lanjutan_tengah_diklaim_menghitung_lanjutan_tengah(): void
    {
        // Kontrol: B diklaim dan ditunda ke C yang belum diklaim, sehingga anggaran terhitung pada B saja.
        $a = $this->kegiatan('Pelatihan asesor', 700000, null, ['status' => 'ditunda']);
        $this->klaim($a);
        $b = $this->kegiatan('Pelatihan asesor', 800000, $this->fixture['periode2'], ['status' => 'ditunda', 'kegiatan_asal_id' => $a->id]);
        $this->klaim($b);
        $this->kegiatan('Pelatihan asesor', 900000, $this->periodeTiga(), ['kegiatan_asal_id' => $b->id]);

        $this->assertSame('800000', $this->total->perUnitTahun((string) $this->fixture['unit']->id, 2026)['total']);
    }

    public function test_klaim_tahap_pengukuran_ikut_dihitung(): void
    {
        $this->klaim($this->kegiatan('Benchmarking', 400000));
        $pengukuran = PengukuranKinerja::create(['indikator_id' => $this->fixture['indikator']->id, 'tahun' => 2026, 'periode_id' => $this->fixture['periode1']->id,
            'jadwal_snapshot_id' => $this->fixture['snapshot']->id, 'sumber_nilai' => 'manual', 'created_by' => $this->fixture['pic']->id]);
        $tambahan = $this->kegiatan('Kegiatan TW IV dari pengukuran', 600000, $this->fixture['periode2']);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->header->id, 'kegiatan_id' => $tambahan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'pengukuran', 'pengukuran_id' => $pengukuran->id, 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);

        $this->assertSame('1000000', $this->total->perRencanaAksi($this->header)['total']);
        $this->assertSame('1000000', $this->total->perIku((string) $this->fixture['indikator']->id, 2026)['total']);
    }

    /** @param array<string, mixed> $atribut */
    private function kegiatan(string $nama, ?int $anggaran, ?Periode $periode = null, array $atribut = []): Kegiatan
    {
        return Kegiatan::create(['unit_id' => $this->fixture['unit']->id, 'tahun' => 2026, 'periode_id' => ($periode ?? $this->fixture['periode1'])->id,
            'nama' => $nama, 'anggaran' => $anggaran, 'created_by' => $this->fixture['pic']->id, ...$atribut]);
    }

    private function klaim(Kegiatan $kegiatan, ?RencanaAksi $header = null): void
    {
        KlaimKegiatan::create(['rencana_aksi_id' => ($header ?? $this->header)->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);
    }

    private function periodeTiga(): Periode
    {
        return Periode::firstOrCreate(['nama' => 'Triwulan III', 'urutan' => 3], ['aktif' => true, 'is_nilai_akhir' => false]);
    }

    private function headerIkuLain(): RencanaAksi
    {
        $indikator = $this->fixture['indikator']->replicate();
        $indikator->kode = 'I-UJI-LAIN';
        $indikator->save();

        return RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->fixture['unit']->id,
            'jadwal_tahunan_id' => $this->fixture['jadwal']->id, 'penanggung_jawab_id' => $this->fixture['pic']->id, 'status_alur' => RencanaAksi::STATUS_DRAFT, 'versi' => 1,
            'created_by' => $this->fixture['pic']->id]);
    }
}
