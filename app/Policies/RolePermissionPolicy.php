<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\DB;

class RolePermissionPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    /**
     * Role aktual membatasi pengelola; Superadmin tetap tunduk pada deny.
     * Pemanggil menyediakan actor fresh, dan menguncinya untuk mutasi.
     *
     * @return array{allowed: bool, reason: string, akses_update: array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}}
     */
    public function decide(User $actor): array
    {
        $access = $this->permissions->decide($actor, 'akses:update');
        $superadmin = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $actor->id)->where('roles.kode', 'superadmin')->where('roles.aktif', true)->exists();
        $reason = match (true) {
            ! $actor->is_active => 'inactive_user',
            ! $superadmin => 'not_superadmin',
            ! $access['allowed'] => 'access_denied',
            default => 'allow',
        };

        return ['allowed' => $reason === 'allow', 'reason' => $reason, 'akses_update' => $access];
    }
}
