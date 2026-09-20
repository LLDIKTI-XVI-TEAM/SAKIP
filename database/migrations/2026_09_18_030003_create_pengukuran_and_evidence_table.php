<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengukuran_kinerjas', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->integer('tahun');
            $t->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $t->foreignUuid('jadwal_snapshot_id')->constrained('jadwal_snapshot')->restrictOnDelete();
            $t->decimal('nilai', 30, 12)->nullable();
            $t->enum('sumber_nilai', ['manual', 'komponen', 'historis']);
            $t->enum('status_perhitungan', ['belum_diisi', 'terhitung', 'tidak_dapat_dihitung'])->default('belum_diisi');
            $t->text('alasan_tidak_dapat_dihitung')->nullable();
            $t->text('alasan_historis')->nullable();
            $t->text('sumber_historis')->nullable();
            $t->text('catatan')->nullable();
            $t->enum('status_alur', ['draft', 'diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'])->default('draft');
            $t->integer('versi')->default(1);
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['indikator_id', 'tahun', 'periode_id']);
        });
        DB::statement("ALTER TABLE pengukuran_kinerjas ADD CONSTRAINT nilai_status_cocok CHECK ((status_perhitungan = 'terhitung' AND nilai IS NOT NULL) OR (status_perhitungan IN ('belum_diisi','tidak_dapat_dihitung') AND nilai IS NULL))");
        Schema::create('pengukuran_komponen', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pengukuran_id')->constrained('pengukuran_kinerjas')->restrictOnDelete();
            $t->foreignUuid('komponen_id')->constrained('indikator_komponen')->restrictOnDelete();
            $t->decimal('nilai', 30, 12)->nullable();
            $t->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('updated_at');
            $t->unique(['pengukuran_id', 'komponen_id']);
        });
        Schema::create('pengukuran_versi', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pengukuran_id')->constrained('pengukuran_kinerjas')->restrictOnDelete();
            $t->foreignUuid('rencana_aksi_versi_id')->nullable()->constrained('rencana_aksi_versi')->restrictOnDelete();
            $t->foreignUuid('jadwal_snapshot_id')->constrained('jadwal_snapshot')->restrictOnDelete();
            $t->integer('nomor');
            $t->foreignUuid('diajukan_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('diajukan_at');
            $t->enum('jalur_pengajuan', ['pic', 'perencanaan']);
            $t->jsonb('dasar_izin_pengajuan');
            $t->jsonb('snapshot');
            $t->foreignUuid('disahkan_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('disahkan_at')->nullable();
            $t->unique(['pengukuran_id', 'nomor']);
        });
        // Data hulu klaim dibaca saat snapshot; tidak menyediakan CRUD kegiatan/RA baru.
        Schema::create('kegiatan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('unit_id')->constrained('unit')->restrictOnDelete();
            $t->integer('tahun');
            $t->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $t->string('nama');
            $t->text('tujuan');
            $t->integer('sasaran_peserta')->nullable();
            $t->string('keterangan_peserta')->nullable();
            $t->string('lokasi')->nullable();
            $t->date('tanggal_rencana')->nullable();
            $t->date('tanggal_realisasi')->nullable();
            $t->decimal('anggaran', 30, 12)->nullable();
            $t->enum('status', ['rencana', 'terlaksana', 'tidak_terlaksana', 'ditunda', 'batal'])->default('rencana');
            $t->integer('realisasi_peserta')->nullable();
            $t->text('justifikasi')->nullable();
            $t->uuid('kegiatan_asal_id')->nullable();
            $t->text('uraian_pelaksanaan')->nullable();
            $t->text('kendala')->nullable();
            $t->text('strategi_tindaklanjut')->nullable();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::table('kegiatan', fn (Blueprint $t) => $t->foreign('kegiatan_asal_id')->references('id')->on('kegiatan')->restrictOnDelete());
        Schema::create('klaim_kegiatan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('rencana_aksi_id')->constrained('rencana_aksi')->restrictOnDelete();
            $t->foreignUuid('kegiatan_id')->constrained('kegiatan')->restrictOnDelete();
            $t->foreignUuid('komponen_id')->nullable()->constrained('indikator_komponen')->restrictOnDelete();
            $t->enum('arah_dampak', ['menambah', 'mengurangi'])->default('menambah');
            $t->string('catatan')->nullable();
            $t->enum('sumber_klaim', ['rencana_aksi', 'pengukuran']);
            $t->foreignUuid('pengukuran_id')->nullable()->constrained('pengukuran_kinerjas')->restrictOnDelete();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
        });
        DB::statement("CREATE UNIQUE INDEX klaim_kegiatan_unik ON klaim_kegiatan(rencana_aksi_id,kegiatan_id,COALESCE(komponen_id,'00000000-0000-0000-0000-000000000000'::uuid))");
        DB::statement("ALTER TABLE klaim_kegiatan ADD CONSTRAINT klaim_sumber_cocok CHECK ((sumber_klaim='rencana_aksi' AND pengukuran_id IS NULL) OR (sumber_klaim='pengukuran' AND pengukuran_id IS NOT NULL))");
        Schema::create('berkas', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('jenis_berkas_id')->nullable()->constrained('jenis_berkas')->restrictOnDelete();
            $t->string('berkasable_type');
            $t->uuid('berkasable_id');
            $t->index(['berkasable_type', 'berkasable_id']);
            $t->uuid('menggantikan_id')->nullable();
            $t->text('alasan_koreksi')->nullable();
            $t->enum('mode', ['file', 'tautan', 'teks']);
            $t->string('nama_asli')->nullable();
            $t->string('path')->nullable();
            $t->string('mime')->nullable();
            $t->bigInteger('ukuran_bytes')->nullable();
            $t->string('tautan', 2048)->nullable();
            $t->text('isi_teks')->nullable();
            $t->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
            $t->timestamp('dihapus_pada')->nullable();
            $t->foreignUuid('dihapus_oleh')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('berkas', fn (Blueprint $t) => $t->foreign('menggantikan_id')->references('id')->on('berkas')->restrictOnDelete());
        Schema::create('riwayat_pengukurans', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pengukuran_kinerja_id')->constrained('pengukuran_kinerjas')->restrictOnDelete();
            $t->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $t->string('status_dari');
            $t->string('status_ke');
            $t->text('catatan');
            $t->timestamps();
        });
        // Isi versi beku sejak submit; hanya pengesah/waktu boleh diisi sekali dan bersamaan.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_submission_version_mutation() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Versi pengajuan tidak dapat dihapus.' USING ERRCODE = '23514';
                END IF;
                IF (to_jsonb(NEW) - 'disahkan_by' - 'disahkan_at') IS DISTINCT FROM (to_jsonb(OLD) - 'disahkan_by' - 'disahkan_at')
                    OR OLD.disahkan_by IS NOT NULL OR OLD.disahkan_at IS NOT NULL
                    OR NEW.disahkan_by IS NULL OR NEW.disahkan_at IS NULL THEN
                    RAISE EXCEPTION 'Versi pengajuan beku; pengesahan hanya sekali.' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
        foreach (['rencana_aksi_versi', 'pengukuran_versi'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_metadata_sah CHECK ((disahkan_by IS NULL) = (disahkan_at IS NULL))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_nomor_positif CHECK (nomor > 0)");
            DB::statement("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION reject_submission_version_mutation()");
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_referenced_schedule_snapshot() RETURNS trigger AS $$
            DECLARE target_id uuid; next_id uuid;
            BEGIN
                IF TG_TABLE_NAME = 'jadwal_snapshot' THEN
                    target_id := OLD.id; next_id := OLD.id;
                ELSIF TG_OP = 'INSERT' THEN
                    target_id := NEW.jadwal_snapshot_id; next_id := NEW.jadwal_snapshot_id;
                ELSIF TG_OP = 'DELETE' THEN
                    target_id := OLD.jadwal_snapshot_id; next_id := OLD.jadwal_snapshot_id;
                ELSE
                    target_id := OLD.jadwal_snapshot_id; next_id := NEW.jadwal_snapshot_id;
                END IF;
                IF EXISTS(SELECT 1 FROM rencana_aksi WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM pengukuran_kinerjas WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM rencana_aksi_versi WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM pengukuran_versi WHERE jadwal_snapshot_id IN (target_id,next_id)) THEN
                    RAISE EXCEPTION 'Snapshot jadwal yang dirujuk bersifat beku.' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER jadwal_snapshot_referenced BEFORE UPDATE OR DELETE ON jadwal_snapshot FOR EACH ROW EXECUTE FUNCTION guard_referenced_schedule_snapshot();
            CREATE TRIGGER jadwal_snapshot_komponen_referenced BEFORE INSERT OR UPDATE OR DELETE ON jadwal_snapshot_komponen FOR EACH ROW EXECUTE FUNCTION guard_referenced_schedule_snapshot();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS jadwal_snapshot_referenced ON jadwal_snapshot');
        DB::statement('DROP TRIGGER IF EXISTS jadwal_snapshot_komponen_referenced ON jadwal_snapshot_komponen');
        foreach (['riwayat_pengukurans', 'berkas', 'klaim_kegiatan', 'kegiatan', 'pengukuran_versi', 'pengukuran_komponen', 'pengukuran_kinerjas'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP TRIGGER IF EXISTS rencana_aksi_versi_immutable ON rencana_aksi_versi');
        DB::statement('DROP FUNCTION IF EXISTS reject_submission_version_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS guard_referenced_schedule_snapshot()');
    }
};
