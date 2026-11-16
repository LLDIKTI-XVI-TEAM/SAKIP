<?php

namespace Tests\Feature\TargetRenstra;

use App\Models\Renstra;
use App\Models\TargetRenstra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetRenstraRentangTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    public function test_penyempitan_rentang_renstra_ditolak_bila_ada_target_renstra_di_luar(): void
    {
        $aktor = $this->calendarActor(['indikator:read', 'target:update', 'renstra:update']);
        $indikator = $this->targetIndicator($aktor);
        $renstra = $indikator->sasaranStrategis->renstra;
        // Baris yang sudah dikosongkan tetap dihitung.
        TargetRenstra::create(['indikator_id' => $indikator->id, 'tahun' => 2029, 'nilai' => null, 'updated_by' => $aktor->id]);
        $ubah = fn (int $mulai, int $selesai) => $this->actingAs($aktor)->from("/renstra/{$renstra->id}/edit")->put("/renstra/{$renstra->id}",
            ['expected_state' => $renstra->fresh()->stateToken(), 'nama' => $renstra->nama, 'tahun_mulai' => $mulai, 'tahun_selesai' => $selesai]);

        $ubah(2025, 2028)->assertSessionHasErrors(['tahun_mulai' => 'Rentang tahun harus tetap mencakup seluruh tahun target Renstra yang sudah ada.']);
        $this->assertSame([2025, 2029], [$renstra->fresh()->tahun_mulai, $renstra->fresh()->tahun_selesai]);

        $ubah(2026, 2029)->assertSessionHasNoErrors();
        $this->assertSame(2026, Renstra::findOrFail($renstra->id)->tahun_mulai);
    }
}
