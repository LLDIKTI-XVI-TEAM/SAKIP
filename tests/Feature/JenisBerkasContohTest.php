<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Persyaratan tahap perencanaan kegiatan dan contoh berkasnya (Q42 §42.3–§42.4,
 * Q43 §43.2 butir 2): contoh berkas bukan bukti, dapat ditambah, diganti, dan
 * dihapus beralasan oleh Perencanaan, serta diunduh lewat route berizin.
 */
class JenisBerkasContohTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(AccessCatalogSeeder::class);
        $this->perencanaan = $this->userWithRole('perencanaan');
        $this->pegawai = $this->userWithRole('pegawai');
    }

    public function test_perencanaan_menyimpan_persyaratan_tahap_perencanaan_kegiatan_dengan_penanda_beranggaran(): void
    {
        $payload = ['nama' => 'RAB', 'tahap' => 'kegiatan_perencanaan', 'wajib' => true, 'izinkan_file' => true,
            'format_diizinkan' => 'pdf,xlsx', 'hanya_kegiatan_beranggaran' => true];

        $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->perencanaan)->post('/jenis-berkas', ['nama' => 'Laporan', 'tahap' => 'pengukuran'] + $payload)->assertSessionHasNoErrors();

        $this->assertTrue(JenisBerkas::where('tahap', 'kegiatan_perencanaan')->sole()->hanya_kegiatan_beranggaran);
        // Penanda tidak bermakna di luar tahap perencanaan kegiatan.
        $this->assertFalse(JenisBerkas::where('tahap', 'pengukuran')->sole()->hanya_kegiatan_beranggaran);
    }

    public function test_contoh_memakai_default_konfigurasi_tanpa_mengikuti_batas_persyaratan(): void
    {
        config(['uploads.default_max_kb' => 200, 'uploads.allowed_extensions' => 'pdf']);
        $jenis = $this->persyaratan(['format_diizinkan' => 'xlsx', 'ukuran_maks_kb' => 1]);
        $url = "/jenis-berkas/{$jenis->id}/contoh";

        $this->actingAs($this->perencanaan)->post($url, ['file' => UploadedFile::fake()->create('contoh-besar.pdf', 201, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->post($url, ['file' => UploadedFile::fake()->create('contoh.xlsx', 100)])->assertSessionHasErrors('file');
        $this->assertSame(0, BuktiDukung::where('berkasable_type', 'jenis_berkas')->count());

        $this->post($url, ['file' => UploadedFile::fake()->create('contoh.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();
        $contoh = BuktiDukung::where('berkasable_type', 'jenis_berkas')->sole();
        $this->assertSame($jenis->id, $contoh->berkasable_id);
        $this->assertNull($contoh->jenis_berkas_id);
        $this->assertSame('contoh.pdf', $contoh->nama_asli);
        Storage::disk('local')->assertExists($contoh->path);
    }

    public function test_contoh_berkas_diunggah_diganti_dan_dihapus_beralasan_serta_diunduh_pemegang_izin_baca(): void
    {
        $jenis = $this->persyaratan();

        $this->actingAs($this->perencanaan)->post("/jenis-berkas/{$jenis->id}/contoh", ['file' => UploadedFile::fake()->create('contoh-rab.xlsx', 20)])
            ->assertSessionHasNoErrors();
        $pertama = BuktiDukung::where('berkasable_type', 'jenis_berkas')->sole();
        $this->assertNull($pertama->jenis_berkas_id);
        $this->actingAs($this->pegawai)->get("/jenis-berkas/{$jenis->id}/contoh/{$pertama->id}")->assertOk()->assertDownload('contoh-rab.xlsx');
        $this->actingAs($this->perencanaan)->get('/jenis-berkas')->assertInertia(fn ($page) => $page
            ->where('jenisBerkasList.0.contoh_berkas.0.id', $pertama->id)->where('jenisBerkasList.0.contoh_berkas.0.nama_asli', 'contoh-rab.xlsx'));

        $this->actingAs($this->perencanaan)->post("/jenis-berkas/{$jenis->id}/contoh", [
            'file' => UploadedFile::fake()->create('contoh-rab-2026.pdf', 20), 'menggantikan_id' => $pertama->id, 'alasan_koreksi' => 'Format terbaru',
        ])->assertSessionHasNoErrors();
        $pengganti = BuktiDukung::where('menggantikan_id', $pertama->id)->sole();
        $this->assertSame('Format terbaru', $pengganti->alasan_koreksi);
        $this->actingAs($this->pegawai)->get("/jenis-berkas/{$jenis->id}/contoh/{$pertama->id}")->assertNotFound();

        $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jenis->id}/contoh/{$pengganti->id}", ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->assertNull($pengganti->fresh()->dihapus_pada);
        $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jenis->id}/contoh/{$pengganti->id}", ['alasan' => 'Contoh sudah tidak berlaku'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($pengganti->fresh()->dihapus_pada);
        Storage::disk('local')->assertExists($pengganti->path);
        $audit = AuditLog::where('tindakan', 'berkas.hapus')->where('objek_id', $pengganti->id)->sole();
        $this->assertSame('Contoh sudah tidak berlaku', $audit->alasan);
        $this->assertSame('jenis_berkas:update', $audit->dasar_izin['permission']);
    }

    public function test_tanpa_izin_ubah_persyaratan_unggah_dan_hapus_contoh_ditolak_dan_diaudit(): void
    {
        $jenis = $this->persyaratan();
        $contoh = $this->contoh($jenis);

        $this->actingAs($this->pegawai)->post("/jenis-berkas/{$jenis->id}/contoh", ['file' => UploadedFile::fake()->create('contoh.pdf', 10)])->assertForbidden();
        $this->actingAs($this->pegawai)->delete("/jenis-berkas/{$jenis->id}/contoh/{$contoh->id}", ['alasan' => 'Tidak berhak menghapus'])->assertForbidden();

        $this->assertSame(1, BuktiDukung::where('berkasable_type', 'jenis_berkas')->count());
        $this->assertNull($contoh->fresh()->dihapus_pada);
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.unggah_ditolak')->where('objek_id', $jenis->id)->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.hapus_ditolak')->where('objek_id', $jenis->id)->count());
    }

    public function test_contoh_berkas_hanya_untuk_tahap_perencanaan_kegiatan_dan_ikut_terhapus_bersama_persyaratan(): void
    {
        $pengukuran = $this->persyaratan(['tahap' => 'pengukuran']);
        $this->actingAs($this->perencanaan)->post("/jenis-berkas/{$pengukuran->id}/contoh", ['file' => UploadedFile::fake()->create('contoh.pdf', 10)])
            ->assertSessionHasErrors('file');

        $jenis = $this->persyaratan();
        $contoh = $this->contoh($jenis);
        $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jenis->id}", [
            'alasan' => 'Persyaratan diganti', 'expected_updated_at' => $jenis->fresh()->updated_at->toISOString(),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($contoh->fresh()->dihapus_pada);
        $this->assertSame([$contoh->id], AuditLog::where('tindakan', 'jenis_berkas.hapus')->sole()->nilai_lama['contoh_berkas_ids']);
    }

    /** @param array<string, mixed> $atribut */
    private function persyaratan(array $atribut = []): JenisBerkas
    {
        return JenisBerkas::create([
            'nama' => 'RAB', 'tahap' => 'kegiatan_perencanaan', 'wajib' => true, 'izinkan_file' => true,
            'hanya_kegiatan_beranggaran' => true, 'aktif' => true, 'created_by' => $this->perencanaan->id, ...$atribut,
        ]);
    }

    private function contoh(JenisBerkas $jenis): BuktiDukung
    {
        return BuktiDukung::create(['berkasable_type' => 'jenis_berkas', 'berkasable_id' => $jenis->id, 'mode' => 'file', 'nama_asli' => 'contoh.pdf',
            'path' => 'berkas/jenis_berkas/contoh.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 10, 'uploaded_by' => $this->perencanaan->id, 'created_at' => now()]);
    }

    private function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $kode)->firstOrFail()->id, [
            'id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now(),
        ]);

        return $user;
    }
}
