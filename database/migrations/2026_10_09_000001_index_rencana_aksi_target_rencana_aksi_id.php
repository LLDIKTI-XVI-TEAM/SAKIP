<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seluruh matriks target dibaca per `rencana_aksi_id` (halaman RA, pratinjau
 * tiap ketikan, simpan, rekonsiliasi snapshot). Dua index unik yang ada
 * bersifat parsial (`komponen_id IS NULL` / `IS NOT NULL`) sehingga tidak
 * dapat dipakai untuk filter itu; tanpa index ini setiap pembacaan matriks
 * memindai seluruh tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rencana_aksi_target', function (Blueprint $table): void {
            $table->index('rencana_aksi_id', 'ra_target_rencana_aksi_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_aksi_target', function (Blueprint $table): void {
            $table->dropIndex('ra_target_rencana_aksi_idx');
        });
    }
};
