<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Pengukuran yang sudah disahkan dibuka kembali ke PJ efektif; payload sama dengan pengembalian. */
class PengukuranDibukaKembali implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $pengukuranId,
        public readonly int $versi,
        public readonly ?string $penerimaId,
        public readonly string $aktorId,
        public readonly string $alasan,
    ) {}
}
