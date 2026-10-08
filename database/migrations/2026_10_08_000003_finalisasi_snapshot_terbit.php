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
 *
 * Dijalankan di bawah advisory lock yang sama dengan jalur aktivasi supaya
 * aktivasi yang sedang berjalan tidak lolos dari pemindaian satu kali ini
 * (lihat komentar pada `up()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        $jumlah = 0;

        // Serialisasi dengan jalur aktivasi (Review10 P1): aktivasi memegang
        // advisory lock bersama `sakip:periode-konfigurasi` selama transaksinya
        // (lihat Periode::lockConfiguration()). Kunci eksklusif dengan nama yang
        // sama membuat migrasi ini menunggu aktivasi yang sedang berjalan commit
        // lebih dahulu — termasuk aktivasi dari versi kode lama yang masih
        // menyisipkan snapshot `komposisi_final=false`. Tanpa ini ada jendela:
        // migrasi selesai sebelum aktivasi itu commit, snapshot terbitnya lolos
        // dari pemindaian satu kali ini, dan replay aktivasi kemudian no-op
        // sehingga komposisinya tetap dapat disisipi komponen.
        DB::transaction(function () use (&$jumlah): void {
            DB::select("SELECT pg_advisory_xact_lock(hashtextextended('sakip:periode-konfigurasi', 0))");

            $jumlah = DB::table('jadwal_snapshot')
                ->where('komposisi_final', false)
                ->whereIn('jadwal_id', fn ($query) => $query->select('id')->from('jadwal_tahunan')->whereIn('status', ['aktif', 'ditutup']))
                ->update(['komposisi_final' => true]);
        });

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
