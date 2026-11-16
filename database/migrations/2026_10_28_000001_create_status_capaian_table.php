<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status capaian manual per versi pengukuran disahkan (PRD §21, Data Model §2.21, Workflow §16).
 * Baris bersifat append-only: revisi menulis baris baru pada versi yang sama dan baris terbaru
 * (created_at, lalu id) yang berlaku; tidak ada UNIQUE per versi karena satu versi menyimpan riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_capaian', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pengukuran_id')->constrained('pengukuran_kinerjas')->restrictOnDelete();
            // AC-1: status melekat pada versi yang disahkan, bukan pada header pengukuran.
            $t->foreignUuid('pengukuran_versi_id')->constrained('pengukuran_versi')->restrictOnDelete();
            $t->enum('status', ['tercapai', 'belum_tercapai']);
            $t->enum('sumber', ['manual', 'data_sumber'])->default('manual');
            $t->foreignUuid('ditetapkan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            // Presisi mikrodetik (AC-4): dua revisi pada detik yang sama tetap dapat diurutkan sebelum tie-break `id`.
            $t->timestamp('created_at', 6);
        });
        DB::statement("ALTER TABLE status_capaian ADD CONSTRAINT status_capaian_ditetapkan_oleh_sesuai_sumber CHECK (
            (sumber = 'manual' AND ditetapkan_oleh IS NOT NULL)
            OR (sumber = 'data_sumber' AND ditetapkan_oleh IS NULL))");
        // Query status berlaku per versi dan rekap lintas pengukuran; kolom uuid FK sekaligus memimpin index.
        DB::statement('CREATE INDEX status_capaian_versi_berlaku_idx ON status_capaian (pengukuran_versi_id, created_at DESC, id DESC)');
        DB::statement('CREATE INDEX status_capaian_pengukuran_berlaku_idx ON status_capaian (pengukuran_id, created_at DESC, id DESC)');
        // Append-only di lapisan basis data: revisi wajib menulis baris baru, sehingga riwayat tidak dapat ditimpa
        // lewat query builder, SQL langsung, maupun rollback aplikasi.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_status_capaian_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Baris status capaian bersifat append-only.' USING ERRCODE = '23514';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER status_capaian_immutable
                BEFORE UPDATE OR DELETE ON status_capaian
                FOR EACH ROW EXECUTE FUNCTION reject_status_capaian_mutation();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('status_capaian')->exists()) {
            throw new RuntimeException('Rollback ditolak: status capaian sudah tercatat.');
        }
        // Trigger ikut terhapus bersama tabel; penurunan eksplisit menjaga fungsi tidak tertinggal saat tabel sudah tidak ada.
        if (Schema::hasTable('status_capaian')) {
            DB::unprepared('DROP TRIGGER IF EXISTS status_capaian_immutable ON status_capaian');
        }
        Schema::dropIfExists('status_capaian');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_status_capaian_mutation()');
    }
};
