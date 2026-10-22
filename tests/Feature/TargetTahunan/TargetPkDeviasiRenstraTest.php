<?php

namespace Tests\Feature\TargetTahunan;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\RenstraPk;
use App\Models\TargetKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetPkDeviasiRenstraTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    private const PESAN_NAIK = 'Target PK lebih rendah dari target Renstra tahun ini. Isi alasan deviasi untuk menyimpan.';

    private const PESAN_TURUN = 'Target PK lebih tinggi dari target Renstra tahun ini, padahal indikator ini membaik bila nilainya turun. Isi alasan deviasi untuk menyimpan.';

    #[DataProvider('deviasiTanpaAlasan')]
    public function test_target_pk_menyimpang_tanpa_alasan_ditolak_dengan_pesan_menurut_arah(string $arah, string $renstra, string $pk, string $pesan): void
    {
        [$actor, $indikator] = $this->konteks($arah, $renstra);

        $this->simpan($actor, $indikator, ['target_tahunan' => $pk])
            ->assertUnprocessable()->assertJsonPath('errors.alasan_deviasi_renstra.0', $pesan);

        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->count());
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function deviasiTanpaAlasan(): array
    {
        return [
            'naik_baik di bawah' => ['naik_baik', '80', '79.99', self::PESAN_NAIK],
            'turun_baik di atas' => ['turun_baik', '80', '80.01', self::PESAN_TURUN],
            'turun_baik terhadap Renstra nol' => ['turun_baik', '0', '0.01', self::PESAN_TURUN],
        ];
    }

    public function test_alasan_deviasi_tersimpan_teraudit_dan_perubahan_alasan_saja_bukan_noop(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');

        $this->simpan($actor, $indikator, ['target_tahunan' => '79.99', 'alasan_deviasi_renstra' => 'PK revisi menurunkan target QA-91'])
            ->assertOk()->assertJsonPath('changed', true);
        $this->assertSame('PK revisi menurunkan target QA-91', DB::table('target_kinerjas')->value('alasan_deviasi_renstra'));
        $pertama = AuditLog::where('tindakan', 'target_tahunan.simpan')->sole();
        $this->assertSame('PK revisi menurunkan target QA-91', $pertama->nilai_baru['alasan_deviasi_renstra'] ?? null);

        $this->simpan($actor, $indikator, ['target_tahunan' => '79.99', 'alasan_deviasi_renstra' => 'Alasan diperbarui QA-92'])
            ->assertOk()->assertJsonPath('changed', true);
        $kedua = AuditLog::where('tindakan', 'target_tahunan.simpan')->whereNotNull('nilai_lama')->sole();
        $this->assertSame('PK revisi menurunkan target QA-91', $kedua->nilai_lama['alasan_deviasi_renstra'] ?? null);
        $this->assertSame('Alasan diperbarui QA-92', $kedua->nilai_baru['alasan_deviasi_renstra'] ?? null);
    }

    #[DataProvider('arah')]
    public function test_nilai_sama_dengan_target_renstra_tidak_memerlukan_alasan_dan_alasan_disimpan_kosong(string $arah): void
    {
        [$actor, $indikator] = $this->konteks($arah, '80');

        $this->simpan($actor, $indikator, ['target_tahunan' => '80', 'alasan_deviasi_renstra' => 'Tidak diperlukan'])->assertOk()->assertJsonPath('changed', true);

        $this->assertSame('80.000000000000', DB::table('target_kinerjas')->value('target_tahunan'));
        $this->assertNull(DB::table('target_kinerjas')->value('alasan_deviasi_renstra'));
    }

    /** @return array<string, array{string}> */
    public static function arah(): array
    {
        return ['naik_baik' => ['naik_baik'], 'turun_baik' => ['turun_baik']];
    }

    #[DataProvider('renstraBelumAda')]
    public function test_target_renstra_belum_ada_tidak_memerlukan_alasan_dan_alasan_disimpan_kosong(bool $denganBaris): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', null, $denganBaris);

        $this->simpan($actor, $indikator, ['target_tahunan' => '10', 'alasan_deviasi_renstra' => 'Tidak diperlukan'])->assertOk()->assertJsonPath('changed', true);

        $this->assertSame('10.000000000000', DB::table('target_kinerjas')->value('target_tahunan'));
        $this->assertNull(DB::table('target_kinerjas')->value('alasan_deviasi_renstra'));
    }

    /** @return array<string, array{bool}> */
    public static function renstraBelumAda(): array
    {
        return ['baris tidak ada' => [false], 'nilai kosong' => [true]];
    }

    public function test_alasan_dikosongkan_saat_target_pk_tidak_lagi_menyimpang(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');
        $this->simpan($actor, $indikator, ['target_tahunan' => '79.99', 'alasan_deviasi_renstra' => 'Sempat di bawah'])->assertOk();

        $this->simpan($actor, $indikator, ['target_tahunan' => '85', 'alasan_deviasi_renstra' => 'Sempat di bawah'])->assertOk()->assertJsonPath('changed', true);

        $this->assertNull(DB::table('target_kinerjas')->value('alasan_deviasi_renstra'));
    }

    public function test_simpan_tanpa_perubahan_pada_baris_lama_yang_menyimpang_tetap_noop(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '70']);

        $this->simpan($actor, $indikator, ['target_tahunan' => '70'])->assertOk()->assertJsonPath('changed', false);

        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->count());
    }

    public function test_editor_dan_halaman_pk_memuat_target_renstra_dan_status_deviasi(): void
    {
        [$actor, $indikator] = $this->konteks('turun_baik', '80');
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '85', 'alasan_deviasi_renstra' => 'Tersimpan QA-93']);

        $this->actingAs($actor)->getJson($this->targetPkUrl($indikator, '/editor'))->assertOk()
            ->assertJsonPath('target_renstra', '80')->assertJsonPath('status_deviasi', 'menyimpang')
            ->assertJsonPath('alasan_deviasi_renstra', 'Tersimpan QA-93')->assertJsonPath('indikator.arah', 'turun_baik');

        $this->get('/perjanjian-kinerja/'.RenstraPk::sole()->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('target_pk.0.indikator.0.target_renstra', '80')
                ->where('target_pk.0.indikator.0.status_deviasi', 'menyimpang')->where('target_pk.0.indikator.0.arah', 'turun_baik'));
    }

    /** @return array{User, IndikatorKinerja} */
    private function konteks(string $arah, ?string $renstra, bool $denganBaris = true): array
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($actor);
        $indikator->update(['arah' => $arah]);
        if ($denganBaris) {
            TargetRenstra::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'nilai' => $renstra, 'updated_by' => $actor->id]);
        }

        return [$actor, $indikator];
    }

    /** @param array<string, mixed> $nilai */
    private function simpan(User $actor, IndikatorKinerja $indikator, array $nilai): TestResponse
    {
        $state = $this->actingAs($actor)->getJson($this->targetPkUrl($indikator, '/editor'))->assertOk()->json('expected_state');

        return $this->putJson($this->targetPkUrl($indikator), ['baseline' => null, 'target_tahunan' => null, 'expected_state' => $state,
            'operation_id' => (string) Str::uuid(), ...$nilai]);
    }
}
