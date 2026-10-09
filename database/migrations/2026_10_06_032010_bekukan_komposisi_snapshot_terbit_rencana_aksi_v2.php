<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bekukan komposisi snapshot pasca-finalisasi (Review8 V2 F1).
     *
     * Keputusan: (a) finalisasi atomik snapshot+komponen sebelum publik lalu
     * tolak seluruh INSERT komponen pasca-publik, BUKAN (b) ubah identitas
     * versi tiap komposisi berubah. Alasan: selaras immutable-sejak-terbit U1
     * (Review7 U1 F2) — identitas versi (`jadwal_id,indikator_id,nomor_versi`)
     * tetap stabil sebagai token konkurensi baca-tulis (`expected_snapshot_id`
     * + `expected_snapshot_versi`); opsi (b) memaksa bump versi semu tiap ada
     * sisipan sehingga token basi + rekonsiliasi transisi (`RekonsiliasiTargetDraf`
     * jepit→terbaru) berisik tanpa peristiwa koreksi resmi. Opsi (a) menutup
     * celah F1 — INSERT komponen ke v2 yang tampil (terbaru) namun belum
     * dijepit pin lama tetap lolos guard rujukan U1 — dengan satu flag
     * pengunci `komposisi_final`: publikasi = INSERT snapshot (false) +
     * INSERT komponen + UPDATE finalisasi true dalam satu transaksi; pasca-
     * finalisasi seluruh INSERT komponen ditolak (23514) walau belum dirujuk,
     * sehingga koreksi sah wajib via sisipan berversi (snapshot baru +
     * komponennya, pola fixture/produksi dua-langkah tetap hijau selama induk
     * baru belum difinalisasi). UPDATE/DELETE komponen tetap selalu ditolak
     * (U1 utuh, berlaku pula saat draf agar jendela publikasi all-or-nothing
     * via rollback, bukan tambal in-place); satu-satunya UPDATE snapshot yang
     * diizinkan adalah penguncian (kolom non-flag identik + NEW true; no-op
     * flag-sama diizinkan agar save tanpa-dirty tak gagal).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('jadwal_snapshot', 'komposisi_final')) {
            Schema::table('jadwal_snapshot', function (Blueprint $table) {
                $table->boolean('komposisi_final')->default(false)
                    ->comment('Pengunci komposisi Review8 V2 F1: false = jendela publikasi atomik (INSERT komponen awal lolos bila tak dirujuk); true = publik/final (seluruh INSERT komponen ditolak, koreksi via versi baru).');
            });
        }

        // Pasang penjaga baru DULU sebelum backfill: guard lama U1 menolak
        // seluruh UPDATE snapshot sehingga backfill finalisasi tertolak;
        // guard baru mengizinkan false->true (kolom non-flag identik).
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

        DB::statement('UPDATE jadwal_snapshot SET komposisi_final = TRUE WHERE komposisi_final = FALSE');
    }

    /**
     * Kembalikan penjaga ke varian immutable-sejak-terbit U1 lalu lepas flag.
     *
     * Aman non-destruktif: hanya kolom pengunci boolean yang di-drop (tidak
     * ada data pengguna di dalamnya; fresh migrate ulang mem-finalisasi ulang
     * via up()); teks fungsi disalin persis dari up() migrasi
     * `2026_10_05_130000_snapshot_immutable_sejak_terbit_rencana_aksi_u1`.
     * Urutan: pulihkan fungsi dulu (tanpa rujukan kolom baru) baru drop kolom
     * agar tak ada jendela fungsi merujuk kolom hilang.
     */
    public function down(): void
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

        if (Schema::hasColumn('jadwal_snapshot', 'komposisi_final')) {
            Schema::table('jadwal_snapshot', function (Blueprint $table) {
                $table->dropColumn('komposisi_final');
            });
        }
    }
};
