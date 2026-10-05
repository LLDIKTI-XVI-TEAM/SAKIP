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
     * Backfill jepit draf lama (Review7 U2 F3).
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
     * backup (draf dibuat setelah cutover D7, atau tabel backup hilang),
     * peta bernilai NULL, atau snapshot rujukan sudah tak ada. NULL
     * tersebut dibaca fail-closed sebagai "telusuri seluruh versi sejak
     * awal" (`RekonsiliasiTargetDraf::nomorJepit` → 0) bila snapshot ada,
     * dan diabaikan bila konteks memang tanpa snapshot.
     *
     * Interaksi trigger immutable U1
     * (`2026_10_05_130000_snapshot_immutable_sejak_terbit_rencana_aksi_u1`):
     * backfill ini `UPDATE rencana_aksi`, sedangkan penjaga
     * `guard_referenced_schedule_snapshot()` terpasang pada
     * `jadwal_snapshot` dan `jadwal_snapshot_komponen` (lihat
     * `2026_09_18_030003`), bukan pada `rencana_aksi` — sehingga pengisian
     * jepit tak memicu penolakan 23514.
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
            <<<'SQL'
            UPDATE rencana_aksi AS ra
            SET snapshot_draf_id = backup.jadwal_snapshot_id
            FROM "%s" AS backup
            INNER JOIN jadwal_snapshot AS js ON js.id = backup.jadwal_snapshot_id
            WHERE backup.rencana_aksi_id = ra.id
              AND ra.snapshot_draf_id IS NULL
              AND backup.jadwal_snapshot_id IS NOT NULL
            SQL,
            self::BACKUP_TABLE
        ));
    }

    /**
     * Kembalikan hasil backfill tanpa merusak jepit baru.
     *
     * Hanya baris yang masih memegang nilai backfill persis
     * (`snapshot_draf_id = backup.jadwal_snapshot_id`) yang di-NULL-kan
     * kembali. Baris yang sudah dimajukan `SimpanTargetPeriode` ke snapshot
     * koreksi lebih baru (nilai != peta backup) dipertahankan — down()
     * tidak mengarang atau menghapus jepit sah pasca-backfill. Tanpa tabel
     * backup atau kolom jepit, down() adalah no-op yang aman.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('rencana_aksi', 'snapshot_draf_id')) {
            return;
        }

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        DB::statement(sprintf(
            <<<'SQL'
            UPDATE rencana_aksi AS ra
            SET snapshot_draf_id = NULL
            FROM "%s" AS backup
            WHERE backup.rencana_aksi_id = ra.id
              AND ra.snapshot_draf_id = backup.jadwal_snapshot_id
            SQL,
            self::BACKUP_TABLE
        ));
    }
};
