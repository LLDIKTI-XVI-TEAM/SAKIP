<?php

namespace App\Actions\Auth;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionKeycloakUser
{
    public function __construct(private WriteAuditLog $audit) {}

    /** @param array{subject:string,nama:string,email:string} $identity */
    public function handle(#[\SensitiveParameter] array $identity): User
    {
        return DB::transaction(function () use ($identity) {
            // Serialisasi subject yang sama sebelum lookup, termasuk saat row belum ada.
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:sso:'.$identity['subject']]);
            $user = User::where('keycloak_id', $identity['subject'])->first();
            if ($user) {
                $user->update(['nama' => $identity['nama'], 'email' => $identity['email']]);

                return $user;
            }
            $role = Role::where('kode', 'pegawai')->where('aktif', true)->sole();
            $user = User::create(['keycloak_id' => $identity['subject'], 'nama' => $identity['nama'], 'email' => $identity['email'], 'is_active' => false]);
            $audit = $this->audit->handle([
                'actor_type' => 'system', 'sumber' => 'sso_onboarding', 'tindakan' => 'user_roles.tambah',
                'objek_tipe' => 'users', 'objek_id' => $user->id,
                'nilai_baru' => ['role_id' => $role->id, 'is_active' => false],
                'alasan' => 'Sistem — onboarding SSO',
            ]);
            DB::table('user_roles')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'role_id' => $role->id, 'diberikan_oleh' => null, 'sumber_pemberian' => 'sso_onboarding', 'audit_id' => $audit->id, 'created_at' => now()]);

            return $user;
        });
    }
}
