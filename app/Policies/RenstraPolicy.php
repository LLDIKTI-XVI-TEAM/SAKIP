<?php

namespace App\Policies;

use App\Models\Berkas;
use App\Models\Renstra;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;

class RenstraPolicy
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_READ));
    }

    public function view(User $user, Renstra $renstra): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_READ));
    }

    public function create(User $user): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_CREATE));
    }

    public function update(User $user, ?Renstra $renstra = null): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_UPDATE));
    }

    public function delete(User $user, ?Renstra $renstra = null): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_DELETE));
    }

    public function deleteAttachment(User $user, Renstra $renstra, ?Berkas $berkas = null): Response
    {
        $parentDeleteDecision = $this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_DELETE);
        $parentUpdateDecision = $this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_UPDATE);

        if (! $parentDeleteDecision->allowed && ! $parentUpdateDecision->allowed) {
            return $this->response($parentDeleteDecision);
        }

        $berkasDecision = $this->permissionResolver->resolve($user, PermissionCodes::BERKAS_DELETE);

        return $this->response($berkasDecision);
    }

    public function viewAttachment(User $user, Renstra $renstra, ?Berkas $berkas = null): Response
    {
        $parentDecision = $this->permissionResolver->resolve($user, PermissionCodes::RENSTRA_READ);
        if (! $parentDecision->allowed) {
            return $this->response($parentDecision);
        }

        $decision = $this->permissionResolver->resolve($user, PermissionCodes::BERKAS_READ);

        return $this->response($decision);
    }

    private function response(PermissionDecision $decision): Response
    {
        return $decision->allowed
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin yang efektif untuk melakukan tindakan ini.');
    }
}
