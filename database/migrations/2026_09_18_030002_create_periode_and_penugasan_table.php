<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_jadwals', function (Blueprint $table) {
            $table->id();
            $table->integer('tahun');
            $table->tinyInteger('triwulan'); // 1, 2, 3, 4
            $table->string('nama_periode', 100);
            $table->dateTime('tanggal_mulai');
            $table->dateTime('tanggal_selesai');
            $table->enum('status', ['buka', 'tutup'])->default('tutup');
            $table->boolean('is_tahun_ditutup')->default(false);
            $table->timestamps();
            $table->unique(['tahun', 'triwulan']);
        });

        Schema::create('penugasan_indikators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_kinerja_id')->constrained('indikator_kinerjas')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // PIC Pegawai
            $table->integer('tahun');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_indikators');
        Schema::dropIfExists('periode_jadwals');
    }
};
