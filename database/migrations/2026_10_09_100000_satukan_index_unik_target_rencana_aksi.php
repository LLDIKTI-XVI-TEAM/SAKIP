<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Satu index unik `(rencana_aksi_id, periode_id, komponen_id) NULLS NOT
 * DISTINCT` menggantikan dua index unik parsial (baris manual dengan
 * `komponen_id` NULL dan baris berkomponen). Semantik keunikan setara:
 * NULL dianggap sama sehingga baris manual tetap satu per periode.
 *
 * Index penuh ini melayani filter `rencana_aksi_id` lewat kolom terdepannya,
 * sehingga `ra_target_rencana_aksi_idx` tidak diperlukan lagi, dan menjadi
 * arbiter `ON CONFLICT` untuk upsert matriks target. `NULLS NOT DISTINCT`
 * membutuhkan PostgreSQL 15 atau lebih baru; Blueprint belum mendukungnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index baru dibuat sebelum index lama dilepas agar keunikan tidak
        // pernah kosong di tengah migrasi.
        DB::statement('CREATE UNIQUE INDEX ra_target_unik ON rencana_aksi_target (rencana_aksi_id, periode_id, komponen_id) NULLS NOT DISTINCT');
        DB::statement('DROP INDEX ra_target_manual_unik');
        DB::statement('DROP INDEX ra_target_komponen_unik');
        DB::statement('DROP INDEX ra_target_rencana_aksi_idx');
    }

    public function down(): void
    {
        DB::statement('CREATE UNIQUE INDEX ra_target_manual_unik ON rencana_aksi_target (rencana_aksi_id, periode_id) WHERE komponen_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX ra_target_komponen_unik ON rencana_aksi_target (rencana_aksi_id, periode_id, komponen_id) WHERE komponen_id IS NOT NULL');
        DB::statement('CREATE INDEX ra_target_rencana_aksi_idx ON rencana_aksi_target (rencana_aksi_id)');
        DB::statement('DROP INDEX ra_target_unik');
    }
};
