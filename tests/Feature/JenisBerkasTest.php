<?php

namespace Tests\Feature;

use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
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
        $this->seed(AccessCatalogSeeder::class);

        $this->perencanaan = $this->userWithRole('perencanaan');
        $this->admin = $this->userWithRole('admin');
        $this->pegawai = $this->userWithRole('pegawai');

        $unit = Unit::create(['nama' => 'Unit Pengujian', 'created_by' => $this->perencanaan->id]);
        $renstra = Renstra::create([
            'kode' => 'R-UJI',
            'nama' => 'Renstra Uji',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'S-UJI',
            'deskripsi' => 'Sasaran Uji',
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI',
            'nama' => 'Indikator Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'is_aktif' => true,
        ]);
    }

    protected function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('kode', $kode)->firstOrFail();
        if (RolePermissionPresets::hasDefinedPreset($kode)) {
            foreach (Permission::whereIn('kode', RolePermissionPresets::forRole($kode))->get() as $permission) {
                $role->permissions()->syncWithoutDetaching([
                    $permission->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
                ]);
            }
        }
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    /**
     * TEST-1: Create persyaratan valid tersimpan beserta dasar_izin audit.
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

        $audit = AuditLog::where('tindakan', 'jenis_berkas.buat')->latest('waktu')->first();
        $this->assertNotNull($audit);
        $this->assertNotNull($audit->dasar_izin);
        $this->assertSame('allow', $audit->dasar_izin['reason']);
        $this->assertSame('jenis_berkas:create', $audit->dasar_izin['permission']);
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
     * TEST-4: Perubahan substantif wajib menyertakan alasan dan mencatat dasar_izin audit before/after.
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

        // Update dengan alasan berhasil dan tercatat di audit_log beserta dasar_izin
        $successUpdate = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Diperbarui',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => false,
            'alasan' => 'Penyesuaian kebutuhan bukti mode tautan',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);
        $successUpdate->assertRedirect('/jenis-berkas');

        $auditUpdate = AuditLog::where('tindakan', 'jenis_berkas.ubah')->where('objek_id', $jb->id)->first();
        $this->assertNotNull($auditUpdate);
        $this->assertSame('Penyesuaian kebutuhan bukti mode tautan', $auditUpdate->alasan);
        $this->assertNotNull($auditUpdate->dasar_izin);
        $this->assertSame('allow', $auditUpdate->dasar_izin['reason']);
        $this->assertSame('jenis_berkas:update', $auditUpdate->dasar_izin['permission']);

        // Delete dengan alasan berhasil dan tercatat di audit_log beserta dasar_izin
        $deleteResponse = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Penghapusan katalog persyaratan yang sudah tidak relevan',
        ]);
        $deleteResponse->assertRedirect('/jenis-berkas');

        $auditDelete = AuditLog::where('tindakan', 'jenis_berkas.hapus')->where('objek_id', $jb->id)->first();
        $this->assertNotNull($auditDelete);
        $this->assertSame('Penghapusan katalog persyaratan yang sudah tidak relevan', $auditDelete->alasan);
        $this->assertNotNull($auditDelete->dasar_izin);
        $this->assertSame('allow', $auditDelete->dasar_izin['reason']);
        $this->assertSame('jenis_berkas:delete', $auditDelete->dasar_izin['permission']);
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

    /**
     * TEST-6: Mutasi dan audit dibungkus dalam transaksi atomik (rollback jika audit gagal).
     */
    public function test_mutation_and_audit_are_atomic_in_single_transaction(): void
    {
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('Audit system down'));

        $payload = [
            'nama' => 'Laporan Harusnya Rollback',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
        ];

        try {
            $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        } catch (RuntimeException $e) {
            $this->assertSame('Audit system down', $e->getMessage());
        }

        // Jenis berkas tidak boleh tersimpan tanpa audit
        $this->assertDatabaseMissing('jenis_berkas', [
            'nama' => 'Laporan Harusnya Rollback',
        ]);
    }

    /**
     * TEST-7: Pembaruan dengan versi usang (concurrency conflict) ditolak.
     */
    public function test_update_with_stale_version_is_rejected(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Target',
            'tahap' => 'pengukuran',
            'wajib' => false,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $staleTimestamp = now()->subMinutes(10)->toISOString();

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Update Konflik',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Mencoba update dengan versi usang',
            'expected_updated_at' => $staleTimestamp,
        ]);

        $response->assertSessionHasErrors('konflik');
        $this->assertSame('Laporan Target', $jb->fresh()->nama);
    }

    /**
     * TEST-8: Index menampilkan indikator nonaktif yang sedang direferensikan.
     */
    public function test_index_includes_referenced_inactive_indicators(): void
    {
        $unit = Unit::first();
        $sasaran = SasaranStrategis::first();
        $inactiveIndikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-NONAKTIF',
            'nama' => 'Indikator Lama Dinonaktifkan',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'is_aktif' => false,
        ]);

        JenisBerkas::create([
            'nama' => 'Syarat Khusus Indikator Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveIndikator->id,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->get('/jenis-berkas');
        $response->assertOk();

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('JenisBerkas/Index')
            ->has('indikators', fn (AssertableInertia $prop) => $prop
                ->where('0.kode', 'I-NONAKTIF')
                ->where('0.is_aktif', false)
                ->etc()
            )
        );
    }
}
