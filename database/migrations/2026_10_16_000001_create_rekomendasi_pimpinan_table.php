<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rekomendasi Pimpinan per indikator × tahun × periode (Q45 §45.2, Q51, Q54). Baris disetujui bersifat riwayat final;
 * perubahan membentuk baris baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekomendasi_pimpinan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->integer('tahun');
            $t->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $t->text('isi');
            $t->foreignUuid('ditetapkan_oleh')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['draf', 'disetujui'])->default('draf');
            $t->foreignUuid('disetujui_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('disetujui_oleh_role')->nullable();
            $t->timestamp('disetujui_pada')->nullable();
            $t->text('rujukan_sumber')->nullable();
            $t->integer('versi')->default(1);
            $t->timestamp('created_at');
            $t->index(['indikator_id', 'tahun', 'periode_id', 'status']);
        });
        DB::statement("ALTER TABLE rekomendasi_pimpinan ADD CONSTRAINT rekomendasi_persetujuan_cocok CHECK (
            (status = 'draf' AND disetujui_oleh IS NULL AND disetujui_oleh_role IS NULL AND disetujui_pada IS NULL)
            OR (status = 'disetujui' AND disetujui_oleh IS NOT NULL AND disetujui_oleh_role IS NOT NULL AND disetujui_pada IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX rekomendasi_pimpinan_satu_draf ON rekomendasi_pimpinan (indikator_id, tahun, periode_id) WHERE status = 'draf'");
        // Query builder dan SQL langsung tidak boleh mengubah rekomendasi yang sudah berlaku.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_rekomendasi_disetujui_mutation() RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'disetujui' THEN
                    RAISE EXCEPTION 'Rekomendasi yang sudah disetujui bersifat final.' USING ERRCODE = '23514';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER rekomendasi_disetujui_final
                BEFORE UPDATE OR DELETE ON rekomendasi_pimpinan
                FOR EACH ROW EXECUTE FUNCTION reject_rekomendasi_disetujui_mutation();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('rekomendasi_pimpinan')->exists()) {
            throw new RuntimeException('Rollback ditolak: rekomendasi pimpinan sudah tercatat.');
        }
        Schema::dropIfExists('rekomendasi_pimpinan');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_rekomendasi_disetujui_mutation()');
    }
};
