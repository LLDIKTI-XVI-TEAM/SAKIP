<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionDecision;
use Illuminate\Support\Facades\DB;

class ResolveLockedActor
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Mengunci baris aktor secara eksklusif beserta baris ACL penentu izin,
     * lalu mengevaluasi ulang keputusan izin memakai state terkini di dalam
     * transaksi pemanggil.
     *
     * Urutan kunci dibuat tetap (grant global saja, bukan grant unit) agar
     * tidak deadlock silang dengan pencabutan grant unit pada RevokeGrant.
     * Pemeriksaan status nonaktif dilakukan sebelum penguncian ACL supaya
     * akun yang sudah tidak aktif langsung fail-closed.
     *
     * @return array{aktor: ?User, keputusan: PermissionDecision}
     */
    public function handle(User $actor, string $permission): array
    {
        /** @var User|null $lockedActor */
        $lockedActor = User::whereKey($actor->id)->lockForUpdate()->first();
        if (! $lockedActor || $lockedActor->status !== 'aktif') {
            return [
                'aktor' => $lockedActor,
                'keputusan' => $this->resolver->resolve($lockedActor ?? $actor, $permission),
            ];
        }

        DB::table('user_roles')->where('user_id', $lockedActor->id)->sharedLock()->get();
        DB::table('user_permission_granted')->where('user_id', $lockedActor->id)->whereNull('unit_id')->sharedLock()->get();
        DB::table('user_permission_denied')->where('user_id', $lockedActor->id)->sharedLock()->get();

        $actorRoleIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $lockedActor->id)
            ->where('roles.aktif', true)
            ->pluck('roles.id')
            ->all();
        sort($actorRoleIds);
        if (! empty($actorRoleIds)) {
            Role::whereIn('id', $actorRoleIds)->orderBy('id')->sharedLock()->get();
        }

        $perm = Permission::where('kode', $permission)->sharedLock()->first();
        if ($perm && ! empty($actorRoleIds)) {
            DB::table('role_permissions')->whereIn('role_id', $actorRoleIds)->where('permission_id', $perm->id)->sharedLock()->get();
        }

        return [
            'aktor' => $lockedActor,
            'keputusan' => $this->resolver->resolve($lockedActor, $permission),
        ];
    }
}
