<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penelusuran audit selalu diurutkan dari `waktu` terbaru dan sering difilter per aktor.
 * Index `(objek_tipe, objek_id, waktu)` yang sudah ada hanya melayani filter objek,
 * sehingga tanpa dua index ini halaman pertama memindai dan mengurutkan seluruh tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->index('waktu', 'audit_log_waktu_idx');
            $table->index(['actor_id', 'waktu'], 'audit_log_actor_waktu_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropIndex('audit_log_actor_waktu_idx');
            $table->dropIndex('audit_log_waktu_idx');
        });
    }
};
