<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Support\PermissionDecision;

class PermissionResolver
{
    public function resolve(User $user, string $permissionCode, ?int $unitId = null): PermissionDecision
    {
        $permission = Permission::query()
            ->where('name', $permissionCode)
            ->where('guard_name', 'web')
            ->where('aktif', true)
            ->first();

        if ($permission === null) {
            return new PermissionDecision(false, $permissionCode, [
                'alasan' => 'permission_tidak_terdaftar_atau_nonaktif',
                'sumber_allow' => [],
            ]);
        }

        if ($permission->butuh_scope === 'global' && $unitId !== null) {
            return new PermissionDecision(false, $permissionCode, [
                'alasan' => 'scope_unit_tidak_berlaku_untuk_permission_global',
                'sumber_allow' => [],
            ]);
        }

        $deny = UserPermissionDenial::query()
            ->where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->when(
                $unitId === null,
                fn ($query) => $query->whereNull('unit_id'),
                fn ($query) => $query->where(function ($query) use ($unitId): void {
                    $query->whereNull('unit_id')->orWhere('unit_id', $unitId);
                }),
            )
            ->first();

        $roleNames = $user->roles()
            ->whereHas('permissions', fn ($query) => $query->where('permissions.id', $permission->id))
            ->pluck('name')
            ->values()
            ->all();

        $hasDirectPermission = $user->permissions()
            ->where('permissions.id', $permission->id)
            ->exists();

        $sources = [];

        if ($roleNames !== []) {
            $sources[] = [
                'tipe' => 'role',
                'roles' => $roleNames,
            ];
        }

        if ($hasDirectPermission) {
            $sources[] = [
                'tipe' => 'direct_permission',
                'permission_id' => $permission->id,
            ];
        }

        if ($deny !== null) {
            return new PermissionDecision(false, $permissionCode, [
                'alasan' => 'explicit_deny',
                'sumber_allow' => $sources,
                'deny' => [
                    'id' => $deny->id,
                    'scope' => $deny->unit_id === null ? 'global' : 'unit',
                    'unit_id' => $deny->unit_id,
                    'alasan' => $deny->alasan,
                ],
            ]);
        }

        if ($sources === []) {
            return new PermissionDecision(false, $permissionCode, [
                'alasan' => 'tidak_ada_allow_efektif',
                'sumber_allow' => [],
            ]);
        }

        return new PermissionDecision(true, $permissionCode, [
            'alasan' => 'allow_efektif',
            'sumber_allow' => $sources,
        ]);
    }
}
