<?php

namespace Tests\Feature\TargetTahunan;

use App\Actions\Perencanaan\IndexSasaranIndikator;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetTahunanUiIntegrationTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    public function test_redirect_keeps_the_selected_renstra_filter(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $editor = app(ShowTargetTahunan::class)->handle($actor, $indicator->id, 2026);
        $origin = route('perencanaan.sasaran-indikator.index', ['renstra_id' => $editor['renstra']['id']]);
        $this->actingAs($actor)->from($origin)->withHeader('X-Inertia', 'true')
            ->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [
                'baseline' => null, 'target_tahunan' => null, 'expected_state' => $editor['expected_state'], 'operation_id' => (string) Str::uuid(),
            ])->assertRedirect($origin);
    }

    public function test_redirect_does_not_trust_an_external_origin_or_add_unrelated_query(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $editor = app(ShowTargetTahunan::class)->handle($actor, $indicator->id, 2026);
        $this->actingAs($actor)->from('https://untrusted.example/perencanaan/sasaran-indikator?renstra_id='.$editor['renstra']['id'].'&other=1')->withHeader('X-Inertia', 'true')
            ->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [
                'baseline' => null, 'target_tahunan' => null, 'expected_state' => $editor['expected_state'], 'operation_id' => (string) Str::uuid(),
            ])->assertRedirect(route('perencanaan.sasaran-indikator.index'));
    }

    public function test_indicator_row_contains_start_year_for_a_valid_initial_editor_year(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $indicator->update(['tahun_mulai_berlaku' => 2027]);
        $props = app(IndexSasaranIndikator::class)->handle($actor, $indicator->sasaranStrategis->renstra_id, true);
        $row = collect($props['sasarans'])->flatMap(fn ($sasaran) => $sasaran['indikator_kinerjas'])->firstWhere('id', $indicator->id);
        $this->assertArrayHasKey('tahun_mulai_berlaku', $row);
        $this->assertSame(2027, $row['tahun_mulai_berlaku']);
    }
}
