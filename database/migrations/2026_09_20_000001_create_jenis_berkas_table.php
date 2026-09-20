<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_berkas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama');
            $table->enum('tahap', ['rencana_aksi', 'pengukuran', 'kegiatan']);
            $table->foreignId('indikator_id')->nullable()->constrained('indikator_kinerjas')->nullOnDelete();
            $table->boolean('wajib')->default(false);
            $table->text('keterangan')->nullable();
            $table->boolean('izinkan_file')->default(true);
            $table->boolean('izinkan_tautan')->default(false);
            $table->boolean('izinkan_teks')->default(false);
            $table->boolean('semua_mode_wajib')->default(false);
            $table->integer('urutan')->default(0);
            $table->string('format_diizinkan')->nullable();
            $table->integer('ukuran_maks_kb')->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_berkas');
    }
};
