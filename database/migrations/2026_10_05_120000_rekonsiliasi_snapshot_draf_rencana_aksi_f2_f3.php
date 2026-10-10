<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jepit snapshot draf untuk rekonsiliasi transisi dan pembekuan snapshot draf.
     *
     * Kolom `rencana_aksi.snapshot_draf_id` adalah rujukan UUID nullable
     * TANPA foreign key: revisi sempit yang audit-safe. Kolom ini
     * tidak dipakai otorisasi (izin tetap berbasis `unit_id` + resolver),
     * tidak diekspos ke klien, dan tidak divalidasi dari request — hanya
     * ditulis server (`EnsureDraftRencanaAksi` saat buat,
     * `SimpanTargetPeriode` tiap simpan) sebagai "terakhir direkonsiliasi
     * di bawah snapshot X". Kegunaannya ganda: (a) aplikasi menelusuri
     * versi antara jepit→terbaru untuk mendeteksi baris basi transisi
     * (v1→v2 tanpa simpan→v3 tak boleh bangkitkan nilai lama);
     * (b) trigger `guard_referenced_schedule_snapshot()` menolak mutasi
     * langsung snapshot yang masih dijepit draf.
     *
     * Nilai NULL berarti "belum dijepit": dibaca fail-closed sebagai
     * "telusuri seluruh versi sejak awal" bila snapshot ada, dan diabaikan
     * bila konteks memang tanpa snapshot (jadwal belum pernah aktif).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('rencana_aksi', 'snapshot_draf_id')) {
            Schema::table('rencana_aksi', function (Blueprint $table) {
                $table->uuid('snapshot_draf_id')->nullable()
                    ->comment('Rujukan non-FK audit-safe ke snapshot konteks draf (revisi D7 sempit); bukan otorisasi.');
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
     * Kembalikan penjaga ke varian tanpa jepit draf lalu lepas kolom.
     *
     * Aman tanpa tabel backup (pola migrasi lifecycle sebelumnya yang disederhanakan):
     * kolom nullable dan NULL bermakna fail-closed ("telusuri seluruh
     * versi"), sehingga kehilangannya tidak merusak data pengguna —
     * migrasi ulang menghasilkan jepit baru lewat jalur tulis biasa.
     * Teks fungsi di bawah disalin persis dari up() migrasi
     * `2026_10_04_043803_align_rencana_aksi_header_d1_d5_d7`.
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
                    OR EXISTS(SELECT 1 FROM pengukuran_versi WHERE jadwal_snapshot_id IN (target_id,next_id)) THEN
                    RAISE EXCEPTION 'Snapshot jadwal yang dirujuk bersifat beku.' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        if (Schema::hasColumn('rencana_aksi', 'snapshot_draf_id')) {
            Schema::table('rencana_aksi', function (Blueprint $table) {
                $table->dropColumn('snapshot_draf_id');
            });
        }
    }
};
