<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisBerkasTest extends TestCase
{
    use RefreshDatabase;

    protected User $perencanaan;

    protected User $admin;

    protected User $pegawai;

    protected IndikatorKinerja $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->perencanaan = User::where('email', 'perencanaan@lldikti16.kemdikbud.go.id')->first();
        $this->admin = User::where('email', 'admin@lldikti16.kemdikbud.go.id')->first();
        $this->pegawai = User::where('email', 'pic.kelembagaan@lldikti16.kemdikbud.go.id')->first();
        $this->indikator = IndikatorKinerja::first();
    }

    /**
     * TEST-1: Create persyaratan valid tersimpan.
     */
    public function test_perencanaan_can_create_valid_jenis_berkas(): void
    {
        $payload = [
            'nama' => 'Laporan Capaian Triwulan',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'wajib' => true,
            'keterangan' => 'Dokumen resmi ditandatangani pimpinan unit',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'semua_mode_wajib' => false,
            'urutan' => 1,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 5120,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertRedirect('/jenis-berkas');

        $this->assertDatabaseHas('jenis_berkas', [
            'nama' => 'Laporan Capaian Triwulan',
            'tahap' => 'pengukuran',
            'wajib' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tindakan' => 'jenis_berkas.buat',
            'objek_tipe' => 'jenis_berkas',
        ]);
    }

    /**
     * TEST-2: Tanpa mode diizinkan ditolak.
     */
    public function test_submit_without_allowed_mode_is_rejected(): void
    {
        $payload = [
            'nama' => 'Persyaratan Tanpa Mode',
            'tahap' => 'pengukuran',
            'izinkan_file' => false,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertSessionHasErrors('modes');
    }

    /**
     * TEST-3: semua_mode_wajib tersimpan sesuai semantik.
     */
    public function test_semua_mode_wajib_semantics(): void
    {
        $payload = [
            'nama' => 'Bukti Dukung Lengkap',
            'tahap' => 'kegiatan',
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
            'semua_mode_wajib' => true,
            'wajib' => true,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertRedirect('/jenis-berkas');

        $this->assertDatabaseHas('jenis_berkas', [
            'nama' => 'Bukti Dukung Lengkap',
            'semua_mode_wajib' => true,
        ]);
    }

    /**
     * TEST-4: Perubahan substantif wajib menyertakan alasan dan mencatat audit before/after.
     */
    public function test_update_and_delete_require_audit_reason_and_records_audit_trail(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Awal',
            'tahap' => 'pengukuran',
            'wajib' => false,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        // Update tanpa alasan ditolak
        $failUpdate = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Diperbarui',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'alasan' => '',
        ]);
        $failUpdate->assertSessionHasErrors('alasan');

        // Update dengan alasan berhasil dan tercatat di audit_logs
        $successUpdate = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Diperbarui',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => false,
            'alasan' => 'Penyesuaian kebutuhan bukti mode tautan',
        ]);
        $successUpdate->assertRedirect('/jenis-berkas');

        $this->assertDatabaseHas('audit_logs', [
            'tindakan' => 'jenis_berkas.ubah',
            'objek_id' => $jb->id,
            'alasan' => 'Penyesuaian kebutuhan bukti mode tautan',
        ]);

        // Delete dengan alasan berhasil dan tercatat di audit_logs
        $deleteResponse = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Penghapusan katalog persyaratan yang sudah tidak relevan',
        ]);
        $deleteResponse->assertRedirect('/jenis-berkas');

        $this->assertDatabaseHas('audit_logs', [
            'tindakan' => 'jenis_berkas.hapus',
            'objek_id' => $jb->id,
            'alasan' => 'Penghapusan katalog persyaratan yang sudah tidak relevan',
        ]);
    }

    /**
     * TEST-5: Otorisasi fail closed: Admin dan Pegawai ditolak (403).
     */
    public function test_unauthorized_users_cannot_mutate_jenis_berkas(): void
    {
        $payload = [
            'nama' => 'Mencoba Menyusup',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
        ];

        $responseAdmin = $this->actingAs($this->admin)->post('/jenis-berkas', $payload);
        $responseAdmin->assertStatus(403);

        $responsePegawai = $this->actingAs($this->pegawai)->post('/jenis-berkas', $payload);
        $responsePegawai->assertStatus(403);
    }
}
