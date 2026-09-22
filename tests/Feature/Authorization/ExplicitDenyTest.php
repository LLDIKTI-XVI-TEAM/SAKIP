<?php

namespace Tests\Feature\Authorization;

use App\Actions\Access\CreateDeny;
use App\Actions\Access\RevokeDeny;
use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ExplicitDenyTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $target;

    private Unit $unit;

    private Permission $permission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['is_active' => true]);
        $this->target = User::factory()->create(['is_active' => true]);
        $this->unit = Unit::create(['nama' => 'Unit A', 'status' => 'aktif', 'created_by' => $this->actor->id]);
        $this->permission = Permission::where('kode', 'pengukuran:update')->sole();
        $this->giveRole($this->actor, 'superadmin', Permission::where('kode', 'akses:update')->sole());
    }

    private function giveRole(User $user, string $code, Permission $permission): void
    {
        $role = Role::where('kode', $code)->sole();
        $role->permissions()->syncWithoutDetaching([$permission->id => ['id' => Str::uuid(), 'created_at' => now()]]);
        $user->roles()->sync([$role->id => ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]]);
    }

    private function create(?string $unitId = null): UserPermissionDeny
    {
        return app(CreateDeny::class)->handle($this->actor, $this->target->id, $this->permission->id, $unitId, '  Pembatasan evaluasi  ');
    }

    public function test_create_scoped_then_global_deny_overrides_role_and_grant_with_atomic_provenance(): void
    {
        $this->giveRole($this->target, 'pegawai', $this->permission);
        $other = Unit::create(['nama' => 'Unit B', 'status' => 'aktif', 'created_by' => $this->actor->id]);
        UserPermissionGrant::create(['user_id' => $this->target->id, 'permission_id' => $this->permission->id, 'unit_id' => $this->unit->id, 'alasan' => 'Grant awal', 'diberikan_oleh' => $this->actor->id]);
        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($this->target, $this->permission->kode, $this->unit->id));
        $deny = $this->create($this->unit->id);
        $this->assertFalse($resolver->allows($this->target, $this->permission->kode, $this->unit->id));
        $this->assertTrue($resolver->allows($this->target, $this->permission->kode, $other->id));
        $audit = AuditLog::sole();
        $this->assertSame('user_permission_denied.tambah', $audit->tindakan);
        $this->assertSame('user_permission_denied', $audit->objek_tipe);
        $this->assertSame($deny->id, $audit->objek_id);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame('manual', $audit->sumber);
        $this->assertNull($audit->nilai_lama);
        $this->assertEquals([
            'id' => $deny->id, 'user_id' => $this->target->id, 'permission_id' => $this->permission->id,
            'permission_kode' => $this->permission->kode, 'butuh_scope' => 'unit', 'unit_id' => $this->unit->id,
            'unit_nama' => 'Unit A', 'alasan' => 'Pembatasan evaluasi', 'ditetapkan_oleh' => $this->actor->id,
            'created_at' => $deny->created_at->toISOString(),
        ], $audit->nilai_baru);
        $this->assertTrue($audit->dasar_izin['allowed']);
        $this->assertSame('akses:update', $audit->dasar_izin['permission']);
        $this->create();
        $this->assertFalse($resolver->allows($this->target, $this->permission->kode, $other->id));
        $this->assertDatabaseCount('user_permission_denied', 2);
    }

    public function test_create_unit_context_deny_for_global_permission_preserves_contextless_allow(): void
    {
        $this->permission = Permission::where('kode', 'dashboard:read')->sole();
        $this->giveRole($this->target, 'pegawai', $this->permission);
        $this->create($this->unit->id);
        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($this->target, 'dashboard:read'));
        $this->assertFalse($resolver->allows($this->target, 'dashboard:read', $this->unit->id));
        $this->assertTrue($resolver->allows($this->target, 'dashboard:read', (string) Str::uuid()));
    }

    #[DataProvider('invalidInput')]
    public function test_create_invalid_input_has_no_mutation_or_audit(string $case, string $field): void
    {
        $targetId = $this->target->id;
        $permissionId = $this->permission->id;
        $unitId = $this->unit->id;
        $reason = 'Alasan';
        match ($case) {
            'blank' => $reason = '   ',
            'long' => $reason = str_repeat('a', 2001),
            'target' => $targetId = (string) Str::uuid(),
            'permission' => $permissionId = (string) Str::uuid(),
            'inactive' => $this->permission->update(['aktif' => false]),
            'foreign' => $this->permission->update(['kode' => 'legacy:izin_uji']),
            'unit' => $unitId = (string) Str::uuid(),
            'malformed' => $unitId = 'bukan-uuid',
        };
        try {
            app(CreateDeny::class)->handle($this->actor, $targetId, $permissionId, $unitId, $reason);
            $this->fail('Input tidak sah harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('user_permission_denied', 0);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public static function invalidInput(): array
    {
        return [['blank', 'alasan'], ['long', 'alasan'], ['target', 'user_id'], ['permission', 'permission_id'], ['inactive', 'permission_id'], ['foreign', 'permission_id'], ['unit', 'unit_id'], ['malformed', 'unit_id']];
    }

    public function test_create_accepts_inactive_target_and_unit_without_granting_or_activating(): void
    {
        $this->target->update(['is_active' => false]);
        $this->unit->update(['status' => 'nonaktif']);
        $deny = $this->create($this->unit->id);
        $this->assertTrue($deny->exists);
        $this->assertFalse($this->target->fresh()->is_active);
        $this->assertSame('nonaktif', $this->unit->fresh()->status);
        $this->assertDatabaseCount('user_permission_granted', 0);
    }

    #[DataProvider('scopes')]
    public function test_create_duplicate_preserves_original_row_and_audit(bool $scoped): void
    {
        $unitId = $scoped ? $this->unit->id : null;
        $deny = $this->create($unitId)->fresh();
        $history = AuditLog::sole()->getRawOriginal();
        try {
            app(CreateDeny::class)->handle($this->actor, $this->target->id, $this->permission->id, $unitId, 'Tidak boleh menimpa');
            $this->fail('Tuple duplikat harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permission_id', $exception->errors());
        }
        $this->assertSame($deny->getRawOriginal(), $deny->fresh()->getRawOriginal());
        $this->assertSame($history, AuditLog::sole()->getRawOriginal());
        $this->assertDatabaseCount('user_permission_denied', 1);
    }

    public static function scopes(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('operators')]
    public function test_create_rechecks_live_authorization_without_role_bypass(string $role): void
    {
        $permission = Permission::where('kode', 'akses:update')->sole();
        $this->giveRole($this->actor, $role, $permission);
        $this->assertTrue(app(PermissionResolver::class)->allows($this->actor, 'akses:update'));
        UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => $permission->id, 'alasan' => 'Cabut pengelola', 'ditetapkan_oleh' => $this->actor->id]);
        try {
            $this->create();
            $this->fail('Deny terbaru harus berlaku.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('user_permission_denied', ['user_id' => $this->target->id]);
        }
        $audit = AuditLog::sole();
        $this->assertSame('user_permission_denied.ditolak', $audit->tindakan);
        $this->assertSame('users', $audit->objek_tipe);
        $this->assertSame($this->target->id, $audit->objek_id);
        $this->assertFalse($audit->dasar_izin['allowed']);
        $this->assertNull($audit->nilai_baru);
    }

    public static function operators(): array
    {
        return [['admin'], ['superadmin']];
    }

    public function test_create_audit_failure_rolls_back_deny(): void
    {
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            $this->create();
            $this->fail('Audit gagal harus membatalkan deny.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit-unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('user_permission_denied', 0);
        $this->assertDatabaseCount('audit_log', 0);
    }

    #[DataProvider('scopes')]
    public function test_create_maps_only_real_named_deny_constraint_after_rollback(bool $scoped): void
    {
        // Writer nonkooperatif melewati precheck; PostgreSQL tetap menjadi pengaman terakhir.
        Event::listen('eloquent.creating: '.UserPermissionDeny::class, function (UserPermissionDeny $deny): void {
            DB::table('user_permission_denied')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $deny->user_id, 'permission_id' => $deny->permission_id,
                'unit_id' => $deny->unit_id, 'alasan' => 'Writer lain', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
            ]);
        });
        try {
            $this->create($scoped ? $this->unit->id : null);
            $this->fail('Constraint database harus diterjemahkan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permission_id', $exception->errors());
        }
        $this->assertDatabaseCount('user_permission_denied', 0);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_create_does_not_disguise_unrelated_database_failure_as_duplicate_deny(): void
    {
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andReturnUsing(function (): void {
            DB::table('permissions')->insert(['id' => Str::uuid(), 'kode' => $this->permission->kode, 'entitas' => 'pengukuran', 'aksi' => 'update']);
        });
        try {
            $this->create();
            $this->fail('Error constraint lain harus diteruskan.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
            $this->assertStringContainsString('permissions_kode_unique', $exception->errorInfo[2]);
        }
        $this->assertDatabaseCount('user_permission_denied', 0);
        $this->assertDatabaseCount('audit_log', 0);
    }

    #[DataProvider('remainingAccess')]
    public function test_revoke_audits_original_snapshot_and_re_evaluates_remaining_access(string $state, bool $allowed): void
    {
        $this->giveRole($this->target, 'pegawai', $this->permission);
        $deny = $this->create($this->unit->id);
        $created = AuditLog::sole();
        $history = $created->getRawOriginal();
        if ($state === 'global-deny') {
            $this->create();
        } elseif ($state === 'no-allow') {
            $this->target->roles()->detach();
        } elseif ($state === 'inactive') {
            $this->permission->update(['aktif' => false]);
        }
        app(RevokeDeny::class)->handle($this->actor, $deny->id, '  Batasan dicabut  ');
        $this->assertDatabaseMissing('user_permission_denied', ['id' => $deny->id]);
        $audit = AuditLog::where('tindakan', 'user_permission_denied.hapus')->sole();
        $this->assertSame($deny->id, $audit->objek_id);
        $this->assertSame('user_permission_denied', $audit->objek_tipe);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame('Batasan dicabut', $audit->alasan);
        $this->assertSame($created->nilai_baru, $audit->nilai_lama);
        $this->assertNull($audit->nilai_baru);
        $this->assertTrue($audit->dasar_izin['allowed']);
        $this->assertSame($history, $created->fresh()->getRawOriginal());
        $this->assertSame($allowed, app(PermissionResolver::class)->allows($this->target, $this->permission->kode, $this->unit->id));
        $this->assertDatabaseCount('user_permission_granted', 0);
    }

    public static function remainingAccess(): array
    {
        return [['allow', true], ['global-deny', false], ['no-allow', false], ['inactive', false]];
    }

    public function test_revoke_legacy_permission_outside_catalog_preserves_reference_and_provenance(): void
    {
        $permission = Permission::create(['kode' => 'legacy:izin_uji', 'entitas' => 'legacy', 'aksi' => 'izin_uji', 'aktif' => true, 'butuh_scope' => 'global']);
        $this->assertNotContains($permission->kode, PermissionCatalog::codes());
        $deny = UserPermissionDeny::create(['user_id' => $this->target->id, 'permission_id' => $permission->id, 'unit_id' => $this->unit->id, 'alasan' => 'Provenance legacy', 'ditetapkan_oleh' => $this->target->id]);
        $deny->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();
        $reference = $permission->fresh()->getRawOriginal();
        $before = $deny->fresh()->auditSnapshot();
        app(RevokeDeny::class)->handle($this->actor, $deny->id, 'Bersihkan deny legacy');
        $this->assertDatabaseMissing('user_permission_denied', ['id' => $deny->id]);
        $this->assertSame($reference, $permission->fresh()->getRawOriginal());
        $audit = AuditLog::sole();
        $this->assertEquals($before, $audit->nilai_lama);
        $this->assertSame('legacy:izin_uji', $audit->nilai_lama['permission_kode']);
        $this->assertSame('2026-01-01T00:00:00.000000Z', $audit->nilai_lama['created_at']);
        $this->assertSame('Bersihkan deny legacy', $audit->alasan);
        $this->assertFalse(app(PermissionResolver::class)->allows($this->target, $permission->kode, $this->unit->id));
    }

    public function test_revoke_exact_uuid_rejects_second_revoke_and_aba_without_touching_replacement(): void
    {
        $first = $this->create();
        app(RevokeDeny::class)->handle($this->actor, $first->id, 'Cabut awal');
        foreach ([false, true] as $replacement) {
            if ($replacement) {
                $latest = $this->create()->fresh();
            }
            $history = DB::table('audit_log')->orderBy('id')->get()->toJson();
            try {
                app(RevokeDeny::class)->handle($this->actor, $first->id, 'Permintaan basi');
                $this->fail('UUID lama tidak boleh menunjuk tuple baru.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('deny_id', $exception->errors());
            }
            $this->assertSame($history, DB::table('audit_log')->orderBy('id')->get()->toJson());
            if ($replacement) {
                $this->assertSame($latest->getRawOriginal(), $latest->fresh()->getRawOriginal());
            }
        }
    }

    #[DataProvider('invalidRevoke')]
    public function test_revoke_rejects_invalid_input(string $id, string $reason, string $field): void
    {
        $deny = $this->create();
        try {
            app(RevokeDeny::class)->handle($this->actor, $id === 'existing' ? $deny->id : $id, $reason);
            $this->fail('Input revoke harus valid.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseHas('user_permission_denied', ['id' => $deny->id]);
        $this->assertDatabaseCount('audit_log', 1);
    }

    public static function invalidRevoke(): array
    {
        return [['existing', '   ', 'alasan'], ['existing', str_repeat('a', 2001), 'alasan'], ['malformed', 'Cabut', 'deny_id']];
    }

    public function test_revoke_rechecks_live_permission_before_disclosing_missing_or_existing_deny(): void
    {
        $deny = $this->create();
        UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'alasan' => 'Pembatasan pengelola', 'ditetapkan_oleh' => $this->target->id]);
        foreach ([$deny->id, (string) Str::uuid()] as $id) {
            try {
                app(RevokeDeny::class)->handle($this->actor, $id, 'Tidak berizin');
                $this->fail('Permission harus diperiksa sebelum keberadaan row.');
            } catch (AuthorizationException) {
                $entry = AuditLog::where('tindakan', 'user_permission_denied.ditolak')->where('objek_id', $id)->sole();
                $this->assertSame('user_permission_denied', $entry->objek_tipe);
                $this->assertFalse($entry->dasar_izin['allowed']);
                $this->assertNull($entry->nilai_lama);
            }
        }
        $this->assertDatabaseHas('user_permission_denied', ['id' => $deny->id]);
        $this->assertSame(2, AuditLog::where('tindakan', 'user_permission_denied.ditolak')->count());
    }

    public function test_revoke_audit_failure_rolls_back_deletion_and_preserves_history(): void
    {
        $deny = $this->create()->fresh();
        $history = AuditLog::sole()->getRawOriginal();
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            app(RevokeDeny::class)->handle($this->actor, $deny->id, 'Cabut');
            $this->fail('Audit failure harus rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit-unavailable', $exception->getMessage());
        }
        $this->assertSame($deny->getRawOriginal(), $deny->fresh()->getRawOriginal());
        $this->assertSame($history, AuditLog::sole()->getRawOriginal());
    }

    public function test_http_requires_active_authorized_actor_and_gates_known_uuid_without_audit(): void
    {
        $deny = $this->create();
        $paths = ['/akses/deny', '/akses/deny/opsi/pengguna', '/akses/deny/opsi/unit', '/akses/deny/opsi/izin'];
        $this->get('/akses/deny')->assertRedirect('/login');
        foreach (['missing', 'denied', 'inactive'] as $state) {
            $operator = User::factory()->create(['is_active' => $state !== 'inactive']);
            if ($state === 'denied') {
                $this->giveRole($operator, 'admin', Permission::where('kode', 'akses:update')->sole());
                UserPermissionDeny::create(['user_id' => $operator->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'alasan' => 'Deny operator', 'ditetapkan_oleh' => $this->actor->id]);
            }
            $this->actingAs($operator);
            foreach ($paths as $path) {
                $response = $this->get($path);
                $state === 'inactive' ? $response->assertRedirect('/auth/pending') : $response->assertForbidden();
            }
            foreach (['/akses/deny', '/akses/deny/'.$deny->id.'/cabut'] as $path) {
                $response = $this->post($path, $this->payload());
                $state === 'inactive' ? $response->assertRedirect('/auth/pending') : $response->assertForbidden();
            }
        }
        $this->assertDatabaseHas('user_permission_denied', ['id' => $deny->id]);
        $this->assertDatabaseCount('audit_log', 1);
    }

    private function payload(): array
    {
        return ['user_id' => $this->target->id, 'permission_id' => $this->permission->id, 'unit_id' => null, 'alasan' => 'Pembatasan HTTP'];
    }

    public function test_http_uses_only_akses_update_and_normal_receipt_flashes_once(): void
    {
        $this->assertFalse(app(PermissionResolver::class)->allows($this->actor, 'pengguna:read'));
        $this->actingAs($this->actor)->get('/akses/deny')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/DenyIndex', false)->where('can.manageDeny', true)->where('auth.can.manageDeny', true)->has('denies', 0));
        $this->post('/akses/deny', $this->payload())->assertStatus(303)->assertRedirect('/akses/deny/hasil')
            ->assertSessionHas('denyResult', ['actor_id' => $this->actor->id, 'status' => 'created']);
        $this->get('/akses/deny/hasil')->assertRedirect('/akses/deny');
        $this->get('/akses/deny')->assertInertia(fn (Assert $page) => $page->hasFlash('denyStatus', 'created'))
            ->assertViewHas('page', fn (array $page) => ($page['clearHistory'] ?? false) === true);
        $this->get('/akses/deny')->assertInertia(fn (Assert $page) => $page->missingFlash('denyStatus'));
    }

    public function test_http_rejects_spoofed_provenance_and_revoke_target_overrides(): void
    {
        $this->actingAs($this->actor);
        $this->post('/akses/deny', $this->payload() + ['actor_id' => $this->target->id, 'ditetapkan_oleh' => $this->target->id, 'dasar_izin' => ['allowed' => true], 'created_at' => now()->toISOString(), 'is_active' => true, 'grant' => ['x']])
            ->assertSessionHasErrors(['actor_id', 'ditetapkan_oleh', 'dasar_izin', 'created_at', 'is_active', 'grant']);
        $this->assertDatabaseCount('audit_log', 0);
        $deny = $this->create();
        $this->post('/akses/deny/'.$deny->id.'/cabut', $this->payload())->assertSessionHasErrors(['user_id', 'permission_id']);
        $this->post('/akses/deny/not-uuid/cabut', ['alasan' => 'Cabut'])->assertNotFound();
        $this->post('/akses/deny/'.Str::uuid().'/cabut', ['alasan' => 'Cabut'])->assertSessionHasErrors('deny_id');
        $this->assertDatabaseHas('user_permission_denied', ['id' => $deny->id]);
        $this->assertDatabaseCount('audit_log', 1);
    }

    public function test_http_legacy_and_inactive_deny_remain_visible_and_revokable_but_not_create_candidates(): void
    {
        $this->permission->update(['kode' => 'legacy:izin_uji', 'aktif' => false]);
        $this->target->update(['nama' => 'Target Legacy', 'is_active' => false]);
        $this->unit->update(['status' => 'nonaktif']);
        $deny = UserPermissionDeny::create(['user_id' => $this->target->id, 'permission_id' => $this->permission->id, 'unit_id' => $this->unit->id, 'alasan' => 'Legacy', 'ditetapkan_oleh' => $this->actor->id]);
        $this->actingAs($this->actor)->get('/akses/deny?q=Target%20Legacy')->assertInertia(fn (Assert $page) => $page
            ->component('Access/DenyIndex', false)->has('denies', 1)->where('denies.0.id', $deny->id)
            ->where('denies.0.permission.kode', 'legacy:izin_uji')->where('denies.0.permission.aktif', false)
            ->where('denies.0.user.is_active', false)->where('denies.0.unit.status', 'nonaktif')
            ->missing('denies.0.user.keycloak_id')->missing('denies.0.user.roles')->missing('denies.0.permission.sensitif'));
        $this->getJson('/akses/deny/opsi/izin?q=legacy')->assertOk()->assertJsonCount(0, 'items');
        $this->permission->update(['aktif' => true]);
        $this->getJson('/akses/deny/opsi/izin?q=legacy')->assertOk()->assertJsonCount(0, 'items');
        $this->post('/akses/deny/'.$deny->id.'/cabut', ['alasan' => 'Bersihkan legacy'])->assertSessionHasNoErrors()->assertRedirect('/akses/deny/hasil');
        $this->assertDatabaseMissing('user_permission_denied', ['id' => $deny->id]);
        $this->assertSame('legacy:izin_uji', AuditLog::sole()->nilai_lama['permission_kode']);
    }

    public function test_http_list_and_lookups_are_bounded_filtered_and_projected(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $target = User::factory()->create(['nama' => sprintf('Fixture %02d', $i), 'is_active' => false]);
            $unit = Unit::create(['nama' => sprintf('Fixture %02d', $i), 'status' => 'nonaktif', 'created_by' => $this->actor->id]);
            UserPermissionDeny::create(['user_id' => $target->id, 'permission_id' => $this->permission->id, 'unit_id' => $unit->id, 'alasan' => 'Fixture', 'ditetapkan_oleh' => $this->actor->id]);
        }
        $this->actingAs($this->actor)->get('/akses/deny?q=Fixture')->assertInertia(fn (Assert $page) => $page
            ->component('Access/DenyIndex', false)->has('denies', 20)->where('pagination.current_page', 1)
            ->where('pagination.next_page_url', fn ($url) => str_contains($url, 'page=2')));
        $this->get('/akses/deny?q=Fixture&page=2')->assertInertia(fn (Assert $page) => $page->has('denies', 1)->where('pagination.next_page_url', null));
        foreach (['pengguna', 'unit'] as $lookup) {
            $response = $this->getJson('/akses/deny/opsi/'.$lookup.'?q=Fixture')->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('hasMore', true);
            $this->assertSame('Fixture 00', $response->json('items.0.nama'));
            $this->assertSame($lookup === 'pengguna' ? ['id', 'nama', 'email', 'is_active'] : ['id', 'nama', 'status'], array_keys($response->json('items.0')));
            $this->getJson('/akses/deny/opsi/'.$lookup.'?q=Fixture&page=2')->assertJsonCount(1, 'items')->assertJsonPath('hasMore', false);
        }
        $response = $this->getJson('/akses/deny/opsi/izin')->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('hasMore', true);
        $this->assertSame(['id', 'kode', 'keterangan', 'butuh_scope'], array_keys($response->json('items.0')));
        foreach (['/akses/deny', '/akses/deny/opsi/pengguna', '/akses/deny/opsi/unit', '/akses/deny/opsi/izin'] as $path) {
            $this->getJson($path.'?page=0&q='.str_repeat('x', 101))->assertUnprocessable()->assertJsonValidationErrors(['q', 'page']);
        }
    }

    public function test_http_self_deny_receipt_is_actor_bound_and_cannot_restore_privilege(): void
    {
        $this->actingAs($this->actor)->post('/akses/deny', ['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'unit_id' => null, 'alasan' => 'Batasi diri'])
            ->assertStatus(303)->assertRedirect('/akses/deny/hasil');
        $this->get('/akses/deny/hasil')->assertInertia(fn (Assert $page) => $page->component('Access/DenyResult', false)
            ->where('status', 'created')->where('canReturn', false)->where('auth.can.manageDeny', false)->missing('denies')->missing('target')->missing('alasan'));
        $this->get('/akses/deny/hasil')->assertInertia(fn (Assert $page) => $page->where('status', null));
        $deny = UserPermissionDeny::sole();
        $this->post('/akses/deny/'.$deny->id.'/cabut', ['alasan' => 'Pulihkan diri'])->assertForbidden();
        $this->get('/akses/deny')->assertForbidden();
        $this->assertDatabaseCount('audit_log', 1);
        $this->withSession(['denyResult' => ['actor_id' => $this->target->id, 'status' => 'created']])->get('/akses/deny/hasil')->assertInertia(fn (Assert $page) => $page->where('status', null));
        $this->giveRole($this->target, 'admin', Permission::where('kode', 'akses:update')->sole());
        $this->actingAs($this->target)->post('/akses/deny/'.$deny->id.'/cabut', ['alasan' => 'Pemulihan oleh operator lain'])->assertSessionHasNoErrors()->assertRedirect('/akses/deny/hasil');
        $this->assertTrue(app(PermissionResolver::class)->allows($this->actor->fresh(), 'akses:update'));
        $this->assertDatabaseCount('audit_log', 2);
    }
}
