<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\ActivateJadwal;
use App\Models\JadwalSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class DinilaiTahunanSnapshotTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    public function test_activation_freezes_annual_marker_and_later_master_change_keeps_history(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00', 'Asia/Makassar'));
        $this->readyJadwal(withIndicator: false);
        $indicator = $this->eligibleIndicator(target: '76.25');
        $indicator->update(['dinilai_tahunan' => true]);
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());

        $snapshot = JadwalSnapshot::where('indikator_id', $indicator->id)->sole();
        $this->assertTrue($snapshot->dinilai_tahunan);
        $indicator->update(['dinilai_tahunan' => false]);
        $this->assertTrue($snapshot->fresh()->dinilai_tahunan);
        $this->assertFalse($indicator->fresh()->dinilai_tahunan);
    }
}
