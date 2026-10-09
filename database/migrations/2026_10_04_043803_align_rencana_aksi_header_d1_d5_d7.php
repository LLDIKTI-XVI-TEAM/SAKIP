<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai beku lokal untuk migrasi ini (tanpa import model domain).
     *
     * Daftar ini disalin apa adanya dari kontrak yang berlaku saat migrasi
     * ditulis, agar hasil fresh migrate di masa depan identik walau kode
     * domain berubah.
     */
    private const BACKUP_TABLE = '_backup_rencana_aksi_jadwal_snapshot_20261004';

    private const TARGET_MANUAL_INDEX = 'ra_target_manual_unik';

    private const TARGET_KOMPONEN_INDEX = 'ra_target_komponen_unik';

    private const MAX_SAMPLE_IDS = 20;

    /**
     * Selaraskan header rencana aksi: tambah alasan deviasi vs target PK,
     * lepas rujukan snapshot jadwal dari header, pastikan keunikan target
     * ramah-NULL.
     *
     * Alasan deviasi disimpan di header (bukan dipakai ulang dari alasan
     * revisi) agar makna keduanya tidak tercampur pada validasi pengajuan
     * dan tampilan. Target manual menyimpan satu baris bernilai NULL per
     * periode tanpa komponen semu; dua baris manual pada kombinasi yang sama
     * tetap ditolak basis data. Header tidak menyimpan rujukan snapshot
     * jadwal; tabel snapshot tetap ada sebagai sumber himpunan komponen
     * efektif. Skor turunan tidak disimpan (murni hasil tampilan).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('rencana_aksi', 'alasan_deviasi_pk')) {
            Schema::table('rencana_aksi', function (Blueprint $table) {
                $table->text('alasan_deviasi_pk')->nullable();
            });
        }

        if (Schema::hasColumn('rencana_aksi', 'jadwal_snapshot_id')) {
            DB::statement(sprintf('DROP TABLE IF EXISTS "%s"', self::BACKUP_TABLE));
            DB::statement(sprintf(
                'CREATE TABLE "%s" AS SELECT id AS rencana_aksi_id, jadwal_snapshot_id FROM rencana_aksi',
                self::BACKUP_TABLE
            ));

            Schema::table('rencana_aksi', function (Blueprint $table) {
                $table->dropForeign(['jadwal_snapshot_id']);
            });
            Schema::table('rencana_aksi', function (Blueprint $table) {
                $table->dropColumn('jadwal_snapshot_id');
            });
        }

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON rencana_aksi_target(rencana_aksi_id, periode_id) WHERE komponen_id IS NULL',
            self::TARGET_MANUAL_INDEX
        ));
        DB::statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON rencana_aksi_target(rencana_aksi_id, periode_id, komponen_id) WHERE komponen_id IS NOT NULL',
            self::TARGET_KOMPONEN_INDEX
        ));

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
                IF EXISTS(SELECT 1 FROM pengukuran_kinerjas WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM rencana_aksi_versi WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM pengukuran_versi WHERE jadwal_snapshot_id IN (target_id,next_id)) THEN
                    RAISE EXCEPTION 'Snapshot jadwal yang dirujuk bersifat beku.' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }

    /**
     * Kembalikan kolom rujukan snapshot persis definisi awal dari tabel
     * backup, lalu hapus kolom alasan deviasi.
     *
     * Baris yang dibuat setelah migrasi maju (tanpa pasangan backup)
     * membuat rollback THROW fail-closed — operator tentukan nilai yang
     * benar manual lalu jalankan ulang. Index target dibiarkan karena
     * dimiliki migrasi dasar.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('rencana_aksi', 'jadwal_snapshot_id')) {
            Schema::table('rencana_aksi', function (Blueprint $table) {
                $table->foreignUuid('jadwal_snapshot_id')->nullable()->constrained('jadwal_snapshot')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            throw new RuntimeException(sprintf(
                'Rollback gagal: tabel backup "%s" tidak ditemukan. Tabel ini dibuat oleh up() '
                .'SEBELUM kolom rujukan snapshot di-drop. Tanpa tabel backup, nilai awal tidak dapat '
                .'dikembalikan (down() tidak mengarang nilai). Pulihkan tabel backup '
                .'(kolom rencana_aksi_id + jadwal_snapshot_id) dari arsip database lalu jalankan ulang rollback.',
                self::BACKUP_TABLE
            ));
        }

        DB::statement(sprintf(
            <<<'SQL'
            UPDATE rencana_aksi AS ra
            SET jadwal_snapshot_id = backup.jadwal_snapshot_id
            FROM "%s" AS backup
            WHERE backup.rencana_aksi_id = ra.id
            SQL,
            self::BACKUP_TABLE
        ));

        $tanpaBackupCount = DB::table('rencana_aksi')->whereNull('jadwal_snapshot_id')->count();

        if ($tanpaBackupCount > 0) {
            $idList = DB::table('rencana_aksi')
                ->whereNull('jadwal_snapshot_id')
                ->limit(self::MAX_SAMPLE_IDS)
                ->pluck('id')
                ->map(fn ($id) => "'{$id}'")
                ->implode(', ');

            throw new RuntimeException(sprintf(
                'Rollback gagal: %d baris rencana aksi tanpa pasangan baris di tabel backup "%s" (contoh id: %s), '
                .'kemungkinan dibuat setelah migrasi maju. down() tidak mengarang nilai rujukan snapshot. '
                .'Tentukan nilai yang benar manual, mis. UPDATE rencana_aksi SET jadwal_snapshot_id = \'<uuid-snapshot>\' '
                .'WHERE id IN (...); lalu jalankan ulang rollback.',
                $tanpaBackupCount,
                self::BACKUP_TABLE,
                $idList
            ));
        }

        DB::statement(sprintf('DROP TABLE "%s"', self::BACKUP_TABLE));

        Schema::table('rencana_aksi', function (Blueprint $table) {
            $table->uuid('jadwal_snapshot_id')->nullable(false)->change();
        });

        if (Schema::hasColumn('rencana_aksi', 'alasan_deviasi_pk')) {
            Schema::table('rencana_aksi', function (Blueprint $table) {
                $table->dropColumn('alasan_deviasi_pk');
            });
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
            SQL);
    }
};
