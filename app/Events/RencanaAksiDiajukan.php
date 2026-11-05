<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Rencana aksi diajukan untuk verifikasi; `rencanaAksiId` + `versiPengajuanId` mengidentifikasi satu pengajuan. */
class RencanaAksiDiajukan implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $rencanaAksiId,
        public readonly string $versiPengajuanId,
        public readonly string $aktorId,
    ) {}
}
