<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class RencanaAksiDibukaKembali implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $rencanaAksiId,
        public readonly string $versiPengajuanId,
        public readonly string $pengajuHistorisId,
        public readonly ?string $penerimaId,
        public readonly string $aktorId,
        public readonly string $alasan,
        public readonly string $dariStatus = 'disahkan',
    ) {}
}
