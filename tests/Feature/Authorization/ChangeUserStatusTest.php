<?php

namespace Tests\Feature\Authorization;

use App\Actions\Access\ChangeUserStatus;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChangeUserStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = $this->userWithRole('superadmin');
        $this->target = $this->userWithRole('pegawai');
    }

    public function test_deactivation_blocks_target_session_and_is_audited(): void
    {
        $this->actingAs($this->target)->get('/pengukuran')->assertOk();

        $this->actingAs($this->actor)->post($this->url($this->target), $this->payload('nonaktif'))
            ->assertRedirect()->assertSessionHas('success', 'Akun berhasil dinonaktifkan.');

        $this->assertSame('nonaktif', $this->target->fresh()->status);
        $audit = AuditLog::where('tindakan', 'pengguna.nonaktifkan')->sole();
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame($this->target->id, $audit->objek_id);
        $this->assertSame('Pegawai pindah tugas', $audit->alasan);
        $this->assertSame(['status' => 'aktif'], $audit->nilai_lama);
        $this->assertSame(['status' => 'nonaktif'], $audit->nilai_baru);
        $this->actingAs($this->target->fresh())->get('/pengukuran')->assertRedirect('/auth/pending');
        $this->get('/auth/pending')->assertInertia(fn (Assert $page) => $page->component('Auth/Pending')->where('pendingReason', 'inactive'));
    }

    public function test_reactivation_keeps_existing_role_and_grant(): void
    {
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $this->target->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'), 'unit_id' => null, 'diberikan_oleh' => $this->actor->id, 'alasan' => 'Grant uji', 'created_at' => now()]);
        $this->target->update(['status' => 'nonaktif']);
        $before = $this->accessRows($this->target);

        $this->actingAs($this->actor)->post($this->url($this->target), $this->payload('aktif', 'nonaktif'))
            ->assertSessionHas('success', 'Akun berhasil diaktifkan kembali.');

        $this->assertSame('aktif', $this->target->fresh()->status);
        $this->assertSame($before, $this->accessRows($this->target));
        $this->assertSame(1, AuditLog::where('tindakan', 'pengguna.aktivasi')->where('objek_id', $this->target->id)->count());
    }

    public function test_actor_cannot_change_own_status(): void
    {
        $this->actingAs($this->actor)->post($this->url($this->actor), $this->payload('nonaktif'))->assertSessionHasErrors('status');

        $this->assertSame('aktif', $this->actor->fresh()->status);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengguna.status_ditolak')->count());
    }

    public function test_last_active_superadmin_cannot_be_deactivated(): void
    {
        $admin = $this->userWithRole('admin');
        $inactive = $this->userWithRole('superadmin');
        $inactive->update(['status' => 'nonaktif']);

        $this->actingAs($admin)->post($this->url($this->actor), $this->payload('nonaktif'))->assertSessionHasErrors('status');
        $this->assertSame('aktif', $this->actor->fresh()->status);

        $this->userWithRole('superadmin');
        $this->actingAs($admin)->post($this->url($this->actor), $this->payload('nonaktif'))->assertSessionHas('success', 'Akun berhasil dinonaktifkan.');
        $this->assertSame('nonaktif', $this->actor->fresh()->status);
    }

    public function test_empty_reason_missing_permission_and_deny_are_rejected(): void
    {
        $this->actingAs($this->actor)->post($this->url($this->target), ['status' => 'nonaktif', 'expected_status' => 'aktif', 'alasan' => ''])->assertSessionHasErrors('alasan');
        $this->actingAs($this->userWithRole('pegawai'))->post($this->url($this->target), $this->payload('nonaktif'))->assertForbidden();
        $this->deny($this->actor, 'akses:update');
        $this->actingAs($this->actor->fresh())->post($this->url($this->target), $this->payload('nonaktif'))->assertForbidden();

        $this->assertSame('aktif', $this->target->fresh()->status);
    }

    public function test_permission_lost_before_action_is_denied_and_audited(): void
    {
        $this->deny($this->actor, 'akses:update');
        try {
            app(ChangeUserStatus::class)->handle($this->actor, $this->target->id, 'nonaktif', 'Pegawai pindah tugas', 'aktif');
            $this->fail('Aktor tanpa izin tidak boleh mengubah status akun.');
        } catch (AuthorizationException) {
            $this->assertSame('aktif', $this->target->fresh()->status);
        }
        $this->assertSame(1, AuditLog::where('tindakan', 'pengguna.status_ditolak')->count());
    }

    public function test_stale_expected_status_is_rejected_without_change(): void
    {
        $this->target->update(['status' => 'nonaktif']);

        $this->actingAs($this->actor)->post($this->url($this->target), $this->payload('nonaktif', 'aktif'))->assertSessionHasErrors('expected_status');

        $this->assertSame('nonaktif', $this->target->fresh()->status);
        $this->assertSame(0, AuditLog::where('tindakan', 'pengguna.nonaktifkan')->count());
    }

    public function test_same_status_is_unchanged_without_new_audit(): void
    {
        $before = AuditLog::count();

        $this->actingAs($this->actor)->post($this->url($this->target), $this->payload('aktif', 'aktif'))->assertSessionHas('success', 'Status akun tidak berubah.');

        $this->assertSame($before, AuditLog::count());
    }

    private function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $kode)->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }

    private function url(User $user): string
    {
        return '/akses/peran/'.$user->id.'/status';
    }

    /** @return array{status: string, expected_status: string, alasan: string} */
    private function payload(string $status, string $expected = 'aktif'): array
    {
        return ['status' => $status, 'expected_status' => $expected, 'alasan' => 'Pegawai pindah tugas'];
    }

    private function deny(User $user, string $permission): void
    {
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'ditetapkan_oleh' => $user->id, 'alasan' => 'Pembatasan uji', 'created_at' => now()]);
    }

    /** @return array{roles: string, grants: string} */
    private function accessRows(User $user): array
    {
        return [
            'roles' => DB::table('user_roles')->where('user_id', $user->id)->orderBy('id')->get()->toJson(),
            'grants' => DB::table('user_permission_granted')->where('user_id', $user->id)->orderBy('id')->get()->toJson(),
        ];
    }
}
