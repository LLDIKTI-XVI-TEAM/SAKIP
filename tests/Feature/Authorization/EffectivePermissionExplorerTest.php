<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EffectivePermissionExplorerTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = $this->user('pegawai');
        $this->source('user_permission_granted', $this->actor, 'pengguna:read');
        $this->actingAs($this->actor);
    }

    private function user(?string $role = null, string $status = 'aktif'): User
    {
        $user = User::factory()->create(['status' => $status]);
        if ($role !== null) {
            $user->roles()->attach(Role::where('kode', $role)->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        }

        return $user;
    }

    private function source(string $table, User $user, string $code, ?string $unit = null): string
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert(['id' => $id, 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $unit, 'alasan' => 'Diagnosis sintetis', $table === 'user_permission_granted' ? 'diberikan_oleh' : 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);

        return $id;
    }

    private function url(User $user, array $query = []): string
    {
        return '/akses/jelaskan-izin?'.http_build_query(['user_id' => $user->id, ...$query]);
    }

    public function test_read_only_actor_can_open_but_live_deny_blocks_page_and_lookups(): void
    {
        $this->get('/akses/jelaskan-izin')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/EffectivePermissionIndex')->where('selectedUser', null)
            ->has('permissions', 0)->where('auth.can.viewEffectivePermissions', true));
        $this->source('user_permission_denied', $this->actor, 'pengguna:read');
        foreach (['/akses/jelaskan-izin', '/akses/jelaskan-izin/opsi/pengguna?q=aa', '/akses/jelaskan-izin/opsi/unit?q=aa'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_scope_role_grant_and_deny_are_explained_without_changing_the_decision(): void
    {
        $target = $this->user('perencanaan');
        $unit = Unit::create(['nama' => 'Unit Diagnosis', 'created_by' => $this->actor->id]);
        $grant = $this->source('user_permission_granted', $target, 'pengukuran:update', $unit->id);
        $deny = $this->source('user_permission_denied', $target, 'pengukuran:update', $unit->id);
        $this->get($this->url($target, ['unit_id' => $unit->id, 'q' => 'pengukuran:update']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 1)->where('permissions.0.decision.allowed', false)
            ->where('permissions.0.decision.reason', 'explicit_deny')
            ->where('permissions.0.decision.grants', [$grant])->where('permissions.0.decision.denies', [$deny])
            ->has('permissions.0.sources', 3)->where('selectedUser.role.kode', 'perencanaan')
            ->missing('selectedUser.keycloak_id')->missing('selectedUser.phone'));
        $this->get($this->url($target, ['q' => 'pengukuran:update']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissions.0.decision.reason', 'invalid_scope')->where('permissions.0.status', 'Pilih unit untuk memeriksa'));
        $unit->update(['status' => 'nonaktif']);
        DB::table('user_permission_denied')->where('id', $deny)->delete();
        $this->get($this->url($target, ['unit_id' => $unit->id, 'q' => 'pengukuran:update']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissions.0.decision.allowed', true)->where('selectedUnit.status', 'nonaktif'));
    }

    public function test_global_permission_obeys_unit_deny_only_in_that_context(): void
    {
        $target = $this->user('pegawai');
        $unit = Unit::create(['nama' => 'Unit A', 'created_by' => $this->actor->id]);
        $this->source('user_permission_denied', $target, 'pengukuran:read', $unit->id);
        $this->get($this->url($target, ['q' => 'pengukuran:read']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('permissions.0.decision.allowed', true));
        $this->get($this->url($target, ['q' => 'pengukuran:read', 'unit_id' => $unit->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('permissions.0.decision.reason', 'explicit_deny'));
    }

    public function test_stored_sources_are_visible_but_no_role_inactive_and_legacy_fail_closed(): void
    {
        $target = $this->user();
        $this->source('user_permission_granted', $target, 'dashboard:read');
        $this->get($this->url($target, ['q' => 'dashboard:read']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissions.0.decision.reason', 'no_role')->where('permissions.0.sources.0.effective', false)
            ->where('permissions.0.sources.0.alasan', 'Diagnosis sintetis'));
        $target->update(['status' => 'nonaktif']);
        $this->get($this->url($target, ['q' => 'pengukuran:update']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissions.0.decision.reason', 'inactive_user')->where('permissions.0.status', 'Tidak efektif'));
        $legacy = Permission::create(['kode' => 'legacy:read', 'entitas' => 'legacy', 'aksi' => 'read', 'butuh_scope' => 'global', 'aktif' => true]);
        $this->source('user_permission_granted', $target, $legacy->kode);
        $this->get($this->url($target, ['q' => 'legacy']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 0)->has('diagnostics', 1)->where('diagnostics.0.kode', 'legacy:read')
            ->where('diagnostics.0.decision.allowed', false));
        $target = $this->user('pegawai');
        Permission::where('kode', 'dashboard:read')->update(['aktif' => false]);
        $this->get($this->url($target, ['q' => 'dashboard:read']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('permissions.0.decision.reason', 'unknown_permission'));
    }

    public function test_catalog_pagination_lookup_and_validation_are_bounded(): void
    {
        $target = $this->user('superadmin');
        $this->get($this->url($target))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 20)->where('pagination.page', 1)
            ->where('pagination.next_page_url', fn ($url) => str_contains($url, 'page=2') && str_contains($url, $target->id)));
        User::factory()->count(23)->create(['nama' => 'Pilihan Sintetis', 'status' => 'nonaktif']);
        $this->getJson('/akses/jelaskan-izin/opsi/pengguna?q=Pilihan')->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('hasMore', true)->assertJsonPath('items.0.status', 'nonaktif')->assertJsonMissingPath('items.0.keycloak_id');
        foreach ([['user_id' => 'bad'], ['unit_id' => (string) Str::uuid()], ['scope' => 'bad'], ['q' => str_repeat('a', 101)], ['page' => 0], ['diagnostic_page' => 0]] as $query) {
            $this->getJson('/akses/jelaskan-izin?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors(array_key_first($query));
        }
    }

    public function test_get_does_not_mutate_access_or_audit_and_has_no_mutating_routes(): void
    {
        $snapshot = fn () => collect(['user_roles', 'role_permissions', 'user_permission_granted', 'user_permission_denied', 'audit_log'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $this->get($this->url($this->actor))->assertOk();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertContains($this->call($method, '/akses/jelaskan-izin')->status(), [404, 405]);
        }
        $this->assertSame($before, $snapshot());
    }

    public function test_batch_matches_single_preserves_uuid_scope_and_is_fresh_with_constant_queries(): void
    {
        $target = $this->user('pegawai');
        $unit = Unit::create(['nama' => 'Batch', 'created_by' => $this->actor->id]);
        $grant = $this->source('user_permission_granted', $target, 'pengukuran:update', $unit->id);
        $resolver = app(PermissionResolver::class);
        $codes = [...PermissionCatalog::codes(), 'legacy:unknown'];
        $batch = $resolver->decideMany($target, $codes, strtoupper($unit->id));
        foreach ($codes as $code) {
            $this->assertSame($resolver->decide($target, $code, $unit->id), $batch[$code]);
        }
        $this->assertSame([$grant], $batch['pengukuran:update']['grants']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $resolver->decideMany($target, PermissionCatalog::codes(), $unit->id);
        $this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->source('user_permission_denied', $target, 'pengukuran:update', $unit->id);
        $this->assertSame('explicit_deny', $resolver->decideMany($target, ['pengukuran:update'], $unit->id)['pengukuran:update']['reason']);
        $target->roles()->detach();
        $this->assertSame('no_role', $resolver->decideMany($target, ['legacy:unknown'], 'bad')['legacy:unknown']['reason']);
        $target->status = 'nonaktif';
        $this->assertSame('inactive_user', $resolver->decideMany($target, ['legacy:unknown'])['legacy:unknown']['reason']);
    }

    public function test_lookup_and_diagnosis_second_pages_preserve_context_and_filters(): void
    {
        $target = $this->user('perencanaan');
        for ($i = 0; $i < 23; $i++) {
            Unit::create(['nama' => 'Pilihan Unit '.sprintf('%02d', $i), 'created_by' => $this->actor->id, 'status' => 'nonaktif']);
        }
        $this->getJson('/akses/jelaskan-izin/opsi/unit?q=Pilihan&page=2')->assertOk()->assertJsonCount(3, 'items')->assertJsonPath('page', 2)->assertJsonPath('hasMore', false);
        for ($i = 0; $i < 21; $i++) {
            $permission = Permission::create(['kode' => 'legacy:'.sprintf('%02d', $i), 'entitas' => 'legacy', 'aksi' => (string) $i, 'butuh_scope' => 'global', 'aktif' => true]);
            $this->source('user_permission_granted', $target, $permission->kode);
        }
        $this->get($this->url($target, ['q' => 'legacy', 'scope' => 'global']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('diagnostics', 20)->where('diagnosticPagination.next_page_url', fn ($url) => str_contains($url, 'diagnostic_page=2') && str_contains($url, 'q=legacy') && str_contains($url, $target->id)));
        $this->get($this->url($target, ['q' => 'legacy', 'scope' => 'global', 'diagnostic_page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('diagnostics', 1)->where('diagnostics.0.decision.reason', 'unknown_permission')->where('diagnostics.0.sources.0.effective', false));
        Role::where('kode', 'perencanaan')->update(['aktif' => false]);
        $this->get($this->url($target, ['q' => 'dashboard:read']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selectedUser.role.aktif', false)->where('permissions.0.decision.reason', 'no_role')->where('permissions.0.sources.0.effective', false));
    }

    public function test_endpoint_query_count_does_not_grow_with_permission_rows(): void
    {
        $target = $this->user('superadmin');
        $unit = Unit::create(['nama' => 'Konteks Query', 'created_by' => $this->actor->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $one = $this->get($this->url($target, ['q' => 'dashboard:read', 'unit_id' => $unit->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('permissions', 1));
        $singleCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $many = $this->get($this->url($target, ['unit_id' => $unit->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('permissions', 20));
        $pageCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($singleCount + 1, $pageCount);
    }

    public function test_search_treats_wildcards_and_escape_as_literal_text(): void
    {
        $target = $this->user('pegawai');
        $comparison = Permission::create(['kode' => 'legacy:comparison', 'entitas' => 'legacy', 'aksi' => 'read', 'keterangan' => 'Pembanding', 'butuh_scope' => 'global', 'aktif' => true]);
        $this->source('user_permission_granted', $target, $comparison->kode);
        User::factory()->create(['nama' => 'Pengguna Pembanding', 'email' => 'comparison@example.test']);
        Unit::create(['nama' => 'Unit Pembanding', 'created_by' => $this->actor->id]);

        foreach (['%', '_', '\\'] as $index => $character) {
            $literalUser = User::factory()->create(['nama' => 'Pengguna Literal '.$character, 'email' => 'literal-'.$index.'@example.test', 'status' => 'nonaktif']);
            $userIds = [$literalUser->id];
            if ($character !== '\\') {
                $emailUser = User::factory()->create(['nama' => 'Pengguna Email '.$index, 'email' => 'literal'.$character.$index.'@example.test']);
                $userIds[] = $emailUser->id;
            }
            $unit = Unit::create(['nama' => 'Unit Literal '.$character, 'created_by' => $this->actor->id, 'status' => 'nonaktif']);
            $literalCode = Permission::create(['kode' => 'legacy:code'.$index.$character, 'entitas' => 'legacy', 'aksi' => 'read', 'butuh_scope' => 'global', 'aktif' => true]);
            $literalDescription = Permission::create(['kode' => 'legacy:description'.$index, 'entitas' => 'legacy', 'aksi' => 'read', 'keterangan' => 'Keterangan literal '.$character, 'butuh_scope' => 'global', 'aktif' => true]);
            $this->source('user_permission_granted', $target, $literalCode->kode);
            $this->source('user_permission_granted', $target, $literalDescription->kode);
            Permission::where('kode', 'dashboard:read')->update(['keterangan' => 'Keterangan literal '.$character]);

            $query = http_build_query(['q' => $character]);
            $users = $this->getJson('/akses/jelaskan-izin/opsi/pengguna?'.$query)->assertOk()->assertJsonCount(count($userIds), 'items');
            $this->assertEqualsCanonicalizing($userIds, array_column($users->json('items'), 'id'));
            $this->getJson('/akses/jelaskan-izin/opsi/unit?'.$query)->assertOk()->assertJsonCount(1, 'items')
                ->assertJsonPath('items.0.id', $unit->id)->assertJsonPath('items.0.status', 'nonaktif');
            $this->get($this->url($target, ['q' => $character]))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', $character)
                ->where('permissions', fn ($rows) => collect($rows)->contains('kode', 'dashboard:read'))
                ->has('diagnostics', 2)
                ->where('diagnostics', fn ($rows) => collect($rows)->pluck('kode')->sort()->values()->all() === collect([$literalCode->kode, $literalDescription->kode])->sort()->values()->all()));
        }
    }
}
