<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Versi PK, target versi, dan lampiran versi dijaga append-only di database,
 * sehingga histori PK awal dan PK akhir tidak dapat diubah lewat jalur mana pun.
 */
class RevisiPkImutabilitasTest extends TestCase
{
    use RefreshDatabase;

    private string $userId;

    private string $pkId;

    private string $indikatorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = User::factory()->create(['status' => 'aktif'])->id;
        $renstraId = Renstra::create([
            'kode' => 'REN-2025-2029', 'nama' => 'Renstra fixture', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'is_aktif' => true, 'created_by' => $this->userId,
        ])->id;
        $this->pkId = (string) Str::uuid();
        DB::table('renstra_pk')->insert([
            'id' => $this->pkId, 'renstra_id' => $renstraId, 'tahun' => 2026, 'nomor_pk' => 'PK-2026-01',
            'tanggal_pk' => '2026-01-10', 'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstraId, 'kode' => 'S1', 'deskripsi' => 'Sasaran fixture']);
        $unit = Unit::create(['nama' => 'Unit fixture', 'status' => 'aktif', 'created_by' => $this->userId]);
        $this->indikatorId = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id, 'kode' => 'IKU-1', 'nama' => 'Indikator fixture',
            'satuan' => 'persen', 'presisi' => 2, 'tahun_mulai_berlaku' => 2026, 'created_by' => $this->userId, 'created_by_role' => 'perencanaan',
        ])->id;
    }

    public function test_versi_dan_target_tidak_dapat_diubah_atau_dihapus(): void
    {
        $versi1 = $this->sisipkanVersi(1);
        $versi2 = $this->sisipkanVersi(2);
        $target = $this->sisipkanTarget($versi2, '85');

        $this->assertDitolak(fn () => DB::table('renstra_pk_versi')->where('id', $versi2)->update(['alasan' => 'diubah']));
        $this->assertDitolak(fn () => DB::table('renstra_pk_versi')->where('id', $versi1)->update(['berkas_awal_ids' => '[]']));
        $this->assertDitolak(fn () => DB::table('renstra_pk_versi')->where('id', $versi1)->delete());
        $this->assertDitolak(fn () => DB::table('renstra_pk_versi_target')->where('id', $target)->update(['nilai' => '90']));
        $this->assertDitolak(fn () => DB::table('renstra_pk_versi_target')->where('id', $target)->delete());

        $this->assertSame('85.000000000000', DB::table('renstra_pk_versi_target')->where('id', $target)->value('nilai'));
        $this->assertSame(2, DB::table('renstra_pk_versi')->count());
    }

    public function test_lampiran_versi_tidak_dapat_diubah_dihapus_lunak_atau_dihapus(): void
    {
        $berkas = $this->sisipkanBerkas('renstra_pk_versi', $this->sisipkanVersi(2));

        $this->assertDitolak(fn () => DB::table('berkas')->where('id', $berkas)->update(['keterangan' => 'diganti']));
        $this->assertDitolak(fn () => DB::table('berkas')->where('id', $berkas)->update(['dihapus_pada' => now(), 'dihapus_oleh' => $this->userId]));
        $this->assertDitolak(fn () => DB::table('berkas')->where('id', $berkas)->delete());
        $this->assertNull(DB::table('berkas')->where('id', $berkas)->value('dihapus_pada'));
    }

    public function test_lampiran_induk_pk_tetap_dapat_dihapus_lunak(): void
    {
        $berkas = $this->sisipkanBerkas('renstra_pk', $this->pkId);

        DB::table('berkas')->where('id', $berkas)->update(['dihapus_pada' => now(), 'dihapus_oleh' => $this->userId]);

        $this->assertNotNull(DB::table('berkas')->where('id', $berkas)->value('dihapus_pada'));
    }

    public function test_check_versi_1_hanya_memuat_daftar_lampiran_awal(): void
    {
        $this->assertDitolak(fn () => $this->sisipkanVersi(1, ['berkas_awal_ids' => null]));
        $this->assertDitolak(fn () => $this->sisipkanVersi(1, ['berkas_awal_ids' => '{}']));
        $this->assertDitolak(fn () => $this->sisipkanVersi(1, ['nomor_surat' => 'B-1/2026']));
        $this->assertDitolak(fn () => $this->sisipkanVersi(1, ['alasan' => 'Revisi']));
        $this->assertDitolak(fn () => $this->sisipkanVersi(1, ['disetujui_oleh' => $this->userId, 'disetujui_pada' => now()]));
        $this->assertDitolak(fn () => $this->sisipkanVersi(0));

        $this->sisipkanVersi(1, ['berkas_awal_ids' => '[]']);
        $this->assertSame(1, DB::table('renstra_pk_versi')->count());
    }

    public function test_check_versi_revisi_wajib_surat_alasan_penyetuju_tanpa_lampiran_awal(): void
    {
        $this->assertDitolak(fn () => $this->sisipkanVersi(2, ['alasan' => null]));
        $this->assertDitolak(fn () => $this->sisipkanVersi(2, ['nomor_surat' => null]));
        $this->assertDitolak(fn () => $this->sisipkanVersi(2, ['tanggal_surat' => null]));
        $this->assertDitolak(fn () => $this->sisipkanVersi(2, ['disetujui_oleh' => null]));
        $this->assertDitolak(fn () => $this->sisipkanVersi(2, ['disetujui_pada' => null]));
        $this->assertDitolak(fn () => $this->sisipkanVersi(2, ['berkas_awal_ids' => '[]']));

        $this->sisipkanVersi(2);
        $this->assertSame(1, DB::table('renstra_pk_versi')->count());
    }

    public function test_nomor_versi_unik_per_pk_dan_nilai_target_wajib_valid(): void
    {
        $versi = $this->sisipkanVersi(2);
        $this->assertDitolak(fn () => $this->sisipkanVersi(2));

        $this->assertDitolak(fn () => $this->sisipkanTarget($versi, null));
        $this->assertDitolak(fn () => $this->sisipkanTarget($versi, '-1'));
        $this->assertDitolak(fn () => $this->sisipkanTarget($versi, 'NaN'));
        $this->sisipkanTarget($versi, '0');
        $this->assertDitolak(fn () => $this->sisipkanTarget($versi, '5'));
    }

    private function assertDitolak(callable $aksi): void
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Database seharusnya menolak perubahan ini.');
    }

    /** @param  array<string, mixed>  $timpa */
    private function sisipkanVersi(int $nomor, array $timpa = []): string
    {
        $id = (string) Str::uuid();
        $isi = $nomor === 1
            ? ['berkas_awal_ids' => '[]']
            : ['nomor_surat' => 'B-12/2026', 'tanggal_surat' => '2026-11-01', 'alasan' => 'Revisi PK',
                'disetujui_oleh' => $this->userId, 'disetujui_pada' => now(), 'berkas_awal_ids' => null];
        DB::table('renstra_pk_versi')->insert([
            'id' => $id, 'renstra_pk_id' => $this->pkId, 'nomor_versi' => $nomor, 'created_at' => now(), ...$isi, ...$timpa,
        ]);

        return $id;
    }

    private function sisipkanTarget(string $versiId, ?string $nilai): string
    {
        $id = (string) Str::uuid();
        DB::table('renstra_pk_versi_target')->insert([
            'id' => $id, 'renstra_pk_versi_id' => $versiId, 'indikator_id' => $this->indikatorId, 'nilai' => $nilai,
        ]);

        return $id;
    }

    private function sisipkanBerkas(string $tipe, string $indukId): string
    {
        $id = (string) Str::uuid();
        DB::table('berkas')->insert([
            'id' => $id, 'berkasable_type' => $tipe, 'berkasable_id' => $indukId, 'mode' => 'file', 'nama_asli' => 'pk.pdf',
            'path' => "berkas/{$tipe}/{$indukId}/pk.pdf", 'mime' => 'application/pdf', 'ukuran_bytes' => 10,
            'uploaded_by' => $this->userId, 'created_at' => now(),
        ]);

        return $id;
    }
}
