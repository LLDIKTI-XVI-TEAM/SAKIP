<?php

namespace Tests\Feature\Pengingat;

use App\Models\JadwalSnapshot;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Pengingat\SusunPengingatTenggat;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Pemetaan kandidat evaluator menjadi identitas kejadian dan parameter pesan EWS.
 * Fixture dasar: jadwal aktif 2026 dengan batas pengisian 2026-03-15.
 */
class SusunPengingatTenggatTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
    }

    /** @return array<string, mixed> */
    private function susun(string $tanggal, array $hMinus): array
    {
        return app(SusunPengingatTenggat::class)->untukTanggal(
            CarbonImmutable::parse($tanggal.' 09:00', 'Asia/Makassar'),
            $hMinus,
        );
    }

    private function indikatorDenganPic(string $kode, User $pic): JadwalSnapshot
    {
        $snapshot = $this->indikatorBaru($kode);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $snapshot;
    }

    public function test_h_minus_menghasilkan_identitas_kejadian_dan_parameter(): void
    {
        $hasil = $this->susun('2026-03-14', [7, 3, 1]);

        $this->assertSame('2026-03-14', $hasil['tanggal']);
        $this->assertSame(1, $hasil['diperiksa']);
        $this->assertCount(1, $hasil['pesan']);

        $pesan = $hasil['pesan'][0];
        $this->assertSame($this->actor->id, $pesan['user_id']);
        $this->assertSame(1, $pesan['h']);
        $this->assertSame('2026-03-15', $pesan['jatuh_tempo']);
        $this->assertSame('ews:h1', $pesan['kejadian']);
        $this->assertSame($pesan['periode_jadwal_id'].':2026-03-15', $pesan['konteks_periode']);
        $this->assertSame(1, $pesan['jumlah']);
        $this->assertSame(['h' => 1, 'jatuh_tempo' => '2026-03-15', 'jumlah' => 1], $pesan['parameter']);
    }

    public function test_beberapa_indikator_satu_pic_tetap_satu_pesan(): void
    {
        $this->indikatorDenganPic('I-EWS-2', $this->actor);
        $this->indikatorDenganPic('I-EWS-3', $this->actor);

        $hasil = $this->susun('2026-03-14', [1]);

        $this->assertCount(1, $hasil['pesan']);
        $this->assertSame(3, $hasil['pesan'][0]['jumlah']);
        $this->assertSame(3, $hasil['pesan'][0]['parameter']['jumlah']);
    }

    public function test_tanggal_selain_h_minus_tidak_menghasilkan_pesan(): void
    {
        $hasil = $this->susun('2026-03-13', [7, 3, 1]);

        $this->assertSame([], $hasil['pesan']);
        $this->assertSame(1, $hasil['diperiksa']);
    }

    public function test_konteks_periode_memuat_tenggat_yang_berlaku(): void
    {
        $tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $tw1->update(['pengisian_selesai' => '2026-03-20']);

        $hasil = $this->susun('2026-03-19', [1]);

        $this->assertCount(1, $hasil['pesan']);
        $this->assertSame($tw1->id.':2026-03-20', $hasil['pesan'][0]['konteks_periode']);
        $this->assertSame('2026-03-20', $hasil['pesan'][0]['parameter']['jatuh_tempo']);
    }
}
