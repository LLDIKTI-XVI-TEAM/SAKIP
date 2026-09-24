<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Validasi tidak ada baris yatim (orphan rows) pada jadwal_snapshot yang merujuk ke unit yang tidak eksis
        $orphanedSnapshotCount = DB::table('jadwal_snapshot')
            ->leftJoin('unit', 'jadwal_snapshot.unit_id', '=', 'unit.id')
            ->whereNull('unit.id')
            ->count();

        if ($orphanedSnapshotCount > 0) {
            throw new RuntimeException("Tidak dapat menambahkan foreign key constraint: ditemukan {$orphanedSnapshotCount} baris jadwal_snapshot dengan unit_id yang tidak valid.");
        }

        Schema::table('jadwal_snapshot', function (Blueprint $table) {
            $table->foreign('unit_id')->references('id')->on('unit')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_snapshot', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
        });
    }
};
