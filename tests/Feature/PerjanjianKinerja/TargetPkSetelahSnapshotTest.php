<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\Jadwal\ActivateJadwal;
use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Services\RencanaAksi\KesiapanPengajuan;
use App\Support\DeviasiTargetPk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class TargetPkSetelahSnapshotTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    public function test_snapshot_menyalin_target_pk_bernilai_penuh(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00', 'Asia/Makassar'));
        $this->readyJadwal(withIndicator: false);
        $indikator = $this->eligibleIndicator(target: '94.05', baseline: '74.2');

        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());

        $snapshot = JadwalSnapshot::where('indikator_id', $indikator->id)->sole();
        $this->assertSame('94.050000000000', $snapshot->getRawOriginal('target'));
        $this->assertSame('74.200000000000', $snapshot->getRawOriginal('baseline'));
    }

    public function test_ubah_target_setelah_snapshot_tanpa_alasan_diterima_dan_pembaca_tetap_memakai_snapshot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00', 'Asia/Makassar'));
        $this->readyJadwal();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $pk = $this->ready['pk'];
        $indikatorId = JadwalSnapshot::sole()->indikator_id;
        $sidikJari = $this->snapshotFingerprint();
        $url = "/perjanjian-kinerja/{$pk->id}/target/{$indikatorId}";

        $this->actingAs($this->activator(['indikator:read', 'target:update']));
        $state = $this->getJson("{$url}/editor")->assertOk()->assertJsonPath('has_snapshot', true)->json('expected_state');
        $this->putJson($url, ['baseline' => null, 'target_tahunan' => '50', 'expected_state' => $state, 'operation_id' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('changed', true);

        $this->assertSame('50.000000000000', DB::table('target_kinerjas')->where('indikator_kinerja_id', $indikatorId)->value('target_tahunan'));
        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan')->sole();
        $this->assertSame('Pencatatan audit untuk tindakan target_tahunan.simpan.', $audit->alasan);
        $this->assertSame('50', $audit->nilai_baru['target_tahunan']);
        $this->assertEquals($sidikJari, $this->snapshotFingerprint());
        // Rekonsiliasi Rencana Aksi membandingkan skor terakhir dengan target beku 76,25, bukan master 50.
        $deviasi = app(KesiapanPengajuan::class)->deviasi([['nilai' => '76.25']], JadwalSnapshot::sole());
        $this->assertSame(DeviasiTargetPk::SETARA, $deviasi['status']);
    }
}
