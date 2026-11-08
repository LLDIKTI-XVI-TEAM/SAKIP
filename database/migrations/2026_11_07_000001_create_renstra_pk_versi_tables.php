<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renstra_pk_versi', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('renstra_pk_id')->constrained('renstra_pk')->restrictOnDelete();
            $t->integer('nomor_versi');
            $t->string('nomor_surat')->nullable();
            $t->date('tanggal_surat')->nullable();
            $t->foreignUuid('periode_mulai_id')->nullable()->constrained('periode')->restrictOnDelete();
            $t->text('alasan')->nullable();
            $t->foreignUuid('disetujui_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('disetujui_pada')->nullable();
            $t->jsonb('berkas_awal_ids')->nullable();
            $t->timestamp('created_at');
            $t->unique(['renstra_pk_id', 'nomor_versi']);
        });
        // Versi 1 = PK awal: tanpa surat atau penyetuju, membawa daftar id lampiran induk yang dibekukan.
        // Versi revisi wajib surat Eselon I, alasan, dan penyetuju; periode mulai boleh kosong untuk revisi dokumen saja.
        DB::statement(<<<'SQL'
            ALTER TABLE renstra_pk_versi ADD CONSTRAINT renstra_pk_versi_isi_check CHECK (
                (nomor_versi = 1 AND nomor_surat IS NULL AND tanggal_surat IS NULL AND alasan IS NULL
                    AND disetujui_oleh IS NULL AND disetujui_pada IS NULL AND periode_mulai_id IS NULL
                    AND berkas_awal_ids IS NOT NULL AND jsonb_typeof(berkas_awal_ids) = 'array')
                OR (nomor_versi > 1 AND nomor_surat IS NOT NULL AND tanggal_surat IS NOT NULL AND alasan IS NOT NULL
                    AND disetujui_oleh IS NOT NULL AND disetujui_pada IS NOT NULL AND berkas_awal_ids IS NULL)
            )
            SQL);

        Schema::create('renstra_pk_versi_target', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('renstra_pk_versi_id')->constrained('renstra_pk_versi')->restrictOnDelete();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->decimal('nilai', 30, 12);
            $t->unique(['renstra_pk_versi_id', 'indikator_id']);
        });
        DB::statement("ALTER TABLE renstra_pk_versi_target ADD CONSTRAINT renstra_pk_versi_target_nilai_check CHECK (nilai >= 0 AND nilai <> 'NaN'::numeric)");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION tolak_ubah_versi_pk() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Versi PK tidak dapat diubah atau dihapus';
            END;
            $$;
            CREATE TRIGGER renstra_pk_versi_tetap BEFORE UPDATE OR DELETE ON renstra_pk_versi
            FOR EACH ROW EXECUTE FUNCTION tolak_ubah_versi_pk();
            CREATE TRIGGER renstra_pk_versi_target_tetap BEFORE UPDATE OR DELETE ON renstra_pk_versi_target
            FOR EACH ROW EXECUTE FUNCTION tolak_ubah_versi_pk();
            SQL);

        // Lampiran versi PK ikut dilindungi seperti arsip dokumen resmi jadwal: tidak dapat dihapus atau diganti.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION lindungi_arsip_dokumen_resmi() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode', 'renstra_pk_versi') THEN
                        RAISE EXCEPTION 'Arsip dokumen resmi tidak dapat dihapus';
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode', 'renstra_pk_versi') OR NEW.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode', 'renstra_pk_versi') THEN
                    RAISE EXCEPTION 'Arsip dokumen resmi tidak dapat diubah';
                END IF;
                RETURN NEW;
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        if (DB::table('renstra_pk_versi')->exists()) {
            throw new RuntimeException('Rollback ditolak: versi PK sudah tercatat.');
        }

        // Definisi fungsi dikembalikan persis seperti migrasi arsip dokumen resmi.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION lindungi_arsip_dokumen_resmi() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode') THEN
                        RAISE EXCEPTION 'Arsip dokumen resmi tidak dapat dihapus';
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode') OR NEW.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode') THEN
                    RAISE EXCEPTION 'Arsip dokumen resmi tidak dapat diubah';
                END IF;
                RETURN NEW;
            END;
            $$;
            SQL);
        Schema::drop('renstra_pk_versi_target');
        Schema::drop('renstra_pk_versi');
        DB::statement('DROP FUNCTION tolak_ubah_versi_pk()');
    }
};
