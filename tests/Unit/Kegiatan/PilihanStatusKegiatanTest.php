<?php

namespace Tests\Unit\Kegiatan;

use App\Models\Kegiatan;
use App\Models\Periode;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PilihanStatusKegiatanTest extends TestCase
{
    public function test_rencana_menawarkan_tiga_status_dan_periode_lebih_akhir(): void
    {
        $p = $this->kegiatan('rencana', 'tw1')->pilihanStatus($this->periodeAktif());

        $this->assertSame(['ditunda', 'tidak_terlaksana', 'batal'], $p['status']);
        $this->assertSame(['tw2', 'tw3'], array_column($p['periode_tujuan'], 'id'));
        $this->assertSame(['id' => 'tw2', 'nama' => 'Triwulan II'], $p['periode_tujuan'][0]);
    }

    public function test_tidak_terlaksana_hanya_tunda_dan_batal(): void
    {
        $p = $this->kegiatan('tidak_terlaksana', 'tw1')->pilihanStatus($this->periodeAktif());

        $this->assertSame(['ditunda', 'batal'], $p['status']);
    }

    #[DataProvider('statusFinal')]
    public function test_status_final_tanpa_pilihan(string $status): void
    {
        $p = $this->kegiatan($status, 'tw1')->pilihanStatus($this->periodeAktif());

        $this->assertSame([], $p['status']);
    }

    public static function statusFinal(): array
    {
        return [['terlaksana'], ['ditunda'], ['batal']];
    }

    public function test_periode_terakhir_tanpa_tunda(): void
    {
        $p = $this->kegiatan('rencana', 'tw3')->pilihanStatus($this->periodeAktif());

        $this->assertSame(['tidak_terlaksana', 'batal'], $p['status']);
        $this->assertSame([], $p['periode_tujuan']);
    }

    public function test_urutan_sama_bukan_periode_lebih_akhir(): void
    {
        $aktif = $this->periodeAktif()->push($this->periode('tw3b', 'Triwulan III-B', 3));

        $p = $this->kegiatan('rencana', 'tw3')->pilihanStatus($aktif);

        $this->assertSame([], $p['periode_tujuan']);
    }

    /** @return Collection<int, Periode> */
    private function periodeAktif(): Collection
    {
        return collect([
            $this->periode('tw1', 'Triwulan I', 1),
            $this->periode('tw2', 'Triwulan II', 2),
            $this->periode('tw3', 'Triwulan III', 3),
        ]);
    }

    private function periode(string $id, string $nama, int $urutan): Periode
    {
        return (new Periode)->forceFill(['id' => $id, 'nama' => $nama, 'urutan' => $urutan]);
    }

    private function kegiatan(string $status, string $periodeId): Kegiatan
    {
        $periode = $this->periodeAktif()->firstWhere('id', $periodeId);

        return (new Kegiatan)->forceFill(['status' => $status])->setRelation('periode', $periode);
    }
}
