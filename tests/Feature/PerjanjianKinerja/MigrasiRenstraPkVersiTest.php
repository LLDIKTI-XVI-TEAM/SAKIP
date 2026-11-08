<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\Renstra;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrasiRenstraPkVersiTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI_VERSI = '2026_11_07_000001_create_renstra_pk_versi_tables';

    private const MIGRASI_ARSIP = '2026_10_29_000001_lindungi_arsip_dokumen_resmi';

    public function test_rollback_mengembalikan_fungsi_arsip_persis_lalu_migrasi_ulang_berhasil(): void
    {
        $this->migrasi(self::MIGRASI_VERSI)->down();
        $setelahRollback = $this->definisiFungsiArsip();

        $this->assertFalse(Schema::hasTable('renstra_pk_versi'));
        $this->assertFalse(Schema::hasTable('renstra_pk_versi_target'));
        $this->assertSame(0, DB::table('pg_trigger')->whereIn('tgname', ['renstra_pk_versi_tetap', 'renstra_pk_versi_target_tetap'])->count());

        $arsip = $this->migrasi(self::MIGRASI_ARSIP);
        $arsip->down();
        $arsip->up();
        $this->assertSame($this->definisiFungsiArsip(), $setelahRollback);

        $this->migrasi(self::MIGRASI_VERSI)->up();
        $this->assertTrue(Schema::hasTable('renstra_pk_versi'));
        $this->assertStringContainsString("'renstra_pk_versi'", $this->definisiFungsiArsip());
    }

    public function test_rollback_ditolak_bila_versi_pk_sudah_tercatat(): void
    {
        $userId = User::factory()->create(['status' => 'aktif'])->id;
        $renstraId = Renstra::create([
            'kode' => 'REN-2025-2029', 'nama' => 'Renstra fixture', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'is_aktif' => true, 'created_by' => $userId,
        ])->id;
        $pkId = (string) Str::uuid();
        DB::table('renstra_pk')->insert([
            'id' => $pkId, 'renstra_id' => $renstraId, 'tahun' => 2026, 'tanggal_pk' => '2026-01-10',
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('renstra_pk_versi')->insert([
            'id' => (string) Str::uuid(), 'renstra_pk_id' => $pkId, 'nomor_versi' => 1, 'berkas_awal_ids' => '[]', 'created_at' => now(),
        ]);

        try {
            $this->migrasi(self::MIGRASI_VERSI)->down();
            $this->fail('Rollback seharusnya ditolak selama versi PK sudah tercatat.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback ditolak', $e->getMessage());
        }

        $this->assertSame(1, DB::table('renstra_pk_versi')->count());
    }

    private function definisiFungsiArsip(): string
    {
        return DB::selectOne("SELECT pg_get_functiondef('lindungi_arsip_dokumen_resmi'::regproc) AS def")->def;
    }

    private function migrasi(string $nama): Migration
    {
        return require database_path("migrations/{$nama}.php");
    }
}
