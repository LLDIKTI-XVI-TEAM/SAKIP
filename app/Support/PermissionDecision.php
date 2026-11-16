<?php

namespace App\Support;

final readonly class PermissionDecision
{
    /**
     * @param  array<string, mixed>  $basis
     */
    public function __construct(
        public bool $allowed,
        public string $permission,
        public array $basis,
    ) {}

    /** @return array<string, mixed> */
    public function toAuditBasis(): array
    {
        return [
            'permission' => $this->permission,
            'keputusan' => $this->allowed ? 'diizinkan' : 'ditolak',
            ...$this->basis,
        ];
    }
}
