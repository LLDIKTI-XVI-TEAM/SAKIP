<?php

namespace App\Services;

use App\Services\Authorization\PermissionResolver as AuthorizationPermissionResolver;

/**
 * Compatibility wrapper for legacy consumers.
 * Canonical implementation resides at \App\Services\Authorization\PermissionResolver.
 *
 * @deprecated Gunakan \App\Services\Authorization\PermissionResolver secara langsung.
 */
class PermissionResolver extends AuthorizationPermissionResolver {}
