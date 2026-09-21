<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionResolver
{
    /**
     * Resolusi hidup dengan deny menang. PIC, waktu, status, dan akses induk berkas
     * merupakan gerbang terpisah; izin baca ringkasan tidak membuka bukti dukung.
     *
     * @return array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}
     */
    public function decide(User $user, string $kode, ?string $unitId = null): array
    {
        $result = ['allowed' => false, 'permission' => $kode, 'reason' => 'no_allow', 'roles' => [], 'grants' => [], 'denies' => []];
        if (! $user->is_active) {
            return [...$result, 'reason' => 'inactive_user'];
        }
        $permission = Permission::where('kode', $kode)->where('aktif', true)->first();
        if (! $permission) {
            return [...$result, 'reason' => 'unknown_permission'];
        }
        if (($unitId !== null && ! Str::isUuid($unitId)) || ($permission->butuh_scope === 'unit' && $unitId === null)) {
            return [...$result, 'reason' => 'invalid_scope'];
        }
        $roles = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)->where('roles.aktif', true)
            ->where('role_permissions.permission_id', $permission->id)->pluck('roles.id')->all();
        $grants = DB::table('user_permission_granted')->where('user_id', $user->id)->where('permission_id', $permission->id)
            ->when($permission->butuh_scope === 'unit', fn ($query) => $query->where('unit_id', $unitId), fn ($query) => $query->whereNull('unit_id'))->pluck('id')->all();
        $denies = DB::table('user_permission_denied')->where('user_id', $user->id)->where('permission_id', $permission->id)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('unit_id');
                if ($unitId !== null) {
                    $query->orWhere('unit_id', $unitId);
                }
            })->pluck('id')->all();
        $allowed = $denies === [] && ($roles !== [] || $grants !== []);

        return ['allowed' => $allowed, 'permission' => $kode, 'reason' => $denies !== [] ? 'explicit_deny' : ($allowed ? 'allow' : 'no_allow'), 'roles' => $roles, 'grants' => $grants, 'denies' => $denies];
    }

    public function allows(User $user, string $kode, ?string $unitId = null): bool
    {
        return $this->decide($user, $kode, $unitId)['allowed'];
    }
}
