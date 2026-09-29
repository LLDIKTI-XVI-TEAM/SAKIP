<?php

namespace App\Exceptions;

use App\Support\PermissionDecision;
use RuntimeException;

class ReauthorizationDenialException extends RuntimeException
{
    public function __construct(
        public readonly PermissionDecision $decision,
        public readonly string $denialReason,
        string $message = 'Pengguna tidak memiliki izin.',
    ) {
        parent::__construct($message);
    }
}
