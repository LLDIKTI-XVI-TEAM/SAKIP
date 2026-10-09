<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bekukan snapshot sejak terbit (Review7 U1 F2: immutable-sejak-terbit).
     *
     * Keputusan: immutable-sejak-terbit, BUKAN pin-on-read. Alasan: pin-on-read
     * memajukan jepit tanpa membersihkan sehingga bacaan kedua membangkitkan
     * nilai basi (jepit==terbaru → jejak hilang → 100 tampil lagi) dan
     * simpanan parsial berikutnya ikut membangkitkan periode tak terkirim;
     * membersihkan saat baca mengubah GET menjadi destruktif tanpa audit.
     * Immutable menutup jendela mutabel v2 (ditampilkan sebelum save pertama)
     * tanpa tulis-di-jalur-baca, tanpa audit, tanpa ubah versi; token ID+versi
     * selalu mewakili konteks beku karena baris snapshot/komponen tak dapat
     * dimutasi in-place sejak INSERT — koreksi sah tetap via sisipan berversi
     * (INSERT snapshot baru + komponennya, pola fixture/produksi dua-langkah
     * tetap hijau). Sisipan komponen via INSERT langsung ke snapshot terbit
     * tetap terbuka (diterima sadar-risiko; hanya menambah baris kosong di UI,
     * bukan mengubah skor/target beku — koreksi resmi tetap wajib berversi).
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_referenced_schedule_snapshot() RETURNS trigger AS $$
            DECLARE target_id uuid; next_id uuid;
            BEGIN
                -- F2 (Review7 U1): snapshot beku sejak terbit — UPDATE/DELETE
                -- pada baris snapshot selalu ditolak (INSERT versi baru tetap
                -- terbuka). Tanpa syarat rujukan sehingga v2 yang ditampilkan
                -- namun belum dijepit pun tak dapat dimutasi.
                IF TG_TABLE_NAME = 'jadwal_snapshot' THEN
                    IF TG_OP = 'INSERT' THEN
                        RETURN NEW;
                    END IF;
                    RAISE EXCEPTION 'Snapshot jadwal bersifat beku sejak terbit.' USING ERRCODE = '23514';
                END IF;

                -- Komponen beku: UPDATE/DELETE selalu ditolak (INSERT awal
                -- populasi + sisipan langsung tetap terbuka, lihat docblock).
                IF TG_OP = 'UPDATE' OR TG_OP = 'DELETE' THEN
                    IF TG_OP = 'DELETE' THEN
                        target_id := OLD.jadwal_snapshot_id; next_id := OLD.jadwal_snapshot_id;
                    ELSE
                        target_id := OLD.jadwal_snapshot_id; next_id := NEW.jadwal_snapshot_id;
                    END IF;
                    RAISE EXCEPTION 'Komponen snapshot bersifat beku sejak terbit.' USING ERRCODE = '23514';
                END IF;

                -- INSERT komponen: pertahankan guard rujukan (jepit draf +
                -- pengukuran/versi) agar sisipan ke snapshot yang sudah
                -- dipakai tetap ditolak; populasi awal (tak dirujuk) lolos.
                IF TG_OP = 'INSERT' THEN
                    target_id := NEW.jadwal_snapshot_id; next_id := NEW.jadwal_snapshot_id;
                ELSE
                    target_id := OLD.jadwal_snapshot_id; next_id := NEW.jadwal_snapshot_id;
                END IF;
                IF EXISTS(SELECT 1 FROM pengukuran_kinerjas WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM rencana_aksi_versi WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM pengukuran_versi WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM rencana_aksi WHERE snapshot_draf_id IN (target_id,next_id)) THEN
                    RAISE EXCEPTION 'Snapshot jadwal yang dirujuk bersifat beku.' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }

    /**
     * Kembalikan penjaga ke varian jepit-draf (pin-based) Review6 T2.
     *
     * Teks disalin persis dari up() migrasi
     * `2026_10_05_120000_rekonsiliasi_snapshot_draf_rencana_aksi_f2_f3`.
     */
    public function down(): void
    {
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
                    OR EXISTS(SELECT 1 FROM pengukuran_versi WHERE jadwal_snapshot_id IN (target_id,next_id))
                    OR EXISTS(SELECT 1 FROM rencana_aksi WHERE snapshot_draf_id IN (target_id,next_id)) THEN
                    RAISE EXCEPTION 'Snapshot jadwal yang dirujuk bersifat beku.' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }
};
