<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('jenis', ['kepmen', 'permen', 'perpres', 'keputusan_lainnya']);
            $table->string('nomor');
            $table->unsignedSmallInteger('tahun');
            $table->text('tentang');
            $table->date('tanggal')->nullable();
            $table->string('tautan_sumber', 2048)->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['jenis', 'nomor', 'tahun'], 'regulasi_jenis_nomor_tahun_unique');
        });

        Schema::table('renstras', function (Blueprint $table) {
            $table->foreignUuid('regulasi_id')
                ->nullable()
                ->constrained('regulasi')
                ->nullOnDelete();
        });

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->foreignUuid('regulasi_id')
                ->nullable()
                ->after('sasaran_strategis_id')
                ->constrained('regulasi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regulasi_id');
        });

        Schema::table('renstras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regulasi_id');
        });

        Schema::dropIfExists('regulasi');
    }
};
