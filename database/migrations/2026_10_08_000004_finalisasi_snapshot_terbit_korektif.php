<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migrasi korektif finalisasi snapshot terbit (Review10 F4).
 *
 * `2026_10_08_000003_finalisasi_snapshot_terbit` sudah diedarkan dalam bentuk
 * tanpa advisory lock, sehingga database yang sempat menjalankannya telah
 * mencatat migrasi tersebut dan tidak akan mengeksekusi versi berserialisasi
 * apa pun. Migrasi ini menjalankan ulang backfill yang sama dengan timestamp
 * baru supaya benar-benar berjalan di database existing; sifatnya idempoten
 * karena hanya menyentuh snapshot yang masih `komposisi_final = false`.
 *
 * Lock + update berada dalam satu transaksi eksplisit dengan advisory lock
 * eksklusif bernama sama seperti jalur aktivasi (`Periode::lockConfiguration()`)
 * agar aktivasi yang sedang berjalan commit lebih dahulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jumlah = 0;

        DB::transaction(function () use (&$jumlah): void {
            DB::select("SELECT pg_advisory_xact_lock(hashtextextended('sakip:periode-konfigurasi', 0))");

            $jumlah = DB::table('jadwal_snapshot')
                ->where('komposisi_final', false)
                ->whereIn('jadwal_id', fn ($query) => $query->select('id')->from('jadwal_tahunan')->whereIn('status', ['aktif', 'ditutup']))
                ->update(['komposisi_final' => true]);
        });

        if ($jumlah > 0) {
            Log::info("Finalisasi snapshot terbit (korektif): {$jumlah} baris diselaraskan.");
        }
    }

    /**
     * Sengaja tanpa aksi: mengembalikan flag ke `false` akan membuka lagi celah
     * komponen yang dapat disisipkan ke komposisi yang sudah terbit.
     */
    public function down(): void {}
};
