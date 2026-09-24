<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorKomponenModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_catalog_includes_komponen_permissions(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $this->assertDatabaseHas('permissions', ['kode' => 'komponen:read', 'sensitif' => false]);
        $this->assertDatabaseHas('permissions', ['kode' => 'komponen:create', 'sensitif' => false]);
        $this->assertDatabaseHas('permissions', ['kode' => 'komponen:update', 'sensitif' => true]);
        $this->assertDatabaseHas('permissions', ['kode' => 'komponen:delete', 'sensitif' => true]);

        $perencanaan = RolePermissionPresets::forRole('perencanaan');
        $this->assertContains('komponen:create', $perencanaan);
        $this->assertContains('komponen:update', $perencanaan);
        $this->assertContains('komponen:delete', $perencanaan);
        $this->assertContains('komponen:read', $perencanaan);

        $pegawai = RolePermissionPresets::forRole('pegawai');
        $this->assertContains('komponen:read', $pegawai);
        $this->assertNotContains('komponen:create', $pegawai);
        $this->assertNotContains('komponen:update', $pegawai);
        $this->assertNotContains('komponen:delete', $pegawai);
    }

    public function test_indikator_komponen_model_relationships_and_casts(): void
    {
        $user = User::factory()->create();
        $renstra = Renstra::create([
            'kode' => 'RENSTRA-TEST',
            'nama' => 'Renstra Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Strategis Test',
            'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit Test',
            'status' => 'aktif',
            'created_by' => $user->id,
        ]);

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'IKU-TEST',
            'nama' => 'Indikator Test',
            'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'is_aktif' => true,
        ]);

        $komponen = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang Test',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'satuan' => 'orang',
            'aktif' => true,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($komponen->timestamps);
        $this->assertSame(1.0, (float) $komponen->bobot);
        $this->assertSame(1, $komponen->urutan);
        $this->assertTrue($komponen->aktif);
        $this->assertSame($indikator->id, $komponen->indikator->id);
        $this->assertSame($user->id, $komponen->creator->id);
        $this->assertTrue($indikator->komponen->contains($komponen));
    }
}
