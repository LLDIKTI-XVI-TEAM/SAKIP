<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jadwal_tahunan ADD CONSTRAINT jadwal_koreksi_urutan_waktu CHECK ((koreksi_mulai IS NULL AND koreksi_sampai IS NULL) OR (koreksi_mulai IS NOT NULL AND koreksi_sampai IS NOT NULL AND koreksi_mulai < koreksi_sampai))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jadwal_tahunan DROP CONSTRAINT jadwal_koreksi_urutan_waktu');
    }
};
