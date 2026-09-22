<?php

namespace Tests\Feature\Perencanaan;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerencanaanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superadminUser;

    private User $perencanaanUser;

    private User $pegawaiUser;

    private Unit $unitKLSI;

    private Unit $unitUmum;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);

        $this->superadminUser = User::factory()->create([
            'nama' => 'Superadmin',
            'email' => 'superadmin@sakip.test',
            'is_active' => true,
        ]);

        $this->perencanaanUser = User::factory()->create([
            'nama' => 'Staf Perencanaan',
            'email' => 'perencanaan@sakip.test',
            'is_active' => true,
        ]);

        $this->pegawaiUser = User::factory()->create([
            'nama' => 'Staf Pegawai',
            'email' => 'pegawai@sakip.test',
            'is_active' => true,
        ]);

        // Setup Role Superadmin
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $this->superadminUser->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // Setup Role Perencanaan
        $perencanaanRole = Role::where('kode', 'perencanaan')->firstOrFail();
        $this->perencanaanUser->roles()->attach($perencanaanRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // Attach permissions to Perencanaan role
        $renstraRead = Permission::where('kode', 'renstra:read')->firstOrFail();
        $indikatorRead = Permission::where('kode', 'indikator:read')->firstOrFail();
        $rencanaAksiRead = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        $perencanaanRole->permissions()->attach([
            $renstraRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $indikatorRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $rencanaAksiRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ]);

        // Attach all to Superadmin
        $superadminRole->permissions()->attach([
            $renstraRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $indikatorRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $rencanaAksiRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ]);

        // Setup Role Pegawai (tidak memiliki permission perencanaan)
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $this->pegawaiUser->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // Buat Unit di DB yang sesuai dengan mockup data
        $this->unitKLSI = Unit::create([
            'nama' => 'Pokja Kelembagaan dan Sistem Informasi',
            'status' => 'aktif',
            'created_by' => $this->superadminUser->id,
        ]);

        $this->unitUmum = Unit::create([
            'nama' => 'Bagian Umum',
            'status' => 'aktif',
            'created_by' => $this->superadminUser->id,
        ]);
    }

    public function test_guest_is_redirected_to_login_for_planning_routes(): void
    {
        $this->get('/renstra')->assertRedirect('/login');
        $this->get('/indikator')->assertRedirect('/login');
        $this->get('/rencana-aksi')->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_renstra(): void
    {
        $this->actingAs($this->pegawaiUser)
            ->get('/renstra')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_renstra(): void
    {
        $this->actingAs($this->perencanaanUser)
            ->get('/renstra')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Renstra/Index'));
    }

    public function test_user_without_permission_cannot_access_indikator(): void
    {
        $this->actingAs($this->pegawaiUser)
            ->get('/indikator')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_indikator(): void
    {
        $this->actingAs($this->perencanaanUser)
            ->get('/indikator')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Indikator/Index'));
    }

    public function test_user_without_permission_cannot_access_rencana_aksi(): void
    {
        $this->actingAs($this->pegawaiUser)
            ->get('/rencana-aksi')
            ->assertForbidden();
    }

    public function test_user_with_role_can_access_rencana_aksi_and_sees_data(): void
    {
        $this->actingAs($this->perencanaanUser)
            ->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Index')
                ->has('rencanaAksiList', 5)
            );
    }

    public function test_user_with_global_deny_cannot_access_rencana_aksi(): void
    {
        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        UserPermissionDeny::create([
            'user_id' => $this->perencanaanUser->id,
            'permission_id' => $perm->id,
            'unit_id' => null,
            'alasan' => 'Dilarang akses perencanaan secara global.',
            'ditetapkan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->perencanaanUser)
            ->get('/rencana-aksi')
            ->assertForbidden();
    }

    public function test_user_with_unit_grant_can_access_rencana_aksi_and_data_is_filtered(): void
    {
        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        // Pegawai diberikan grant unit khusus Pokja KLSI
        UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $perm->id,
            'unit_id' => $this->unitKLSI->id,
            'alasan' => 'Penugasan penyusunan rencana aksi Pokja KLSI.',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->pegawaiUser)
            ->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Index')
                ->has('rencanaAksiList', 2) // Hanya 2 item Pokja KLSI di mockup
                ->where('rencanaAksiList.0.unit_nama', 'Pokja Kelembagaan dan Sistem Informasi')
                ->where('rencanaAksiList.1.unit_nama', 'Pokja Kelembagaan dan Sistem Informasi')
            );
    }

    public function test_user_with_role_and_unit_deny_has_denied_unit_filtered_out(): void
    {
        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        // Staf Perencanaan di-deny untuk Bagian Umum
        UserPermissionDeny::create([
            'user_id' => $this->perencanaanUser->id,
            'permission_id' => $perm->id,
            'unit_id' => $this->unitUmum->id,
            'alasan' => 'Konflik kepentingan pada Bagian Umum.',
            'ditetapkan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->perencanaanUser)
            ->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Index')
                ->has('rencanaAksiList', 4) // Total 5 dikurangi 1 item Bagian Umum
            );
    }
}
