<?php

namespace Tests\Feature\Periode;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Q70 §70.1: tanggal periode tersimpan sebagai hari dan bulan tanpa tahun, valid untuk setiap tahun. */
class TanggalPeriodeSkemaTest extends TestCase
{
    use RefreshDatabase;

    private const KOLOM = ['bulan_mulai', 'hari_mulai', 'bulan_selesai', 'hari_selesai'];

    /** @param array<string, int|null> $tanggal */
    private function simpan(array $tanggal, string $nama = 'Periode uji'): string
    {
        $id = (string) Str::uuid();
        DB::table('periode')->insert(['id' => $id, 'nama' => $nama, 'urutan' => 1, 'aktif' => false, 'is_nilai_akhir' => false, ...$tanggal]);

        return $id;
    }

    /** @return array<string, int|null> */
    private static function tanggal(?int $bulanMulai, ?int $hariMulai, ?int $bulanSelesai, ?int $hariSelesai): array
    {
        return array_combine(self::KOLOM, [$bulanMulai, $hariMulai, $bulanSelesai, $hariSelesai]);
    }

    /** @return array<string, int|null> */
    private function tersimpan(string $id): array
    {
        return (array) DB::table('periode')->where('id', $id)->first(self::KOLOM);
    }

    public function test_tanggal_tersimpan_sebagai_hari_dan_bulan_tanpa_tahun(): void
    {
        $id = $this->simpan(self::tanggal(2, 15, 5, 14));

        $this->assertEquals(self::tanggal(2, 15, 5, 14), $this->tersimpan($id));
        $this->assertSame([], array_values(array_filter(
            DB::table('information_schema.columns')->where('table_name', 'periode')->pluck('column_name')->all(),
            fn (string $kolom): bool => str_contains($kolom, 'tahun'),
        )));
    }

    /** @param array<string, int|null> $tanggal */
    #[DataProvider('tanggalTidakSah')]
    public function test_check_menolak_tanggal_yang_tidak_berlaku_setiap_tahun(array $tanggal): void
    {
        try {
            DB::transaction(fn () => $this->simpan($tanggal));
            $this->fail('CHECK periode_tanggal_valid tidak menolak '.json_encode($tanggal));
        } catch (QueryException $exception) {
            $this->assertStringContainsString('periode_tanggal_valid', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('periode')->count());
    }

    /** @return array<string, array{array<string, int|null>}> */
    public static function tanggalTidakSah(): array
    {
        return [
            'sebagian kosong' => [self::tanggal(1, 1, null, null)],
            'bulan di luar 1-12' => [self::tanggal(1, 1, 13, 1)],
            'hari nol' => [self::tanggal(1, 0, 3, 31)],
            '31 April' => [self::tanggal(4, 31, 6, 30)],
            '29 Februari' => [self::tanggal(1, 1, 2, 29)],
            'mulai setelah selesai' => [self::tanggal(4, 1, 3, 31)],
        ];
    }

    public function test_kontrol_positif_tanpa_tanggal_satu_hari_dan_akhir_bulan_sah(): void
    {
        $kosong = $this->simpan(self::tanggal(null, null, null, null), 'Tanpa tanggal');
        $satuHari = $this->simpan(self::tanggal(1, 1, 1, 1), 'Satu hari');
        $akhirBulan = $this->simpan(self::tanggal(2, 28, 12, 31), 'Akhir bulan');

        $this->assertEquals(self::tanggal(null, null, null, null), $this->tersimpan($kosong));
        $this->assertEquals(self::tanggal(1, 1, 1, 1), $this->tersimpan($satuHari));
        $this->assertEquals(self::tanggal(2, 28, 12, 31), $this->tersimpan($akhirBulan));
    }

    public function test_migrasi_mengisi_tanggal_standar_triwulan_dan_membiarkan_periode_lain_kosong(): void
    {
        $migrasi = require database_path('migrations/2026_10_25_000001_tambah_tanggal_ke_periode.php');
        $migrasi->down();
        $ids = [];
        foreach (['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV', 'Semester Uji'] as $urutan => $nama) {
            $ids[$nama] = (string) Str::uuid();
            DB::table('periode')->insert(['id' => $ids[$nama], 'nama' => $nama, 'urutan' => $urutan + 1, 'aktif' => true, 'is_nilai_akhir' => $nama === 'Triwulan IV']);
        }

        $migrasi->up();

        $this->assertEquals(self::tanggal(1, 1, 3, 31), $this->tersimpan($ids['Triwulan I']));
        $this->assertEquals(self::tanggal(4, 1, 6, 30), $this->tersimpan($ids['Triwulan II']));
        $this->assertEquals(self::tanggal(7, 1, 9, 30), $this->tersimpan($ids['Triwulan III']));
        $this->assertEquals(self::tanggal(10, 1, 12, 31), $this->tersimpan($ids['Triwulan IV']));
        $this->assertEquals(self::tanggal(null, null, null, null), $this->tersimpan($ids['Semester Uji']));
    }
}
