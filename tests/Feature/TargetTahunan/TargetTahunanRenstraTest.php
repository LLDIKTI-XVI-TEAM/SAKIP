<?php

namespace Tests\Feature\TargetTahunan;

use App\Actions\Renstra\UpdateRenstraAction;
use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Models\AuditLog;
use App\Models\TargetKinerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetTahunanRenstraTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    #[DataProvider('persistedValues')]
    public function test_shrink_preserves_all_existing_target_years(?string $baseline, ?string $target): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update', 'renstra:update']);
        $indicator = $this->targetIndicator($actor);
        TargetKinerja::create(['indikator_kinerja_id' => $indicator->id, 'tahun' => 2029, 'baseline' => $baseline, 'target_tahunan' => $target]);
        $renstra = $indicator->sasaranStrategis->renstra;
        try {
            app(UpdateRenstraAction::class)->handle($actor, $renstra, ['tahun_selesai' => 2028, 'expected_state' => $renstra->stateToken()]);
            $this->fail('Penyempitan harus menolak target existing di luar rentang.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tahun_mulai', $exception->errors());
        }
        $this->assertSame(2029, $renstra->fresh()->tahun_selesai);
        $this->assertSame(1, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'renstra.ubah')->count());
    }

    public static function persistedValues(): array
    {
        return [['74.2', null], [null, '0'], [null, null]];
    }

    public function test_empty_first_save_does_not_block_shrink(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update', 'renstra:update']);
        $indicator = $this->targetIndicator($actor);
        $editor = app(ShowTargetTahunan::class)->handle($actor, $indicator->id, 2029);
        app(SaveTargetTahunan::class)->handle($actor, $indicator->id, 2029, ['baseline' => null, 'target_tahunan' => null, 'expected_state' => $editor['expected_state'], 'operation_id' => (string) Str::uuid()]);
        $renstra = $indicator->sasaranStrategis->renstra;
        app(UpdateRenstraAction::class)->handle($actor, $renstra, ['tahun_selesai' => 2028, 'expected_state' => $renstra->stateToken()]);
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(2028, $renstra->fresh()->tahun_selesai);
    }
}
