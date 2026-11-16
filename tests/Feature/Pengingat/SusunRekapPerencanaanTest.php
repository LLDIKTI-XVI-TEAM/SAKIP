<?php

namespace Tests\Feature\Pengingat;

use App\Models\PeriodeJadwal;
use App\Models\Unit;
use App\Services\Pengingat\SusunRekapPerencanaan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Isi rekap progres Perencanaan per jendela pengisian.
 * Fixture dasar: jadwal aktif 2026, Triwulan I dengan batas pengisian 2026-03-15 dan satu header draf (I-UJI).
 */
class SusunRekapPerencanaanTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw1;

    private Unit $unitB;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $this->unitB = $this->unitBaru('Unit B');
    }

    public function test_jumlah_per_status_sesuai_fixture(): void
    {
        $this->isiFixtureStatus();

        $rekap = $this->parameter();

        // Nilai tiap status sengaja berbeda agar kolom yang tertukar terdeteksi.
        $this->assertSame(3, $rekap['belum_mengisi']);
        $this->assertSame(1, $rekap['draf']);
        $this->assertSame(2, $rekap['dikembalikan']);
        $this->assertSame(4, $rekap['diajukan']);
        $this->assertSame(6, $rekap['belum_selesai']);
        $this->assertSame(10, $rekap['total']);
        $this->assertSame('Belum selesai per unit: Unit B 4; Unit Pengujian 2.', $rekap['rincian_unit']);
        $this->assertSame(3, $rekap['h']);
        $this->assertSame('2026-03-15', $rekap['jatuh_tempo']);
        $this->assertSame('15 Maret 2026', $rekap['jatuh_tempo_tampil']);
        $this->assertSame('Triwulan I', $rekap['periode']);
        $this->assertSame(2026, $rekap['tahun']);
        $this->assertSame(route('dashboard'), $rekap['url']);
    }

    public function test_unit_yang_ditolak_baca_tidak_masuk_rekap_penerima(): void
    {
        $this->isiFixtureStatus();

        $rekap = $this->parameter(DB::table('unit')->where('id', $this->unitB->id)->select('id'));

        // Kontrol positif: test_jumlah_per_status_sesuai_fixture menghitung Unit B tanpa penolakan.
        $this->assertSame(1, $rekap['belum_mengisi']);
        $this->assertSame(0, $rekap['dikembalikan']);
        $this->assertSame(4, $rekap['total']);
        $this->assertSame('Belum selesai per unit: Unit Pengujian 2.', $rekap['rincian_unit']);
    }

    public function test_tanpa_yang_belum_selesai_tetap_menghasilkan_rekap(): void
    {
        $this->ajukan($this->pengukuran, []);

        $rekap = $this->parameter();

        $this->assertSame(0, $rekap['belum_selesai']);
        $this->assertSame(1, $rekap['diajukan']);
        $this->assertSame(1, $rekap['total']);
        $this->assertSame('Semua indikator sudah diajukan.', $rekap['rincian_unit']);
    }

    public function test_rincian_unit_dibatasi_sepuluh_unit(): void
    {
        foreach (range(1, 11) as $n) {
            $this->indikatorBaru('I-U'.$n, [], ['unit_id' => $this->unitBaru(sprintf('Unit %02d', $n))->id]);
        }

        $rincian = $this->parameter()['rincian_unit'];

        // Sebelas unit baru dan Unit Pengujian masing-masing satu; urutan nama memutus seri.
        $this->assertStringStartsWith('Belum selesai per unit: Unit 01 1; Unit 02 1;', $rincian);
        $this->assertStringContainsString('Unit 10 1; dan 2 unit lain.', $rincian);
        $this->assertStringNotContainsString('Unit 11', $rincian);
    }

    /** @return array<string, array{0:string, 1:list<int>}> */
    public static function hariRekap(): array
    {
        return ['H-3' => ['2026-03-12', [3]], 'H-1' => ['2026-03-14', [1]]];
    }

    /** @param  list<int>  $h */
    #[DataProvider('hariRekap')]
    public function test_h3_dan_h1_memilih_jendela_yang_jatuh_tempo(string $tanggal, array $h): void
    {
        $periode = app(SusunRekapPerencanaan::class)->periodeUntuk(CarbonImmutable::parse($tanggal.' 08:00', 'Asia/Makassar'), [3, 1]);

        $this->assertSame([$this->tw1->id], array_map(fn (array $p): string => $p['periode']->id, $periode));
        $this->assertSame($h, array_column($periode, 'h'));
    }

    /** @return array<string, array{0:string}> */
    public static function hariTanpaRekap(): array
    {
        return ['H-7' => ['2026-03-08'], 'H-2' => ['2026-03-13'], 'H-0' => ['2026-03-15'], 'lewat' => ['2026-03-16']];
    }

    #[DataProvider('hariTanpaRekap')]
    public function test_hari_selain_h3_h1_tidak_memilih_jendela(string $tanggal): void
    {
        // Kontrol positif: test_h3_dan_h1_memilih_jendela_yang_jatuh_tempo pada fixture yang sama.
        $this->assertSame([], app(SusunRekapPerencanaan::class)->periodeUntuk(CarbonImmutable::parse($tanggal.' 08:00', 'Asia/Makassar'), [3, 1]));
    }

    /** @return array<string, string|int> */
    private function parameter(?Builder $unitDitolak = null): array
    {
        return app(SusunRekapPerencanaan::class)->parameter($this->tw1, 3,
            $unitDitolak ?? DB::table('unit')->whereRaw('1 = 0')->select('id'), CarbonImmutable::parse('2026-03-12 08:00', 'Asia/Makassar'));
    }

    /**
     * Unit Pengujian: I-UJI draf, satu tanpa header, satu diajukan, satu diverifikasi.
     * Unit B: dua tanpa header, dua dikembalikan, dua disahkan. Tidak satu pun indikator baru mempunyai PJ.
     * Tidak dihitung: indikator arsip, unit nonaktif, dan versi yang belum berlaku pada Triwulan I.
     */
    private function isiFixtureStatus(): void
    {
        $this->indikatorBaru('I-A-KOSONG');
        $this->ajukan($this->headerUntuk($this->indikatorBaru('I-A-AJU')), []);
        $this->ajukan($this->headerUntuk($this->indikatorBaru('I-A-VER')), [], 'diverifikasi');

        $b = ['unit_id' => $this->unitB->id];
        $this->indikatorBaru('I-B-KOSONG-1', [], $b);
        $this->indikatorBaru('I-B-KOSONG-2', [], $b);
        foreach (['I-B-KEMBALI-1', 'I-B-KEMBALI-2'] as $kode) {
            $this->headerUntuk($this->indikatorBaru($kode, [], $b))->forceFill(['status_alur' => 'dikembalikan'])->save();
        }
        foreach (['I-B-SAH-1', 'I-B-SAH-2'] as $kode) {
            $this->sahkan($this->headerUntuk($this->indikatorBaru($kode, [], $b)), ['nilai' => '70']);
        }

        $this->indikatorBaru('I-ARSIP', ['status' => 'arsip']);
        $nonaktif = $this->unitBaru('Unit Nonaktif');
        $this->indikatorBaru('I-NONAKTIF', [], ['unit_id' => $nonaktif->id]);
        $nonaktif->forceFill(['status' => 'nonaktif'])->save();
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $this->indikatorBaru('I-TW2', [], ['periode_mulai_id' => $tw2->periode_id]);
    }
}
