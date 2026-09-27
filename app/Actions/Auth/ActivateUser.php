<?php

namespace App\Actions\Auth;

use App\Actions\Audit\WriteAuditLog;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ActivateUser
{
    public function __construct(private PermissionResolver $permissions, private WriteAuditLog $audit) {}

    public function handle(User $actor, string $targetId, string $reason): bool
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan aktivasi wajib diisi.');
        }
        $result = DB::transaction(function () use ($actor, $targetId, $reason) {
            $target = User::whereKey($targetId)->lockForUpdate()->firstOrFail();
            $decision = $this->permissions->decide($actor->fresh(), 'akses:update');
            if (! $decision['allowed']) {
                return ['denied' => $decision];
            }
            if ($target->status === 'aktif') {
                return ['changed' => false];
            }
            $target->update(['status' => 'aktif']);
            $this->audit->handle([
                'actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual',
                'tindakan' => 'pengguna.aktivasi', 'objek_tipe' => 'users', 'objek_id' => $target->id,
                'nilai_lama' => ['status' => 'nonaktif'], 'nilai_baru' => ['status' => 'aktif'],
                'alasan' => $reason, 'dasar_izin' => $decision,
            ]);

            return ['changed' => true];
        });
        if (isset($result['denied'])) {
            // Audit penolakan tidak berada dalam transaksi mutasi yang dibatalkan.
            $this->audit->handle([
                'actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual',
                'tindakan' => 'pengguna.aktivasi_ditolak', 'objek_tipe' => 'users', 'objek_id' => $targetId,
                'alasan' => 'Izin aktivasi tidak tersedia.', 'dasar_izin' => $result['denied'],
            ]);
            throw new AuthorizationException('Anda tidak berwenang mengaktifkan akun.');
        }

        return $result['changed'];
    }
}
