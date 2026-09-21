<?php

namespace App\Services;

use App\Models\User;
use App\Services\Authorization\PermissionResolver as AuthorizationPermissionResolver;
use App\Support\PermissionDecision;

class PermissionResolver
{
    public function __construct(private readonly AuthorizationPermissionResolver $authorizationPermissionResolver) {}

    public function resolve(User $user, string $permissionCode, ?string $unitId = null): PermissionDecision
    {
        $decision = $this->authorizationPermissionResolver->decide($user, $permissionCode, $unitId);

        return new PermissionDecision($decision['allowed'], $permissionCode, [
            'alasan' => $decision['reason'],
            'sumber_allow' => [
                'roles' => $decision['roles'],
                'grants' => $decision['grants'],
            ],
            'deny' => $decision['denies'],
        ]);
    }
}
