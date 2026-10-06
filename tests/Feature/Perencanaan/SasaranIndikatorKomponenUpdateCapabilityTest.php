<?php

namespace Tests\Feature\Perencanaan;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SasaranIndikatorKomponenUpdateCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_memuat_capability_komponen_update_sesuai_izin(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $role = Role::where('kode', 'perencanaan')->firstOrFail();
        $role->permissions()->syncWithoutDetaching(
            Permission::whereIn('kode', RolePermissionPresets::forRole('perencanaan'))->pluck('id')
                ->mapWithKeys(fn (string $id) => [$id => ['id' => (string) Str::uuid(), 'created_at' => now()]])->all()
        );

        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        // Perencanaan memiliki komponen:update via preset; payload can harus memuatnya (R8-01 parity gate-vs-API).
        $this->actingAs($user)->get('/perencanaan/sasaran-indikator')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.komponen_update', true)
            );
    }
}
