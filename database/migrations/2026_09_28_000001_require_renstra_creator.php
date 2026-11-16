<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Penyusun lama harus terverifikasi sebelum constraint wajib diterapkan. */
    public function up(): void
    {
        if (DB::table('renstras')->whereNull('created_by')->exists()) {
            throw new RuntimeException('Renstra lama belum memiliki penyusun. Tetapkan created_by berdasarkan bukti yang sah sebelum menjalankan migrasi.');
        }

        Schema::table('renstras', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->uuid('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /** Kembalikan kontrak sebelumnya tanpa menghapus identitas penyusun yang sudah ada. */
    public function down(): void
    {
        Schema::table('renstras', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->uuid('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
