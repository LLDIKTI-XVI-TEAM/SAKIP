<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invariant database: snapshot difinalkan saat jadwalnya terbit.
 *
 * Advisory lock pada migrasi backfill hanya melindungi aktivasi yang sudah
 * in-flight. Pada rolling deploy, worker versi lama masih dapat menerbitkan
 * snapshot `komposisi_final = false` setelah migrasi selesai; pemindaian satu
 * kali tidak akan melihatnya lagi dan replay aktivasi menjadi no-op sehingga
 * komposisinya tetap dapat disisipi komponen.
 *
 * Karena trigger hidup di database, aktivasi dari versi kode apa pun —
 * termasuk worker lama — ikut membekukan snapshot yang diterbitkan pada
 * transisi status ke `aktif` (aktivasi pertama maupun `jadwal:buka_kembali`).
 * Aplikasi tetap memfinalkan secara eksplisit agar audit mencatat status final;
 * trigger ini adalah lapisan yang tidak bergantung pada kode aplikasi.
 *
 * `down()` melepas trigger + fungsinya. Snapshot yang sudah terlanjur final
 * tidak dikembalikan ke `false` karena transisi itu membuka kembali celah yang
 * sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION finalisasi_snapshot_saat_jadwal_aktif() RETURNS trigger AS $$
            BEGIN
                -- Hanya kolom flag yang berubah, sehingga guard snapshot (Review9 W1)
                -- tetap meloloskan transisi false -> true di dalam transaksi ini.
                UPDATE jadwal_snapshot
                   SET komposisi_final = TRUE
                 WHERE jadwal_id = NEW.id
                   AND komposisi_final = FALSE;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS finalisasi_snapshot_saat_jadwal_aktif ON jadwal_tahunan');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER finalisasi_snapshot_saat_jadwal_aktif
            AFTER UPDATE ON jadwal_tahunan
            FOR EACH ROW
            WHEN (OLD.status IS DISTINCT FROM NEW.status AND NEW.status = 'aktif')
            EXECUTE FUNCTION finalisasi_snapshot_saat_jadwal_aktif();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS finalisasi_snapshot_saat_jadwal_aktif ON jadwal_tahunan');
        DB::unprepared('DROP FUNCTION IF EXISTS finalisasi_snapshot_saat_jadwal_aktif()');
    }
};
