<?php

namespace Tests\Feature\Perencanaan;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SasaranIndikatorStaleTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    private Renstra $renstra;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogSeeder::class);
        $this->pasangPresetRole('perencanaan');

        $this->perencanaan = $this->buatUserDenganRole('perencanaan', 'perencanaan-stale-test@sakip.test');

        $this->renstra = Renstra::create([
            'kode' => 'RENSTRA-2025-2029',
            'nama' => 'Renstra LLDIKTI Wilayah XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $this->unit = Unit::create([
            'nama' => 'Bagian Tata Usaha',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);
    }

    public function test_update_indikator_menolak_payload_usang_dengan_409_tanpa_mutasi_dan_tanpa_audit_kedua(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-STALE',
            'deskripsi' => 'Sasaran uji stale-write guard',
            'urutan' => 1,
        ]);

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-STALE',
            'nama' => 'Nama Awal',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $tokenLama = $indikator->updated_at?->toISOString() ?? $indikator->created_at->toISOString();

        Carbon::setTestNow(now()->addSeconds(5));

        $payloadA = [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-STALE',
            'nama' => 'Nama Oleh Tab Pertama',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $tokenLama,
        ];

        $responseA = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", $payloadA);
        $responseA->assertSessionHasNoErrors();
        $responseA->assertRedirect();

        $this->assertSame('Nama Oleh Tab Pertama', $indikator->fresh()->nama);

        Carbon::setTestNow(now()->addSeconds(5));

        $payloadB = [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-STALE',
            'nama' => 'Nama Oleh Tab Kedua Usang',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $tokenLama,
        ];

        $responseB = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", $payloadB);
        $responseB->assertSessionHasErrors('konflik');

        $responseBJson = $this->actingAs($this->perencanaan)->putJson("/perencanaan/indikator/{$indikator->id}", $payloadB);
        $responseBJson->assertStatus(409);
        $responseBJson->assertJsonValidationErrors(['konflik']);

        $this->assertSame('Nama Oleh Tab Pertama', $indikator->fresh()->nama);

        $this->assertSame(1, AuditLog::where('tindakan', 'indikator.ubah')->where('objek_id', $indikator->id)->count());

        Carbon::setTestNow();
    }

    public function test_update_sasaran_menolak_payload_usang_dengan_409_tanpa_mutasi_dan_tanpa_audit_kedua(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-STALE-2',
            'deskripsi' => 'Deskripsi awal',
            'urutan' => 1,
        ]);

        $tokenLama = $sasaran->updated_at?->toISOString() ?? $sasaran->created_at->toISOString();

        Carbon::setTestNow(now()->addSeconds(5));

        $responseA = $this->actingAs($this->perencanaan)->put("/perencanaan/sasaran/{$sasaran->id}", [
            'kode' => 'SS-STALE-2',
            'deskripsi' => 'Deskripsi oleh tab pertama',
            'urutan' => 1,
            'expected_updated_at' => $tokenLama,
        ]);
        $responseA->assertSessionHasNoErrors();
        $responseA->assertRedirect();

        $this->assertSame('Deskripsi oleh tab pertama', $sasaran->fresh()->deskripsi);

        Carbon::setTestNow(now()->addSeconds(5));

        $payloadB = [
            'kode' => 'SS-STALE-2',
            'deskripsi' => 'Deskripsi oleh tab kedua usang',
            'urutan' => 1,
            'expected_updated_at' => $tokenLama,
        ];

        $responseB = $this->actingAs($this->perencanaan)->put("/perencanaan/sasaran/{$sasaran->id}", $payloadB);
        $responseB->assertSessionHasErrors('konflik');

        $responseBJson = $this->actingAs($this->perencanaan)->putJson("/perencanaan/sasaran/{$sasaran->id}", $payloadB);
        $responseBJson->assertStatus(409);
        $responseBJson->assertJsonValidationErrors(['konflik']);

        $this->assertSame('Deskripsi oleh tab pertama', $sasaran->fresh()->deskripsi);

        $this->assertSame(1, AuditLog::where('tindakan', 'sasaran.ubah')->where('objek_id', $sasaran->id)->count());

        Carbon::setTestNow();
    }

    private function buatUserDenganRole(string $roleName, string $email): User
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => $email,
            'status' => 'aktif',
        ]);

        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function pasangPresetRole(string $roleName): void
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $permissionCodes = RolePermissionPresets::forRole($roleName);

        $permissionIds = Permission::whereIn('kode', $permissionCodes)->pluck('id');

        $role->permissions()->syncWithoutDetaching(
            $permissionIds->mapWithKeys(fn (string $id) => [
                $id => [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                ],
            ])->all()
        );
    }
}
