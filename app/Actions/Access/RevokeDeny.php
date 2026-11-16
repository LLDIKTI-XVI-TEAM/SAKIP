<?php

namespace App\Actions\Access;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RevokeDeny
{
    public function __construct(private PermissionResolver $permissions, private WriteAuditLog $audit) {}

    public function handle(User $actor, string $denyId, string $reason): void
    {
        $reason = trim($reason);
        Validator::make(['deny_id' => $denyId, 'alasan' => $reason], [
            'deny_id' => ['required', 'uuid'], 'alasan' => ['required', 'string', 'max:2000'],
        ])->validate();
        $denied = DB::transaction(function () use ($actor, $denyId, $reason) {
            // Kandidat hanya menentukan lock target; keberadaan row baru diungkap setelah recheck izin.
            $targetId = UserPermissionDeny::whereKey($denyId)->value('user_id');
            $users = User::whereIn('id', array_filter([$actor->id, $targetId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->id) ?? User::whereKey($actor->id)->firstOrFail();
            $decision = $this->permissions->decide($currentActor, 'akses:update');
            if (! $decision['allowed']) {
                return $decision;
            }
            $deny = UserPermissionDeny::whereKey($denyId)->lockForUpdate()->first();
            if (! $deny || $deny->user_id !== $targetId) {
                throw ValidationException::withMessages(['deny_id' => 'Pencabutan izin ini sudah berubah atau dicabut. Muat ulang data.']);
            }
            // Revoke memakai referensi tersimpan, termasuk izin nonaktif atau yang sudah keluar katalog.
            $deny->setRelation('permission', Permission::whereKey($deny->permission_id)->sharedLock()->firstOrFail());
            $deny->setRelation('unit', $deny->unit_id === null ? null : Unit::whereKey($deny->unit_id)->sharedLock()->firstOrFail());
            $snapshot = $deny->auditSnapshot();
            $deny->delete();
            $this->audit->handle([
                'actor_type' => 'user', 'actor_id' => $currentActor->id, 'sumber' => 'manual',
                'tindakan' => 'user_permission_denied.hapus', 'objek_tipe' => 'user_permission_denied', 'objek_id' => $denyId,
                'alasan' => $reason, 'nilai_lama' => $snapshot, 'dasar_izin' => $decision,
            ]);

            return null;
        });
        if ($denied !== null) {
            $this->audit->handle([
                'actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual',
                'tindakan' => 'user_permission_denied.ditolak', 'objek_tipe' => 'user_permission_denied', 'objek_id' => $denyId,
                'alasan' => 'Anda tidak berwenang mengelola pencabutan izin.', 'dasar_izin' => $denied,
            ]);
            throw new AuthorizationException('Anda tidak berwenang mengelola pencabutan izin.');
        }
    }
}
