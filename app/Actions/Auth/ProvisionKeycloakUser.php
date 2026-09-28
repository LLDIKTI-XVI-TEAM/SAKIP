<?php

namespace App\Actions\Auth;

use App\Actions\Audit\WriteAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            // Identitas baru belum membawa hak akses; aktivasi dan penetapan peran terpisah.
            $user = User::create(['keycloak_id' => $identity['subject'], 'nama' => $identity['nama'], 'email' => $identity['email'], 'status' => 'nonaktif']);
            $this->audit->handle([
                'actor_type' => 'system', 'sumber' => 'sso_onboarding', 'tindakan' => 'pengguna.terdaftar',
                'objek_tipe' => 'users', 'objek_id' => $user->id,
                'nilai_baru' => ['status' => 'nonaktif'],
                'alasan' => 'Sistem — onboarding SSO',
            ]);

            return $user;
        });
    }
}
