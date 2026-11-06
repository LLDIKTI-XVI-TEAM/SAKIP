<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;
use Illuminate\Support\Facades\Log;

class PenyediaLog implements PenyediaKanalNotifikasi
{
    public function kirim(NotifikasiPengiriman $pengiriman): HasilKirim
    {
        $rujukan = 'log:'.$pengiriman->id;
        Log::info('notifikasi.simulasi_diterima', ['pengiriman_id' => $pengiriman->id, 'kanal' => $pengiriman->kanal, 'rujukan' => $rujukan]);

        return HasilKirim::diterima($rujukan);
    }
}
