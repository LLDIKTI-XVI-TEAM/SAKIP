<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q74 §74.2: biaya terpakai satu angka rupiah per kegiatan, nonnegatif;
     * null selama kegiatan belum dicatat terlaksana. Tipe sama dengan `anggaran`.
     */
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            $table->decimal('biaya_terpakai', 30, 12)->nullable();
        });
        DB::statement('ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_biaya_terpakai_nonnegatif CHECK (biaya_terpakai IS NULL OR biaya_terpakai >= 0)');
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            $table->dropColumn('biaya_terpakai');
        });
    }
};
