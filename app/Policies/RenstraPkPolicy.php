<?php

namespace App\Policies;

use App\Actions\PerjanjianKinerja\SetujuiRevisiPerjanjianKinerja;
use App\Models\RenstraPk;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\HandlesAuthorization;

class RenstraPkPolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected PermissionResolver $permissionResolver,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->status === 'aktif' && $user->roles()->where('roles.aktif', true)->exists();
    }

    public function view(User $user, RenstraPk $pk): bool
    {
        return $user->status === 'aktif' && $user->roles()->where('roles.aktif', true)->exists();
    }

    public function create(User $user): bool
    {
        return $this->permissionResolver->allows($user, PermissionCodes::PK_CREATE);
    }

    public function update(User $user, ?RenstraPk $pk = null): bool
    {
        return $this->permissionResolver->allows($user, PermissionCodes::PK_UPDATE);
    }

    public function deleteBerkas(User $user, ?RenstraPk $pk = null): bool
    {
        return $this->permissionResolver->allows($user, PermissionCodes::PK_UPDATE)
            && $this->permissionResolver->allows($user, PermissionCodes::BERKAS_DELETE);
    }

    public function uploadBerkas(User $user, ?RenstraPk $pk = null): bool
    {
        return $this->permissionResolver->allows($user, PermissionCodes::BERKAS_UPLOAD);
    }

    /** Revisi PK berversi: keempat izin revisi sekaligus dan PK sudah dikunci jadwal; status Renstra hanya menggerbang pengubah target. */
    public function revisi(User $user, RenstraPk $pk): bool
    {
        return collect(SetujuiRevisiPerjanjianKinerja::IZIN)->every(fn (string $kode): bool => $this->permissionResolver->allows($user, $kode))
            && $pk->dipakaiJadwalAktif();
    }

    public function downloadBerkas(User $user, RenstraPk $pk): bool
    {
        return $this->view($user, $pk)
            && $this->permissionResolver->allows($user, PermissionCodes::BERKAS_READ);
    }
}
