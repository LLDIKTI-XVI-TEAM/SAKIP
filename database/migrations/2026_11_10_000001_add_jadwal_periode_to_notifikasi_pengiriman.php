<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan tujuan outbox `jadwal_periode` untuk pengingat EWS (ISS-12.04 fase 2).
 *
 * Nilai `objek_tipe` dibatasi CHECK constraint PostgreSQL, bukan tipe enum native,
 * sehingga perubahan dilakukan dengan pola DROP/ADD seperti migrasi audit sebelumnya.
 * `down()` menolak rollback selama masih ada baris `jadwal_periode` agar data
 * pengiriman tidak hilang diam-diam.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'notifikasi_pengiriman_objek_tipe_check';

    public function up(): void
    {
        $this->batasi(['rencana_aksi', 'pengukuran', 'jadwal_periode']);
    }

    public function down(): void
    {
        if (DB::table('notifikasi_pengiriman')->where('objek_tipe', 'jadwal_periode')->exists()) {
            throw new RuntimeException('Rollback ditolak: baris outbox jadwal_periode harus dipindahkan atau diarsipkan lebih dahulu.');
        }

        $this->batasi(['rencana_aksi', 'pengukuran']);
    }

    /** @param  list<string>  $nilai */
    private function batasi(array $nilai): void
    {
        $daftar = implode(', ', array_map(fn (string $v): string => "'$v'", $nilai));
        DB::statement('ALTER TABLE notifikasi_pengiriman DROP CONSTRAINT '.self::CONSTRAINT);
        DB::statement('ALTER TABLE notifikasi_pengiriman ADD CONSTRAINT '.self::CONSTRAINT." CHECK (objek_tipe IN ($daftar))");
    }
};
