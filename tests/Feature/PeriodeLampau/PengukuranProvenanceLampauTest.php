<?php

namespace Tests\Feature\PeriodeLampau;

use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PengukuranPeriodeLampauFixtures;
use Tests\TestCase;

/**
 * Provenance pengajuan pengukuran periode lampau (ISS-07.05 AC-3): nilai
 * terikat snapshot jadwal yang sah, jalur Perencanaan dibekukan dari sumber
 * allow peran, dan audit mencatat aktor beserta dasar izinnya (Data Model
 * §2.20; ADR-0002; ADR-0004). Pembacaan versi hanya baca karena
 * `pengukuran_versi` append-only.
 */
class PengukuranProvenanceLampauTest extends TestCase
{
    use PengukuranPeriodeLampauFixtures, RefreshDatabase;

    public function test_versi_lampau_membekukan_jalur_perencanaan_dan_dasar_izin_dari_peran(): void
    {
        [$fixture, $pengukuran] = $this->pengajuanLampau();

        $versi = DB::table('pengukuran_versi')->where('pengukuran_id', $pengukuran->id)->sole();
        $dasar = json_decode($versi->dasar_izin_pengajuan, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('perencanaan', $versi->jalur_pengajuan);
        $this->assertSame('pengukuran:update', $dasar['permission']);
        $this->assertSame('allow', $dasar['reason']);
        $this->assertNotEmpty($dasar['roles']);
        $this->assertSame([], $dasar['grants']);
        $this->assertSame($fixture['unit']->id, $dasar['unit_id']);
        $this->assertSame($fixture['perencanaan']->id, $versi->diajukan_by);
    }

    public function test_audit_pengajuan_lampau_mencatat_aktor_dan_dasar_izin(): void
    {
        [$fixture, $pengukuran] = $this->pengajuanLampau();

        $audit = AuditLog::where('tindakan', 'pengukuran.ajukan')->where('objek_id', $pengukuran->id)->sole();

        $this->assertSame($fixture['perencanaan']->id, $audit->actor_id);
        $this->assertSame('pengukuran', $audit->objek_tipe);
        $this->assertSame('pengukuran:update', $audit->dasar_izin['permission']);
        $this->assertSame('allow', $audit->dasar_izin['reason']);
        // Nilai yang diajukan ikut terekam pada state sesudah (auditState header).
        $this->assertSame(80.0, (float) $audit->nilai_baru['nilai']);
        $this->assertSame('perencanaan', $audit->nilai_baru['versi_pengajuan']['jalur_pengajuan']);
        $this->assertSame('pengukuran:update', $audit->nilai_baru['versi_pengajuan']['dasar_izin_pengajuan']['permission']);
    }

    public function test_snapshot_versi_sama_dengan_snapshot_header(): void
    {
        [$fixture, $pengukuran] = $this->pengajuanLampau();

        $versi = KinerjaSnapshot::where('pengukuran_id', $pengukuran->id)->sole();
        $header = $pengukuran->fresh();
        $sah = JadwalSnapshot::findOrFail($versi->jadwal_snapshot_id);

        $this->assertSame($header->jadwal_snapshot_id, $versi->jadwal_snapshot_id);
        $this->assertSame($fixture['snapshot']->id, $versi->jadwal_snapshot_id);
        $this->assertSame([$fixture['indikator']->id, $fixture['unit']->id], [$sah->indikator_id, $sah->unit_id]);
        $this->assertSame($header->periode_id, $versi->snapshot['periode']['id']);
        $this->assertSame($header->tahun, $versi->snapshot['tahun']);
        $this->assertSame($sah->target, $versi->snapshot['target_pk']);
        $this->assertSame($header->nilai, $versi->snapshot['nilai']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: PengukuranKinerja}
     */
    private function pengajuanLampau(): array
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();
        $pengukuran = $this->drafPengukuran($fixture, $fixture['periode1']);
        $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 80])->assertRedirect('/pengukuran');

        return [$fixture, $pengukuran];
    }
}
