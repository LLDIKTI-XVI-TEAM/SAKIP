<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\Berkas;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\RenstraPkVersi;
use App\Models\Role;
use App\Models\User;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** PK awal (lampiran induk) dan PK akhir (lampiran versi) diunduh lewat route PK dengan hak baca berkas yang sama. */
class RevisiPkUnduhTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    private User $pegawai;

    private Renstra $renstra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        Storage::fake('local');
        $this->perencanaan = $this->pengguna('perencanaan');
        $this->pegawai = $this->pengguna('pegawai');
        $this->renstra = Renstra::create(['kode' => 'REN-2025-2029', 'nama' => 'Renstra fixture', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'is_aktif' => true, 'created_by' => $this->perencanaan->id]);
    }

    public function test_pk_awal_dan_pk_akhir_dapat_diunduh_dengan_hak_baca_berkas(): void
    {
        $pk = $this->pk(2026);
        $awal = $this->unggah($pk, 'pk-awal.pdf');
        $versi = $this->versi($pk);
        $akhir = $this->unggah($versi, 'pk-akhir.pdf');

        $this->assertStringStartsWith("berkas/renstra_pk/{$pk->id}/", $awal->path);
        $this->assertStringStartsWith("berkas/renstra_pk_versi/{$versi->id}/", $akhir->path);
        $this->assertSame('renstra_pk_versi', $akhir->berkasable_type);

        $this->actingAs($this->perencanaan)->get("/perjanjian-kinerja/{$pk->id}/berkas/{$awal->id}/unduh")
            ->assertOk()->assertDownload('pk-awal.pdf');
        $this->actingAs($this->perencanaan)->get("/perjanjian-kinerja/{$pk->id}/berkas/{$akhir->id}/unduh")
            ->assertOk()->assertDownload('pk-akhir.pdf');

        $this->actingAs($this->pegawai)->get("/perjanjian-kinerja/{$pk->id}/berkas/{$akhir->id}/unduh")->assertForbidden();
    }

    public function test_lampiran_versi_milik_pk_lain_tidak_dapat_diunduh_lewat_pk_ini(): void
    {
        $pk = $this->pk(2026);
        $lain = $this->pk(2027);
        $berkasLain = $this->unggah($this->versi($lain), 'pk-lain.pdf');

        $this->actingAs($this->perencanaan)->get("/perjanjian-kinerja/{$pk->id}/berkas/{$berkasLain->id}/unduh")->assertNotFound();
        $this->actingAs($this->perencanaan)->get("/perjanjian-kinerja/{$lain->id}/berkas/{$berkasLain->id}/unduh")->assertOk();
    }

    private function pk(int $tahun): RenstraPk
    {
        return RenstraPk::create(['renstra_id' => $this->renstra->id, 'tahun' => $tahun, 'nomor_pk' => "PK-{$tahun}",
            'tanggal_pk' => "{$tahun}-01-15", 'created_by' => $this->perencanaan->id]);
    }

    private function versi(RenstraPk $pk): RenstraPkVersi
    {
        RenstraPkVersi::create(['renstra_pk_id' => $pk->id, 'nomor_versi' => 1, 'berkas_awal_ids' => []]);

        return RenstraPkVersi::create(['renstra_pk_id' => $pk->id, 'nomor_versi' => 2, 'nomor_surat' => 'B-12/2026', 'tanggal_surat' => '2026-11-01',
            'alasan' => 'Revisi PK', 'disetujui_oleh' => $this->perencanaan->id, 'disetujui_pada' => now()]);
    }

    private function unggah(RenstraPk|RenstraPkVersi $induk, string $nama): Berkas
    {
        $stored = [];
        app(PerjanjianKinerjaAttachmentService::class)->simpanLampiran($induk,
            [['mode' => 'file', 'file' => UploadedFile::fake()->create($nama, 20, 'application/pdf')]], $this->perencanaan, $stored);

        return Berkas::where('berkasable_id', $induk->id)->where('nama_asli', $nama)->sole();
    }

    private function pengguna(string $peran): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $peran)->firstOrFail()->id,
            ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }
}
