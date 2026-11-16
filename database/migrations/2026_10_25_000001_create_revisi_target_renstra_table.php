<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi target Renstra aktif (Q71): baris `draf` menampung isian autosave dan berubah menjadi histori `disimpan` bernomor
 * saat revisi disimpan, sehingga draf dan histori memakai tabel yang sama. Draf hanya memuat sel yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisi_target_renstra', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('renstra_id')->constrained('renstras')->restrictOnDelete();
            $t->enum('status', ['draf', 'disimpan'])->default('draf');
            $t->integer('nomor')->nullable();
            $t->text('justifikasi')->nullable();
            $t->integer('versi')->default(1);
            $t->foreignUuid('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('disimpan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('disimpan_pada', 6)->nullable();
            $t->timestamp('created_at', 6)->nullable();
            $t->timestamp('updated_at', 6);
            $t->unique(['renstra_id', 'nomor']);
        });
        // Justifikasi draf boleh kosong karena ikut tersimpan otomatis; kewajibannya baru berlaku saat disimpan.
        DB::statement("ALTER TABLE revisi_target_renstra ADD CONSTRAINT revisi_target_renstra_penyimpanan_cocok CHECK (versi >= 1 AND (
            (status = 'draf' AND nomor IS NULL AND disimpan_oleh IS NULL AND disimpan_pada IS NULL)
            OR (status = 'disimpan' AND nomor IS NOT NULL AND nomor >= 1 AND justifikasi IS NOT NULL AND justifikasi ~ '\\S'
                AND disimpan_oleh IS NOT NULL AND disimpan_pada IS NOT NULL)))");
        DB::statement("CREATE UNIQUE INDEX revisi_target_renstra_satu_draf ON revisi_target_renstra (renstra_id) WHERE status = 'draf'");

        Schema::create('revisi_target_renstra_nilai', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('revisi_id')->constrained('revisi_target_renstra')->cascadeOnDelete();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->integer('tahun');
            $t->decimal('nilai_lama', 30, 12)->nullable();
            $t->decimal('nilai_baru', 30, 12)->nullable();
            $t->unique(['revisi_id', 'indikator_id', 'tahun']);
        });
        // NaN dianggap lebih besar daripada angka biasa oleh PostgreSQL, sehingga perlu ditolak eksplisit.
        DB::statement("ALTER TABLE revisi_target_renstra_nilai ADD CONSTRAINT revisi_target_renstra_nilai_valid_check CHECK (
            (nilai_lama IS NULL OR (nilai_lama >= 0 AND nilai_lama <> 'NaN'::numeric))
            AND (nilai_baru IS NULL OR (nilai_baru >= 0 AND nilai_baru <> 'NaN'::numeric)))");
    }

    public function down(): void
    {
        if (DB::table('revisi_target_renstra')->exists()) {
            throw new RuntimeException('Rollback ditolak: revisi target Renstra sudah tercatat.');
        }
        Schema::dropIfExists('revisi_target_renstra_nilai');
        Schema::dropIfExists('revisi_target_renstra');
    }
};
