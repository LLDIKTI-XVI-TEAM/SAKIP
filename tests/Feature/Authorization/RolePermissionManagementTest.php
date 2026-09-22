<?php

namespace Tests\Feature\Authorization;

use App\Actions\Access\ChangeRolePermission;
use App\Actions\Audit\WriteAuditLog;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Policies\RolePermissionPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RolePermissionReceipt;
use App\Services\Authorization\RolePermissionState;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Role $source;

    private Role $target;

    private Permission $permission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('X-Inertia-Version', (string) app(HandleInertiaRequests::class)->version(request()));
        $this->withCookie(config('session.cookie'), str_repeat('a', 40));
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['is_active' => true]);
        $this->source = Role::where('kode', 'superadmin')->sole();
        $this->target = Role::where('kode', 'pic')->sole();
        $this->permission = Permission::where('kode', 'dashboard:read')->sole();
        $this->attach($this->source, Permission::where('kode', 'akses:update')->sole());
        $this->assign($this->actor, $this->source);
    }

    #[DataProvider('policyCases')]
    public function test_ops1_requires_live_superadmin_and_permission_without_pengguna_read(string $case, bool $allowed, string $reason): void
    {
        if ($case === 'admin') {
            $admin = Role::where('kode', 'admin')->sole();
            $this->attach($admin, Permission::where('kode', 'akses:update')->sole());
            DB::table('user_roles')->where('user_id', $this->actor->id)->update(['role_id' => $admin->id]);
        } elseif ($case === 'inactive_user') {
            $this->actor->update(['is_active' => false]);
        } elseif ($case === 'inactive_role') {
            $this->source->update(['aktif' => false]);
        } elseif ($case === 'deny') {
            UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'alasan' => 'Pembatasan', 'ditetapkan_oleh' => $this->actor->id]);
        } elseif ($case === 'no_allow') {
            $this->source->permissions()->detach();
        }
        $decision = app(RolePermissionPolicy::class)->decide($this->actor->fresh());
        $this->assertSame($allowed, $decision['allowed']);
        $this->assertSame($reason, $decision['reason']);
        $this->assertSame('akses:update', $decision['akses_update']['permission']);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public static function policyCases(): array
    {
        return [
            ['allowed', true, 'allow'], ['admin', false, 'not_superadmin'],
            ['inactive_user', false, 'inactive_user'], ['inactive_role', false, 'not_superadmin'],
            ['deny', false, 'access_denied'], ['no_allow', false, 'access_denied'],
        ];
    }

    public function test_snapshot_is_stable_and_tracks_pivot_identity_metadata_and_successful_audit_only(): void
    {
        $empty = $this->state();
        $this->assertSame($empty, $this->state());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $empty['token']);
        $this->attach($this->target, $this->permission);
        $attached = $this->state();
        $this->assertSame(['dashboard:read'], $attached['codes']);
        $this->assertNotSame($empty['token'], $attached['token']);
        DB::table('role_permissions')->where('role_id', $this->target->id)->update(['id' => Str::uuid()]);
        $replaced = $this->state();
        $this->assertNotSame($attached['token'], $replaced['token']);
        $this->permission->update(['aktif' => false]);
        $this->assertNotSame($replaced['token'], $this->state()['token']);
        $this->target->permissions()->detach();
        app(WriteAuditLog::class)->handle(['actor_type' => 'user', 'actor_id' => $this->actor->id, 'sumber' => 'manual', 'tindakan' => 'role_permissions.ditolak', 'objek_tipe' => 'roles', 'objek_id' => $this->target->id, 'alasan' => 'Ditolak']);
        $this->assertSame($empty['token'], $this->state()['token']);
        app(WriteAuditLog::class)->handle(['actor_type' => 'user', 'actor_id' => $this->actor->id, 'sumber' => 'manual', 'tindakan' => 'role_permissions.ubah', 'objek_tipe' => 'roles', 'objek_id' => $this->target->id, 'alasan' => 'Perubahan tercatat']);
        $this->assertNotSame($empty['token'], $this->state()['token']);
    }

    public function test_delta_audit_preserves_unrelated_rows_and_applies_live_to_both_holders(): void
    {
        $users = User::factory()->count(2)->create(['is_active' => true]);
        foreach ($users as $user) {
            $this->assign($user, $this->target);
        }
        $scoped = Permission::where('kode', 'pengukuran:update')->sole();
        $inactive = Permission::where('kode', 'audit:read')->sole();
        $inactive->update(['aktif' => false]);
        $legacy = Permission::create(['kode' => 'legacy:read', 'entitas' => 'legacy', 'aksi' => 'read']);
        foreach ([$scoped, $inactive, $legacy] as $permission) {
            $this->attach($this->target, $permission);
        }
        $grant = UserPermissionGrant::create(['user_id' => $users[1]->id, 'permission_id' => $this->permission->id, 'alasan' => 'Grant tetap', 'diberikan_oleh' => $this->actor->id]);
        $denyUser = User::factory()->create(['is_active' => true]);
        $this->assign($denyUser, $this->target);
        UserPermissionDeny::create(['user_id' => $denyUser->id, 'permission_id' => $this->permission->id, 'alasan' => 'Deny tetap', 'ditetapkan_oleh' => $this->actor->id]);
        $unit = Unit::create(['nama' => 'Unit Preservasi', 'created_by' => $this->actor->id]);
        $renstra = Renstra::create(['kode' => 'R-P', 'nama' => 'Renstra Preservasi', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_aktif' => true]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-P', 'deskripsi' => 'Sasaran Preservasi']);
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id, 'kode' => 'I-P', 'nama' => 'Indikator Preservasi', 'satuan' => 'poin', 'tipe_perhitungan' => 'manual']);
        PenugasanIndikator::create(['indikator_id' => $indikator->id, 'user_id' => $users[0]->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Penugasan tetap', 'created_at' => now()]);
        $protected = $this->preserved();
        $pivots = DB::table('role_permissions')->orderBy('id')->get()->toJson();
        $this->assertSame('added', $this->change('add', '  Penambahan global  '));
        $resolver = app(PermissionResolver::class);
        foreach ($users as $user) {
            $this->assertTrue($resolver->allows($user, 'dashboard:read'));
        }
        $this->assertFalse($resolver->allows($denyUser, 'dashboard:read'));
        $audit = AuditLog::sole();
        $this->assertSame(['permissions' => ['audit:read', 'legacy:read', 'pengukuran:update']], $audit->nilai_lama);
        $this->assertSame(['permissions' => ['audit:read', 'dashboard:read', 'legacy:read', 'pengukuran:update']], $audit->nilai_baru);
        $this->assertSame('role_permissions.ubah', $audit->tindakan);
        $this->assertSame('roles', $audit->objek_tipe);
        $this->assertSame($this->target->id, $audit->objek_id);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('manual', $audit->sumber);
        $this->assertSame('Penambahan global', $audit->alasan);
        $this->assertTrue($audit->dasar_izin['akses_update']['allowed']);
        $this->assertEquals(['required_role' => 'superadmin', 'allowed' => true], $audit->dasar_izin['role_policy']);
        $history = $audit->getRawOriginal();
        $this->assertSame('revoked', $this->change('revoke'));
        $revoke = AuditLog::where('id', '!=', $audit->id)->sole();
        $this->assertSame($audit->nilai_baru, $revoke->nilai_lama);
        $this->assertSame($audit->nilai_lama, $revoke->nilai_baru);
        $this->assertFalse($resolver->allows($users[0], 'dashboard:read'));
        $this->assertTrue($resolver->allows($users[1], 'dashboard:read'));
        $this->assertSame($history, $audit->fresh()->getRawOriginal());
        $this->assertSame($pivots, DB::table('role_permissions')->orderBy('id')->get()->toJson());
        $this->assertSame($protected, $this->preserved());
        $this->assertSame($grant->alasan, $grant->fresh()->alasan);
    }

    #[DataProvider('invalidChanges')]
    public function test_invalid_delta_is_rejected_without_mutation_or_audit(string $case, string $operation, string $field): void
    {
        $reason = 'Perubahan';
        if ($case === 'blank' || $case === 'long') {
            $reason = $case === 'blank' ? '   ' : str_repeat('x', 2001);
        } elseif ($case === 'role_inactive') {
            $this->target->update(['aktif' => false]);
        } elseif ($case === 'role_unknown') {
            $this->target->update(['kode' => 'legacy']);
        } elseif ($case === 'permission_inactive') {
            $this->permission->update(['aktif' => false]);
        } elseif ($case === 'permission_unknown') {
            $this->permission->update(['kode' => 'legacy:read']);
        } elseif ($case === 'scoped') {
            $this->permission = Permission::where('kode', 'pengukuran:update')->sole();
        }
        if ($operation === 'revoke') {
            $this->attach($this->target, $this->permission);
        }
        $before = DB::table('role_permissions')->orderBy('id')->get()->toJson();
        try {
            $this->change($operation, $reason);
            $this->fail('Input domain tidak sah harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame($before, DB::table('role_permissions')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('audit_log', 0);
    }

    public static function invalidChanges(): array
    {
        $cases = [];
        foreach (['add', 'revoke'] as $operation) {
            foreach (['blank' => 'alasan', 'long' => 'alasan', 'role_inactive' => 'role_id', 'role_unknown' => 'role_id', 'permission_inactive' => 'permission_id', 'permission_unknown' => 'permission_id', 'scoped' => 'permission_id'] as $case => $field) {
                $cases[$case.'_'.$operation] = [$case, $operation, $field];
            }
        }

        return $cases;
    }

    public function test_stale_is_checked_before_noop_and_both_aba_cycles_are_rejected(): void
    {
        $empty = $this->state()['token'];
        $this->assertSame('unchanged', $this->change('revoke'));
        $this->assertDatabaseCount('audit_log', 0);
        $this->change('add');
        $present = $this->state()['token'];
        $pivot = DB::table('role_permissions')->where('role_id', $this->target->id)->sole();
        $this->assertSame('unchanged', $this->change('add'));
        $this->assertEquals($pivot, DB::table('role_permissions')->where('role_id', $this->target->id)->sole());
        $this->change('revoke');
        $this->assertStale('revoke', $empty);
        $this->change('add');
        $this->assertStale('add', $present);
        $this->assertDatabaseCount('audit_log', 3);
    }

    public function test_audit_failure_rolls_back_the_delta(): void
    {
        $this->instance(WriteAuditLog::class, new class extends WriteAuditLog
        {
            public function handle(array $attributes): AuditLog
            {
                throw new RuntimeException('Audit tidak tersedia');
            }
        });
        $before = $this->state();
        try {
            $this->change('add');
            $this->fail('Kegagalan audit harus membatalkan perubahan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit tidak tersedia', $exception->getMessage());
        }
        $this->assertSame($before, $this->state());
        $this->assertDatabaseCount('audit_log', 0);
    }

    #[DataProvider('actionDenials')]
    public function test_action_rechecks_authorization_and_audits_denial_without_target_details(string $case): void
    {
        $this->assertTrue(app(RolePermissionPolicy::class)->decide($this->actor)['allowed']);
        if ($case === 'deny') {
            UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'alasan' => 'Pembatasan', 'ditetapkan_oleh' => $this->actor->id]);
        } else {
            DB::table('user_roles')->where('user_id', $this->actor->id)->update(['role_id' => $this->target->id]);
            UserPermissionGrant::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'alasan' => 'Grant tetap', 'diberikan_oleh' => $this->actor->id]);
        }
        try {
            $this->change('add');
            $this->fail('Akses aktual harus diperiksa kembali.');
        } catch (AuthorizationException) {
            $audit = AuditLog::sole();
            $this->assertSame('role_permissions.ditolak', $audit->tindakan);
            $this->assertSame('users', $audit->objek_tipe);
            $this->assertSame($this->actor->id, $audit->objek_id);
            $this->assertNull($audit->nilai_lama);
            $this->assertNull($audit->nilai_baru);
        }
        $this->assertSame([], $this->state()['codes']);
    }

    public static function actionDenials(): array
    {
        return [['deny'], ['assignment']];
    }

    #[DataProvider('preActionRecoveryCases')]
    public function test_pre_action_recovery_does_not_mutate_audit_or_issue_receipt(int $status, string $reason): void
    {
        if ($status === 419) {
            $this->actingAs($this->actor);
            // Sama dengan proof recovery: hanya shortcut testing dimatikan, validasi framework tetap aktif.
            $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app->make('encrypter')) extends PreventRequestForgery
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            });
        }
        $path = '/akses/izin-peran/'.$this->target->id;
        $before = [];
        foreach (['role_permissions', 'audit_log', 'cache'] as $table) {
            $before[$table] = DB::table($table)->orderBy($table === 'cache' ? 'key' : 'id')->get()->toJson();
        }
        $this->post($path, $this->payload(), ['X-Inertia' => 'true'])
            ->assertStatus($status)->assertHeaderMissing('Location')->assertHeaderMissing('X-Inertia-Location')
            ->assertJsonPath('recovery.reason', $reason)
            ->assertJsonPath('recovery.rejected', ['method' => 'POST', 'path' => $path, 'before_action' => true]);
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy($table === 'cache' ? 'key' : 'id')->get()->toJson());
        }
    }

    public static function preActionRecoveryCases(): array
    {
        return [[401, 'authentication_required'], [419, 'csrf_mismatch']];
    }

    public function test_http_gate_is_pure_and_input_cannot_spoof_provenance_or_receipt(): void
    {
        $this->get('/akses/izin-peran')->assertRedirect('/login');
        $this->actingAs($this->actor)->get('/akses/izin-peran')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/RolePermissionIndex', false)->has('roles', 6)->where('roles.3.kode', 'pic')
            ->where('selectedRole', null)->where('receiptId', null)->where('auth.can.manageRolePermissions', true));
        $this->post('/akses/izin-peran/'.$this->target->id, $this->payload() + ['actor_id' => $this->actor->id, 'receipt' => Str::uuid(), 'status' => 'added', 'unit_id' => Str::uuid()])
            ->assertSessionHasErrors(['actor_id', 'receipt', 'status', 'unit_id']);
        $this->post('/akses/izin-peran/bad', $this->payload())->assertNotFound();
        $this->post('/akses/izin-peran/'.Str::uuid(), $this->payload())->assertSessionHasErrors('role_id');
        $this->post('/akses/izin-peran/'.$this->target->id, array_replace($this->payload(), ['permission_id' => (string) Str::uuid()]))->assertSessionHasErrors('permission_id');
        $this->post('/akses/izin-peran/'.$this->target->id, array_replace($this->payload(), ['expected_state' => 'bad', 'operation' => 'replace', 'alasan' => ' ']))->assertSessionHasErrors(['expected_state', 'operation', 'alasan']);
        UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'akses:update')->value('id'), 'alasan' => 'Deny gate', 'ditetapkan_oleh' => $this->actor->id]);
        $this->get('/akses/izin-peran')->assertForbidden();
        $this->post('/akses/izin-peran/'.$this->target->id, $this->payload())->assertForbidden();
        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_index_bounds_attached_data_and_excludes_noneditable_create_candidates(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $legacy = Permission::create(['kode' => sprintf('legacy:%02d', $i), 'entitas' => 'legacy', 'aksi' => (string) $i]);
            $this->attach($this->target, $legacy);
        }
        $this->attach($this->target, Permission::where('kode', 'pengukuran:update')->sole());
        $inactive = Permission::where('kode', 'audit:read')->sole();
        $inactive->update(['aktif' => false]);
        $this->attach($this->target, $inactive);
        $this->actingAs($this->actor)->get('/akses/izin-peran?role='.$this->target->id)->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 20)->where('permissions.0.non_editable_reason', 'inactive')
            ->where('permissions.1.non_editable_reason', 'unknown')->has('expectedState')->missing('permissions.0.pivot_id')
            ->missing('permissions.0.sensitif')->missing('users')->missing('audit'));
        $this->get('/akses/izin-peran?role='.$this->target->id.'&q=pengukuran:update')->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 1)->where('permissions.0.non_editable_reason', 'scoped')->where('permissions.0.editable', false));
        $this->get('/akses/izin-peran?role='.$this->target->id.'&view=available&q=dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 1)->where('permissions.0.kode', 'dashboard:read')->where('permissions.0.editable', true)->where('permissions.0.attached', false));
        $this->get('/akses/izin-peran?role='.$this->target->id.'&view=available&q=legacy')->assertInertia(fn (Assert $page) => $page->has('permissions', 0));
    }

    #[DataProvider('nonSuperadminCases')]
    public function test_http_rejects_non_superadmin_even_with_role_allow_or_grant(bool $grant): void
    {
        $role = Role::where('kode', $grant ? 'pegawai' : 'admin')->sole();
        DB::table('user_roles')->where('user_id', $this->actor->id)->update(['role_id' => $role->id]);
        $access = Permission::where('kode', 'akses:update')->sole();
        if ($grant) {
            UserPermissionGrant::create(['user_id' => $this->actor->id, 'permission_id' => $access->id, 'alasan' => 'Grant sah', 'diberikan_oleh' => $this->actor->id]);
        } else {
            $this->attach($role, $access);
        }
        $this->assertTrue(app(PermissionResolver::class)->allows($this->actor, 'akses:update'));
        $this->actingAs($this->actor)->get('/akses/izin-peran')->assertForbidden();
        $this->post('/akses/izin-peran/'.$this->target->id, $this->payload())->assertForbidden();
        $this->assertDatabaseCount('audit_log', 0);
        $this->assertSame([], $this->state()['codes']);
    }

    public static function nonSuperadminCases(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('readOrders')]
    public function test_two_posts_in_one_session_keep_outcomes_correlated_through_final_flash(bool $reverse): void
    {
        $this->actingAs($this->actor)->withHeader('X-Inertia', 'true');
        $a = $this->post('/akses/izin-peran/'.$this->target->id, $this->payload())->assertStatus(303)->headers->get('Location');
        $sessionId = session()->getId();
        $this->target = Role::where('kode', 'pegawai')->sole();
        $this->attach($this->target, $this->permission);
        $b = $this->post('/akses/izin-peran/'.$this->target->id, $this->payload('revoke'))->assertStatus(303)->headers->get('Location');
        $this->assertSame($sessionId, session()->getId());
        $this->assertNotSame($a, $b);
        $reads = $reverse ? [[$b, 'revoked'], [$a, 'added']] : [[$a, 'added'], [$b, 'revoked']];
        foreach ($reads as [$url, $status]) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->get($url)->assertOk()->assertJsonPath('component', 'Access/RolePermissionIndex')
                ->assertJsonPath('props.receiptId', $query['receipt'])->assertJsonMissingPath('props.status')
                ->assertJsonPath('flash.rolePermissionOutcome.receipt_id', $query['receipt'])
                ->assertJsonPath('flash.rolePermissionOutcome.status', $status);
            $this->get($url)->assertOk()->assertJsonPath('component', 'Access/RolePermissionResult')->assertJsonPath('flash.rolePermissionOutcome', null);
            $this->get('/akses/izin-peran?status=added')->assertOk()->assertJsonPath('props.receiptId', null)->assertJsonPath('flash.rolePermissionOutcome', null);
        }
        $this->assertDatabaseCount('audit_log', 2);
    }

    public static function readOrders(): array
    {
        return [[false], [true]];
    }

    public function test_receipt_cannot_be_consumed_by_another_actor_session_or_twice_and_expires(): void
    {
        $receipts = app(RolePermissionReceipt::class);
        $ref = $receipts->issue($this->actor->id, 'session-a', 'added');
        $this->assertNotNull($ref);
        $this->assertNull($receipts->consume((string) Str::uuid(), 'session-a', $ref));
        $this->assertNull($receipts->consume($this->actor->id, 'session-b', $ref));
        $this->assertSame(['receipt_id' => $ref, 'status' => 'added'], $receipts->consume($this->actor->id, 'session-a', $ref));
        $this->assertNull($receipts->consume($this->actor->id, 'session-a', $ref));
        $later = $receipts->issue($this->actor->id, 'session-a', 'revoked');
        $this->travel(301)->seconds();
        $this->assertNull($receipts->consume($this->actor->id, 'session-a', $later));
        $this->assertNull($receipts->consume($this->actor->id, 'session-a', 'malformed'));
        $this->actingAs($this->actor)->withHeader('X-Inertia', 'true')->get('/akses/izin-peran/hasil?status=added')->assertOk()
            ->assertJsonPath('component', 'Access/RolePermissionResult')->assertJsonPath('flash.rolePermissionOutcome', null);
    }

    public function test_cache_failure_never_replays_committed_mutation(): void
    {
        Cache::shouldReceive('store')->with('database')->andThrow(new RuntimeException('Cache tidak tersedia'));
        $this->actingAs($this->actor)->post('/akses/izin-peran/'.$this->target->id, $this->payload())->assertStatus(303)->assertRedirect('/akses/izin-peran/hasil');
        $this->assertDatabaseHas('role_permissions', ['role_id' => $this->target->id, 'permission_id' => $this->permission->id]);
        $this->assertDatabaseCount('audit_log', 1);
    }

    public function test_receipt_delete_failure_returns_unknown_after_commit_without_replaying_action(): void
    {
        $this->actingAs($this->actor)->withHeader('X-Inertia', 'true');
        $url = $this->post('/akses/izin-peran/'.$this->target->id, $this->payload())->assertStatus(303)->headers->get('Location');
        $databaseCache = Cache::store('database');
        $failingCache = \Mockery::mock($databaseCache)->makePartial();
        $failingCache->shouldReceive('forget')->once()->andThrow(new RuntimeException('Gagal menghapus receipt'));
        Cache::shouldReceive('store')->with('database')->andReturn($failingCache);
        $this->get($url)->assertOk()->assertJsonPath('flash.rolePermissionOutcome', null)->assertJsonPath('component', 'Access/RolePermissionResult');
        $this->assertDatabaseHas('role_permissions', ['role_id' => $this->target->id, 'permission_id' => $this->permission->id]);
        $this->assertDatabaseCount('audit_log', 1);
    }

    #[DataProvider('readOrders')]
    public function test_self_revocation_returns_minimal_receipt_or_index_when_independent_grant_survives(bool $grant): void
    {
        $second = User::factory()->create(['is_active' => true]);
        $this->assign($second, $this->source);
        $this->target = $this->source;
        $this->permission = Permission::where('kode', 'akses:update')->sole();
        if ($grant) {
            UserPermissionGrant::create(['user_id' => $this->actor->id, 'permission_id' => $this->permission->id, 'alasan' => 'Grant independen', 'diberikan_oleh' => $this->actor->id]);
        }
        $this->actingAs($this->actor)->withHeader('X-Inertia', 'true');
        $url = $this->post('/akses/izin-peran/'.$this->target->id, $this->payload('revoke'))->assertStatus(303)->headers->get('Location');
        $result = $this->get($url)->assertOk()->assertJsonPath('flash.rolePermissionOutcome.status', 'revoked')->assertJsonMissingPath('props.status');
        $result->assertJsonPath('component', $grant ? 'Access/RolePermissionIndex' : 'Access/RolePermissionResult');
        if (! $grant) {
            $result->assertJsonPath('props.canReturn', false)->assertJsonMissingPath('props.roles')->assertJsonMissingPath('props.permissions');
        }
        $this->assertFalse(app(RolePermissionPolicy::class)->decide($second)['allowed']);
        $this->get($url)->assertJsonPath('flash.rolePermissionOutcome', null);
    }

    private function payload(string $operation = 'add'): array
    {
        return ['permission_id' => $this->permission->id, 'operation' => $operation, 'alasan' => 'Perubahan HTTP', 'expected_state' => $this->state()['token']];
    }

    private function assertStale(string $operation, string $token): void
    {
        try {
            app(ChangeRolePermission::class)->handle($this->actor, $this->target->id, $this->permission->id, $operation, 'Form lama', $token);
            $this->fail('ABA harus ditolak sebelum noop.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_state', $exception->errors());
        }
    }

    private function change(string $operation, string $reason = 'Perubahan global'): string
    {
        return app(ChangeRolePermission::class)->handle($this->actor, $this->target->id, $this->permission->id, $operation, $reason, $this->state()['token']);
    }

    private function preserved(): array
    {
        $values = [];
        foreach (['users', 'roles', 'permissions', 'user_roles', 'user_permission_granted', 'user_permission_denied', 'penanggung_jawab'] as $table) {
            $values[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $values;
    }

    private function state(?Role $role = null): array
    {
        return DB::transaction(fn () => app(RolePermissionState::class)->capture(Role::whereKey(($role ?? $this->target)->id)->sharedLock()->firstOrFail()));
    }

    private function attach(Role $role, Permission $permission): void
    {
        $role->permissions()->attach($permission->id, ['id' => Str::uuid(), 'created_at' => now()]);
    }

    private function assign(User $user, Role $role): void
    {
        $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
    }
}
