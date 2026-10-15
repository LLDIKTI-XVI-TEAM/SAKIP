<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\AjukanRencanaAksi;
use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Models\AuditLog;
use App\Models\JadwalSnapshotKomponen;
use App\Models\RencanaAksiVersi;
use App\Services\RencanaAksi\PeriodeTanpaTarget;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

class RencanaAksiTanpaTargetTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    public function test_annual_empty_target_can_be_submitted_reviewed_and_ratified(): void
    {
        $fixture = $this->fixtureTahunan();
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [$this->target($fixture['periode1']->id, null), $this->target($fixture['periode2']->id, 100)],
            tambahan: ['expected_snapshot_versi' => 2]);

        $version = app(AjukanRencanaAksi::class)->handle($fixture['pic'], $header->id, ['expected_versi' => 2]);
        $this->assertSame([null, 'tidak_ditargetkan', []], [$version->snapshot['target_periode'][0]['nilai'],
            $version->snapshot['target_periode'][0]['status_perhitungan'], $version->snapshot['target_periode'][0]['komponen']]);
        $this->assertSame(['100.00', 'terhitung'], [$version->snapshot['target_periode'][1]['nilai'], $version->snapshot['target_periode'][1]['status_perhitungan']]);
        $this->get('/rencana-aksi/'.$header->id)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.periode.0.skor.status_perhitungan', 'tidak_ditargetkan'));

        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id,
            ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $version->id]);
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id,
            ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $version->id]);
        $this->assertSame('disahkan', $header->fresh()->status_alur);
        $this->assertSame($fixture['perencanaan']->id, $version->fresh()->disahkan_by);
    }

    #[DataProvider('targetWajib')]
    public function test_normal_required_or_partial_target_is_rejected(bool $annual, bool $finalEmpty, bool $partial): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $fixture = $annual ? $this->fixtureTahunan($partial) : $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);
        $targets = $partial ? [
            $this->target($fixture['periode1']->id, 0, $fixture['pembilang']->id),
            $this->target($fixture['periode1']->id, null, $fixture['penyebut']->id),
            ...$this->rasio($fixture, $fixture['periode2'], 100, 100),
        ] : [$this->target($fixture['periode1']->id, $finalEmpty ? 100 : null), $this->target($fixture['periode2']->id, $finalEmpty ? null : 100)];
        $this->simpan($fixture, $header, 1, $targets, tambahan: ['expected_snapshot_versi' => $annual ? 2 : 1]);

        try {
            app(AjukanRencanaAksi::class)->handle($fixture['pic'], $header->id, ['expected_versi' => 2]);
            $this->fail('Target wajib atau komponen yang baru terisi sebagian harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pengajuan', $exception->errors());
        }
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->assertSame(['draft', 2], [$header->fresh()->status_alur, $header->fresh()->versi]);
    }

    public static function targetWajib(): array
    {
        return ['nonannual kosong' => [false, false, false], 'final tahunan kosong' => [true, true, false],
            'komponen tahunan baru sebagian' => [true, false, true]];
    }

    public function test_annual_zero_target_remains_calculated(): void
    {
        $fixture = $this->fixtureTahunan();
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [$this->target($fixture['periode1']->id, 0), $this->target($fixture['periode2']->id, 100)],
            tambahan: ['expected_snapshot_versi' => 2]);
        $version = app(AjukanRencanaAksi::class)->handle($fixture['pic'], $header->id, ['expected_versi' => 2]);
        $this->assertSame(['0.00', 'terhitung'], [$version->snapshot['target_periode'][0]['nilai'], $version->snapshot['target_periode'][0]['status_perhitungan']]);
    }

    public function test_nonannual_period_past_at_activation_can_have_no_target(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 1)->setTime(1, 0));
        $fixture = $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));
        $this->simpan($fixture, $header, 1, [$this->target($fixture['periode1']->id, null), $this->target($fixture['periode2']->id, 100)], $fixture['perencanaan']);
        $version = app(AjukanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, ['expected_versi' => 2]);
        $this->assertSame('tidak_ditargetkan', $version->snapshot['target_periode'][0]['status_perhitungan']);
    }

    public function test_all_empty_annual_components_keep_the_frozen_definitions(): void
    {
        $fixture = $this->fixtureTahunan(true);
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [
            $this->target($fixture['periode1']->id, null, $fixture['pembilang']->id), $this->target($fixture['periode1']->id, null, $fixture['penyebut']->id),
            ...$this->rasio($fixture, $fixture['periode2'], 100, 100),
        ], tambahan: ['expected_snapshot_versi' => 2]);
        $version = app(AjukanRencanaAksi::class)->handle($fixture['pic'], $header->id, ['expected_versi' => 2]);
        $row = $version->snapshot['target_periode'][0];
        $this->assertSame([null, 'tidak_ditargetkan'], [$row['nilai'], $row['status_perhitungan']]);
        $this->assertSame([$fixture['pembilang']->id, $fixture['penyebut']->id], array_column($row['komponen'], 'komponen_id'));
        $this->assertSame([null, null], array_column($row['komponen'], 'nilai'));
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id,
            ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $version->id]);
        $this->assertSame('diverifikasi', $header->fresh()->status_alur);
    }

    public function test_annual_marker_edit_is_audited_without_rewriting_existing_snapshot(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $fixture = $this->buatFixtureManual();
        $indicator = $fixture['indikator'];
        $this->actingAs($fixture['perencanaan'])->put('/perencanaan/indikator/'.$indicator->id,
            [...$indicator->only(['sasaran_strategis_id', 'unit_id', 'kode', 'nama', 'satuan', 'tipe_perhitungan', 'arah', 'presisi', 'desimal_tampilan']),
                'expected_updated_at' => $indicator->updated_at->toISOString(), 'dinilai_tahunan' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($indicator->fresh()->dinilai_tahunan);
        $this->assertFalse($fixture['snapshot']->fresh()->dinilai_tahunan);
        $this->assertTrue(AuditLog::where('tindakan', 'indikator.ubah')->where('objek_id', $indicator->id)->sole()->nilai_baru['dinilai_tahunan']);
        $this->get('/perencanaan/indikator/'.$indicator->id.'/editor')->assertJsonPath('indikator.dinilai_tahunan', true);
    }

    #[DataProvider('penandaRusak')]
    public function test_review_rejects_invalid_no_target_snapshot(string $case): void
    {
        $ratio = $case === 'komponen sebagian';
        $fixture = $this->fixtureTahunan($ratio);
        $header = $this->buatDraf($fixture);
        $targets = $ratio ? [
            $this->target($fixture['periode1']->id, null, $fixture['pembilang']->id), $this->target($fixture['periode1']->id, null, $fixture['penyebut']->id),
            ...$this->rasio($fixture, $fixture['periode2'], 100, 100),
        ] : [$this->target($fixture['periode1']->id, null), $this->target($fixture['periode2']->id, 100)];
        $this->simpan($fixture, $header, 1, $targets, tambahan: ['expected_snapshot_versi' => 2]);
        $version = app(AjukanRencanaAksi::class)->handle($fixture['pic'], $header->id, ['expected_versi' => 2]);
        $broken = $version->snapshot;
        if ($case === 'final normal') {
            $broken['target_periode'][1]['nilai'] = null;
            $broken['target_periode'][1]['status_perhitungan'] = 'tidak_ditargetkan';
        } elseif ($case === 'nol bukan kosong') {
            $broken['target_periode'][0]['nilai'] = '0';
        } else {
            $broken['target_periode'][0]['komponen'][0]['nilai'] = '0';
        }
        // Versi rusak dibuat baru untuk menguji reader; versi immutable yang valid tidak ditimpa.
        $forged = RencanaAksiVersi::create([...$version->only(['rencana_aksi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan']),
            'nomor' => 2, 'snapshot' => $broken]);
        try {
            app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id,
                ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $forged->id]);
            $this->fail('Snapshot tanpa target yang tidak sah harus ditolak saat review.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame('diajukan', $header->fresh()->status_alur);
        $this->assertNull($forged->fresh()->disahkan_at);
    }

    public static function penandaRusak(): array
    {
        return ['final normal' => ['final normal'], 'nol bukan kosong' => ['nol bukan kosong'], 'komponen sebagian' => ['komponen sebagian']];
    }

    public function test_annual_marker_is_immutable_on_published_snapshot(): void
    {
        $fixture = $this->fixtureTahunan();
        try {
            DB::transaction(fn () => DB::table('jadwal_snapshot')->where('id', $fixture['snapshot']->id)->update(['dinilai_tahunan' => false]));
            $this->fail('Penanda snapshot terbit tidak boleh berubah.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', (string) $exception->getCode());
        }
        $this->assertTrue($fixture['snapshot']->fresh()->dinilai_tahunan);
    }

    #[DataProvider('batasAktivasi')]
    public function test_past_period_uses_activation_business_date(string $activatedAt, bool $expected): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->activated_at = $activatedAt;
        $window = $fixture['jadwal']->periode()->where('periode_id', $fixture['periode1']->id)->firstOrFail();
        $this->assertSame($expected, PeriodeTanpaTarget::isPeriodeLampau($fixture['jadwal'], $window));
    }

    public static function batasAktivasi(): array
    {
        return ['hari yang sama WITA' => ['2026-03-31 15:59:59', false], 'hari berikutnya WITA' => ['2026-03-31 16:00:00', true]];
    }

    /** @return array<string, mixed> */
    private function fixtureTahunan(bool $rasio = false): array
    {
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $fixture = $rasio ? $this->buatFixtureRasio() : $this->buatFixtureManual();
        $source = $fixture['snapshot'];
        $snapshot = $this->terbitkanSnapshotKoreksi($fixture, ['dinilai_tahunan' => true, 'komposisi_final' => false, 'tipe_perhitungan' => $source->tipe_perhitungan]);
        foreach ($source->komponen as $component) {
            JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $snapshot->id,
                ...$component->only(['komponen_id', 'kode', 'label', 'peran', 'bobot', 'urutan'])]);
        }
        $snapshot->update(['komposisi_final' => true]);
        $fixture['snapshot'] = $snapshot;

        return $fixture;
    }

    private function target(string $periodeId, string|int|null $value, ?string $componentId = null): array
    {
        return ['periode_id' => $periodeId, 'komponen_id' => $componentId, 'nilai' => $value, 'keterangan' => null];
    }
}
