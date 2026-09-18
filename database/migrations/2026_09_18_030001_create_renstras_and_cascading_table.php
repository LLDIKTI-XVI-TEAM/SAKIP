<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renstras', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->integer('tahun_mulai');
            $table->integer('tahun_selesai');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('sasaran_strategis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renstra_id')->constrained('renstras')->cascadeOnDelete();
            $table->string('kode', 50);
            $table->text('deskripsi');
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('indikator_kinerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sasaran_strategis_id')->constrained('sasaran_strategis')->cascadeOnDelete();
            $table->string('kode', 50);
            $table->text('nama');
            $table->text('definisi_operasional')->nullable();
            $table->string('satuan', 50);
            $table->enum('tipe_perhitungan', ['naik_baik', 'turun_baik'])->default('naik_baik');
            $table->string('jenis_agregasi', 50)->default('terakhir');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('target_kinerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_kinerja_id')->constrained('indikator_kinerjas')->cascadeOnDelete();
            $table->integer('tahun');
            $table->decimal('target_tahunan', 14, 2)->default(0);
            $table->decimal('target_tw1', 14, 2)->default(0);
            $table->decimal('target_tw2', 14, 2)->default(0);
            $table->decimal('target_tw3', 14, 2)->default(0);
            $table->decimal('target_tw4', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['indikator_kinerja_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_kinerjas');
        Schema::dropIfExists('indikator_kinerjas');
        Schema::dropIfExists('sasaran_strategis');
        Schema::dropIfExists('renstras');
    }
};
