<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ulangi backfill `000004` sesudah trigger `000005` terpasang. Worker lama yang
 * mengaktifkan jadwal di antara kedua migrasi itu meninggalkan snapshot terbuka
 * yang tidak lagi memicu trigger; pemindaian ini menutup jendela tersebut tanpa
 * bergantung pada drain worker. Idempoten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jumlah = DB::transaction(function (): int {
            DB::select("SELECT pg_advisory_xact_lock(hashtextextended('sakip:periode-konfigurasi', 0))");

            return DB::table('jadwal_snapshot')
                ->where('komposisi_final', false)
                ->whereIn('jadwal_id', fn ($query) => $query->select('id')->from('jadwal_tahunan')->whereIn('status', ['aktif', 'ditutup']))
                ->update(['komposisi_final' => true]);
        });

        if ($jumlah > 0) {
            Log::info("Finalisasi snapshot terbit (sesudah trigger): {$jumlah} baris diselaraskan.");
        }
    }

    /**
     * Sengaja tanpa aksi: mengembalikan flag ke `false` membuka lagi celah
     * komponen yang dapat disisipkan ke komposisi yang sudah terbit.
     */
    public function down(): void {}
};
