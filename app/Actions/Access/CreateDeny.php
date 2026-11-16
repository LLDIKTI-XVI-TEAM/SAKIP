<?php

namespace App\Actions\Access;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateDeny
{
    public function __construct(private PermissionResolver $permissions, private WriteAuditLog $audit) {}

    public function handle(User $actor, string $targetId, string $permissionId, ?string $unitId, string $reason): UserPermissionDeny
    {
        $reason = trim($reason);
        Validator::make(['user_id' => $targetId, 'permission_id' => $permissionId, 'unit_id' => $unitId, 'alasan' => $reason], [
            'user_id' => ['required', 'uuid'], 'permission_id' => ['required', 'uuid'],
            'unit_id' => ['nullable', 'uuid'], 'alasan' => ['required', 'string', 'max:2000'],
        ])->validate();

        try {
            $result = DB::transaction(function () use ($actor, $targetId, $permissionId, $unitId, $reason) {
                // Semua mutasi akses mengunci user dengan urutan sama, termasuk target tanpa deny awal.
                $users = User::whereIn('id', [$actor->id, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $currentActor = $users->get($actor->id) ?? User::whereKey($actor->id)->firstOrFail();
                $decision = $this->permissions->decide($currentActor, 'akses:update');
                if (! $decision['allowed']) {
                    return $decision;
                }
                if (! $users->has($targetId)) {
                    throw ValidationException::withMessages(['user_id' => 'Pengguna tidak tersedia.']);
                }
                $permission = Permission::whereKey($permissionId)->sharedLock()->first();
                if (! $permission || ! $permission->aktif || ! in_array($permission->kode, PermissionCatalog::codes(), true)) {
                    throw ValidationException::withMessages(['permission_id' => 'Pilih izin katalog yang aktif.']);
                }
                $unit = $unitId === null ? null : Unit::whereKey($unitId)->sharedLock()->first();
                if ($unitId !== null && ! $unit) {
                    throw ValidationException::withMessages(['unit_id' => 'Unit tidak tersedia.']);
                }
                if (UserPermissionDeny::where('user_id', $targetId)->where('permission_id', $permissionId)->where('unit_id', $unitId)->exists()) {
                    throw ValidationException::withMessages(['permission_id' => 'Pencabutan izin untuk pengguna dan cakupan ini sudah ada.']);
                }
                $deny = UserPermissionDeny::create([
                    'user_id' => $targetId, 'permission_id' => $permissionId, 'unit_id' => $unitId,
                    'alasan' => $reason, 'ditetapkan_oleh' => $currentActor->id,
                ]);
                // Baca waktu yang tersimpan agar snapshot create/revoke identik pada presisi database.
                $deny->refresh()->setRelation('permission', $permission)->setRelation('unit', $unit);
                $this->audit->handle([
                    'actor_type' => 'user', 'actor_id' => $currentActor->id, 'sumber' => 'manual',
                    'tindakan' => 'user_permission_denied.tambah', 'objek_tipe' => 'user_permission_denied', 'objek_id' => $deny->id,
                    'alasan' => $reason, 'nilai_baru' => $deny->auditSnapshot(), 'dasar_izin' => $decision,
                ]);

                return $deny;
            });
        } catch (QueryException $exception) {
            // Hanya benturan tuple deny diterjemahkan, sesudah transaksi PostgreSQL dibatalkan.
            if (($exception->errorInfo[0] ?? null) === '23505'
                && preg_match('/"user_permission_denied_(?:global|scoped)_unique"/', $exception->errorInfo[2] ?? '') === 1) {
                throw ValidationException::withMessages(['permission_id' => 'Pencabutan izin untuk pengguna dan cakupan ini sudah ada.']);
            }
            throw $exception;
        }
        if ($result instanceof UserPermissionDeny) {
            return $result;
        }
        $this->audit->handle([
            'actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual',
            'tindakan' => 'user_permission_denied.ditolak', 'objek_tipe' => 'users', 'objek_id' => $targetId,
            'alasan' => 'Anda tidak berwenang mengelola pencabutan izin.', 'dasar_izin' => $result,
        ]);
        throw new AuthorizationException('Anda tidak berwenang mengelola pencabutan izin.');
    }
}
