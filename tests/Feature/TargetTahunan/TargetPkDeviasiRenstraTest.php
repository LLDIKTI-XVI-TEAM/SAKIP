<?php

namespace Tests\Feature\TargetTahunan;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\TargetKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TargetRenstraFixtures;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

/** Q72 §72.1: target PK yang berubah dan menyimpang dari target Renstra menurut arah indikator ditolak. */
class TargetPkDeviasiRenstraTest extends TestCase
{
    use RefreshDatabase, TargetRenstraFixtures, TargetTahunanFixtures;

    #[DataProvider('menyimpang')]
    public function test_target_pk_menyimpang_ditolak_dengan_pesan_menurut_arah(string $arah, string $renstra, ?string $tersimpan, string $pk, string $pesan): void
    {
        [$actor, $indikator] = $this->konteks($arah, $renstra);
        if ($tersimpan !== null) {
            TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => $tersimpan]);
        }

        // Q72 §72.2 butir 1: alasan perubahan menggantikan alasan deviasi, jadi tidak meloloskan nilai menyimpang.
        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => $pk, ...($tersimpan !== null ? ['alasan' => 'Koreksi PK QA-94'] : [])])
            ->assertUnprocessable()->assertJsonPath('errors.deviasi_renstra.0', $pesan);

        $this->assertSame($tersimpan === null ? [] : ['80.000000000000'], DB::table('target_kinerjas')->pluck('target_tahunan')->all());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $ditolak = AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole();
        $this->assertSame(['tahun' => 2026, 'alasan_penolakan' => 'target_menyimpang_renstra'], $ditolak->nilai_baru);
        $this->assertSame(['diizinkan', 'diizinkan'], [$ditolak->dasar_izin['indikator:read']['keputusan'], $ditolak->dasar_izin['target:update']['keputusan']]);
    }

    /** @return array<string, array{string, string, ?string, string, string}> */
    public static function menyimpang(): array
    {
        return [
            'naik_baik di bawah' => ['naik_baik', '80', null, '79.99', 'Target PK di bawah target Renstra 2026 (80). Revisi target Renstra dulu.'],
            'turun_baik di atas' => ['turun_baik', '80', null, '80.01', 'Target PK di atas target Renstra 2026 (80). Revisi target Renstra dulu.'],
            'turun_baik terhadap Renstra nol' => ['turun_baik', '0', null, '0.01', 'Target PK di atas target Renstra 2026 (0). Revisi target Renstra dulu.'],
            // Angka pesan tanpa pembulatan tampilan, sama dengan nilai yang dibandingkan.
            'naik_baik terhadap Renstra tiga desimal' => ['naik_baik', '85.004', null, '85', 'Target PK di bawah target Renstra 2026 (85,004). Revisi target Renstra dulu.'],
            'baris tersimpan diubah dengan alasan' => ['naik_baik', '80', '80', '79', 'Target PK di bawah target Renstra 2026 (80). Revisi target Renstra dulu.'],
        ];
    }

    public function test_menyimpang_sekaligus_tanpa_alasan_hanya_memunculkan_pesan_deviasi(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '80']);

        $errors = $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => '79'])->assertUnprocessable()->json('errors');

        $this->assertSame(['deviasi_renstra'], array_keys($errors));
        $this->assertSame('target_menyimpang_renstra', AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole()->nilai_baru['alasan_penolakan']);
    }

    public function test_renstra_aktif_juga_menolak_target_menyimpang(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_AKTIF);

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => '79'])->assertUnprocessable()
            ->assertJsonPath('errors.deviasi_renstra.0', 'Target PK di bawah target Renstra 2026 (80). Revisi target Renstra dulu.');

        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame('target_menyimpang_renstra', AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole()->nilai_baru['alasan_penolakan']);
    }

    #[DataProvider('sesuai')]
    public function test_target_sama_atau_lebih_baik_tersimpan(string $arah, string $pk, string $tersimpan): void
    {
        [$actor, $indikator] = $this->konteks($arah, '80');

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => $pk])->assertOk()->assertJsonPath('changed', true);

        $this->assertSame($tersimpan, DB::table('target_kinerjas')->value('target_tahunan'));
    }

    /** @return array<string, array{string, string, string}> */
    public static function sesuai(): array
    {
        return [
            'naik_baik sama' => ['naik_baik', '80', '80.000000000000'],
            'naik_baik lebih tinggi' => ['naik_baik', '80.01', '80.010000000000'],
            'turun_baik sama' => ['turun_baik', '80', '80.000000000000'],
            'turun_baik lebih rendah' => ['turun_baik', '79.99', '79.990000000000'],
        ];
    }

    /** @param array<string, string> $nilai */
    #[DataProvider('tanpaPembanding')]
    public function test_renstra_belum_ada_atau_target_kosong_tidak_ditolak_karena_deviasi(?string $renstra, bool $denganBaris, array $nilai, ?string $target): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', $renstra, $denganBaris);

        $this->simpanTargetPk($actor, $indikator, $nilai)->assertOk()->assertJsonPath('changed', true);

        $this->assertSame($target, DB::table('target_kinerjas')->value('target_tahunan'));
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->count());
    }

    /** @return array<string, array{?string, bool, array<string, string>, ?string}> */
    public static function tanpaPembanding(): array
    {
        return [
            'baris Renstra tidak ada' => [null, false, ['target_tahunan' => '10'], '10.000000000000'],
            'nilai Renstra kosong' => [null, true, ['target_tahunan' => '10'], '10.000000000000'],
            'target PK kosong' => ['80', true, ['baseline' => '5'], null],
        ];
    }

    public function test_setelah_target_renstra_direvisi_simpan_ulang_tanpa_muat_ulang_berhasil(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');
        // Mode revisi hanya untuk Renstra aktif.
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_AKTIF);
        $renstraId = $indikator->sasaranStrategis->renstra_id;
        $state = $this->actingAs($actor)->getJson($this->targetPkUrl($indikator, '/editor'))->assertOk()->json('expected_state');
        $payload = ['baseline' => null, 'target_tahunan' => '75', 'expected_state' => $state];
        $this->putJson($this->targetPkUrl($indikator), [...$payload, 'operation_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('deviasi_renstra');

        $this->mulaiRevisi($actor, $renstraId)->assertSessionHasNoErrors();
        $this->simpanDrafRevisi($actor, $renstraId, 1, [$indikator->id => [2026 => '75']])->assertSessionHasNoErrors();
        $this->simpanRevisi($actor, $renstraId, 2, 'Target Renstra 2026 disesuaikan PK QA-95.')->assertSessionHasNoErrors();

        // Target Renstra tidak masuk token, sehingga form yang sama dapat disimpan ulang tanpa memuat ulang editor.
        $this->putJson($this->targetPkUrl($indikator), [...$payload, 'operation_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('changed', true);
        $this->assertSame('75.000000000000', DB::table('target_kinerjas')->value('target_tahunan'));
    }

    public function test_baris_lama_menyimpang_tetap_dapat_menyimpan_baseline_dan_lencana_tetap_tampil(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '70']);

        // Q72 §72.1 butir 6 dan §72.4 butir 1: deviasi hanya dinilai bila target berubah, jadi perubahan baseline saja tetap tersimpan.
        $this->simpanTargetPk($actor, $indikator, ['baseline' => '60', 'target_tahunan' => '70'])->assertOk()->assertJsonPath('changed', true);
        $this->assertSame(['60.000000000000', '70.000000000000'], [DB::table('target_kinerjas')->value('baseline'), DB::table('target_kinerjas')->value('target_tahunan')]);
        $this->simpanTargetPk($actor, $indikator, ['baseline' => '60', 'target_tahunan' => '70'])->assertOk()->assertJsonPath('changed', false);
        $this->assertSame(1, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->count());

        $this->actingAs($actor)->getJson($this->targetPkUrl($indikator, '/editor'))->assertOk()->assertJsonPath('status_deviasi', 'menyimpang');
        $this->get('/perjanjian-kinerja/'.RenstraPk::sole()->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('target_pk.0.indikator.0.status_deviasi', 'menyimpang'));
    }

    public function test_alasan_deviasi_tidak_lagi_diterima_dan_nilai_lama_tetap_utuh(): void
    {
        [$actor, $indikator] = $this->konteks('naik_baik', '80');
        $target = TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '85', 'alasan_deviasi_renstra' => 'Tersimpan QA-93']);
        $lama = app(AuditLogger::class)->catat(actor: $actor, tindakan: 'target_tahunan.simpan', objekTipe: 'target_tahunan', objekId: $target->id,
            nilaiBaru: ['indikator_id' => $indikator->id, 'tahun' => 2026, 'baseline' => null, 'target_tahunan' => '85', 'alasan_deviasi_renstra' => 'Tersimpan QA-93']);

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => '90', 'alasan' => 'Koreksi PK QA-93', 'alasan_deviasi_renstra' => 'Tidak dipakai lagi'])
            ->assertUnprocessable()->assertJsonPath('errors.target_tahunan.0', 'Permintaan memuat field yang tidak didukung.');
        $this->assertSame('input_tidak_valid', AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole()->nilai_baru['alasan_penolakan']);

        $this->simpanTargetPk($actor, $indikator, ['target_tahunan' => '90', 'alasan' => 'Koreksi PK QA-93'])->assertOk()->assertJsonPath('changed', true);
        // Q72 §72.2 butir 4: kolom lama hanya histori; tidak ditulis lagi dan tidak masuk audit baru.
        $this->assertSame('Tersimpan QA-93', DB::table('target_kinerjas')->value('alasan_deviasi_renstra'));
        $baru = AuditLog::where('tindakan', 'target_tahunan.simpan')->whereKeyNot($lama->id)->sole();
        $this->assertArrayNotHasKey('alasan_deviasi_renstra', $baru->nilai_lama);
        $this->assertArrayNotHasKey('alasan_deviasi_renstra', $baru->nilai_baru);
        $this->assertSame('Tersimpan QA-93', $lama->fresh()->nilai_baru['alasan_deviasi_renstra']);
    }

    public function test_editor_mengirim_tautan_target_renstra_hanya_bila_dapat_mengubah(): void
    {
        [$actor, $indikator] = $this->konteks('turun_baik', '80');
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '85', 'alasan_deviasi_renstra' => 'Tersimpan QA-93']);
        $renstraId = $indikator->sasaranStrategis->renstra_id;

        $this->actingAs($actor)->getJson($this->targetPkUrl($indikator, '/editor'))->assertOk()
            ->assertJsonMissingPath('alasan_deviasi_renstra')->assertJsonPath('can.update', true)
            ->assertJsonPath('tautan_target_renstra', "/perencanaan/sasaran-indikator?renstra_id={$renstraId}&tab=target")
            ->assertJsonPath('target_renstra', '80')->assertJsonPath('status_deviasi', 'menyimpang')->assertJsonPath('indikator.arah', 'turun_baik');
        $this->get('/perjanjian-kinerja/'.RenstraPk::sole()->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('target_pk.0.indikator.0.target_renstra', '80')
                ->where('target_pk.0.indikator.0.status_deviasi', 'menyimpang')->where('target_pk.0.indikator.0.arah', 'turun_baik'));

        $indikator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->assertTautan($actor, $indikator, null);
        $indikator->update(['status' => IndikatorKinerja::STATUS_AKTIF]);
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_NONAKTIF);
        $this->assertTautan($actor, $indikator, null);
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_AKTIF);
        $this->assertTautan($actor, $indikator, "/perencanaan/sasaran-indikator?renstra_id={$renstraId}&tab=target");
        // Role fixture dipakai bersama: aktor baru tanpa target:update ikut mencabut izin aktor pertama.
        $this->assertTautan($this->calendarActor(['indikator:read']), $indikator, null);
    }

    private function assertTautan(User $actor, IndikatorKinerja $indikator, ?string $tautan): void
    {
        $this->actingAs($actor)->getJson($this->targetPkUrl($indikator, '/editor'))->assertOk()
            ->assertJsonPath('can.update', $tautan !== null)->assertJsonPath('tautan_target_renstra', $tautan);
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
}
