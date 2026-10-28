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
        $this->actor = User::factory()->create(['status' => 'aktif']);
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
        $this->actor->update(['status' => 'nonaktif']);
        $this->get('/akses/izin-peran')->assertRedirect(route('auth.pending'));
    }

    public function test_persisted_membership_is_bounded_searchable_and_honest_about_inactive_or_legacy_metadata(): void
    {
        $role = Role::where('kode', 'superadmin')->sole();
        $role->update(['aktif' => false]);
        $this->get('/akses/izin-peran?role='.$role->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 20)->where('selectedRole.aktif', false)
            ->where('pagination.from', 1)->where('pagination.to', 20)->where('pagination.total', $role->permissions()->count())
            ->where('pagination.links', fn ($links) => collect($links)->contains(fn ($link) => str_contains((string) $link['url'], 'page=2')))
            ->missing('permissions.0.editable')->missing('permissions.0.attached')->missing('users')->missing('grants')->missing('denies'));
        $legacy = Permission::create(['kode' => 'legacy:read', 'entitas' => 'legacy', 'aksi' => 'read', 'butuh_scope' => 'global', 'aktif' => false]);
        $role->permissions()->attach($legacy->id, ['id' => Str::uuid(), 'created_at' => now()]);
        $this->get('/akses/izin-peran?role='.$role->id.'&q=legacy')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 1)->where('permissions.0.kode', 'legacy:read')->where('permissions.0.aktif', false)
            ->where('permissions.0.keterangan', null)->where('permissions.0.in_catalog', false));
        $this->get('/akses/izin-peran?role='.$role->id.'&q='.urlencode('grant izin tambahan'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('permissions', 1)->where('permissions.0.kode', 'delegasi:update'));
    }

    public function test_role_list_reports_persisted_permission_count_including_inactive_or_legacy(): void
    {
        $count = fn (string $kode) => DB::table('role_permissions')->where('role_id', Role::where('kode', $kode)->value('id'))->count();
        [$pegawaiBefore, $adminBefore] = [$count('pegawai'), $count('admin')];
        $pegawai = Role::where('kode', 'pegawai')->sole();
        $legacy = Permission::create(['kode' => 'legacy:read', 'entitas' => 'legacy', 'aksi' => 'read', 'butuh_scope' => 'global', 'aktif' => false]);
        $pegawai->permissions()->attach($legacy->id, ['id' => Str::uuid(), 'created_at' => now()]);
        Role::where('kode', 'admin')->sole()->permissions()->detach(Permission::where('kode', 'audit:read')->value('id'));

        $this->get('/akses/izin-peran')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('roles.0.jumlah_izin', $count('superadmin'))->where('roles.1.jumlah_izin', $adminBefore - 1)
            ->where('roles.2.jumlah_izin', $count('perencanaan'))->where('roles.3.jumlah_izin', $count('pimpinan'))
            ->where('roles.4.jumlah_izin', $pegawaiBefore + 1));
        $this->get('/akses/izin-peran?role='.$pegawai->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selectedRole.jumlah_izin', $pegawaiBefore + 1)->where('pagination.total', $pegawaiBefore + 1));
    }

    public function test_search_treats_percent_and_underscore_as_literal_text(): void
    {
        $role = Role::where('kode', 'superadmin')->sole();
        foreach (['zzaxb:read', 'zza_b:read'] as $kode) {
            $permission = Permission::create(['kode' => $kode, 'entitas' => strtok($kode, ':'), 'aksi' => 'read', 'butuh_scope' => 'global', 'aktif' => true]);
            $role->permissions()->attach($permission->id, ['id' => Str::uuid(), 'created_at' => now()]);
        }
        // Kontrol: pencarian sebagian tetap cocok untuk keduanya.
        $this->get('/akses/izin-peran?role='.$role->id.'&q=zza')->assertInertia(fn (Assert $page) => $page->has('permissions', 2));
        // `_` dan `%` adalah karakter biasa, bukan wildcard SQL.
        $this->get('/akses/izin-peran?role='.$role->id.'&q=zza_b')->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 1)->where('permissions.0.kode', 'zza_b:read'));
        $this->get('/akses/izin-peran?role='.$role->id.'&q='.urlencode('%%'))->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 0)->where('pagination.total', 0));
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
