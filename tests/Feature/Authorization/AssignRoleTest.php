<?php

namespace Tests\Feature\Authorization;

use App\Actions\Access\AssignRole;
use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AssignRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['nama' => 'Z Pengelola', 'is_active' => true]);
        $this->target = User::factory()->create(['is_active' => false]);
        $role = Role::where('kode', 'superadmin')->sole();
        foreach (Permission::whereIn('kode', ['pengguna:read', 'akses:update'])->get() as $permission) {
            $role->permissions()->attach($permission->id, ['id' => Str::uuid(), 'created_at' => now()]);
        }
        $this->actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    public function test_first_assignment_accepts_pic_and_change_preserves_one_pivot_and_historical_audit(): void
    {
        $action = app(AssignRole::class);
        $pic = Role::where('kode', 'pic')->sole();
        $this->assertSame('assigned', $action->handle($this->actor, $this->target->id, $pic->id, '  Penugasan awal  ', null));
        $before = DB::table('user_roles')->where('user_id', $this->target->id)->sole();
        $audit = AuditLog::findOrFail($before->audit_id);
        $this->assertSame('user_roles.tambah', $audit->tindakan);
        $this->assertNull($audit->nilai_lama);
        $this->assertSame(['role_id' => $pic->id, 'role_kode' => 'pic'], $audit->nilai_baru);
        $this->assertSame('Penugasan awal', $audit->alasan);
        $this->assertTrue($audit->dasar_izin['pengguna_read']['allowed']);
        $this->assertTrue($audit->dasar_izin['akses_update']['allowed']);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $history = $audit->getRawOriginal();
        $pegawai = Role::where('kode', 'pegawai')->sole();
        $this->assertSame('changed', $action->handle($this->actor, $this->target->id, $pegawai->id, 'Pergantian', $this->token()));
        $after = DB::table('user_roles')->where('user_id', $this->target->id)->sole();
        $this->assertSame($before->id, $after->id);
        $this->assertSame($before->created_at, $after->created_at);
        $this->assertSame($this->actor->id, $after->diberikan_oleh);
        $this->assertSame('manual', $after->sumber_pemberian);
        $this->assertSame($pegawai->id, $after->role_id);
        $change = AuditLog::findOrFail($after->audit_id);
        $this->assertSame('user_roles.ubah', $change->tindakan);
        $this->assertSame(['role_id' => $pic->id, 'role_kode' => 'pic'], $change->nilai_lama);
        $this->assertSame(['role_id' => $pegawai->id, 'role_kode' => 'pegawai'], $change->nilai_baru);
        $this->assertSame($history, $audit->fresh()->getRawOriginal());
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $this->target->id)->count());
        $this->assertFalse($this->target->fresh()->is_active);
    }

    public function test_fresh_noop_preserves_provenance_but_stale_aba_token_is_rejected(): void
    {
        $action = app(AssignRole::class);
        $pic = Role::where('kode', 'pic')->value('id');
        $other = Role::where('kode', 'pegawai')->value('id');
        $action->handle($this->actor, $this->target->id, $pic, 'Awal', null);
        $first = $this->token();
        $this->assertSame('unchanged', $action->handle($this->actor, $this->target->id, $pic, 'Ulang', array_reverse($first, true)));
        $this->assertSame($first, $this->token());
        $this->assertDatabaseCount('audit_log', 1);
        $action->handle($this->actor, $this->target->id, $other, 'Ganti', $first);
        $action->handle($this->actor, $this->target->id, $pic, 'Kembali', $this->token());
        $latest = $this->token();
        try {
            $action->handle($this->actor, $this->target->id, $pic, 'Form lama', $first);
            $this->fail('Token ABA harus ditolak sebelum no-op.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_assignment', $exception->errors());
        }
        $this->assertSame($latest, $this->token());
        $this->assertSame(3, AuditLog::whereIn('tindakan', ['user_roles.tambah', 'user_roles.ubah'])->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'user_roles.ditolak')->count());
    }

    #[DataProvider('invalidChanges')]
    public function test_invalid_domain_input_does_not_assign(string $case, string $field): void
    {
        $role = Role::where('kode', 'pic')->sole();
        $reason = 'Perubahan uji';
        if ($case === 'inactive') {
            $role->update(['aktif' => false]);
        } elseif ($case === 'foreign') {
            $role = Role::create(['kode' => 'asing', 'nama' => 'Asing', 'urutan' => 99]);
        } elseif ($case === 'unknown') {
            $role->id = (string) Str::uuid();
        } else {
            $reason = $case === 'long' ? str_repeat('a', 2001) : '   ';
        }
        try {
            app(AssignRole::class)->handle($this->actor, $this->target->id, $role->id, $reason, null);
            $this->fail('Input tidak sah harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['user_roles.tambah', 'user_roles.ubah'])->count());
    }

    public static function invalidChanges(): array
    {
        return [['blank', 'alasan'], ['long', 'alasan'], ['inactive', 'role_id'], ['unknown', 'role_id'], ['foreign', 'role_id']];
    }

    #[DataProvider('requiredPermissions')]
    public function test_action_rechecks_live_deny_even_for_superadmin_and_audits_once(string $permission): void
    {
        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($this->actor, 'pengguna:read'));
        $this->assertTrue($resolver->allows($this->actor, 'akses:update'));
        $this->deny($permission);
        try {
            app(AssignRole::class)->handle($this->actor, $this->target->id, Role::where('kode', 'pic')->value('id'), 'Ditolak', null);
            $this->fail('Izin terbaru harus diperiksa.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        }
        $audit = AuditLog::sole();
        $this->assertSame('user_roles.ditolak', $audit->tindakan);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertCount(2, $audit->dasar_izin);
        $this->assertFalse($audit->dasar_izin[$permission === 'pengguna:read' ? 'pengguna_read' : 'akses_update']['allowed']);
    }

    public static function requiredPermissions(): array
    {
        return [['pengguna:read'], ['akses:update']];
    }

    public function test_failed_audit_rolls_back_assignment(): void
    {
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            app(AssignRole::class)->handle($this->actor, $this->target->id, Role::where('kode', 'pic')->value('id'), 'Rollback', null);
            $this->fail('Audit failure harus membatalkan mutasi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit-unavailable', $exception->getMessage());
        }
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertDatabaseCount('audit_log', 0);
    }

    #[DataProvider('requiredPermissions')]
    public function test_http_gate_rejects_missing_permission_and_deny_without_audit(string $permission): void
    {
        $permissionId = Permission::where('kode', $permission)->value('id');
        $row = DB::table('role_permissions')->where('permission_id', $permissionId)->sole();
        DB::table('role_permissions')->where('id', $row->id)->delete();
        $this->actingAs($this->actor);
        foreach (['missing', 'denied'] as $case) {
            if ($case === 'denied') {
                DB::table('role_permissions')->insert((array) $row);
                $this->deny($permission);
            }
            $this->get('/akses/peran')->assertForbidden();
            $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertForbidden();
            $this->assertDatabaseCount('audit_log', 0);
            $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        }
    }

    public function test_index_bounds_users_filters_and_orders_official_roles_without_sensitive_props(): void
    {
        $this->target->update(['nama' => 'A Target', 'email' => 'target@example.test']);
        foreach (['superadmin', 'admin', 'perencanaan', 'pic', 'pimpinan', 'pegawai'] as $code) {
            $user = User::factory()->create(['nama' => 'Z '.$code, 'is_active' => true]);
            $user->roles()->attach(Role::where('kode', $code)->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        }
        User::factory()->count(15)->create(['nama' => 'Z Pengguna']);
        $this->actingAs($this->actor)->get('/akses/peran')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->has('users.data', 20)->where('users.total', 23)
            ->where('users.data.0.id', $this->target->id)->where('users.data.0.assignment', null)
            ->where('roles.0.kode', 'superadmin')->where('roles.1.kode', 'admin')->where('roles.2.kode', 'perencanaan')
            ->where('roles.3.kode', 'pic')->where('roles.4.kode', 'pimpinan')->where('roles.5.kode', 'pegawai')
            ->missing('users.data.0.keycloak_id')->missing('users.data.0.roles')->missing('users.data.0.role_permissions')
            ->where('auth.can.assignRole', true)->where('can.assignRole', true));
        $this->get('/akses/peran?q=target%40example.test')->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $this->target->id));
        $this->get('/akses/peran?q=A%20Target')->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
        $this->get('/akses/peran?page=2')->assertInertia(fn (Assert $page) => $page->has('users.data', 3));
        $this->get('/akses/peran?page=0')->assertSessionHasErrors('page');
        $this->get('/akses/peran?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
        app(AssignRole::class)->handle($this->actor, $this->target->id, Role::where('kode', 'pic')->value('id'), 'Penugasan', null);
        Role::where('kode', 'pic')->update(['aktif' => false]);
        $this->get('/akses/peran?q=target%40example.test')->assertInertia(fn (Assert $page) => $page
            ->has('roles', 5)->where('roles.3.kode', 'pimpinan')->where('roles.4.kode', 'pegawai')
            ->where('users.data.0.current_role.kode', 'pic')->where('users.data.0.current_role.aktif', false));
        $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertSessionHasErrors('role_id');
    }

    #[DataProvider('invalidPayloads')]
    public function test_http_rejects_invalid_payload_without_mutation(array $override, array $remove, string $field): void
    {
        $payload = array_replace($this->payload(), $override);
        foreach ($remove as $key) {
            unset($payload[$key]);
        }
        $this->actingAs($this->actor)->from('/akses/peran')->post('/akses/peran/'.$this->target->id, $payload)
            ->assertRedirect('/akses/peran')->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            [['alasan' => '   '], [], 'alasan'],
            [[], ['expected_assignment'], 'expected_assignment'],
            [['expected_assignment' => ['id' => 'bad']], [], 'expected_assignment'],
            [['actor_id' => 'spoofed'], [], 'actor_id'],
            [['expected_assignment' => ['id' => 'bad', 'role_id' => 'bad', 'audit_id' => null, 'extra' => 'bad']], [], 'expected_assignment'],
        ];
    }

    public function test_self_change_receipt_remains_available_after_losing_access_and_expires(): void
    {
        $this->target = $this->actor;
        $this->actingAs($this->actor)->from('/akses/peran')->post('/akses/peran/'.$this->actor->id, $this->payload())
            ->assertStatus(303)->assertRedirect('/akses/peran/hasil');
        $this->get('/akses/peran/hasil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentResult', false)->where('status', 'changed')->where('canReturn', false)
            ->where('auth.can.assignRole', false)->missing('users')->missing('target')->missing('keycloak_id'));
        $this->get('/akses/peran')->assertForbidden();
        $this->get('/akses/peran/hasil')->assertInertia(fn (Assert $page) => $page->where('status', null));
        $this->withSession(['roleAssignmentResult' => ['actor_id' => (string) Str::uuid(), 'status' => 'assigned']])
            ->get('/akses/peran/hasil')->assertInertia(fn (Assert $page) => $page->where('status', null));
    }

    public function test_http_assigns_first_role_and_protects_guest_pending_and_unknown_target(): void
    {
        $this->get('/akses/peran')->assertRedirect('/login');
        $this->actingAs($this->target)->get('/akses/peran')->assertRedirect('/auth/pending');
        $this->actingAs($this->actor)->post('/akses/peran/not-uuid', $this->payload())->assertNotFound();
        $this->post('/akses/peran/'.Str::uuid(), $this->payload())->assertNotFound();
        $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303)->assertRedirect('/akses/peran/hasil');
        $this->get('/akses/peran/hasil')->assertRedirect('/akses/peran');
        $this->get('/akses/peran')->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->hasFlash('roleAssignmentStatus', 'assigned'));
        $this->get('/akses/peran')->assertInertia(fn (Assert $page) => $page->missingFlash('roleAssignmentStatus'));
        $this->assertSame('pic', $this->target->roles()->value('kode'));
    }

    private function payload(): array
    {
        return ['role_id' => Role::where('kode', 'pic')->value('id'), 'alasan' => 'Penugasan melalui form', 'expected_assignment' => $this->token()];
    }

    private function token(): ?array
    {
        $row = DB::table('user_roles')->where('user_id', $this->target->id)->first(['id', 'role_id', 'audit_id']);

        return $row ? (array) $row : null;
    }

    private function deny(string $permission): void
    {
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'alasan' => 'Pembatasan uji', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }
}
