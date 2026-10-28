<?php

namespace App\Actions\Laporan;

use App\Models\User;
use App\Services\Laporan\PembacaRekapIndikatorPeriode;

class TampilkanRekapIndikatorPeriode
{
    public function __construct(private readonly PembacaRekapIndikatorPeriode $pembaca) {}

    /** Halaman mempertahankan paginasi dan memakai proyeksi yang sama dengan ekspor. */
    public function handle(User $actor, array $filter): array
    {
        return $this->pembaca->baca($actor, $filter);
    }
}
