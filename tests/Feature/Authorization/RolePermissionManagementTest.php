<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionDeny;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['is_active' => true]);
        $role = Role::where('kode', 'pegawai')->sole();
        $this->actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'pengguna:read')->value('id'), 'unit_id' => null, 'alasan' => 'Hak lihat katalog', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor);
    }

    public function test_read_gate_uses_live_pengguna_read_without_role_allowlist_or_akses_update(): void
    {
        $this->get('/akses/izin-peran')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/RolePermissionIndex')->has('roles', 5)
            ->where('roles.0.kode', 'superadmin')->where('roles.1.kode', 'admin')->where('roles.2.kode', 'perencanaan')
            ->where('roles.3.kode', 'pimpinan')->where('roles.4.kode', 'pegawai')
            ->where('auth.can.viewRolePermissions', true)->where('can.viewRolePermissions', true)
            ->missing('expectedState')->missing('receiptId')->missing('affectsActorRole')->missing('can.manageRolePermissions'));
        $audits = DB::table('audit_log')->count();
        UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'pengguna:read')->value('id'), 'alasan' => 'Cabut akses baca', 'ditetapkan_oleh' => $this->actor->id]);
        $this->get('/akses/izin-peran')->assertForbidden();
        $this->assertSame($audits, DB::table('audit_log')->count());
    }

    public function test_missing_permission_inactive_actor_or_role_cannot_read(): void
    {
        DB::table('user_permission_granted')->delete();
        $this->get('/akses/izin-peran')->assertForbidden();
        $this->actor->update(['is_active' => false]);
        $this->get('/akses/izin-peran')->assertRedirect(route('auth.pending'));
    }

    public function test_persisted_membership_is_bounded_searchable_and_honest_about_inactive_or_legacy_metadata(): void
    {
        $role = Role::where('kode', 'superadmin')->sole();
        $role->update(['aktif' => false]);
        $this->get('/akses/izin-peran?role='.$role->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 20)->where('selectedRole.aktif', false)->where('pagination.page', 1)
            ->where('pagination.next_page_url', fn ($url) => is_string($url) && str_contains($url, 'page=2'))
            ->missing('permissions.0.editable')->missing('permissions.0.attached')->missing('users')->missing('grants')->missing('denies'));
        $legacy = Permission::create(['kode' => 'legacy:read', 'entitas' => 'legacy', 'aksi' => 'read', 'butuh_scope' => 'global', 'aktif' => false]);
        $role->permissions()->attach($legacy->id, ['id' => Str::uuid(), 'created_at' => now()]);
        $this->get('/akses/izin-peran?role='.$role->id.'&q=legacy')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 1)->where('permissions.0.kode', 'legacy:read')->where('permissions.0.aktif', false)
            ->where('permissions.0.keterangan', null)->where('permissions.0.in_catalog', false));
        $this->get('/akses/izin-peran?role='.$role->id.'&q='.urlencode('grant izin tambahan'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('permissions', 1)->where('permissions.0.kode', 'delegasi:update'));
    }

    public function test_filters_validate_official_role_query_length_and_page(): void
    {
        foreach ([['role' => (string) Str::uuid()], ['role' => 'bad'], ['q' => str_repeat('a', 101)], ['page' => 0]] as $query) {
            $this->from('/akses/izin-peran')->get('/akses/izin-peran?'.http_build_query($query))->assertSessionHasErrors(array_key_first($query));
        }
        $pic = Role::create(['kode' => 'pic', 'nama' => 'Legacy', 'urutan' => 6]);
        $this->from('/akses/izin-peran')->get('/akses/izin-peran?role='.$pic->id)->assertSessionHasErrors('role');
    }

    public function test_old_mutations_and_result_cannot_write_or_claim_success(): void
    {
        $role = Role::where('kode', 'superadmin')->sole();
        $before = DB::table('role_permissions')->orderBy('id')->get()->toJson();
        $audits = DB::table('audit_log')->count();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->call($method, '/akses/izin-peran/'.$role->id, ['operation' => 'revoke', 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'alasan' => 'Jalur lama']);
            $this->assertContains($response->status(), [404, 405]);
        }
        $this->get('/akses/izin-peran/hasil?status=added')->assertNotFound();
        $this->assertSame($before, DB::table('role_permissions')->orderBy('id')->get()->toJson());
        $this->assertSame($audits, DB::table('audit_log')->count());
    }
}
