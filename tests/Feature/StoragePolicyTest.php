<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoragePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $perencanaan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->perencanaan = User::factory()->create(['status' => 'aktif']);
        $this->perencanaan->roles()->attach(Role::where('kode', 'perencanaan')->sole()->id, [
            'id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->perencanaan->id, 'created_at' => now(),
        ]);
    }

    public function test_dynamic_storage_limits_enforced_on_regulasi_uploads(): void
    {
        Storage::fake('local');

        // Batasi ukuran maks menjadi 200 KB dan hanya perbolehkan txt
        config(['uploads.default_max_kb' => 200]);
        config(['uploads.allowed_extensions' => 'txt']);

        // File berukuran 300 KB harus ditolak karena melebihi 200 KB
        $responseOversize = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '999/OVERSIZE/2026',
            'tahun' => 2026,
            'tentang' => 'Uji File Terlalu Besar',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'file',
                    'file' => UploadedFile::fake()->create('dokumen.txt', 300, 'text/plain'),
                ],
            ],
        ]);
        $responseOversize->assertSessionHasErrors(['lampiran.0.file']);

        // File ekstensi selain txt (misal pdf) harus ditolak
        $responseWrongFormat = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '1000/WRONG-FORMAT/2026',
            'tahun' => 2026,
            'tentang' => 'Uji Format Tidak Sesuai Kebijakan Dinamis',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'file',
                    'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);
        $responseWrongFormat->assertSessionHasErrors(['lampiran.0.file']);

        // File txt 100 KB harus lolos
        $responseSuccess = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '1001/SUCCESS-DYNAMIC/2026',
            'tahun' => 2026,
            'tentang' => 'Uji Berkas Sah Sesuai Batas Dinamis',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'file',
                    'file' => UploadedFile::fake()->create('dokumen.txt', 100, 'text/plain'),
                ],
            ],
        ]);
        $responseSuccess->assertSessionHasNoErrors();
        $this->assertDatabaseHas('regulasi', ['nomor' => '1001/SUCCESS-DYNAMIC/2026']);

        // Kembalikan daftar format konfigurasi ke default: .doc harus ditolak (karena default hanya pdf,docx,xlsx,jpg,jpeg,png)
        config(['uploads.allowed_extensions' => 'pdf,docx,xlsx,jpg,jpeg,png']);

        $responseDocFallback = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '1002/DOC-REJECT/2026',
            'tahun' => 2026,
            'tentang' => 'Uji Berkas DOC Ditolak pada Fallback Default',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'file',
                    'file' => UploadedFile::fake()->create('dokumen.doc', 100, 'application/msword'),
                ],
            ],
        ]);
        $responseDocFallback->assertSessionHasErrors(['lampiran.0.file']);
    }
}
