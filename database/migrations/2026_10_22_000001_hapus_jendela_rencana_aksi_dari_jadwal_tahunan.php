<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sejak Q41 RA disusun sepanjang tahun, sehingga jendela penyusunan tidak dibaca maupun ditulis lagi.
        // Nilai lama ikut terhapus; payload audit lama yang memuatnya tetap utuh.
        DB::statement('ALTER TABLE jadwal_tahunan DROP CONSTRAINT IF EXISTS jadwal_tahunan_urutan_ra');
        Schema::table('jadwal_tahunan', function (Blueprint $table): void {
            $table->dropColumn(['rencana_aksi_mulai', 'rencana_aksi_selesai']);
        });
    }

    public function down(): void
    {
        // Kolom dan CHECK dikembalikan kosong agar rollback migrasi lama tetap berjalan; nilai lama tidak dipulihkan.
        Schema::table('jadwal_tahunan', function (Blueprint $table): void {
            $table->date('rencana_aksi_mulai')->nullable();
            $table->date('rencana_aksi_selesai')->nullable();
        });
        DB::statement('ALTER TABLE jadwal_tahunan ADD CONSTRAINT jadwal_tahunan_urutan_ra CHECK (rencana_aksi_mulai IS NULL OR rencana_aksi_selesai IS NULL OR rencana_aksi_mulai <= rencana_aksi_selesai)');
    }
};
