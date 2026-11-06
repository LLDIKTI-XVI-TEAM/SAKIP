<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Pengukuran diajukan untuk verifikasi. */
class PengukuranDiajukan implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $pengukuranId,
        public readonly string $aktorId,
    ) {}
}
