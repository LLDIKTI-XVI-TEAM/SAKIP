<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambahkan dasar_hukum ke tabel renstras jika belum ada
        if (Schema::hasTable('renstras') && ! Schema::hasColumn('renstras', 'dasar_hukum')) {
            Schema::table('renstras', function (Blueprint $table) {
                $table->text('dasar_hukum')->nullable()->after('deskripsi');
            });
        }

        // Buat tabel rencana_aksis sesuai spesifikasi Data Model SAKIP Modul 4
        Schema::create('rencana_aksis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_kinerja_id')->constrained('indikator_kinerjas')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->integer('tahun');
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_rencana_aksi');
            $table->text('uraian')->nullable();
            $table->text('target_triwulan_1')->nullable();
            $table->text('target_triwulan_2')->nullable();
            $table->text('target_triwulan_3')->nullable();
            $table->text('target_triwulan_4')->nullable();
            $table->string('status_alur', 30)->default('draft'); // draft, diajukan, diverifikasi, dikembalikan, disahkan
            $table->text('alasan_revisi')->nullable();
            $table->dateTime('disahkan_at')->nullable();
            $table->foreignId('disahkan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['indikator_kinerja_id', 'tahun']);
            $table->index(['unit_kerja_id', 'tahun']);
            $table->index('status_alur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rencana_aksis');

        if (Schema::hasTable('renstras') && Schema::hasColumn('renstras', 'dasar_hukum')) {
            Schema::table('renstras', function (Blueprint $table) {
                $table->dropColumn('dasar_hukum');
            });
        }
    }
};
