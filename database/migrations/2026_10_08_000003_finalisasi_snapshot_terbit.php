<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finalisasi snapshot terbit yang tertinggal (Review10 D3).
 *
 * Guard INSERT komponen hanya menolak sisipan setelah induk difinalkan, dan
 * finalisasi itu sebelumnya tidak pernah dilakukan jalur publikasi sehingga
 * snapshot hasil aktivasi tetap `komposisi_final = false` selamanya. Perbaikan
 * perilakunya ada di `ActivateJadwal`; migrasi ini menutup jendela untuk
 * snapshot yang sudah terlanjur terbit pada jadwal `aktif`/`ditutup` sebelum
 * perbaikan tersebut berlaku.
 *
 * Dibatasi pada jadwal yang sudah terbit: jadwal `draft` secara desain tidak
 * memiliki snapshot, sehingga barisnya tidak perlu disentuh. Transisi
 * `false → true` diizinkan guard (hanya kolom flag yang berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        $jumlah = DB::table('jadwal_snapshot')
            ->where('komposisi_final', false)
            ->whereIn('jadwal_id', fn ($query) => $query->select('id')->from('jadwal_tahunan')->whereIn('status', ['aktif', 'ditutup']))
            ->update(['komposisi_final' => true]);

        if ($jumlah > 0) {
            Log::info("Finalisasi snapshot terbit: {$jumlah} baris diselaraskan.");
        }
    }

    /**
     * Sengaja tanpa aksi: mengembalikan flag ke `false` akan membuka lagi celah
     * komponen yang dapat disisipkan ke komposisi yang sudah terbit.
     */
    public function down(): void {}
};
