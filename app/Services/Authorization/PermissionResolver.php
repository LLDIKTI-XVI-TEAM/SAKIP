<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionDecision;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolver izin kanonis SAKIP: keputusan hidup per pengguna, izin, dan unit
 * dengan deny menang atas allow peran maupun grant.
 *
 * Dipisah sebagai satu-satunya sumber keputusan izin yang dipakai Policy,
 * FormRequest, Action, middleware Inertia, dan Explorer izin, sehingga semua
 * jalur memakai aturan yang sama. Resolver hanya membaca data ACL tanpa
 * kunci; kunci baris aktor dan otorisasi ulang di dalam transaksi tetap
 * milik pemanggil (mis. `ResolveLockedActor`), begitu pula audit penolakan.
 */
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
        return $this->decideMany($user, [$kode], $unitId)[$kode];
    }

    /**
     * Jalur tunggal dan Explorer memakai keputusan yang sama. Data selalu dibaca
     * ulang; evaluasi setelah lock transaksi tidak boleh memakai keputusan lama.
     *
     * @param  list<string>  $codes
     * @return array<string, array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}>
     */
    public function decideMany(User $user, array $codes, ?string $unitId = null): array
    {
        $results = [];
        foreach ($codes as $code) {
            $results[$code] = ['allowed' => false, 'permission' => $code, 'reason' => 'no_allow', 'roles' => [], 'grants' => [], 'denies' => []];
        }
        if ($results === []) {
            return [];
        }
        $earlyReason = $user->status !== 'aktif' ? 'inactive_user' : null;
        if ($earlyReason === null && ! DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)->where('roles.aktif', true)
            ->whereIn('roles.kode', RoleCatalog::codes())->exists()) {
            $earlyReason = 'no_role';
        }
        if ($earlyReason !== null) {
            foreach ($results as &$result) {
                $result['reason'] = $earlyReason;
            }

            return $results;
        }
        $permissions = Permission::whereIn('kode', array_intersect($codes, PermissionCatalog::codes()))
            ->where('aktif', true)->get(['id', 'kode', 'butuh_scope'])->keyBy('kode');
        $valid = [];
        foreach ($results as $code => &$result) {
            $permission = $permissions->get($code);
            if ($permission === null) {
                $result['reason'] = 'unknown_permission';
            } elseif (($permission->butuh_scope === 'unit' && $unitId === null) || ($unitId !== null && ! Str::isUuid($unitId))) {
                $result['reason'] = 'invalid_scope';
            } else {
                $valid[$code] = $permission;
            }
        }
        unset($result);
        if ($valid === []) {
            return $results;
        }
        $ids = array_map(fn (Permission $permission) => $permission->id, $valid);
        $roles = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)->where('roles.aktif', true)
            ->whereIn('role_permissions.permission_id', $ids)->get(['roles.id', 'role_permissions.permission_id'])->groupBy('permission_id');
        // Filter UUID dilakukan PostgreSQL, termasuk input UUID huruf besar.
        $grants = DB::table('user_permission_granted as grants')->leftJoin('unit', 'unit.id', '=', 'grants.unit_id')
            ->where('grants.user_id', $user->id)->whereIn('grants.permission_id', $ids)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('grants.unit_id');
                if ($unitId !== null) {
                    $query->orWhere('grants.unit_id', $unitId);
                }
            })->get(['grants.id', 'grants.permission_id', 'grants.unit_id', 'unit.status'])->groupBy('permission_id');
        $denies = DB::table('user_permission_denied')->where('user_id', $user->id)->whereIn('permission_id', $ids)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('unit_id');
                if ($unitId !== null) {
                    $query->orWhere('unit_id', $unitId);
                }
            })->get(['id', 'permission_id'])->groupBy('permission_id');

        foreach ($valid as $code => $permission) {
            $roleIds = $roles->get($permission->id, collect())->pluck('id')->all();
            $stored = $grants->get($permission->id, collect());
            $matching = $stored->filter(fn ($grant) => $permission->butuh_scope === 'unit'
                ? $grant->unit_id !== null && $grant->status === 'aktif'
                : $grant->unit_id === null);
            $grantIds = $matching->pluck('id')->values()->all();
            $denyIds = $denies->get($permission->id, collect())->pluck('id')->all();
            $allowed = $denyIds === [] && ($roleIds !== [] || $grantIds !== []);
            $reason = $denyIds !== [] ? 'explicit_deny' : ($allowed ? 'allow' : 'no_allow');
            if ($reason === 'no_allow' && $permission->butuh_scope === 'unit'
                && $stored->contains(fn ($grant) => $grant->unit_id !== null && $grant->status !== null && $grant->status !== 'aktif')) {
                $reason = 'inactive_unit';
            }
            $results[$code] = ['allowed' => $allowed, 'permission' => $code, 'reason' => $reason, 'roles' => $roleIds, 'grants' => $grantIds, 'denies' => $denyIds];
        }

        return $results;
    }

    /**
     * Subquery `unit_id` yang di-deny ber-unit untuk pengguna dan izin ini,
     * untuk filter daftar `whereNotIn`. Deny global (unit NULL) tidak ikut
     * karena sudah ditolak gerbang izin halaman.
     */
    public function unitDitolak(User $user, string $kode): Builder
    {
        return DB::table('user_permission_denied')
            ->join('permissions', 'permissions.id', '=', 'user_permission_denied.permission_id')
            ->where('user_permission_denied.user_id', $user->id)
            ->where('permissions.kode', $kode)
            ->whereNotNull('user_permission_denied.unit_id')
            ->select('user_permission_denied.unit_id');
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
