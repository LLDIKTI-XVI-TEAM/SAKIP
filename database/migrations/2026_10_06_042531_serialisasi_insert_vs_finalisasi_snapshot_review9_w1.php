<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Serialisasi INSERT vs finalisasi komposisi snapshot (Review9 W1 F1).
     *
     * Masalah: guard INSERT lama membaca induk via SELECT biasa tanpa kunci
     * sehingga finalisasi konkuren (`komposisi_final=false→true`) vs INSERT
     * komponen dapat lolos bersama: B membaca false basi (READ COMMITTED
     * melihat snapshot lama sebelum A commit) lalu commit setelah A.
     *
     * Perbaikan: kunci baris induk dengan mode berkonflik di kedua sisi.
     * Guard INSERT memakai `SELECT ... FOR UPDATE` pada induk sehingga
     * menunggu/melihat versi terbaru bila finalisasi konkuren memegang lock
     * baris; sisi finalisasi (UPDATE) sudah memegang lock baris via UPDATE
     * itu sendiri — dipertegas dengan `PERFORM ... FOR UPDATE` agar urutan
     * kunci kedua arah sama (induk dahulu) dan tak ada bacaan basi. Siapa pun
     * yang lebih dulu memegang kunci menang; yang menunggu melihat hasil
     * commit terbaru: INSERT setelah final → 23514; finalisasi setelah INSERT
     * ter-commit → mencakup komponen tersebut (serial, bukan hilang).
     * Koreksi berversi (snapshot baru + komponen selagi belum final) tetap
     * terbuka; UPDATE/DELETE komponen dan guard rujukan U1 tak berubah.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_referenced_schedule_snapshot() RETURNS trigger AS $$
            DECLARE target_id uuid; next_id uuid; parent_final boolean;
            BEGIN
                -- Snapshot: INSERT selalu terbuka (versi baru); UPDATE hanya
                -- finalisasi/no-op; DELETE selalu ditolak (U1 utuh).
                IF TG_TABLE_NAME = 'jadwal_snapshot' THEN
                    IF TG_OP = 'INSERT' THEN
                        RETURN NEW;
                    ELSIF TG_OP = 'UPDATE' THEN
                        -- Review9 W1 F1: kunci induk mode berkonflik. UPDATE
                        -- sudah memegang lock baris; PERFORM mempertegas agar
                        -- berkonflik dengan guard INSERT (FOR UPDATE) di bawah.
                        PERFORM 1 FROM jadwal_snapshot AS ks WHERE ks.id = NEW.id FOR UPDATE;
                        IF (OLD.id, OLD.jadwal_id, OLD.indikator_id, OLD.nomor_versi, OLD.menggantikan_id, OLD.alasan_koreksi, OLD.rujukan_koreksi, OLD.periode_mulai_id, OLD.unit_id, OLD.nama, OLD.definisi, OLD.satuan, OLD.presisi, OLD.desimal_tampilan, OLD.arah, OLD.tipe_perhitungan, OLD.target, OLD.baseline)
                            IS NOT DISTINCT FROM
                           (NEW.id, NEW.jadwal_id, NEW.indikator_id, NEW.nomor_versi, NEW.menggantikan_id, NEW.alasan_koreksi, NEW.rujukan_koreksi, NEW.periode_mulai_id, NEW.unit_id, NEW.nama, NEW.definisi, NEW.satuan, NEW.presisi, NEW.desimal_tampilan, NEW.arah, NEW.tipe_perhitungan, NEW.target, NEW.baseline) THEN
                            IF OLD.komposisi_final IS NOT DISTINCT FROM NEW.komposisi_final THEN
                                RETURN NEW;
                            ELSIF OLD.komposisi_final = FALSE AND NEW.komposisi_final = TRUE THEN
                                RETURN NEW;
                            END IF;
                        END IF;
                        RAISE EXCEPTION 'Snapshot jadwal bersifat beku sejak terbit.' USING ERRCODE = '23514';
                    ELSE
                        RAISE EXCEPTION 'Snapshot jadwal bersifat beku sejak terbit.' USING ERRCODE = '23514';
                    END IF;
                END IF;

                -- Komponen beku: UPDATE/DELETE selalu ditolak (U1 utuh).
                IF TG_OP = 'UPDATE' OR TG_OP = 'DELETE' THEN
                    IF TG_OP = 'DELETE' THEN
                        target_id := OLD.jadwal_snapshot_id; next_id := OLD.jadwal_snapshot_id;
                    ELSE
                        target_id := OLD.jadwal_snapshot_id; next_id := NEW.jadwal_snapshot_id;
                    END IF;
                    RAISE EXCEPTION 'Komponen snapshot bersifat beku sejak terbit.' USING ERRCODE = '23514';
                END IF;

                -- INSERT komponen (F1 + Review9 W1): kunci induk FOR UPDATE
                -- agar serial vs finalisasi konkuren; tanpa ini SELECT biasa
                -- membaca false basi lalu lolos. Populasi awal (induk false +
                -- tak dirujuk) tetap lolos.
                IF TG_OP = 'INSERT' THEN
                    SELECT ks.komposisi_final INTO parent_final FROM jadwal_snapshot AS ks WHERE ks.id = NEW.jadwal_snapshot_id FOR UPDATE;
                    IF FOUND AND parent_final THEN
                        RAISE EXCEPTION 'Komposisi snapshot telah difinalisasi; koreksi wajib via versi baru.' USING ERRCODE = '23514';
                    END IF;
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
     * Kembalikan penjaga ke varian Review8 V2 (tanpa kunci FOR UPDATE).
     *
     * Aman non-destruktif: hanya definisi fungsi yang dipulihkan (tanpa
     * perubahan data/kolom); teks disalin persis dari up() migrasi
     * `2026_10_06_032010_bekukan_komposisi_snapshot_terbit_rencana_aksi_v2`.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_referenced_schedule_snapshot() RETURNS trigger AS $$
            DECLARE target_id uuid; next_id uuid; parent_final boolean;
            BEGIN
                -- Snapshot: INSERT selalu terbuka (versi baru); UPDATE hanya
                -- finalisasi/no-op; DELETE selalu ditolak (U1 utuh).
                IF TG_TABLE_NAME = 'jadwal_snapshot' THEN
                    IF TG_OP = 'INSERT' THEN
                        RETURN NEW;
                    ELSIF TG_OP = 'UPDATE' THEN
                        IF (OLD.id, OLD.jadwal_id, OLD.indikator_id, OLD.nomor_versi, OLD.menggantikan_id, OLD.alasan_koreksi, OLD.rujukan_koreksi, OLD.periode_mulai_id, OLD.unit_id, OLD.nama, OLD.definisi, OLD.satuan, OLD.presisi, OLD.desimal_tampilan, OLD.arah, OLD.tipe_perhitungan, OLD.target, OLD.baseline)
                            IS NOT DISTINCT FROM
                           (NEW.id, NEW.jadwal_id, NEW.indikator_id, NEW.nomor_versi, NEW.menggantikan_id, NEW.alasan_koreksi, NEW.rujukan_koreksi, NEW.periode_mulai_id, NEW.unit_id, NEW.nama, NEW.definisi, NEW.satuan, NEW.presisi, NEW.desimal_tampilan, NEW.arah, NEW.tipe_perhitungan, NEW.target, NEW.baseline) THEN
                            IF OLD.komposisi_final IS NOT DISTINCT FROM NEW.komposisi_final THEN
                                RETURN NEW;
                            ELSIF OLD.komposisi_final = FALSE AND NEW.komposisi_final = TRUE THEN
                                RETURN NEW;
                            END IF;
                        END IF;
                        RAISE EXCEPTION 'Snapshot jadwal bersifat beku sejak terbit.' USING ERRCODE = '23514';
                    ELSE
                        RAISE EXCEPTION 'Snapshot jadwal bersifat beku sejak terbit.' USING ERRCODE = '23514';
                    END IF;
                END IF;

                -- Komponen beku: UPDATE/DELETE selalu ditolak (U1 utuh).
                IF TG_OP = 'UPDATE' OR TG_OP = 'DELETE' THEN
                    IF TG_OP = 'DELETE' THEN
                        target_id := OLD.jadwal_snapshot_id; next_id := OLD.jadwal_snapshot_id;
                    ELSE
                        target_id := OLD.jadwal_snapshot_id; next_id := NEW.jadwal_snapshot_id;
                    END IF;
                    RAISE EXCEPTION 'Komponen snapshot bersifat beku sejak terbit.' USING ERRCODE = '23514';
                END IF;

                -- INSERT komponen (F1): tolak bila induk telah difinalisasi
                -- (publik) walau belum dirujuk pin/pengukuran/versi; populasi
                -- awal (induk false + tak dirujuk) tetap lolos.
                IF TG_OP = 'INSERT' THEN
                    SELECT ks.komposisi_final INTO parent_final FROM jadwal_snapshot AS ks WHERE ks.id = NEW.jadwal_snapshot_id;
                    IF FOUND AND parent_final THEN
                        RAISE EXCEPTION 'Komposisi snapshot telah difinalisasi; koreksi wajib via versi baru.' USING ERRCODE = '23514';
                    END IF;
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
};
