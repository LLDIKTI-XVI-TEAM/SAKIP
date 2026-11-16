<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;

class RolePermissionPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    /** Halaman baca mengikuti izin efektif; tidak bergantung label role atau izin mutasi. */
    public function decide(User $actor): array
    {
        return $this->permissions->decide($actor, 'pengguna:read');
    }
}
