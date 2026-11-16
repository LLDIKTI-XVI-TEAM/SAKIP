<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai beku lokal untuk migrasi ini (tanpa import model domain).
     *
     * Nama tabel disalin apa adanya dari migrasi
     * `2026_10_04_043803_align_rencana_aksi_header_d1_d5_d7` agar hasil
     * fresh migrate di masa depan identik walau kode domain berubah.
     */
    private const BACKUP_TABLE = '_backup_rencana_aksi_jadwal_snapshot_20261004';

    /**
     * Penanda baris yang benar-benar diisi up().
     *
     * Tanpa penanda, down() lama (`snapshot_draf_id = backup...`) tak dapat
     * membedakan pin backfill dari jepit sah pra-existing yang kebetulan
     * bernilai sama (up() hanya menyentuh `IS NULL`, down() lama menyentuh
     * semua yang sama) sehingga rollback menghancurkan jepit sah. Tabel sisi
     * deterministik ini mencatat ID ter-update agar down() jujur.
     */
    private const MARKER_TABLE = '_backfill_snapshot_draf_rencana_aksi_u2_ids';

    /**
     * Backfill jepit draf lama.
     *
     * Migrasi penambah `rencana_aksi.snapshot_draf_id`
     * (`2026_10_05_120000`) membiarkan NULL tanpa backfill sehingga draf
     * lama tak terlindungi sampai save berikutnya. Migrasi ini mengisi
     * ulang dari tabel backup pemetaan snapshot lama
     * (`_backup_rencana_aksi_jadwal_snapshot_20261004`, dibuat SEBELUM
     * kolom `jadwal_snapshot_id` di-drop) secara deterministik:
     *
     * - Hanya baris dengan `snapshot_draf_id IS NULL` yang disentuh
     *   (draf baru yang sudah dijepit `EnsureDraft`/`Simpan` tak ditimpa).
     * - Hanya bila ada pasangan baris backup (`backup.rencana_aksi_id`)
     *   dengan `jadwal_snapshot_id IS NOT NULL`.
     * - Hanya bila snapshot rujukan masih ada di `jadwal_snapshot`
     *   (`INNER JOIN`, bukan FK — kolom jepit memang non-FK audit-safe).
     *
     * NULL tersisa hanya bila memang tak ada peta/snapshot: tanpa baris
     * backup (draf dibuat setelah kolom `jadwal_snapshot_id` di-drop, atau tabel backup hilang),
     * peta bernilai NULL, atau snapshot rujukan sudah tak ada. NULL
     * tersebut dibaca fail-closed sebagai "telusuri seluruh versi sejak
     * awal" (`RekonsiliasiTargetDraf::nomorJepit` → 0) bila snapshot ada,
     * dan diabaikan bila konteks memang tanpa snapshot.
     *
     * Interaksi trigger immutable-sejak-terbit
     * (`2026_10_05_130000_snapshot_immutable_sejak_terbit_rencana_aksi_u1`):
     * backfill ini `UPDATE rencana_aksi`, sedangkan penjaga
     * `guard_referenced_schedule_snapshot()` terpasang pada
     * `jadwal_snapshot` dan `jadwal_snapshot_komponen` (lihat
     * `2026_09_18_030003`), bukan pada `rencana_aksi` — sehingga pengisian
     * jepit tak memicu penolakan 23514.
     *
     * up() mencatat ID yang benar-benar diisi ke tabel sisi
     * `MARKER_TABLE` dalam statement atomik yang SAMA dengan `UPDATE`
     * (CTE data-modifying `WITH updated AS (UPDATE ...
     * RETURNING) INSERT INTO penanda SELECT FROM updated`). Pada versi sebelumnya,
     * penanda dipilih via `SELECT` di statement terpisah SEBELUM `UPDATE`
     * sehingga write aplikasi yang commit di antaranya (pin sah, bahkan yang
     * kebetulan sama nilainya dengan peta backup) ikut tertanda tanpa
     * dibackfill, lalu down() me-NULL-kan pin sah tersebut. Dengan satu
     * statement, PostgreSQL memakai satu snapshot: hanya baris yang
     * benar-benar ter-UPDATE yang tercatat — write aplikasi di antara tak
     * mungkin menyelinap. down() hanya me-NULL-kan baris bertanda yang
     * masih memegang nilai backfill persis — jepit pra-existing yang tak
     * terbedakan nilainya tetap utuh karena tak pernah masuk penanda.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('rencana_aksi', 'snapshot_draf_id')) {
            return;
        }

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        DB::statement(sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (rencana_aksi_id uuid PRIMARY KEY)',
            self::MARKER_TABLE
        ));

        // SATU statement atomik — UPDATE + pencatatan penanda
        // via CTE data-modifying (`WITH updated AS (UPDATE ... RETURNING)
        // INSERT INTO penanda SELECT FROM updated`). Satu snapshot PostgreSQL
        // untuk baca+tulis: write aplikasi yang commit tepat di tengah
        // jendela tak dapat ikut tertanda tanpa dibackfill (versi sebelumnya:
        // `INSERT INTO penanda SELECT ...` dan `UPDATE` adalah dua statement
        // terpisah dengan dua snapshot). `ON CONFLICT DO NOTHING` menjaga
        // idempotensi pemanggilan ulang.
        DB::statement(sprintf(
            <<<'SQL'
            WITH updated AS (
                UPDATE rencana_aksi AS ra
                SET snapshot_draf_id = backup.jadwal_snapshot_id
                FROM "%1$s" AS backup
                INNER JOIN jadwal_snapshot AS js ON js.id = backup.jadwal_snapshot_id
                WHERE backup.rencana_aksi_id = ra.id
                  AND ra.snapshot_draf_id IS NULL
                  AND backup.jadwal_snapshot_id IS NOT NULL
                RETURNING ra.id
            )
            INSERT INTO "%2$s" (rencana_aksi_id)
            SELECT id FROM updated
            ON CONFLICT (rencana_aksi_id) DO NOTHING
            SQL,
            self::BACKUP_TABLE,
            self::MARKER_TABLE
        ));
    }

    /**
     * Kembalikan hasil backfill tanpa merusak jepit sah pra-existing.
     *
     * Hanya baris bertanda di `MARKER_TABLE` yang masih memegang nilai
     * backfill persis (`snapshot_draf_id = backup.jadwal_snapshot_id`)
     * yang di-NULL-kan kembali. Baris yang sudah dimajukan
     * `SimpanTargetPeriode` ke snapshot koreksi lebih baru (nilai != peta
     * backup) dipertahankan, dan jepit sah
     * pra-existing yang kebetulan sama nilainya dengan peta backup tak
     * tersentuh karena tak pernah masuk penanda (up() hanya menandai
     * kandidat `IS NULL`). Tanpa tabel penanda (mis. up() versi lama yang
     * tak menandai), down() adalah no-op non-destruktif: tak mengarang
     * atau menghapus jepit yang tak dapat dibedakan. Tanpa tabel backup
     * atau kolom jepit, down() membersihkan penanda bila ada lalu no-op.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('rencana_aksi', 'snapshot_draf_id')) {
            DB::statement(sprintf('DROP TABLE IF EXISTS "%s"', self::MARKER_TABLE));

            return;
        }

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            DB::statement(sprintf('DROP TABLE IF EXISTS "%s"', self::MARKER_TABLE));

            return;
        }

        if (! Schema::hasTable(self::MARKER_TABLE)) {
            return;
        }

        DB::statement(sprintf(
            <<<'SQL'
            UPDATE rencana_aksi AS ra
            SET snapshot_draf_id = NULL
            FROM "%1$s" AS backup, "%2$s" AS penanda
            WHERE penanda.rencana_aksi_id = ra.id
              AND backup.rencana_aksi_id = ra.id
              AND ra.snapshot_draf_id = backup.jadwal_snapshot_id
            SQL,
            self::BACKUP_TABLE,
            self::MARKER_TABLE
        ));

        DB::statement(sprintf('DROP TABLE IF EXISTS "%s"', self::MARKER_TABLE));
    }
};
