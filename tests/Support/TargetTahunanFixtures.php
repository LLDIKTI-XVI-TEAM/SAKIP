<?php

namespace Tests\Support;

use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

trait TargetTahunanFixtures
{
    use JadwalFixtures;

    private function targetIndicator(User $actor): IndikatorKinerja
    {
        $renstra = $this->calendarRenstra($actor);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-'.Str::random(6), 'deskripsi' => 'Sasaran fixture']);
        $unit = Unit::create(['nama' => 'Unit fixture '.Str::random(8), 'status' => 'aktif', 'created_by' => $actor->id]);

        return IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id, 'kode' => 'IKU-'.Str::random(6), 'nama' => 'Indikator fixture', 'satuan' => 'nilai', 'presisi' => 2, 'tahun_mulai_berlaku' => 2026, 'created_by' => $actor->id, 'created_by_role' => 'perencanaan']);
    }
}
