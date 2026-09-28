<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\ActivateUser;
use App\Actions\Auth\BootstrapSuperadmin;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyOnboardingMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
    }

    public function test_pending_legacy_role_is_removed_without_touching_other_access_or_history(): void
    {
        $pending = $this->legacyUser();
        $active = $this->legacyUser('aktif');
        $manual = $this->legacyUser(source: 'manual');
        $otherRole = $this->legacyUser(role: 'admin');
        $operator = app(ProvisionKeycloakUser::class)->handle(['subject' => 'operator', 'nama' => 'QA', 'email' => 'qa@example.test']);
        app(BootstrapSuperadmin::class)->handle($operator->id, 'Operator QA', 'Inisialisasi uji', 'qa');
        foreach (['user_permission_granted' => 'diberikan_oleh', 'user_permission_denied' => 'ditetapkan_oleh'] as $table => $actor) {
            DB::table($table)->insert([
                'id' => Str::uuid(), 'user_id' => $pending->id,
                'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
                $actor => $operator->id, 'alasan' => 'Preservasi', 'created_at' => now(),
            ]);
        }
        $preserved = ['users', 'role_permissions', 'user_permission_granted', 'user_permission_denied', 'auth_bootstraps'];
        $before = $this->snapshot($preserved);
        $auditBefore = DB::table('audit_log')->orderBy('id')->get();
        $oldAssignment = DB::table('user_roles')->where('user_id', $pending->id)->sole();
        $otherAssignments = DB::table('user_roles')->where('user_id', '!=', $pending->id)->orderBy('id')->get();

        $this->migration()->up();

        $this->assertDatabaseMissing('user_roles', ['user_id' => $pending->id]);
        $this->assertSame($before, $this->snapshot($preserved));
        $this->assertEquals($otherAssignments, DB::table('user_roles')->orderBy('id')->get());
        $this->assertEquals($auditBefore, DB::table('audit_log')->whereIn('id', $auditBefore->pluck('id'))->orderBy('id')->get());
        $audit = DB::table('audit_log')->where('tindakan', 'user_roles.hapus')->sole();
        $this->assertSame($pending->id, $audit->objek_id);
        $this->assertSame('system', $audit->actor_type);
        $this->assertSame('sso_onboarding', $audit->sumber);
        $snapshot = json_decode($audit->nilai_lama, true);
        $this->assertEquals(new \DateTimeImmutable($oldAssignment->created_at), new \DateTimeImmutable($snapshot['created_at']));
        $snapshot['created_at'] = $oldAssignment->created_at;
        $this->assertEquals((array) $oldAssignment, $snapshot);
        $this->assertNull($audit->nilai_baru);
        $this->assertNotEmpty($audit->runtime_identity);
        $this->assertNotEmpty($audit->alasan);
        $count = DB::table('audit_log')->count();
        $this->migration()->up();
        $this->assertSame($count, DB::table('audit_log')->count());

        app(ProvisionKeycloakUser::class)->handle(['subject' => $pending->keycloak_id, 'nama' => 'Profil baru', 'email' => 'new@example.test']);
        $this->assertTrue(app(ActivateUser::class)->handle($operator->fresh(), $pending->id, 'Aktivasi tanpa role'));
        $decision = app(PermissionResolver::class)->decide($pending->fresh(), 'dashboard:read');
        $this->assertFalse($decision['allowed']);
        $this->assertSame('no_role', $decision['reason']);
        foreach ([$active, $manual, $otherRole] as $user) {
            $this->assertDatabaseHas('user_roles', ['user_id' => $user->id]);
        }
        $this->expectException(\RuntimeException::class);
        $this->migration()->down();
    }

    public function test_manual_bootstrap_accepts_corrected_legacy_provenance_without_fabricating_registration(): void
    {
        $legacy = $this->legacyUser();
        $auditBefore = DB::table('audit_log')->orderBy('id')->get();
        $this->migration()->up();

        $this->artisan('sakip:bootstrap-superadmin')
            ->expectsQuestion('ID akun SAKIP', $legacy->id)
            ->expectsQuestion('Identitas operator dan referensi otorisasi', 'Operator QA / mandat awal')
            ->expectsQuestion('Alasan bootstrap', 'Inisialisasi akses pertama')
            ->expectsConfirmation('Aktifkan akun ini sebagai Super Admin?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('aktif', $legacy->fresh()->status);
        $this->assertSame('superadmin', $legacy->roles()->sole()->kode);
        $this->assertDatabaseCount('auth_bootstraps', 1);
        $this->assertSame(0, DB::table('audit_log')->where('tindakan', 'pengguna.terdaftar')->count());
        $this->assertEquals($auditBefore, DB::table('audit_log')->whereIn('id', $auditBefore->pluck('id'))->orderBy('id')->get());
    }

    #[DataProvider('invalidProvenance')]
    public function test_unmatched_onboarding_provenance_blocks_cleanup_atomically(string $invalid): void
    {
        $valid = $this->legacyUser();
        $unmatched = $this->legacyUser();
        // Kandidat valid pasti diproses dahulu: kegagalan kedua harus rollback audit + delete pertama.
        DB::table('user_roles')->where('user_id', $valid->id)->update(['id' => '00000000-0000-4000-8000-000000000001']);
        DB::table('user_roles')->where('user_id', $unmatched->id)->update(['id' => '00000000-0000-4000-8000-000000000002']);
        $auditId = DB::table('user_roles')->where('user_id', $valid->id)->value('audit_id');
        if ($invalid !== 'other-user') {
            $audit = (array) DB::table('audit_log')->where('objek_id', $unmatched->id)->sole();
            $snapshot = json_decode($audit['nilai_baru'], true);
            $snapshot[$invalid === 'wrong-role' ? 'role_id' : 'is_active'] = $invalid === 'wrong-role'
                ? Role::where('kode', 'admin')->value('id') : 'false';
            $audit['id'] = $auditId = (string) Str::uuid();
            $audit['nilai_baru'] = json_encode($snapshot, JSON_THROW_ON_ERROR);
            DB::table('audit_log')->insert($audit);
        }
        DB::table('user_roles')->where('user_id', $unmatched->id)
            ->update(['audit_id' => $auditId]);
        $before = $this->snapshot(['users', 'user_roles', 'audit_log']);
        $rejected = false;
        try {
            $this->migration()->up();
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('provenance', $exception->getMessage());
            $rejected = true;
        }
        $this->assertTrue($rejected, 'Provenance ambigu harus menghentikan migration tanpa perubahan parsial.');
        $this->assertSame($before, $this->snapshot(['users', 'user_roles', 'audit_log']));
    }

    public function test_audit_failure_rolls_back_legacy_role_removal(): void
    {
        $this->legacyUser();
        $before = $this->snapshot(['users', 'user_roles', 'audit_log']);
        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT qa_reject_cleanup CHECK (tindakan <> 'user_roles.hapus')");
        try {
            $this->migration()->up();
            $this->fail('Pencabutan tanpa audit tidak boleh berhasil.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        $this->assertSame($before, $this->snapshot(['users', 'user_roles', 'audit_log']));
    }

    public function test_legacy_audit_alone_cannot_authorize_bootstrap_without_corrective_proof(): void
    {
        $legacy = $this->legacyUser();
        DB::table('user_roles')->where('user_id', $legacy->id)->delete();
        $before = $this->snapshot(['users', 'user_roles', 'audit_log', 'auth_bootstraps']);
        try {
            app(BootstrapSuperadmin::class)->handle($legacy->id, 'QA', 'Tanpa bukti corrective', 'qa');
            $this->fail('Audit lama tanpa bukti corrective tidak cukup.');
        } catch (\DomainException) {
            $this->assertSame($before, $this->snapshot(['users', 'user_roles', 'audit_log', 'auth_bootstraps']));
        }
    }

    public function test_bootstrap_rejects_corrective_proof_linked_to_another_users_onboarding(): void
    {
        $corrected = $this->legacyUser();
        $this->migration()->up();
        $target = $this->legacyUser();
        DB::table('user_roles')->where('user_id', $target->id)->delete();
        $forged = (array) DB::table('audit_log')->where('objek_id', $corrected->id)->where('tindakan', 'user_roles.hapus')->sole();
        $forged['id'] = (string) Str::uuid();
        $forged['objek_id'] = $target->id;
        $snapshot = json_decode($forged['nilai_lama'], true);
        $snapshot['user_id'] = $target->id;
        $forged['nilai_lama'] = json_encode($snapshot, JSON_THROW_ON_ERROR);
        DB::table('audit_log')->insert($forged);
        $before = $this->snapshot(['users', 'user_roles', 'audit_log', 'auth_bootstraps']);
        $rejected = false;
        try {
            app(BootstrapSuperadmin::class)->handle($target->id, 'QA', 'Bukti tidak cocok', 'qa');
        } catch (\DomainException) {
            $rejected = true;
        }
        $this->assertTrue($rejected);
        $this->assertSame($before, $this->snapshot(['users', 'user_roles', 'audit_log', 'auth_bootstraps']));
    }

    /** @return array<string, array{string}> */
    public static function invalidProvenance(): array
    {
        return [
            'audit milik user lain' => ['other-user'],
            'snapshot role berbeda' => ['wrong-role'],
            'string false bukan boolean' => ['string-false'],
        ];
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_28_000003_remove_pending_legacy_onboarding_roles.php');
    }

    /** @param list<string> $tables
     * @return array<string, string>
     */
    private function snapshot(array $tables): array
    {
        $result = [];
        foreach ($tables as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    private function legacyUser(string $status = 'nonaktif', string $source = 'sso_onboarding', string $role = 'pegawai'): User
    {
        $user = User::factory()->create(['status' => $status]);
        $roleId = Role::where('kode', $role)->sole()->id;
        $auditId = (string) Str::uuid();
        DB::table('audit_log')->insert([
            'id' => $auditId, 'actor_type' => $source === 'manual' ? 'user' : 'system',
            'actor_id' => $source === 'manual' ? $user->id : null, 'sumber' => $source,
            'tindakan' => 'user_roles.tambah', 'objek_tipe' => 'users', 'objek_id' => $user->id,
            'nilai_baru' => json_encode(['role_id' => $roleId, 'is_active' => false], JSON_THROW_ON_ERROR),
            'alasan' => 'Fixture onboarding lama', 'waktu' => now(),
        ]);
        DB::table('user_roles')->insert([
            'id' => Str::uuid(), 'user_id' => $user->id, 'role_id' => $roleId,
            'sumber_pemberian' => $source, 'diberikan_oleh' => $source === 'manual' ? $user->id : null,
            'audit_id' => $auditId, 'created_at' => now(),
        ]);

        return $user;
    }
}
