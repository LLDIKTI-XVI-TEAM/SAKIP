<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\Berkas;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\User;
use App\Policies\RenstraPkPolicy;
use App\Support\PermissionCodes;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RenstraPkModelAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_renstra_pk_model_attributes_casts_and_relationships(): void
    {
        $user = User::factory()->create();
        $renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'deskripsi' => 'Rencana Strategis',
            'is_aktif' => true,
        ]);

        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-LLDIKTI16-2026-001',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($pk->id);
        $this->assertNotNull($pk->created_at);
        $this->assertNotNull($pk->updated_at);
        $this->assertSame(2026, $pk->tahun);
        $this->assertSame('2026-01-15', $pk->tanggal_pk->toDateString());
        $this->assertTrue($pk->renstra->is($renstra));
        $this->assertTrue($pk->creator->is($user));
        $this->assertTrue($renstra->perjanjianKinerja->contains($pk));

        $berkas = $pk->berkas()->create([
            'jenis_berkas_id' => null,
            'mode' => 'teks',
            'isi_teks' => 'Keterangan dokumen fisik PK 2026',
            'uploaded_by' => $user->id,
        ]);

        $this->assertCount(1, $pk->fresh()->berkas);
        $this->assertTrue($pk->fresh()->berkas->first()->is($berkas));
    }

    public function test_renstra_pk_policy_authorization(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $superadmin = User::factory()->create(['is_active' => true]);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        foreach (Permission::whereIn('kode', [PermissionCodes::PK_CREATE, PermissionCodes::PK_UPDATE, PermissionCodes::BERKAS_DELETE])->get() as $permission) {
            $superadminRole->permissions()->attach($permission->id, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
        }
        $superadmin->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);

        $pegawai = User::factory()->create(['is_active' => true]);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $pegawai->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);

        $inactiveUser = User::factory()->create(['is_active' => false]);

        $renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $superadmin->id,
        ]);

        $policy = app(RenstraPkPolicy::class);

        // Superadmin dengan izin pk:create, pk:update, berkas:delete
        $this->assertTrue($policy->viewAny($superadmin));
        $this->assertTrue($policy->view($superadmin, $pk));
        $this->assertTrue($policy->create($superadmin));
        $this->assertTrue($policy->update($superadmin, $pk));
        $this->assertTrue($policy->deleteBerkas($superadmin, $pk));

        // Pegawai tanpa izin pk:create, pk:update, berkas:delete
        $this->assertTrue($policy->viewAny($pegawai));
        $this->assertTrue($policy->view($pegawai, $pk));
        $this->assertFalse($policy->create($pegawai));
        $this->assertFalse($policy->update($pegawai, $pk));
        $this->assertFalse($policy->deleteBerkas($pegawai, $pk));

        // Inactive user
        $this->assertFalse($policy->viewAny($inactiveUser));
        $this->assertFalse($policy->view($inactiveUser, $pk));
        $this->assertFalse($policy->create($inactiveUser));
        $this->assertFalse($policy->update($inactiveUser, $pk));
        $this->assertFalse($policy->deleteBerkas($inactiveUser, $pk));
    }
}
