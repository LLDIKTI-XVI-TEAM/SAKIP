<?php

namespace Tests\Feature;

use App\Actions\Audit\WriteAuditLog;
use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
     * TEST-3: semua_mode_wajib tersimpan dan diverifikasi pemenuhannya oleh EvaluateEvidence.
     */
    public function test_semua_mode_wajib_semantics(): void
    {
        $payload = [
            'nama' => 'Bukti Dukung Lengkap',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
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

        $jb = JenisBerkas::where('nama', 'Bukti Dukung Lengkap')->firstOrFail();

        // Setup context pengukuran untuk evaluasi pemenuhan bukti
        $renstra = Renstra::where('kode', 'R-UJI')->firstOrFail();
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UJI',
            'tanggal_pk' => '2026-01-01',
            'created_by' => $this->perencanaan->id,
        ]);
        $periode = Periode::create([
            'nama' => 'Triwulan I',
            'urutan' => 1,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        $context = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $this->indikator->id,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $this->indikator->unit_id,
            'nama' => 'Indikator Uji',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 70,
        ]);
        $pengukuran = PengukuranKinerja::create([
            'indikator_id' => $this->indikator->id,
            'tahun' => 2026,
            'periode_id' => $periode->id,
            'jadwal_snapshot_id' => $context->id,
            'sumber_nilai' => 'manual',
            'created_by' => $this->perencanaan->id,
        ]);

        $evaluator = app(EvaluateEvidence::class);

        // 1. Belum ada bukti dukung -> terpenuhi = false, mode kurang = file, tautan, teks
        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertFalse($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertEqualsCanonicalizing(['file', 'tautan', 'teks'], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // 2. Parsial: hanya mode teks disediakan -> terpenuhi tetap false karena semua_mode_wajib = true
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $pengukuran->id,
            'mode' => 'teks',
            'isi_teks' => 'Catatan pemenuhan narasi',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertFalse($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertEqualsCanonicalizing(['file', 'tautan'], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // 3. Parsial: mode tautan ditambahkan -> terpenuhi tetap false (masih kurang mode file)
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $pengukuran->id,
            'mode' => 'tautan',
            'tautan' => 'https://example.com/laporan-kinerja',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertFalse($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertSame(['file'], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // 4. Lengkap: mode file juga diunggah -> terpenuhi = true, mode kurang kosong
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $pengukuran->id,
            'mode' => 'file',
            'nama_asli' => 'laporan.pdf',
            'path' => 'evidence/laporan.pdf',
            'mime' => 'application/pdf',
            'ukuran_bytes' => 2048,
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertTrue($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertSame([], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);
        $this->assertEqualsCanonicalizing(['file', 'tautan', 'teks'], $evaluations[$jb->id]['pemenuhan']['mode_terpenuhi']);
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
            'expected_updated_at' => $jb->updated_at->toISOString(),
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
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
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
     * TEST-5: Otorisasi fail closed: Admin dan Pegawai ditolak (403), dan percobaan mutasi sensitif dicatat audit.
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

        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Terlindungi',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Percobaan update oleh pegawai ditolak 403 dan dicatat di audit log
        $updateResponse = $this->actingAs($this->pegawai)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pembaruan Tidak Sah',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Mencoba ubah tanpa hak',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);
        $updateResponse->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'jenis_berkas.ubah_ditolak',
            'objek_id' => $jb->id,
        ]);

        // Percobaan delete oleh pegawai ditolak 403 dan dicatat di audit log
        $deleteResponse = $this->actingAs($this->pegawai)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus tanpa hak',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);
        $deleteResponse->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'jenis_berkas.hapus_ditolak',
            'objek_id' => $jb->id,
        ]);
    }

    /**
     * TEST-6: Mutasi dan audit dibungkus dalam transaksi atomik (rollback jika audit gagal).
     */
    public function test_mutation_and_audit_are_atomic_in_single_transaction(): void
    {
        $this->withoutExceptionHandling();
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

    /**
     * TEST-9: Penghapusan persyaratan yang masih dirujuk berkas ditolak dengan validation error.
     */
    public function test_cannot_delete_jenis_berkas_referenced_by_berkas(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Dengan Berkas',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        DB::table('berkas')->insert([
            'id' => (string) Str::uuid(),
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => (string) Str::uuid(),
            'mode' => 'teks',
            'isi_teks' => 'Catatan bukti yang dirujuk',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus persyaratan yang sudah dirujuk bukti',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);

        $response->assertSessionHasErrors('alasan');
        $this->assertDatabaseHas('jenis_berkas', ['id' => $jb->id]);
    }

    /**
     * TEST-10: Peringatan flash muncul saat persyaratan wajib file-only disimpan sementara unggahan nonaktif.
     */
    public function test_warning_flashed_when_mandatory_file_only_saved_while_uploads_disabled(): void
    {
        Pengaturan::create([
            'kunci' => 'berkas.unggahan_aktif',
            'nilai' => 'false',
            'tipe' => 'boolean',
            'grup' => 'berkas',
            'updated_at' => now(),
        ]);

        $payload = [
            'nama' => 'Laporan Khusus File Dinonaktifkan',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertRedirect('/jenis-berkas');
        $response->assertSessionHas('warning');
        $this->assertDatabaseHas('jenis_berkas', ['nama' => 'Laporan Khusus File Dinonaktifkan']);
    }

    /**
     * TEST-11: Persyaratan jenis berkas dapat dinonaktifkan melalui update (aktif = false).
     */
    public function test_perencanaan_can_deactivate_jenis_berkas_via_update(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Usang',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Usang (Dinonaktifkan)',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'aktif' => false,
            'alasan' => 'Penonaktifan persyaratan usang agar tidak memblokir pengajuan mendatang',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertRedirect('/jenis-berkas');
        $this->assertDatabaseHas('jenis_berkas', [
            'id' => $jb->id,
            'aktif' => false,
        ]);

        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah')->where('objek_id', $jb->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(false, $audit->nilai_baru['aktif']);
    }

    /**
     * TEST-12: Update wajib menyertakan expected_updated_at dan mendeteksi konflik konkurensi.
     */
    public function test_update_requires_expected_updated_at_and_detects_concurrency_conflict(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Concurrency',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // 1. Tanpa expected_updated_at -> validasi gagal (422)
        $noVersion = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pembaruan Tanpa Versi',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Pembaruan tanpa expected_updated_at',
        ]);
        $noVersion->assertSessionHasErrors('expected_updated_at');

        // 2. Dengan expected_updated_at lama/tidak cocok -> konflik
        $staleTimestamp = now()->subMinutes(10)->toISOString();
        $conflictResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pembaruan Versi Usang',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Mencoba menimpa perubahan',
            'expected_updated_at' => $staleTimestamp,
        ]);
        $conflictResponse->assertSessionHasErrors('konflik');
    }

    /**
     * TEST-13: Store ditolak jika memilih indikator kinerja nonaktif.
     */
    public function test_store_fails_when_assigning_to_inactive_indicator(): void
    {
        $inactiveIndikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->indikator->sasaran_strategis_id,
            'unit_id' => $this->indikator->unit_id,
            'kode' => 'I-NONAKTIF',
            'nama' => 'Indikator Nonaktif',
            'satuan' => 'dokumen',
            'tipe_perhitungan' => 'manual',
            'is_aktif' => false,
        ]);

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Persyaratan Indikator Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveIndikator->id,
            'izinkan_file' => true,
        ]);

        $response->assertSessionHasErrors('indikator_id');
    }

    /**
     * TEST-14: Update mengizinkan indikator nonaktif yang sudah terpasang, tetapi menolak perpindahan ke indikator nonaktif lain.
     */
    public function test_update_allows_existing_inactive_indicator_but_rejects_switching_to_different_inactive(): void
    {
        $inactiveA = IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->indikator->sasaran_strategis_id,
            'unit_id' => $this->indikator->unit_id,
            'kode' => 'I-NONAKTIF-A',
            'nama' => 'Indikator Nonaktif A',
            'satuan' => 'dokumen',
            'tipe_perhitungan' => 'manual',
            'is_aktif' => false,
        ]);

        $inactiveB = IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->indikator->sasaran_strategis_id,
            'unit_id' => $this->indikator->unit_id,
            'kode' => 'I-NONAKTIF-B',
            'nama' => 'Indikator Nonaktif B',
            'satuan' => 'dokumen',
            'tipe_perhitungan' => 'manual',
            'is_aktif' => false,
        ]);

        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Lama Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveA->id,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // 1. Update dengan mempertahankan inactiveA -> diizinkan
        $okResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Persyaratan Lama Tetap A',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveA->id,
            'izinkan_file' => true,
            'alasan' => 'Pembaruan tanpa mengubah indikator nonaktif',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);
        $okResponse->assertRedirect('/jenis-berkas');

        // 2. Update dengan mengubah ke inactiveB -> ditolak
        $failResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pindah ke B Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveB->id,
            'izinkan_file' => true,
            'alasan' => 'Mencoba pindah ke indikator nonaktif lain',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);
        $failResponse->assertSessionHasErrors('indikator_id');
    }

    /**
     * TEST-15: failedAuthorization tidak mengalami TypeError saat payload alasan berupa array dan tetap merespons 403.
     */
    public function test_failed_authorization_handles_array_alasan_without_type_error(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Hak Akses',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Kirim alasan sebagai array oleh user tanpa izin (pegawai)
        $response = $this->actingAs($this->pegawai)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Coba Ubah',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => ['malicious', 'array', 'payload'],
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertStatus(403);

        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah_ditolak')
            ->where('objek_id', $jb->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertIsString($audit->alasan);
    }

    /**
     * TEST-16: Store memicu session flash warning saat mode file diwajibkan dan berkas.unggahan_aktif false.
     */
    public function test_store_flashes_warning_when_required_file_only_and_uploads_disabled(): void
    {
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.unggahan_aktif'],
            [
                'nilai' => 'false',
                'tipe' => 'boolean',
                'grup' => 'berkas',
                'updated_at' => now(),
            ]
        );

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Bukti Wajib File Saat Mati',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ]);

        $response->assertRedirect('/jenis-berkas');
        $response->assertSessionHas('warning');
    }

    /**
     * TEST-17: Delete gagal dan melempar error konflik jika expected_updated_at tidak cocok dengan versi database.
     */
    public function test_delete_fails_when_expected_updated_at_conflicts(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Uji Konflik Delete',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus dengan versi timestamp usang',
            'expected_updated_at' => now()->subHours(2)->toISOString(),
        ]);

        $response->assertSessionHasErrors('konflik');
        $this->assertDatabaseHas('jenis_berkas', ['id' => $jb->id]);
    }

    /**
     * TEST-18: Delete gagal dengan error validasi saat expected_updated_at bukan format tanggal valid.
     */
    public function test_delete_fails_when_expected_updated_at_is_invalid_format(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Uji Format Delete',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus dengan tanggal invalid',
            'expected_updated_at' => 'format-tanggal-rusak',
        ]);

        $response->assertSessionHasErrors('expected_updated_at');
        $this->assertDatabaseHas('jenis_berkas', ['id' => $jb->id]);
    }

    /**
     * TEST-19: Update gagal dengan error validasi (bukan 500) saat expected_updated_at bukan tanggal valid.
     */
    public function test_update_fails_with_validation_error_when_expected_updated_at_is_invalid_format(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Uji Format Update',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Update Nama Baru',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
            'alasan' => 'Alasan yang sah untuk pembaruan',
            'expected_updated_at' => 'string-bukan-tanggal',
        ]);

        $response->assertSessionHasErrors('expected_updated_at');
    }
}
