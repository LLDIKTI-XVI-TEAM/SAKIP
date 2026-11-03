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

/** Q82: perubahan target PK tidak meminta alasan maupun rujukan sumber; jejaknya nilai lama dan baru di audit. */
class TargetPkAlasanPerubahanTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    private const ALASAN_BAKU = 'Pencatatan audit untuk tindakan target_tahunan.simpan.';

    #[DataProvider('perubahanTersimpan')]
    public function test_ubah_target_tersimpan_tanpa_alasan_tersimpan_dan_diaudit(?string $baru, ?string $tersimpanDb): void
    {
        // Renstra 90 membuat target tersimpan 80 menyimpang; mengosongkan target tidak dinilai deviasinya (Q72 §72.1 butir 3).
        [$actor, $indikator] = $this->konteks(['target_tahunan' => '80'], renstra: $baru === null ? '90' : '80');

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => $baru])->assertOk()->assertJsonPath('changed', true);

        $this->assertSame($tersimpanDb, DB::table('target_kinerjas')->value('target_tahunan'));
        $audit = DB::table('audit_log')->where('tindakan', 'target_tahunan.simpan')->sole();
        $this->assertSame(self::ALASAN_BAKU, $audit->alasan);
        $this->assertEquals(['indikator_id' => $indikator->id, 'tahun' => 2026, 'baseline' => null, 'target_tahunan' => '80'], json_decode($audit->nilai_lama, true));
        $this->assertEquals(['indikator_id' => $indikator->id, 'tahun' => 2026, 'baseline' => null, 'target_tahunan' => $baru], json_decode($audit->nilai_baru, true));
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->count());
    }

    /** @return array<string, array{?string, ?string}> */
    public static function perubahanTersimpan(): array
    {
        return ['diubah' => ['85', '85.000000000000'], 'dikosongkan' => [null, null]];
    }

    /** @param array<string, string> $lama */
    #[DataProvider('isianDihapus')]
    public function test_request_dengan_alasan_atau_rujukan_ditolak_sebagai_field_tidak_didukung(array $lama): void
    {
        [$actor, $indikator] = $this->konteks(['target_tahunan' => '80']);

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => '85', ...$lama])->assertUnprocessable()
            ->assertJsonPath('errors.target_tahunan.0', 'Permintaan memuat field yang tidak didukung.');

        $this->assertSame('80.000000000000', DB::table('target_kinerjas')->value('target_tahunan'));
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $this->assertSame(['tahun' => 2026, 'alasan_penolakan' => 'input_tidak_valid'], AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole()->nilai_baru);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function isianDihapus(): array
    {
        // Editor lama selalu mengirim kedua isian, termasuk sebagai teks kosong.
        return [
            'alasan' => [['alasan' => 'Koreksi PK QA-96']],
            'rujukan sumber' => [['rujukan_sumber' => 'SK QA-96']],
            'keduanya kosong' => [['alasan' => '', 'rujukan_sumber' => '']],
        ];
    }

    /** @param array<string, string>|null $tersimpan @param array<string, string> $nilai */
    #[DataProvider('tanpaKewajiban')]
    public function test_pengisian_pertama_dan_perubahan_baseline_saja_tidak_wajib_alasan(?array $tersimpan, array $nilai): void
    {
        [$actor, $indikator] = $this->konteks($tersimpan);

        $this->simpanTargetPk($actor, $indikator, $nilai)->assertOk()->assertJsonPath('changed', true);

        $this->assertSame(self::ALASAN_BAKU, AuditLog::where('tindakan', 'target_tahunan.simpan')->sole()->alasan);
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
     * Target Renstra 80 `naik_baik` membuat target 85 tidak menyimpang, sehingga yang diuji hanya kontrak alasan.
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
