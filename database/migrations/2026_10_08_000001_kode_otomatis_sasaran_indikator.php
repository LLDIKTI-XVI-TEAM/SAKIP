<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan kode berurutan otomatis: kolom `urutan` pada `indikator_kinerjas`
 * sebagai cermin nomor kode (Sasaran sudah memilikinya).
 *
 * Migrasi ini **hanya menambah kolom** dan tidak mengubah data, supaya kolom
 * yang dibutuhkan fitur dapat diterapkan lebih dahulu — termasuk pada database
 * yang datanya belum bersih. Penyelarasan nilai `urutan` dan pemasangan unique
 * index `kode` dijalankan di
 * `2026_10_08_000002_unique_kode_sasaran_indikator` **setelah** pre-check
 * duplikat, sehingga langkah yang mengubah/mengunci data hanya berjalan bila
 * datanya memang siap (backfill `urutan` tak dapat dibalik karena nilai lama
 * tidak disimpan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->integer('urutan')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
