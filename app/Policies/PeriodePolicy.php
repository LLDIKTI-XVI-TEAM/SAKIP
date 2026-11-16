<?php

namespace App\Policies;

use App\Models\Periode;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\Response;

class PeriodePolicy
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /** OR hanya untuk baca pengelolaan; tiap mutasi memakai permission intent sendiri. */
    public function viewAny(User $user): Response
    {
        return $this->create($user)->allowed() || $this->update($user)->allowed() ? Response::allow() : Response::deny();
    }

    public function create(User $user): Response
    {
        return $this->resolver->allows($user, PermissionCodes::PERIODE_CREATE) ? Response::allow() : Response::deny();
    }

    public function update(User $user, ?Periode $periode = null): Response
    {
        return $this->resolver->allows($user, PermissionCodes::PERIODE_UPDATE) ? Response::allow() : Response::deny();
    }
}
