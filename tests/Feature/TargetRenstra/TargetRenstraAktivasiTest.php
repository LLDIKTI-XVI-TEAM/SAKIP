<?php

namespace Tests\Feature\TargetRenstra;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\TargetRenstra;
use App\Support\TargetTahunanDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/** Target Renstra hanya pembanding: kosong atau berbeda dari target PK tidak menghalangi aktivasi dan tidak masuk snapshot. */
class TargetRenstraAktivasiTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00.123456', 'Asia/Makassar'));
    }

    private function activate(array $payload): TestResponse
    {
        return $this->from('/jadwal/'.$this->ready['jadwal']->id)->post('/jadwal/'.$this->ready['jadwal']->id.'/aktivasi', $payload);
    }

    public function test_target_renstra_kosong_atau_berbeda_bukan_gerbang_aktivasi(): void
    {
        $this->readyJadwal();
        $tanpaTargetRenstra = IndikatorKinerja::where('sasaran_strategis_id', $this->ready['sasaran']->id)->sole();
        $berbeda = $this->eligibleIndicator(target: '50');
        TargetRenstra::create(['indikator_id' => $berbeda->id, 'tahun' => 2026, 'nilai' => '90', 'updated_by' => $this->ready['owner']->id]);

        $this->actingAs($this->activator())->activate($this->activationPayload())->assertSessionHasNoErrors();

        $this->assertSame('aktif', JadwalTahunan::findOrFail($this->ready['jadwal']->id)->status);
        $target = fn (IndikatorKinerja $indikator): ?string => TargetTahunanDecimal::normalize(JadwalSnapshot::where('indikator_id', $indikator->id)->sole()->target);
        $this->assertSame('76.25', $target($tanpaTargetRenstra));
        $this->assertSame('50', $target($berbeda));
    }
}
