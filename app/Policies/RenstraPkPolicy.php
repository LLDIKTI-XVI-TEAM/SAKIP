<?php

namespace App\Policies;

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
        return (bool) $user->is_active;
    }

    public function view(User $user, RenstraPk $pk): bool
    {
        return (bool) $user->is_active;
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
            || $this->permissionResolver->allows($user, PermissionCodes::BERKAS_DELETE);
    }
}
