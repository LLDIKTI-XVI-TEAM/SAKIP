<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;

interface PenyediaKanalNotifikasi
{
    /** Adapter wajib meneruskan kunci_idempoten sejak panggilan pertama. */
    public function kirim(NotifikasiPengiriman $pengiriman): HasilKirim;
}
