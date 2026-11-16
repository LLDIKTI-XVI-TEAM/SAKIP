<?php

namespace Tests\Unit\Jadwal;

use App\Models\JadwalTahunan;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class JadwalTahunDitutupTest extends TestCase
{
    public function test_ditutup_bila_status_ditutup_atau_tanggal_penutupan_lewat_pada_zona_bisnis(): void
    {
        $jadwal = fn (string $status, string $penutupan) => (new JadwalTahunan)->forceFill(['status' => $status, 'penutupan' => $penutupan]);
        $pada = CarbonImmutable::parse('2026-12-31 16:30:00', 'UTC'); // 2027-01-01 00:30 Asia/Makassar

        $this->assertTrue($jadwal('ditutup', '2027-06-30')->tahunDitutup($pada));
        $this->assertTrue($jadwal('aktif', '2026-12-31')->tahunDitutup($pada));
        $this->assertFalse($jadwal('aktif', '2027-01-01')->tahunDitutup($pada));
        $this->assertFalse($jadwal('aktif', '2027-06-30')->tahunDitutup($pada));
    }
}
