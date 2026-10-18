<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q42 §42.2–§42.4: anggaran rupiah nonnegatif, tahap persyaratan
     * `kegiatan_perencanaan`, dan penanda persyaratan khusus kegiatan beranggaran.
     * Induk `jenis_berkas` pada `berkas` tidak memerlukan skema karena
     * `berkasable_type` tidak dibatasi CHECK.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_anggaran_nonnegatif CHECK (anggaran IS NULL OR anggaran >= 0)');
        DB::statement('ALTER TABLE jenis_berkas DROP CONSTRAINT jenis_berkas_tahap_check');
        DB::statement("ALTER TABLE jenis_berkas ADD CONSTRAINT jenis_berkas_tahap_check CHECK (tahap IN ('rencana_aksi', 'pengukuran', 'kegiatan', 'kegiatan_perencanaan'))");
        Schema::table('jenis_berkas', function (Blueprint $table): void {
            $table->boolean('hanya_kegiatan_beranggaran')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('jenis_berkas')->where('tahap', 'kegiatan_perencanaan')->exists()) {
            throw new RuntimeException('Persyaratan tahap kegiatan_perencanaan masih ada. Rollback memerlukan keputusan koreksi data.');
        }

        Schema::table('jenis_berkas', function (Blueprint $table): void {
            $table->dropColumn('hanya_kegiatan_beranggaran');
        });
        DB::statement('ALTER TABLE jenis_berkas DROP CONSTRAINT jenis_berkas_tahap_check');
        DB::statement("ALTER TABLE jenis_berkas ADD CONSTRAINT jenis_berkas_tahap_check CHECK (tahap IN ('rencana_aksi', 'pengukuran', 'kegiatan'))");
        DB::statement('ALTER TABLE kegiatan DROP CONSTRAINT kegiatan_anggaran_nonnegatif');
    }
};
