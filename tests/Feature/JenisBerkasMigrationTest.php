<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JenisBerkasMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jenis_berkas_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('jenis_berkas'));
        $this->assertTrue(Schema::hasColumns('jenis_berkas', [
            'id',
            'nama',
            'tahap',
            'indikator_id',
            'wajib',
            'keterangan',
            'izinkan_file',
            'izinkan_tautan',
            'izinkan_teks',
            'semua_mode_wajib',
            'urutan',
            'format_diizinkan',
            'ukuran_maks_kb',
            'aktif',
            'created_by',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_audit_log_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('audit_log'));
        $this->assertTrue(Schema::hasColumns('audit_log', [
            'id',
            'actor_id',
            'actor_type',
            'sumber',
            'waktu',
            'tindakan',
            'objek_tipe',
            'objek_id',
            'nilai_lama',
            'nilai_baru',
            'alasan',
            'dasar_izin',
        ]));
    }
}
