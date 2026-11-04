<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Jendela pengisian PIC berubah dari tertutup menjadi terbuka oleh aksi resmi (aktivasi jadwal atau revisi jendela PIC)
 * pada tanggal bisnis aksi. `indikatorIds` null = semua indikator (jalur normal); daftar = lingkup sesi koreksi
 * (`[]` berarti tidak ada). `tanggalBisnis` dipakai listener menentukan PJ efektif, bukan menghitung "hari ini" sendiri.
 */
class JendelaPengisianDibuka implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $periodeJadwalIds
     * @param  list<string>|null  $indikatorIds
     */
    public function __construct(
        public readonly string $jadwalId,
        public readonly array $periodeJadwalIds,
        public readonly ?array $indikatorIds,
        public readonly string $tanggalBisnis,
    ) {}
}
