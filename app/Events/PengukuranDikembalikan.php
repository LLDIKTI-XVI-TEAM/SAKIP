<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pengukuran dikembalikan ke PJ efektif saat pengembalian (dibaca di dalam transaksi).
 * `pengukuranId` + `versi` (versi header sesudah mutasi) mengidentifikasi satu pengembalian.
 */
class PengukuranDikembalikan implements ShouldDispatchAfterCommit
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
