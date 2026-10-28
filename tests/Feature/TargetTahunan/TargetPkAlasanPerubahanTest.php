<?php

namespace Tests\Feature\TargetTahunan;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\TargetKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

/** Q72 §72.2: alasan perubahan wajib bila nilai target PK yang sudah tersimpan berubah. */
class TargetPkAlasanPerubahanTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    private const PESAN = 'Isi alasan perubahan karena target PK yang tersimpan diubah.';

    /** @param array<string, string> $alasan */
    #[DataProvider('alasanKosong')]
    public function test_ubah_target_tersimpan_tanpa_alasan_ditolak_dan_diaudit(array $alasan): void
    {
        [$actor, $indikator] = $this->konteks(['target_tahunan' => '80']);

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => '85', ...$alasan])->assertUnprocessable()->assertJsonPath('errors.alasan.0', self::PESAN);

        $this->assertSame('80.000000000000', DB::table('target_kinerjas')->value('target_tahunan'));
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $ditolak = AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole();
        $this->assertSame(['tahun' => 2026, 'alasan_penolakan' => 'alasan_perubahan_kosong'], $ditolak->nilai_baru);
        $this->assertSame(['diizinkan', 'diizinkan'], [$ditolak->dasar_izin['indikator:read']['keputusan'], $ditolak->dasar_izin['target:update']['keputusan']]);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function alasanKosong(): array
    {
        return ['tanpa alasan' => [[]], 'spasi saja' => [['alasan' => '   ']]];
    }

    public function test_ubah_target_dengan_alasan_tersimpan_dan_alasan_tercatat(): void
    {
        [$actor, $indikator] = $this->konteks(['target_tahunan' => '80']);

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => '85', 'alasan' => 'Koreksi PK triwulan QA-96'])->assertOk()->assertJsonPath('changed', true);

        $this->assertSame('85.000000000000', DB::table('target_kinerjas')->value('target_tahunan'));
        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan')->sole();
        $this->assertSame('Koreksi PK triwulan QA-96', $audit->alasan);
        $this->assertSame(['80', '85'], [$audit->nilai_lama['target_tahunan'], $audit->nilai_baru['target_tahunan']]);
    }

    public function test_mengosongkan_target_wajib_alasan_tanpa_pemeriksaan_deviasi(): void
    {
        // Target tersimpan 80 sudah menyimpang terhadap Renstra 90; target kosong tidak dinilai deviasinya (Q72 §72.1 butir 3).
        [$actor, $indikator] = $this->konteks(['target_tahunan' => '80'], renstra: '90');

        $errors = $this->simpanTargetPk($actor, $indikator, [])->assertUnprocessable()->json('errors');
        $this->assertSame(['alasan' => [self::PESAN]], $errors);

        $this->simpanTargetPk($actor, $indikator, ['alasan' => 'Target PK ditarik untuk ditinjau QA-97'])->assertOk()->assertJsonPath('changed', true);
        $this->assertNull(DB::table('target_kinerjas')->value('target_tahunan'));
    }

    /** @param array<string, string>|null $tersimpan @param array<string, string> $nilai */
    #[DataProvider('tanpaKewajiban')]
    public function test_pengisian_pertama_dan_perubahan_baseline_saja_tidak_wajib_alasan(?array $tersimpan, array $nilai): void
    {
        [$actor, $indikator] = $this->konteks($tersimpan);

        $this->simpanTargetPk($actor, $indikator, $nilai)->assertOk()->assertJsonPath('changed', true);

        $this->assertSame('Pencatatan audit untuk tindakan target_tahunan.simpan.', AuditLog::where('tindakan', 'target_tahunan.simpan')->sole()->alasan);
    }

    /** @return array<string, array{?array<string, string>, array<string, string>}> */
    public static function tanpaKewajiban(): array
    {
        return [
            'tanpa baris' => [null, ['target_tahunan' => '85']],
            'baris baseline saja' => [['baseline' => '5'], ['baseline' => '5', 'target_tahunan' => '85']],
            'hanya baseline berubah' => [['target_tahunan' => '80'], ['baseline' => '7', 'target_tahunan' => '80']],
        ];
    }

    /** @param array<string, string> $nilai */
    #[DataProvider('setaraTersimpan')]
    public function test_nilai_ternormalkan_sama_bukan_perubahan(array $nilai): void
    {
        [$actor, $indikator] = $this->konteks(['target_tahunan' => '80']);

        $this->simpanTargetPk($actor, $indikator, $nilai)->assertOk()->assertJsonPath('changed', false);

        $this->assertSame(0, AuditLog::whereIn('tindakan', ['target_tahunan.simpan', 'target_tahunan.simpan_ditolak'])->count());
    }

    /** @return array<string, array{array<string, string>}> */
    public static function setaraTersimpan(): array
    {
        return ['koma dan nol di belakang' => [['target_tahunan' => '80,00']], 'nol di depan' => [['target_tahunan' => '080']]];
    }

    /**
     * Target Renstra 80 `naik_baik` membuat target 85 tidak menyimpang, sehingga yang diuji hanya kewajiban alasan.
     *
     * @param  array<string, string>|null  $tersimpan
     * @return array{User, IndikatorKinerja}
     */
    private function konteks(?array $tersimpan, string $renstra = '80'): array
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($actor);
        $indikator->update(['arah' => 'naik_baik']);
        TargetRenstra::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'nilai' => $renstra, 'updated_by' => $actor->id]);
        if ($tersimpan !== null) {
            TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, ...$tersimpan]);
        }

        return [$actor, $indikator];
    }
}
