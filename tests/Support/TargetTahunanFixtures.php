<?php

namespace Tests\Support;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

trait TargetTahunanFixtures
{
    use JadwalFixtures;

    private function targetIndicator(User $actor, ?Renstra $renstra = null): IndikatorKinerja
    {
        $renstra ??= $this->calendarRenstra($actor);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-'.Str::random(6), 'deskripsi' => 'Sasaran fixture']);
        $unit = Unit::create(['nama' => 'Unit fixture '.Str::random(8), 'status' => 'aktif', 'created_by' => $actor->id]);

        return IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id, 'kode' => 'IKU-'.Str::random(6), 'nama' => 'Indikator fixture', 'satuan' => 'nilai', 'presisi' => 2, 'tahun_mulai_berlaku' => 2026, 'created_by' => $actor->id, 'created_by_role' => 'perencanaan']);
    }

    private function targetPk(IndikatorKinerja $indikator, User $actor, int $tahun = 2026): RenstraPk
    {
        return RenstraPk::create(['renstra_id' => $indikator->sasaranStrategis->renstra_id, 'tahun' => $tahun, 'nomor_pk' => null,
            'tanggal_pk' => "$tahun-04-21", 'created_by' => $actor->id]);
    }

    /** Route target PK 2026 untuk indikator; PK dibuat sekali per Renstra-tahun. */
    private function targetPkUrl(IndikatorKinerja $indikator, string $suffix = ''): string
    {
        $pk = RenstraPk::firstOrCreate(['renstra_id' => $indikator->sasaranStrategis->renstra_id, 'tahun' => 2026],
            ['nomor_pk' => null, 'tanggal_pk' => '2026-04-21', 'created_by' => $indikator->created_by]);

        return "/perjanjian-kinerja/{$pk->id}/target/{$indikator->id}{$suffix}";
    }
}
