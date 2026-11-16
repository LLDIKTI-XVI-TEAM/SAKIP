<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Q70 menghapus tahap reviu dari jadwal; nilai lama ikut terhapus, jejaknya tetap utuh di payload audit.
        DB::statement('ALTER TABLE jadwal_periode DROP CONSTRAINT jadwal_periode_urutan_tanggal');
        Schema::table('jadwal_periode', function (Blueprint $table): void {
            $table->dropColumn(['reviu_mulai', 'reviu_selesai']);
        });
        DB::statement('ALTER TABLE jadwal_periode ADD CONSTRAINT jadwal_periode_urutan_tanggal CHECK (pengisian_mulai <= pengisian_selesai)');
    }

    public function down(): void
    {
        // Kolom dikembalikan kosong dengan nama CHECK yang sama agar rollback migrasi lama tetap berjalan; nilai lama tidak dipulihkan.
        DB::statement('ALTER TABLE jadwal_periode DROP CONSTRAINT jadwal_periode_urutan_tanggal');
        Schema::table('jadwal_periode', function (Blueprint $table): void {
            $table->date('reviu_mulai')->nullable();
            $table->date('reviu_selesai')->nullable();
        });
        DB::statement('ALTER TABLE jadwal_periode ADD CONSTRAINT jadwal_periode_urutan_tanggal CHECK (pengisian_mulai <= pengisian_selesai AND (reviu_mulai IS NULL OR reviu_selesai IS NULL OR (pengisian_selesai <= reviu_mulai AND reviu_mulai <= reviu_selesai)))');
    }
};
