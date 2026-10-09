<?php

namespace App\Policies;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\HandlesAuthorization;

class RencanaAksiPolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected PermissionResolver $permissionResolver,
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['uploadEvidence', 'deleteEvidence', 'update'], true)) {
            return null;
        }

        if ($user->status === 'aktif' && $user->hasRole('superadmin')) {
            return true;
        }

        return null;
    }

    /**
     * Memeriksa hak membaca daftar rencana aksi.
     */
    public function viewAny(User $user): bool
    {
        return $user->status === 'aktif'
            && ($user->hasRole('superadmin') || $this->permissionResolver->allows($user, PermissionCodes::RENCANA_AKSI_READ));
    }

    /**
     * Memeriksa hak membaca rencana aksi spesifik.
     */
    public function view(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $user->status === 'aktif'
            && ($user->hasRole('superadmin') || $this->permissionResolver->allows($user, PermissionCodes::RENCANA_AKSI_READ));
    }

    /**
     * Memeriksa hak membuat draft rencana aksi pada unit tertentu.
     */
    public function create(User $user, ?string $unitId = null): bool
    {
        return $user->status === 'aktif'
            && ($user->hasRole('superadmin') || $this->permissionResolver->allows($user, PermissionCodes::RENCANA_AKSI_CREATE, $unitId));
    }

    /**
     * Memeriksa hak mengubah rencana aksi.
     * Tidak dapat diubah jika status rencana aksi sudah disahkan.
     */
    public function update(User $user, RencanaAksi $rencanaAksi): bool
    {
        if ($user->status !== 'aktif' || $rencanaAksi->isDisahkan()) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $this->permissionResolver->allows(
            $user,
            PermissionCodes::RENCANA_AKSI_UPDATE,
            $rencanaAksi->targetUnitId()
        );
    }

    /**
     * Memeriksa hak melihat bukti dukung rencana aksi.
     */
    public function viewEvidence(User $user, RencanaAksi $rencanaAksi): bool
    {
        if ($user->status === 'aktif' && $user->hasRole('superadmin')) {
            return true;
        }

        return $this->view($user, $rencanaAksi)
            && $this->permissionResolver->allows($user, PermissionCodes::BERKAS_READ);
    }

    /**
     * Memeriksa hak mengunggah/menambah bukti dukung rencana aksi.
     * Mengikuti kewenangan induk rencana aksi dan izin upload berkas; ditolak jika status rencana aksi sudah disahkan.
     */
    public function uploadEvidence(User $user, RencanaAksi $rencanaAksi): bool
    {
        if ($user->status !== 'aktif' || $rencanaAksi->isDisahkan()) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $this->permissionResolver->allows(
            $user,
            PermissionCodes::RENCANA_AKSI_UPDATE,
            $rencanaAksi->targetUnitId()
        ) && $this->permissionResolver->allows($user, PermissionCodes::BERKAS_UPLOAD);
    }

    /**
     * Memeriksa hak menghapus bukti dukung rencana aksi.
     * Mengikuti kewenangan induk rencana aksi dan izin hapus berkas; ditolak jika status rencana aksi sudah disahkan.
     */
    public function deleteEvidence(User $user, RencanaAksi $rencanaAksi): bool
    {
        if ($user->status !== 'aktif' || $rencanaAksi->isDisahkan()) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $this->permissionResolver->allows(
            $user,
            PermissionCodes::RENCANA_AKSI_UPDATE,
            $rencanaAksi->targetUnitId()
        ) && $this->permissionResolver->allows($user, PermissionCodes::BERKAS_DELETE);
    }

    /**
     * Memeriksa hak mengunduh file lampiran rencana aksi dari storage privat.
     */
    public function downloadEvidence(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $this->viewEvidence($user, $rencanaAksi);
    }
}
