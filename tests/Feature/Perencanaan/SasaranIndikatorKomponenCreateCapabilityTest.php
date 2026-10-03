<?php

namespace Tests\Feature\Perencanaan;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SasaranIndikatorKomponenCreateCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogSeeder::class);
        $this->pasangPresetRole('perencanaan');

        $this->perencanaan = $this->buatUserDenganRole('perencanaan', 'perencanaan-komponen-create-test@sakip.test');
    }

    public function test_index_memuat_capability_komponen_create_sesuai_izin(): void
    {
        $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.indikator_update', true)
                ->where('can.komponen_create', true)
            );
    }

    public function test_deny_komponen_create_menurunkan_capability_tanpa_mengubah_indikator_update(): void
    {
        $komponenPermission = Permission::where('kode', 'komponen:create')->firstOrFail();
        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $komponenPermission->id,
            'unit_id' => null,
            'alasan' => 'Dilarang menambah komponen untuk pengujian gate Atur Formula R5-02',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);

        $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.indikator_update', true)
                ->where('can.komponen_create', false)
            );
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
