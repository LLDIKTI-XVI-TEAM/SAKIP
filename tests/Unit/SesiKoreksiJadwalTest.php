<?php

namespace Tests\Unit;

use App\Models\JadwalTahunan;
use App\Services\Jadwal\SesiKoreksiJadwal;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SesiKoreksiJadwalTest extends TestCase
{
    public static function batasSesi(): array
    {
        return [
            'sebelum mulai' => ['2026-11-01T00:59:59Z', 'aktif', false],
            'tepat mulai' => ['2026-11-01T01:00:00Z', 'aktif', true],
            'tepat selesai' => ['2026-11-01T09:00:00Z', 'aktif', true],
            'setelah selesai' => ['2026-11-01T09:00:01Z', 'aktif', false],
            'ditutup kembali' => ['2026-11-01T05:00:00Z', 'ditutup', false],
            'draft' => ['2026-11-01T05:00:00Z', 'draft', false],
        ];
    }

    #[DataProvider('batasSesi')]
    public function test_batas_waktu_inklusif_dan_status_jadwal(string $waktu, string $status, bool $boleh): void
    {
        $jadwal = $this->jadwal($status);
        $this->assertSame($boleh, SesiKoreksiJadwal::berlaku($jadwal, 'rencana_aksi', 'indikator-1', ['periode-1'], CarbonImmutable::parse($waktu)));
    }

    public function test_objek_indikator_dan_seluruh_periode_harus_tercakup(): void
    {
        $at = CarbonImmutable::parse('2026-11-01T05:00:00Z');
        $jadwal = $this->jadwal();
        $this->assertTrue(SesiKoreksiJadwal::berlaku($jadwal, 'rencana_aksi', 'indikator-1', ['periode-1'], $at));
        $this->assertFalse(SesiKoreksiJadwal::berlaku($jadwal, 'pengukuran', 'indikator-1', ['periode-1'], $at));
        $this->assertFalse(SesiKoreksiJadwal::berlaku($jadwal, 'rencana_aksi', 'indikator-lain', ['periode-1'], $at));
        $this->assertFalse(SesiKoreksiJadwal::berlaku($jadwal, 'rencana_aksi', 'indikator-1', ['periode-1', 'periode-lain'], $at));
        foreach (['jenis_objek', 'indikator_ids', 'periode_ids'] as $key) {
            $scope = $jadwal->lingkup_koreksi;
            unset($scope[$key]);
            $tanpaScope = $this->jadwal();
            $tanpaScope->lingkup_koreksi = $scope;
            $this->assertFalse(SesiKoreksiJadwal::berlaku($tanpaScope, 'rencana_aksi', 'indikator-1', [], $at));
        }
    }

    public function test_metadata_sesi_menjaga_tahun_terkunci_sebelum_penutupan_asli(): void
    {
        $at = CarbonImmutable::parse('2026-11-01T05:00:00Z');
        $this->assertTrue($this->jadwal()->tahunDitutup($at));
        $normal = $this->jadwal();
        $normal->fill(['koreksi_mulai' => null, 'koreksi_sampai' => null, 'lingkup_koreksi' => null]);
        $this->assertFalse($normal->tahunDitutup($at));
    }

    private function jadwal(string $status = 'aktif'): JadwalTahunan
    {
        return (new JadwalTahunan)->forceFill(['id' => 'jadwal-1', 'tahun' => 2026, 'status' => $status,
            'penutupan' => '2027-01-19', 'koreksi_mulai' => '2026-11-01 01:00:00', 'koreksi_sampai' => '2026-11-01 09:00:00',
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => ['indikator-1'], 'periode_ids' => ['periode-1']]]);
    }
}
