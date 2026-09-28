<?php

namespace Tests\Feature\Auth;

use App\Actions\Audit\WriteAuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_audit_migration_preserves_history_and_refuses_lossy_rollback(): void
    {
        $migration = require database_path('migrations/2026_09_28_000002_allow_user_registration_audit.php');
        $migration->down();
        $user = User::factory()->create();
        $historical = ['id' => (string) Str::uuid(), 'actor_type' => 'system', 'sumber' => 'sso_onboarding', 'tindakan' => 'user_roles.tambah', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'waktu' => now(), 'alasan' => 'Histori', 'nilai_baru' => '{"is_active":false}'];
        DB::table('audit_log')->insert($historical);
        $before = DB::table('audit_log')->get()->toJson();
        $migration->up();
        $this->assertSame($before, DB::table('audit_log')->get()->toJson());
        $writer = app(WriteAuditLog::class);
        $valid = ['actor_type' => 'system', 'sumber' => 'sso_onboarding', 'tindakan' => 'pengguna.terdaftar', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'alasan' => 'Registrasi', 'nilai_baru' => ['status' => 'nonaktif']];
        foreach ([['tindakan' => 'user_roles.tambah'], ['objek_tipe' => 'roles'], ['runtime_identity' => 'palsu'], ['operator_reference' => 'palsu']] as $invalid) {
            try {
                $writer->handle(array_replace($valid, $invalid));
                $this->fail('Provenance registrasi palsu harus ditolak.');
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseCount('audit_log', 1);
            }
        }
        $writer->handle($valid);
        $snapshot = DB::table('audit_log')->orderBy('id')->get()->toJson();
        try {
            $migration->down();
            $this->fail('Rollback tidak boleh menghapus histori registrasi.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('pengguna.terdaftar', $exception->getMessage());
        }
        $this->assertSame($snapshot, DB::table('audit_log')->orderBy('id')->get()->toJson());
    }

    public function test_status_cutover_preserves_identity_relations_and_history_in_both_directions(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'status'));
        $migration = require database_path('migrations/2026_09_28_000001_replace_users_is_active_with_status.php');
        $migration->down();
        $ids = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ($ids as $index => $id) {
            DB::table('users')->insert(['id' => $id, 'keycloak_id' => 'status-'.$index, 'nama' => 'Fixture', 'email' => 'fixture@example.test', 'is_active' => $index === 0, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-02 00:00:00']);
        }
        $role = (string) Str::uuid();
        $permission = (string) Str::uuid();
        DB::table('roles')->insert(['id' => $role, 'kode' => 'pegawai', 'nama' => 'Pegawai', 'urutan' => 5]);
        DB::table('permissions')->insert(['id' => $permission, 'kode' => 'dashboard:read', 'entitas' => 'dashboard', 'aksi' => 'read', 'butuh_scope' => 'global']);
        DB::table('user_roles')->insert(['id' => Str::uuid(), 'user_id' => $ids[0], 'role_id' => $role, 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $ids[0], 'created_at' => now()]);
        foreach (['user_permission_granted' => 'diberikan_oleh', 'user_permission_denied' => 'ditetapkan_oleh'] as $table => $actor) {
            DB::table($table)->insert(['id' => Str::uuid(), 'user_id' => $ids[1], 'permission_id' => $permission, $actor => $ids[0], 'alasan' => 'Fixture', 'created_at' => now()]);
        }
        $audit = (string) Str::uuid();
        DB::table('audit_log')->insert(['id' => $audit, 'actor_id' => $ids[0], 'actor_type' => 'user', 'sumber' => 'manual', 'tindakan' => 'pengguna.aktivasi', 'objek_tipe' => 'users', 'objek_id' => $ids[0], 'waktu' => now(), 'alasan' => 'Histori', 'nilai_lama' => '{"is_active":false}', 'nilai_baru' => '{"is_active":true}']);
        DB::table('auth_bootstraps')->insert(['id' => 'initial', 'user_id' => $ids[0], 'audit_id' => $audit, 'created_at' => now()]);
        $tables = ['users', 'user_roles', 'user_permission_granted', 'user_permission_denied', 'auth_bootstraps', 'audit_log'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $migration->up();
        $this->assertFalse(Schema::hasColumn('users', 'is_active'));
        $this->assertSame('aktif', DB::table('users')->where('id', $ids[0])->value('status'));
        $this->assertSame('nonaktif', DB::table('users')->where('id', $ids[1])->value('status'));
        foreach (array_diff($tables, ['users']) as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $migration->down();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $migration->up();
        $newId = (string) Str::uuid();
        DB::table('users')->insert(['id' => $newId, 'keycloak_id' => 'default', 'nama' => 'Fixture', 'email' => 'default@example.test']);
        $this->assertSame('nonaktif', DB::table('users')->where('id', $newId)->value('status'));
        foreach (['unknown', null] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('users')->where('id', $newId)->update(['status' => $invalid]));
                $this->fail('Status invalid harus ditolak database.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->getCode(), ['23514', '23502']);
            }
        }
    }
}
