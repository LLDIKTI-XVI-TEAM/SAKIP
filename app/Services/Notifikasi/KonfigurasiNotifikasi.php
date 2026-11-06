<?php

namespace App\Services\Notifikasi;

use Illuminate\Support\Facades\DB;

class KonfigurasiNotifikasi
{
    /** Baca saklar terbaru; konfigurasi menjadi fallback bila belum ada nilai pengaturan. */
    public function aktif(string $kanal): bool
    {
        $nilai = DB::table('pengaturan')->where('kunci', 'notifikasi.kanal_'.$kanal.'_aktif')->value('nilai');

        return $nilai === null ? config('notifikasi.kanal.'.$kanal.'.aktif', false) === true : in_array($nilai, ['1', 'true'], true);
    }
}
