<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionResolver
{
    /** @return array{allowed:bool,permission:string,reason:string,roles:list<string>,grants:list<string>,denies:list<string>} */
    public function decide(User $user, string $kode, ?string $unitId = null): array
    {
        return $this->decideMany($user, [$kode], $unitId)[$kode];
    }

    /**
     * Satu pembacaan ACL untuk beberapa izin pada user/unit yang sama, tanpa cache lintas request.
     * PIC, waktu, lifecycle, dan akses induk tetap merupakan guard terpisah.
     *
     * @param  list<string>  $codes
     * @return array<string,array{allowed:bool,permission:string,reason:string,roles:list<string>,grants:list<string>,denies:list<string>}>
     */
    public function decideMany(User $user, array $codes, ?string $unitId = null): array
    {
        $results = [];
        foreach (array_unique($codes) as $code) {
            $results[$code] = ['allowed' => false, 'permission' => $code, 'reason' => 'no_allow', 'roles' => [], 'grants' => [], 'denies' => []];
        }
        if ($results === []) {
            return [];
        }
        if ($user->status !== 'aktif') {
            return array_map(fn ($row) => [...$row, 'reason' => 'inactive_user'], $results);
        }
        // Grant tidak membuka akses akun tanpa klasifikasi resmi yang aktif.
        if (! DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)->where('roles.aktif', true)
            ->whereIn('roles.kode', RoleCatalog::codes())->exists()) {
            return array_map(fn ($row) => [...$row, 'reason' => 'no_role'], $results);
        }
        $permissions = Permission::whereIn('kode', array_intersect(array_keys($results), PermissionCatalog::codes()))
            ->where('aktif', true)->get()->keyBy('kode');
        $ids = $permissions->pluck('id')->all();
        $roles = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)->where('roles.aktif', true)
            ->whereIn('role_permissions.permission_id', $ids)->get(['role_permissions.permission_id', 'roles.id as role_id']);
        $validUnit = $unitId !== null && Str::isUuid($unitId);
        $scopeUnit = $validUnit ? strtolower($unitId) : null;
        $grants = DB::table('user_permission_granted')->leftJoin('unit', 'unit.id', '=', 'user_permission_granted.unit_id')
            ->where('user_id', $user->id)->whereIn('permission_id', $ids)
            ->where(function ($query) use ($unitId, $validUnit): void {
                $query->whereNull('unit_id');
                if ($validUnit) {
                    $query->orWhere('unit_id', $unitId);
                }
            })->get(['user_permission_granted.id', 'permission_id', 'unit_id', 'unit.status as unit_status']);
        $denies = DB::table('user_permission_denied')->where('user_id', $user->id)->whereIn('permission_id', $ids)
            ->where(function ($query) use ($unitId, $validUnit): void {
                $query->whereNull('unit_id');
                if ($validUnit) {
                    $query->orWhere('unit_id', $unitId);
                }
            })->get(['id', 'permission_id']);
        foreach ($results as $code => $result) {
            $permission = $permissions->get($code);
            if (! $permission) {
                $results[$code]['reason'] = 'unknown_permission';

                continue;
            }
            $scoped = $permission->butuh_scope === 'unit';
            if (($scoped && ! $validUnit) || ($unitId !== null && ! $validUnit)) {
                $results[$code]['reason'] = 'invalid_scope';

                continue;
            }
            $roleIds = $roles->where('permission_id', $permission->id)->pluck('role_id')->all();
            $candidateGrants = $grants->where('permission_id', $permission->id);
            $grantIds = ($scoped ? $candidateGrants->where('unit_id', $scopeUnit)->where('unit_status', 'aktif') : $candidateGrants->whereNull('unit_id'))->pluck('id')->all();
            $denyIds = $denies->where('permission_id', $permission->id)->pluck('id')->all();
            $allowed = $denyIds === [] && ($roleIds !== [] || $grantIds !== []);
            $reason = $denyIds !== [] ? 'explicit_deny' : ($allowed ? 'allow' : 'no_allow');
            if ($reason === 'no_allow' && $scoped && $candidateGrants->where('unit_id', $scopeUnit)
                ->whereNotNull('unit_status')->where('unit_status', '!=', 'aktif')->isNotEmpty()) {
                $reason = 'inactive_unit';
            }
            $results[$code] = [...$result, 'allowed' => $allowed, 'reason' => $reason, 'roles' => $roleIds, 'grants' => $grantIds, 'denies' => $denyIds];
        }

        return $results;
    }

    public function allows(User $user, string $kode, ?string $unitId = null): bool
    {
        return $this->decide($user, $kode, $unitId)['allowed'];
    }

    public function resolve(User $user, string $permissionCode, ?string $unitId = null): PermissionDecision
    {
        $decision = $this->decide($user, $permissionCode, $unitId);

        return new PermissionDecision($decision['allowed'], $permissionCode, [
            'alasan' => $decision['reason'],
            'sumber_allow' => ['roles' => $decision['roles'], 'grants' => $decision['grants']],
            'deny' => $decision['denies'],
        ]);
    }
}
