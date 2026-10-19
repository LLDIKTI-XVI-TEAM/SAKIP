<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\Renstra;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrasiTargetPkDanLampiranTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI_NOMOR_PK = '2026_10_19_000002_opsionalkan_nomor_pk_renstra_pk';

    private const MIGRASI_KETERANGAN = '2026_10_19_000003_tambah_keterangan_ke_berkas';

    private string $userId;

    private string $renstraId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = User::factory()->create(['status' => 'aktif'])->id;
        $this->renstraId = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->userId,
        ])->id;
    }

    public function test_pk_tanpa_nomor_tetap_unik_per_renstra_tahun(): void
    {
        $this->sisipkanPk(null);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->sisipkanPk(null);
    }

    public function test_rollback_nomor_pk_ditolak_bila_ada_pk_tanpa_nomor(): void
    {
        $this->sisipkanPk(null);

        $this->expectException(QueryException::class);
        $this->migrasi(self::MIGRASI_NOMOR_PK)->down();
    }

    public function test_rollback_lalu_migrasi_ulang_nomor_pk_saat_semua_pk_bernomor(): void
    {
        $this->sisipkanPk('PK-2026-01');
        $migrasi = $this->migrasi(self::MIGRASI_NOMOR_PK);

        $migrasi->down();
        $this->assertSame('NO', $this->nullable('renstra_pk', 'nomor_pk'));

        $migrasi->up();
        $this->assertSame('YES', $this->nullable('renstra_pk', 'nomor_pk'));
        $this->assertSame('PK-2026-01', DB::table('renstra_pk')->value('nomor_pk'));
    }

    public function test_rollback_keterangan_ditolak_bila_ada_lampiran_berketerangan(): void
    {
        $this->sisipkanBerkas($this->sisipkanPk('PK-2026-01'), 'PK perubahan anggaran');

        try {
            $this->migrasi(self::MIGRASI_KETERANGAN)->down();
            $this->fail('Rollback seharusnya ditolak selama masih ada keterangan lampiran.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback ditolak', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('berkas', 'keterangan'));
        $this->assertSame('PK perubahan anggaran', DB::table('berkas')->value('keterangan'));
    }

    public function test_rollback_lalu_migrasi_ulang_keterangan_saat_belum_ada_isi(): void
    {
        $this->sisipkanBerkas($this->sisipkanPk('PK-2026-01'), null);
        $migrasi = $this->migrasi(self::MIGRASI_KETERANGAN);

        $migrasi->down();
        $this->assertFalse(Schema::hasColumn('berkas', 'keterangan'));

        $migrasi->up();
        $this->assertTrue(Schema::hasColumn('berkas', 'keterangan'));
        $this->assertSame(1, DB::table('berkas')->count());
    }

    private function sisipkanBerkas(string $pkId, ?string $keterangan): void
    {
        DB::table('berkas')->insert([
            'id' => (string) Str::uuid(),
            'berkasable_type' => 'renstra_pk',
            'berkasable_id' => $pkId,
            'mode' => 'tautan',
            'tautan' => 'https://contoh.go.id/pk',
            'uploaded_by' => $this->userId,
            'keterangan' => $keterangan,
            'created_at' => now(),
        ]);
    }

    private function sisipkanPk(?string $nomor): string
    {
        $id = (string) Str::uuid();
        DB::table('renstra_pk')->insert([
            'id' => $id,
            'renstra_id' => $this->renstraId,
            'tahun' => 2026,
            'nomor_pk' => $nomor,
            'tanggal_pk' => '2026-04-21',
            'created_by' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function migrasi(string $nama): Migration
    {
        return require database_path("migrations/{$nama}.php");
    }

    private function nullable(string $tabel, string $kolom): string
    {
        return DB::selectOne(
            'SELECT is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
            [$tabel, $kolom],
        )->is_nullable;
    }
}
