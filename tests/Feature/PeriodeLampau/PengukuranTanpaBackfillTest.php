<?php

namespace Tests\Feature\PeriodeLampau;

use App\Models\AuditLog;
use App\Models\KinerjaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PengukuranPeriodeLampauFixtures;
use Tests\TestCase;

/**
 * Bukti penegasan ISS-07.05 bahwa periode lampau 2026 tidak memakai mekanisme
 * backfill: tidak ada kolom `is_backfill` pada skema pengukuran, request
 * mengabaikan penanda itu (bukan `prohibited`), dan `sumber_nilai` tetap
 * `manual`/`komponen` (Data Model §2.20, §8.4; Workflow §15 butir 1; Q46 §46.1
 * butir 2).
 */
class PengukuranTanpaBackfillTest extends TestCase
{
    use PengukuranPeriodeLampauFixtures, RefreshDatabase;

    public function test_skema_pengukuran_tidak_memiliki_kolom_is_backfill(): void
    {
        foreach (['pengukuran_kinerjas', 'pengukuran_versi', 'pengukuran_komponen', 'jadwal_tahunan', 'jadwal_periode'] as $tabel) {
            $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ditemukan.");
            $this->assertFalse(Schema::hasColumn($tabel, 'is_backfill'), "Tabel {$tabel} tidak boleh memiliki kolom is_backfill.");
        }
    }

    public function test_request_pengukuran_mengabaikan_is_backfill(): void
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();
        $pengukuran = $this->drafPengukuran($fixture, $fixture['periode1']);

        // `is_backfill` tidak termasuk field `prohibited`, jadi request tetap
        // diproses dan penanda klien tidak boleh bocor ke snapshot versi.
        $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 80, 'is_backfill' => true])
            ->assertRedirect('/pengukuran');
        $versi = KinerjaSnapshot::sole();
        $this->assertArrayNotHasKey('is_backfill', $versi->snapshot);

        // Penanda klien tidak membuka jalur PIC pada periode lampau.
        $drafPic = $this->drafPengukuran($fixture, $fixture['periode2']);
        $this->kirimPengukuran($drafPic, $fixture['pic'], ['nilai' => 80, 'is_backfill' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertSame(1, KinerjaSnapshot::count());
    }

    #[DataProvider('sumberNilaiLampau')]
    public function test_pengajuan_lampau_memakai_sumber_nilai_manual_atau_komponen(string $tipe): void
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();

        if ($tipe === 'manual') {
            $pengukuran = $this->drafPengukuran($fixture, $fixture['periode1']);
            $payload = ['nilai' => 75];
        } else {
            [, $snapshot, $komponen] = $this->indikatorPenjumlahanLampau($fixture);
            $pengukuran = $this->drafPengukuran($fixture, $fixture['periode1'], null, $snapshot);
            $payload = ['komponen' => [['komponen_id' => $komponen->id, 'nilai' => 7]]];
        }

        $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], $payload)->assertRedirect('/pengukuran');

        $versi = KinerjaSnapshot::sole();
        $this->assertSame($tipe, $pengukuran->fresh()->sumber_nilai);
        $this->assertSame($tipe, $versi->snapshot['sumber_nilai']);
        $this->assertSame('terhitung', $versi->snapshot['status_perhitungan']);
        $this->assertNotSame('historis', $versi->snapshot['sumber_nilai']);
    }

    public static function sumberNilaiLampau(): array
    {
        return ['manual' => ['manual'], 'komponen' => ['komponen']];
    }

    public function test_sumber_nilai_historis_ditolak_sebagai_backfill_resmi(): void
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();
        $pengukuran = $this->drafPengukuran($fixture, $fixture['periode1']);
        $pengukuran->update(['sumber_nilai' => 'historis', 'alasan_historis' => 'Backfill manual.',
            'sumber_historis' => 'Laporan Kinerja 2025']);

        $response = $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 75]);

        $response->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertContains('Koreksi nilai historis memerlukan alur backfill resmi.', $response->json('errors.versi'));
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(['draft', 1], [$pengukuran->fresh()->status_alur, $pengukuran->fresh()->versi]);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }
}
