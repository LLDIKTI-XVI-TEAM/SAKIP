<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Target Renstra per indikator × tahun sebagai pembanding, bukan sumber capaian. Riwayat revisi dibaca dari audit_log,
 * sehingga tabel hanya menyimpan nilai terkini; Renstra diturunkan dari indikator → sasaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_renstra', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->integer('tahun');
            $t->decimal('nilai', 30, 12)->nullable();
            $t->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at', 6)->nullable();
            $t->timestamp('updated_at', 6);
            $t->unique(['indikator_id', 'tahun']);
        });
        // NaN dianggap lebih besar daripada angka biasa oleh PostgreSQL, sehingga perlu ditolak eksplisit.
        DB::statement("ALTER TABLE target_renstra ADD CONSTRAINT target_renstra_nilai_valid_check CHECK (nilai IS NULL OR (nilai >= 0 AND nilai <> 'NaN'::numeric))");
    }

    public function down(): void
    {
        if (DB::table('target_renstra')->exists()) {
            throw new RuntimeException('Rollback ditolak: target Renstra sudah tersimpan.');
        }
        Schema::dropIfExists('target_renstra');
    }
};
