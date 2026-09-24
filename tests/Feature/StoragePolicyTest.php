<?php

namespace Tests\Feature;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use App\Services\Storage\StorageMetricsService;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\StoragePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StoragePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $admin;

    protected User $perencanaan;

    protected User $pegawai;

    protected IndikatorKinerja $indikator;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->seed(StoragePolicySeeder::class);

        $this->superadmin = $this->userWithRole('superadmin');
        $this->admin = $this->userWithRole('admin');
        $this->perencanaan = $this->userWithRole('perencanaan');
        $this->pegawai = $this->userWithRole('pegawai');

        $this->unit = Unit::create(['nama' => 'Unit Pengujian Storage', 'created_by' => $this->perencanaan->id]);
        $renstra = Renstra::create([
            'kode' => 'R-STORAGE',
            'nama' => 'Renstra Storage Uji',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'S-STORAGE',
            'deskripsi' => 'Sasaran Storage Uji',
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $this->unit->id,
            'kode' => 'I-STORAGE',
            'nama' => 'Indikator Storage Uji',
            'satuan' => 'dokumen',
            'tipe_perhitungan' => 'manual',
            'is_aktif' => true,
        ]);

        $this->seedPengaturanGrupBerkas();
    }

    protected function seedPengaturanGrupBerkas(): void
    {
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.unggahan_aktif'],
            ['nilai' => 'true', 'tipe' => 'boolean', 'grup' => 'berkas', 'updated_at' => now()]
        );
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.ukuran_maks_kb'],
            ['nilai' => '10240', 'tipe' => 'integer', 'grup' => 'berkas', 'updated_at' => now()]
        );
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.format_diizinkan'],
            ['nilai' => 'pdf,docx,xlsx,jpg,jpeg,png', 'tipe' => 'string', 'grup' => 'berkas', 'updated_at' => now()]
        );
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.tautan_selalu_diizinkan'],
            ['nilai' => 'true', 'tipe' => 'boolean', 'grup' => 'berkas', 'updated_at' => now()]
        );
    }

    protected function validPayload(array $overrides = []): array
    {
        $maxUpdatedAt = Pengaturan::where('grup', 'berkas')->max('updated_at');
        $expectedUpdatedAt = $maxUpdatedAt ? Carbon::parse($maxUpdatedAt)->toISOString() : now()->toISOString();
        $currentVersion = (int) (Pengaturan::where('kunci', 'berkas.versi')->value('nilai') ?? 1);

        return array_merge([
            'berkas_unggahan_aktif' => true,
            'berkas_ukuran_maks_kb' => 10240,
            'berkas_format_diizinkan' => 'pdf,docx,xlsx,jpg,jpeg,png',
            'berkas_tautan_selalu_diizinkan' => true,
            'expected_updated_at' => $expectedUpdatedAt,
            'expected_version' => $currentVersion,
            'alasan' => 'Penyesuaian konfigurasi storage berkas aplikasi.',
        ], $overrides);
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
     * TEST-1: Panel metrik storage menampilkan jumlah file, total bytes, serta jumlah bukti tautan/teks lintas induk.
     */
    public function test_storage_metrics_panel_displays_accurate_counts_across_induk(): void
    {
        $regulasi = Regulasi::create([
            'jenis' => 'permen',
            'nomor' => '10/STORAGE/2026',
            'tahun' => 2026,
            'tentang' => 'Regulasi Uji Storage',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // 1. File evidence pada regulasi
        BuktiDukung::create([
            'id' => (string) Str::uuid(),
            'berkasable_type' => 'regulasi',
            'berkasable_id' => $regulasi->id,
            'mode' => 'file',
            'nama_asli' => 'regulasi.pdf',
            'path' => 'berkas/regulasi/regulasi.pdf',
            'mime' => 'application/pdf',
            'ukuran_bytes' => 204800, // 200 KB
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // 2. Tautan evidence pada regulasi
        BuktiDukung::create([
            'id' => (string) Str::uuid(),
            'berkasable_type' => 'regulasi',
            'berkasable_id' => $regulasi->id,
            'mode' => 'tautan',
            'tautan' => 'https://jdih.kemdikbud.go.id/regulasi-10',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // 3. Teks evidence pada regulasi
        BuktiDukung::create([
            'id' => (string) Str::uuid(),
            'berkasable_type' => 'regulasi',
            'berkasable_id' => $regulasi->id,
            'mode' => 'teks',
            'isi_teks' => 'Keterangan ringkasan regulasi storage',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // 4. File evidence via relasi morph produksi Regulasi::berkas()->create()
        $regulasi->berkas()->create([
            'id' => (string) Str::uuid(),
            'mode' => 'file',
            'nama_asli' => 'regulasi_morph.pdf',
            'path' => 'berkas/regulasi/regulasi_morph.pdf',
            'mime' => 'application/pdf',
            'ukuran_bytes' => 102400, // 100 KB
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // 5. File evidence yang telah dihapus (soft-deleted) - tidak boleh dihitung dalam metrik aktif
        BuktiDukung::create([
            'id' => (string) Str::uuid(),
            'berkasable_type' => 'regulasi',
            'berkasable_id' => $regulasi->id,
            'mode' => 'file',
            'nama_asli' => 'deleted.pdf',
            'path' => 'berkas/regulasi/deleted.pdf',
            'mime' => 'application/pdf',
            'ukuran_bytes' => 500000,
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
            'dihapus_pada' => now(),
            'dihapus_oleh' => $this->perencanaan->id,
        ]);

        // 6. File evidence pada PK menggunakan FQCN RenstraPk::class
        $renstra = Renstra::firstOrFail();
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK/STORAGE/01',
            'tanggal_pk' => now()->toDateString(),
            'created_by' => $this->perencanaan->id,
        ]);

        BuktiDukung::create([
            'id' => (string) Str::uuid(),
            'berkasable_type' => RenstraPk::class,
            'berkasable_id' => $pk->id,
            'mode' => 'file',
            'nama_asli' => 'pk_document.pdf',
            'path' => 'berkas/renstra_pk/pk_document.pdf',
            'mime' => 'application/pdf',
            'ukuran_bytes' => 102400, // 100 KB
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // Test langsung Service
        $service = app(StorageMetricsService::class);
        $metrics = $service->calculate();

        $this->assertSame(3, $metrics['file_count']);
        $this->assertSame(409600, $metrics['file_total_bytes']);
        $this->assertSame(1, $metrics['link_count']);
        $this->assertSame(1, $metrics['text_count']);
        $this->assertSame(5, $metrics['total_evidence_count']);

        // Verifikasi pemetaan morph class ke rincian by_induk regulasi
        $this->assertArrayHasKey('regulasi', $metrics['by_induk']);
        $this->assertSame(2, $metrics['by_induk']['regulasi']['file_count']);
        $this->assertSame(307200, $metrics['by_induk']['regulasi']['file_bytes']);
        $this->assertSame(1, $metrics['by_induk']['regulasi']['link_count']);
        $this->assertSame(1, $metrics['by_induk']['regulasi']['text_count']);
        $this->assertSame(4, $metrics['by_induk']['regulasi']['total_count']);

        // Verifikasi pemetaan FQCN RenstraPk::class ke rincian by_induk renstra_pk
        $this->assertArrayHasKey('renstra_pk', $metrics['by_induk']);
        $this->assertSame(1, $metrics['by_induk']['renstra_pk']['file_count']);
        $this->assertSame(102400, $metrics['by_induk']['renstra_pk']['file_bytes']);
        $this->assertSame(1, $metrics['by_induk']['renstra_pk']['total_count']);

        // Test Endpoint via Inertia
        $response = $this->actingAs($this->admin)->get('/pengaturan/storage');
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Pengaturan/StorageIndex')
            ->has('metrics')
            ->where('metrics.file_count', 3)
            ->where('metrics.file_total_bytes', 409600)
            ->where('metrics.link_count', 1)
            ->where('metrics.text_count', 1)
            ->where('metrics.total_evidence_count', 5)
            ->where('metrics.by_induk.regulasi.total_count', 4)
            ->where('metrics.by_induk.renstra_pk.total_count', 1)
            ->has('settings')
            ->where('settings.berkas_unggahan_aktif', true)
            ->where('settings.berkas_ukuran_maks_kb', 10240)
            ->where('can.update', true)
        );
    }

    /**
     * TEST-2: Global upload switch disabled (false) menonaktifkan mode file sedangkan tautan/teks tetap dapat dipakai.
     */
    public function test_global_upload_switch_disabled_blocks_file_uploads_while_links_and_text_remain_active(): void
    {
        Storage::fake('local');

        // Matikan saklar global unggahan
        Pengaturan::where('kunci', 'berkas.unggahan_aktif')->update([
            'nilai' => 'false',
            'updated_at' => now(),
        ]);

        // 1. Percobaan kirim regulasi dengan lampiran mode file DITOLAK
        $responseFile = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '99/FAIL/2026',
            'tahun' => 2026,
            'tentang' => 'Percobaan Unggah File Saat Saklar Mati',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'file',
                    'file' => UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf'),
                ],
            ],
        ]);
        $responseFile->assertSessionHasErrors([
            'lampiran.0.file' => 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.',
        ]);

        // 1b. Kirim file dengan format ilegal (.exe) dan ukuran besar (50 MB) saat saklar mati:
        // Pesan error HARUS spesifik menyatakan saklar mati, BUKAN format atau ukuran!
        $responseIllegalFile = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '99B/FAIL/2026',
            'tahun' => 2026,
            'tentang' => 'Percobaan Unggah File Ilegal Saat Saklar Mati',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'file',
                    'file' => UploadedFile::fake()->create('virus.exe', 50000, 'application/x-msdownload'),
                ],
            ],
        ]);
        $responseIllegalFile->assertSessionHasErrors([
            'lampiran.0.file' => 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.',
        ]);

        // 2. Kirim regulasi dengan mode tautan TETAP DIIZINKAN
        $responseLink = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '100/SUCCESS-LINK/2026',
            'tahun' => 2026,
            'tentang' => 'Pengiriman Tautan Saat Saklar File Mati',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'tautan',
                    'tautan' => 'https://jdih.kemdikbud.go.id/sukses',
                ],
            ],
        ]);
        $responseLink->assertSessionHasNoErrors();
        $responseLink->assertRedirect(route('regulasi.index'));
        $this->assertDatabaseHas('regulasi', ['nomor' => '100/SUCCESS-LINK/2026']);

        // 3. Kirim regulasi dengan mode teks TETAP DIIZINKAN
        $responseText = $this->actingAs($this->perencanaan)->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '101/SUCCESS-TEXT/2026',
            'tahun' => 2026,
            'tentang' => 'Pengiriman Teks Saat Saklar File Mati',
            'aktif' => true,
            'lampiran' => [
                [
                    'mode' => 'teks',
                    'isi_teks' => 'Keterangan tertulis tetap sah.',
                ],
            ],
        ]);
        $responseText->assertSessionHasNoErrors();
        $this->assertDatabaseHas('regulasi', ['nomor' => '101/SUCCESS-TEXT/2026']);
    }

    /**
     * TEST-3: Default format dan ukuran bertindak sebagai fallback saat jenis_berkas batas teknis kosong.
     */
    public function test_default_storage_limits_act_as_fallback_when_jenis_berkas_limits_are_null(): void
    {
        Pengaturan::where('kunci', 'berkas.ukuran_maks_kb')->update(['nilai' => '20480']);
        Pengaturan::where('kunci', 'berkas.format_diizinkan')->update(['nilai' => 'pdf,docx']);

        $evaluator = app(EvaluateEvidence::class);
        $settings = $evaluator->settings();

        $this->assertSame(20480, $settings['ukuran_maks_kb']);
        $this->assertSame('pdf,docx', $settings['format_diizinkan']);
        $this->assertTrue($settings['unggahan_aktif']);

        // Update nilai default di pengaturan
        $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'berkas_unggahan_aktif' => true,
            'berkas_ukuran_maks_kb' => 51200,
            'berkas_format_diizinkan' => 'pdf,docx,xlsx,png',
            'berkas_tautan_selalu_diizinkan' => true,
            'alasan' => 'Menaikkan batas default ukuran fallback menjadi 50MB untuk seluruh berkas.',
        ]))->assertSessionHasNoErrors();

        $updatedSettings = $evaluator->settings();
        $this->assertSame(51200, $updatedSettings['ukuran_maks_kb']);
        $this->assertSame('pdf,docx,xlsx,png', $updatedSettings['format_diizinkan']);
    }

    /**
     * TEST-4: Perubahan kebijakan merekam audit log before/after dengan alasan dan dasar izin per kunci yang berubah.
     */
    public function test_storage_policy_update_records_atomic_audit_log_with_reason_and_permission_basis(): void
    {
        $response = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'berkas_unggahan_aktif' => false,
            'berkas_ukuran_maks_kb' => 15360,
            'berkas_format_diizinkan' => 'pdf,jpg,png',
            'berkas_tautan_selalu_diizinkan' => true, // Tidak berubah (tetap true)
            'alasan' => 'Penyesuaian kebijakan storage operasional dan pembatasan unggahan sementara.',
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/pengaturan/storage');
        $response->assertSessionHas('success');

        // Pastikan nilai database terupdate
        $this->assertSame('false', Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai'));
        $this->assertSame('15360', Pengaturan::where('kunci', 'berkas.ukuran_maks_kb')->value('nilai'));
        $this->assertSame('pdf,jpg,png', Pengaturan::where('kunci', 'berkas.format_diizinkan')->value('nilai'));
        $this->assertSame('true', Pengaturan::where('kunci', 'berkas.tautan_selalu_diizinkan')->value('nilai'));

        // Pastikan audit log tercatat untuk 3 kunci yang berubah, TIDAK untuk kunci yang nilainya sama
        $auditLogs = AuditLog::where('objek_tipe', 'pengaturan')
            ->where('tindakan', 'pengaturan.ubah')
            ->get();

        $this->assertCount(3, $auditLogs);

        foreach ($auditLogs as $log) {
            $this->assertSame($this->admin->id, $log->actor_id);
            $this->assertSame('Penyesuaian kebijakan storage operasional dan pembatasan unggahan sementara.', $log->alasan);
            $this->assertNotNull($log->dasar_izin);
            $this->assertNotEmpty($log->dasar_izin['roles'] ?? []);
        }

        $changedRows = Pengaturan::whereIn('kunci', ['berkas.unggahan_aktif', 'berkas.ukuran_maks_kb', 'berkas.format_diizinkan'])->pluck('id')->all();
        $auditObjekIds = $auditLogs->pluck('objek_id')->all();
        $this->assertEqualsCanonicalizing($changedRows, $auditObjekIds);

        $loggedKeys = $auditLogs->map(fn ($l) => $l->nilai_baru['kunci'] ?? null)->all();
        $this->assertContains('berkas.unggahan_aktif', $loggedKeys);
        $this->assertContains('berkas.ukuran_maks_kb', $loggedKeys);
        $this->assertContains('berkas.format_diizinkan', $loggedKeys);
        $this->assertNotContains('berkas.tautan_selalu_diizinkan', $loggedKeys);
    }

    /**
     * TEST-5: Submit no-op tidak memicu perubahan timestamp atau pencatatan audit log palsu.
     */
    public function test_no_op_policy_update_does_not_modify_timestamps_or_create_false_audit_logs(): void
    {
        $oldUpdatedAt = Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('updated_at');

        $response = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'berkas_unggahan_aktif' => true,
            'berkas_ukuran_maks_kb' => 10240,
            'berkas_format_diizinkan' => 'pdf,docx,xlsx,jpg,jpeg,png',
            'berkas_tautan_selalu_diizinkan' => true,
            'alasan' => 'Mencoba submit tanpa mengubah nilai apa pun.',
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/pengaturan/storage');
        $response->assertSessionHas('message');

        $currentUpdatedAt = Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('updated_at');
        $this->assertEquals($oldUpdatedAt, $currentUpdatedAt);

        // Tidak ada audit log baru yang terbentuk
        $this->assertSame(0, AuditLog::where('objek_tipe', 'pengaturan')->count());
    }

    /**
     * TEST-6: Role tanpa pengaturan:update (Perencanaan/Pegawai) dilarang mengubah kebijakan storage (403 Forbidden)
     * dan insiden unauthorized dicatat ke audit log.
     */
    public function test_unauthorized_users_without_pengaturan_update_cannot_mutate_storage_policy(): void
    {
        // 1. Perencanaan mencoba update
        $responsePerencanaan = $this->actingAs($this->perencanaan)->put('/pengaturan/storage', $this->validPayload([
            'berkas_unggahan_aktif' => false,
            'berkas_ukuran_maks_kb' => 10240,
            'berkas_format_diizinkan' => 'pdf',
            'berkas_tautan_selalu_diizinkan' => true,
            'alasan' => 'Percobaan ilegal oleh tim perencanaan.',
        ]));
        $responsePerencanaan->assertForbidden();

        // 2. Pegawai mencoba update
        $responsePegawai = $this->actingAs($this->pegawai)->put('/pengaturan/storage', $this->validPayload([
            'berkas_unggahan_aktif' => false,
            'berkas_ukuran_maks_kb' => 10240,
            'berkas_format_diizinkan' => 'pdf',
            'berkas_tautan_selalu_diizinkan' => true,
            'alasan' => 'Percobaan ilegal oleh pegawai.',
        ]));
        $responsePegawai->assertForbidden();

        // Nilai tetap tidak berubah
        $this->assertSame('true', Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai'));

        // Finding 3: Percobaan tidak berwenang dicatat dalam audit log sebagai tindakan 'pengaturan.ubah_ditolak'
        $auditLogsDitolak = AuditLog::where('objek_tipe', 'pengaturan')
            ->where('tindakan', 'pengaturan.ubah_ditolak')
            ->get();
        $this->assertCount(2, $auditLogsDitolak);
        $this->assertEqualsCanonicalizing(
            [$this->perencanaan->id, $this->pegawai->id],
            $auditLogsDitolak->pluck('actor_id')->all()
        );
    }

    /**
     * TEST-7: User dengan jenis_berkas:read dapat membaca panel metrik storage dalam mode read-only.
     */
    public function test_users_with_jenis_berkas_read_can_view_metrics_in_readonly_mode(): void
    {
        $response = $this->actingAs($this->perencanaan)->get('/pengaturan/storage');
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Pengaturan/StorageIndex')
            ->has('metrics')
            ->has('settings')
            ->where('can.update', false)
        );

        $responsePegawai = $this->actingAs($this->pegawai)->get('/pengaturan/storage');
        $responsePegawai->assertOk();
        $responsePegawai->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Pengaturan/StorageIndex')
            ->where('can.update', false)
        );
    }

    /**
     * TEST-8: Validasi input menolak batas ukuran < 100 KB, format kosong/invalid, missing timestamp, atau alasan kosong/terlalu pendek.
     */
    public function test_validation_rejects_invalid_size_empty_format_or_empty_reason(): void
    {
        // 1. Ukuran < 100 KB
        $responseSize = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'berkas_ukuran_maks_kb' => 50, // Kurang dari batas minimal 100 KB
        ]));
        $responseSize->assertSessionHasErrors(['berkas_ukuran_maks_kb']);

        // 2. Format kosong
        $responseFormat = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'berkas_format_diizinkan' => '',
        ]));
        $responseFormat->assertSessionHasErrors(['berkas_format_diizinkan']);

        // 3. Alasan terlalu pendek (< 10 karakter)
        $responseReason = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'alasan' => 'Pendek',
        ]));
        $responseReason->assertSessionHasErrors(['alasan']);

        // 4. Missing expected_updated_at
        $payloadNoUpdatedAt = $this->validPayload();
        unset($payloadNoUpdatedAt['expected_updated_at']);
        $responseNoUpdatedAt = $this->actingAs($this->admin)->put('/pengaturan/storage', $payloadNoUpdatedAt);
        $responseNoUpdatedAt->assertSessionHasErrors(['expected_updated_at']);

        // 4b. Missing expected_version
        $payloadNoVersion = $this->validPayload();
        unset($payloadNoVersion['expected_version']);
        $responseNoVersion = $this->actingAs($this->admin)->put('/pengaturan/storage', $payloadNoVersion);
        $responseNoVersion->assertSessionHasErrors(['expected_version']);

        // 5. Invariant anti-blocking: berkas_tautan_selalu_diizinkan bernilai false harus ditolak
        $responseNonFileFalse = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'berkas_tautan_selalu_diizinkan' => false,
        ]));
        $responseNonFileFalse->assertSessionHasErrors(['berkas_tautan_selalu_diizinkan']);
    }

    /**
     * TEST-9: Concurrency conflict throws validation error when expected_updated_at is stale.
     */
    public function test_concurrency_conflict_throws_validation_error_on_stale_expected_updated_at(): void
    {
        $staleTimestamp = now()->subMinutes(10)->toISOString();

        $response = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'expected_updated_at' => $staleTimestamp,
            'berkas_ukuran_maks_kb' => 20480,
            'alasan' => 'Mencoba simpan dengan timestamp kedaluwarsa.',
        ]));

        $response->assertSessionHasErrors(['konflik']);
    }

    /**
     * TEST-9b: Concurrency conflict throws validation error when expected_version is stale.
     */
    public function test_concurrency_conflict_throws_validation_error_on_stale_expected_version(): void
    {
        $response = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
            'expected_version' => 9999, // Versi salah/basi
            'berkas_ukuran_maks_kb' => 20480,
            'alasan' => 'Mencoba simpan dengan versi monotonik kedaluwarsa.',
        ]));

        $response->assertSessionHasErrors(['konflik']);
    }

    /**
     * TEST-10: Format ekstensi yang mengandung karakter ilegal ditolak oleh regex.
     */
    public function test_invalid_format_regex_is_rejected(): void
    {
        $invalidFormats = [
            'pdf;docx', // titik koma
            'pdf/docx', // garis miring
            'pdf*docx', // bintang
            'pdf|docx', // pipe
            'pdf@docx', // simbol @
            'pdf#docx', // tag pagar
        ];

        foreach ($invalidFormats as $invalidFormat) {
            $response = $this->actingAs($this->admin)->put('/pengaturan/storage', $this->validPayload([
                'berkas_format_diizinkan' => $invalidFormat,
            ]));
            $response->assertSessionHasErrors(['berkas_format_diizinkan']);
        }
    }

    /**
     * TEST-11: Dynamic storage limits enforced on regulasi uploads.
     */
    public function test_dynamic_storage_limits_enforced_on_regulasi_uploads(): void
    {
        Storage::fake('local');

        // Batasi ukuran maks menjadi 200 KB dan hanya perbolehkan txt
        Pengaturan::where('kunci', 'berkas.ukuran_maks_kb')->update(['nilai' => '200', 'updated_at' => now()]);
        Pengaturan::where('kunci', 'berkas.format_diizinkan')->update(['nilai' => 'txt', 'updated_at' => now()]);

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

        // Uji fallback default tanpa baris pengaturan berkas: .doc harus ditolak (karena default hanya pdf,docx,xlsx,jpg,jpeg,png)
        Pengaturan::where('kunci', 'berkas.format_diizinkan')->delete();

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
