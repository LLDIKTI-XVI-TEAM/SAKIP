<?php

namespace App\Actions\Access;

use App\Actions\Audit\WriteAuditLog;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeUserStatus
{
    public function __construct(private PermissionResolver $permissions, private WriteAuditLog $audit) {}

    /**
     * @return array{status: 'changed'|'unchanged'}
     */
    public function handle(User $actor, string $targetId, string $status, string $reason, string $expectedStatus): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['alasan' => 'Alasan wajib diisi dan maksimal 2.000 karakter.']);
        }
        $result = DB::transaction(function () use ($actor, $targetId, $status, $reason, $expectedStatus) {
            $superadminIds = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('roles.kode', 'superadmin')->where('roles.aktif', true)->pluck('user_roles.user_id')->all();
            // Kunci aktor, target, dan seluruh superadmin dalam satu urutan agar guard superadmin terakhir tahan balapan.
            $users = User::whereIn('id', array_values(array_unique([$actor->id, $targetId, ...$superadminIds])))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->id) ?? User::whereKey($actor->id)->firstOrFail();
            $decisions = [
                'pengguna_read' => $this->permissions->decide($currentActor, 'pengguna:read'),
                'akses_update' => $this->permissions->decide($currentActor, 'akses:update'),
            ];
            $audit = ['actor_type' => 'user', 'actor_id' => $currentActor->id, 'sumber' => 'manual', 'objek_tipe' => 'users', 'objek_id' => $targetId, 'dasar_izin' => $decisions];
            if (! $decisions['pengguna_read']['allowed'] || ! $decisions['akses_update']['allowed']) {
                return ['denied' => true, 'audit' => $audit, 'message' => 'Anda tidak berwenang mengubah status akun.'];
            }
            $target = $users->get($targetId) ?? User::whereKey($targetId)->firstOrFail();
            if ($target->id === $currentActor->id) {
                return ['field' => 'status', 'audit' => $audit, 'message' => 'Anda tidak dapat mengubah status akun sendiri.'];
            }
            if ($target->status !== $expectedStatus) {
                return ['field' => 'expected_status', 'audit' => $audit, 'message' => 'Status akun telah berubah. Muat ulang data sebelum menyimpan.'];
            }
            if ($target->status === $status) {
                return ['status' => 'unchanged'];
            }
            $otherActiveSuperadmin = $users->only($superadminIds)->except($target->id)->contains(fn (User $user) => $user->status === 'aktif');
            if ($status === 'nonaktif' && in_array($target->id, $superadminIds, true) && ! $otherActiveSuperadmin) {
                return ['field' => 'status', 'audit' => $audit, 'message' => 'Superadmin aktif terakhir tidak dapat dinonaktifkan.'];
            }
            $this->audit->handle($audit + [
                'tindakan' => $status === 'nonaktif' ? 'pengguna.nonaktifkan' : 'pengguna.aktivasi', 'alasan' => $reason,
                'nilai_lama' => ['status' => $target->status], 'nilai_baru' => ['status' => $status],
            ]);
            $target->update(['status' => $status]);

            return ['status' => 'changed'];
        });
        if (isset($result['audit'])) {
            // Penolakan tetap tercatat walau transaksi mutasi dibatalkan.
            $this->audit->handle($result['audit'] + ['tindakan' => 'pengguna.status_ditolak', 'alasan' => $result['message']]);
            if (isset($result['denied'])) {
                throw new AuthorizationException($result['message']);
            }
            throw ValidationException::withMessages([$result['field'] => $result['message']]);
        }

        return $result;
    }
}
