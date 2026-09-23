<?php

namespace App\Actions\Access;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePermissionPolicy;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\RoleCatalog;
use App\Services\Authorization\RolePermissionState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChangeRolePermission
{
    public function __construct(private RolePermissionPolicy $policy, private RolePermissionState $state, private WriteAuditLog $audit) {}

    /**
     * Ubah satu tuple tanpa mengganti set terfilter. Lock sumber izin melindungi
     * recheck ketika operator lain mencabut akses role Superadmin yang sama.
     *
     * @return 'added'|'revoked'|'unchanged'
     */
    public function handle(User $actor, string $roleId, string $permissionId, string $operation, string $reason, string $expectedState): string
    {
        $reason = trim($reason);
        Validator::make(['role_id' => $roleId, 'permission_id' => $permissionId, 'operation' => $operation, 'alasan' => $reason, 'expected_state' => $expectedState], [
            'role_id' => ['required', 'uuid'], 'permission_id' => ['required', 'uuid'],
            'operation' => ['required', 'in:add,revoke'], 'alasan' => ['required', 'string', 'max:2000'],
            'expected_state' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
        ])->validate();

        $result = DB::transaction(function () use ($actor, $roleId, $permissionId, $operation, $reason, $expectedState) {
            $currentActor = User::whereKey($actor->id)->lockForUpdate()->first();
            if (! $currentActor) {
                throw new AuthorizationException('Akun tidak tersedia.');
            }
            $sourceId = DB::table('user_roles')->where('user_id', $currentActor->id)->value('role_id');
            if (! $currentActor->is_active || $sourceId === null) {
                return $this->policy->decide($currentActor);
            }
            // Semua user lock mendahului role; role dan metadata selalu UUID-sorted.
            $roles = Role::whereIn('id', array_unique([$sourceId, $roleId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $roles->get($sourceId);
            if (! $source || ! $source->aktif || $source->kode !== 'superadmin') {
                return $this->policy->decide($currentActor);
            }
            $role = $roles->get($roleId);
            $attached = $role ? DB::table('role_permissions')->where('role_id', $role->id)->pluck('permission_id')->all() : [];
            $permissions = Permission::where(fn ($query) => $query->whereIn('id', [...$attached, $permissionId])->orWhere('kode', 'akses:update'))
                ->orderBy('id')->sharedLock()->get()->keyBy('id');
            $decision = $this->policy->decide($currentActor);
            if (! $decision['allowed']) {
                return $decision;
            }
            // Authorization didahulukan agar error target/token tidak membuka data tanpa izin.
            if (! $role || ! $role->aktif || ! RoleCatalog::contains($role->kode)) {
                throw ValidationException::withMessages(['role_id' => 'Pilih peran resmi yang aktif.']);
            }
            $permission = $permissions->get($permissionId);
            if (! $permission || ! $permission->aktif || ! in_array($permission->kode, PermissionCatalog::codes(), true)) {
                throw ValidationException::withMessages(['permission_id' => 'Pilih izin katalog yang aktif.']);
            }
            if ($permission->butuh_scope !== 'global') {
                throw ValidationException::withMessages(['permission_id' => 'Izin unit tidak dikelola sebagai izin global peran. Gunakan pengelolaan grant per unit.']);
            }
            $before = $this->state->capture($role);
            if (! hash_equals($before['token'], $expectedState)) {
                throw ValidationException::withMessages(['expected_state' => 'Isi izin peran telah berubah. Tinjau data terbaru sebelum menyimpan.']);
            }
            $exists = in_array($permissionId, $attached, true);
            if (($operation === 'add') === $exists) {
                return 'unchanged';
            }
            if ($operation === 'add') {
                DB::table('role_permissions')->insert(['id' => (string) Str::uuid(), 'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now()]);
            } else {
                DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->delete();
            }
            $this->audit->handle([
                'actor_type' => 'user', 'actor_id' => $currentActor->id, 'sumber' => 'manual',
                'tindakan' => 'role_permissions.ubah', 'objek_tipe' => 'roles', 'objek_id' => $role->id,
                'nilai_lama' => ['permissions' => $before['codes']],
                'nilai_baru' => ['permissions' => $this->state->capture($role)['codes']],
                'alasan' => $reason,
                'dasar_izin' => ['role_policy' => ['required_role' => 'superadmin', 'allowed' => true], 'akses_update' => $decision['akses_update']],
            ]);

            return $operation === 'add' ? 'added' : 'revoked';
        });
        if (is_string($result)) {
            return $result;
        }

        // Penolakan Action dicatat sesudah transaksi tanpa menyimpan snapshot target.
        $this->audit->handle([
            'actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual',
            'tindakan' => 'role_permissions.ditolak', 'objek_tipe' => 'users', 'objek_id' => $actor->id,
            'alasan' => 'Anda tidak berwenang mengelola izin peran.',
            'dasar_izin' => ['role_policy' => ['required_role' => 'superadmin', 'allowed' => false, 'reason' => $result['reason']], 'akses_update' => $result['akses_update']],
        ]);
        throw new AuthorizationException('Anda tidak berwenang mengelola izin peran.');
    }
}
