<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengukuran_kinerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penugasan_indikator_id')->constrained('penugasan_indikators')->cascadeOnDelete();
            $table->foreignId('periode_jadwal_id')->constrained('periode_jadwals')->cascadeOnDelete();
            $table->decimal('target', 14, 2)->default(0);
            $table->decimal('realisasi', 14, 2)->nullable();
            $table->decimal('capaian_persen', 8, 2)->nullable();
            $table->enum('status', ['draft', 'diajukan', 'dikembalikan', 'diverifikasi', 'disahkan'])->default('draft');
            $table->text('kendala')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->text('strategi')->nullable();
            $table->dateTime('diajukan_pada')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disahkan_pada')->nullable();
            $table->timestamps();
            $table->unique(['penugasan_indikator_id', 'periode_jadwal_id']);
        });

        Schema::create('bukti_dukungs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengukuran_kinerja_id')->constrained('pengukuran_kinerjas')->cascadeOnDelete();
            $table->string('nama_file');
            $table->string('file_path')->nullable();
            $table->string('tipe_file', 50)->nullable(); // pdf, image, link
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->text('url_tautan')->nullable(); // Alternatif Google Drive/Cloud link
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('riwayat_pengukurans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengukuran_kinerja_id')->constrained('pengukuran_kinerjas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_dari', 50)->nullable();
            $table->string('status_ke', 50);
            $table->text('catatan')->nullable(); // Catatan feedback / alasan pengembalian / pengesahan
            $table->timestamps();
        });

        Schema::create('kinerja_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengukuran_kinerja_id')->constrained('pengukuran_kinerjas')->cascadeOnDelete();
            $table->string('snapshot_hash', 64);
            $table->jsonb('snapshot_data'); // Beku immutabel data capaian
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disahkan_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kinerja_snapshots');
        Schema::dropIfExists('riwayat_pengukurans');
        Schema::dropIfExists('bukti_dukungs');
        Schema::dropIfExists('pengukuran_kinerjas');
    }
};
