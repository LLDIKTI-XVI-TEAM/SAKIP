<?php

namespace Tests\Feature\TargetRenstra;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\TargetKinerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\Support\TargetRenstraFixtures;
use Tests\TestCase;

/**
 * Batas bukti: belum ada mesin capaian yang membaca target Renstra, sehingga yang dibuktikan adalah simpan target Renstra
 * tidak menulis ke sumber capaian (target PK, snapshot jadwal, pengukuran).
 */
class TargetRenstraInvariansiTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase, TargetRenstraFixtures;

    /** @return list<list<array<string, mixed>>> */
    private function sidikJari(): array
    {
        return array_map(fn (string $tabel): array => DB::table($tabel)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all(),
            ['target_kinerjas', 'jadwal_snapshot', 'pengukuran_kinerjas']);
    }

    public function test_simpan_target_renstra_tidak_mengubah_target_pk_snapshot_dan_pengukuran(): void
    {
        $indikator = IndikatorKinerja::findOrFail($this->pengukuran->indikator_id);
        // Simpan langsung hanya untuk Renstra draft; Renstra aktif memakai Mode revisi.
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_DRAFT);
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '70', 'updated_by' => $this->actor->id]);
        DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['status_perhitungan' => 'terhitung', 'nilai' => '70']);
        $sidik = $this->sidikJari();
        foreach ($sidik as $bagian) {
            $this->assertNotEmpty($bagian);
        }

        $this->simpanTargetRenstra($this->actor, $indikator, [2026 => '80'])->assertSessionHasNoErrors();
        $this->simpanTargetRenstra($this->actor, $indikator, [2026 => '85'])->assertSessionHasNoErrors();

        $this->assertEquals($sidik, $this->sidikJari());
        $this->assertSame('85', $this->nilaiTersimpan($indikator)[2026]);
    }

    public function test_simpan_revisi_pada_renstra_aktif_tidak_mengubah_target_pk_snapshot_dan_pengukuran(): void
    {
        $indikator = IndikatorKinerja::findOrFail($this->pengukuran->indikator_id);
        $renstraId = $indikator->sasaranStrategis->renstra_id;
        $this->assertSame(Renstra::STATUS_AKTIF, $indikator->sasaranStrategis->renstra->status);
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '70', 'updated_by' => $this->actor->id]);
        DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['status_perhitungan' => 'terhitung', 'nilai' => '70']);
        $sidik = $this->sidikJari();

        $this->mulaiRevisi($this->actor, $renstraId)->assertSessionHasNoErrors();
        $this->simpanDrafRevisi($this->actor, $renstraId, 1, [$indikator->id => [2026 => '80']])->assertSessionHasNoErrors();
        $this->simpanRevisi($this->actor, $renstraId, 2, 'Target Renstra dinaikkan.')->assertSessionHasNoErrors();

        $this->assertEquals($sidik, $this->sidikJari());
        $this->assertSame('80', $this->nilaiTersimpan($indikator)[2026]);
    }
}
