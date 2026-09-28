<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerjanjianKinerjaHttpTest extends TestCase
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

        $this->perencanaan = User::factory()->create(['status' => 'aktif']);
        $rolePerencanaan = Role::where('kode', 'perencanaan')->firstOrFail();
        $this->perencanaan->roles()->attach($rolePerencanaan->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $this->pegawai = User::factory()->create(['status' => 'aktif']);
        $rolePegawai = Role::where('kode', 'pegawai')->firstOrFail();
        $this->pegawai->roles()->attach($rolePegawai->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $this->renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_perjanjian_kinerja_routes(): void
    {
        $this->get('/perjanjian-kinerja')->assertRedirect('/login');
        $this->post('/perjanjian-kinerja', [])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_index_and_detail(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-VIEW',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://rahasia.example.com/pk-link',
            'nama_asli' => 'Dokumen Tautan PK',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        $this->actingAs($this->pegawai)
            ->get('/perjanjian-kinerja')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PerjanjianKinerja/Index')
                ->has('perjanjianKinerja')
            );

        // Pegawai tanpa izin berkas:read tidak boleh melihat path, tautan, atau isi_teks
        $this->actingAs($this->pegawai)
            ->get("/perjanjian-kinerja/{$pk->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PerjanjianKinerja/Show')
                ->where('pk.id', $pk->id)
                ->where('pk.nomor_pk', 'PK-2026-VIEW')
                ->where('can.read_berkas', false)
                ->where('can.delete_berkas', false)
                ->missing('pk.berkas.0.path')
                ->missing('pk.berkas.0.tautan')
                ->missing('pk.berkas.0.isi_teks')
            );

        // Perencanaan dengan izin berkas:read dapat melihat tautan, namun storage path tetap hidden
        $this->actingAs($this->perencanaan)
            ->get("/perjanjian-kinerja/{$pk->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PerjanjianKinerja/Show')
                ->where('can.read_berkas', true)
                ->where('pk.berkas.0.tautan', 'https://rahasia.example.com/pk-link')
                ->missing('pk.berkas.0.path')
            );
    }

    public function test_user_without_pk_create_cannot_access_create_or_post(): void
    {
        $this->actingAs($this->pegawai)
            ->get('/perjanjian-kinerja/create')
            ->assertForbidden();

        $this->actingAs($this->pegawai)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-UNAUTH',
                'tanggal_pk' => '2026-01-15',
            ])
            ->assertForbidden();
    }

    public function test_authorized_user_can_create_perjanjian_kinerja_with_file(): void
    {
        $file = UploadedFile::fake()->create('pk_legal.pdf', 250, 'application/pdf');

        $response = $this->actingAs($this->perencanaan)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-LLDIKTI-2026',
                'tanggal_pk' => '2026-01-15',
                'lampiran' => [
                    [
                        'mode' => 'file',
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $pk = RenstraPk::where('nomor_pk', 'PK-LLDIKTI-2026')->first();
        $this->assertNotNull($pk);
        $this->assertSame(2026, $pk->tahun);
        $this->assertCount(1, $pk->berkas);

        $berkas = $pk->berkas->first();
        $this->assertSame('file', $berkas->mode);
        $this->assertSame('pk_legal.pdf', $berkas->nama_asli);
        $this->assertTrue(Storage::disk('local')->exists($berkas->path));
    }

    public function test_user_without_pk_update_cannot_edit_or_put(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-EDIT',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $this->actingAs($this->pegawai)
            ->get("/perjanjian-kinerja/{$pk->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->pegawai)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-MUTASI',
                'tanggal_pk' => '2026-01-20',
                'alasan' => 'Alasan pegawai',
            ])
            ->assertForbidden();
    }

    public function test_authorized_user_can_update_perjanjian_kinerja_with_alasan(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-AWAL',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-PERBAIKAN-01',
                'tanggal_pk' => '2026-01-22',
                'alasan' => 'Koreksi penomoran internal LLDIKTI XVI',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame('PK-PERBAIKAN-01', $pk->fresh()->nomor_pk);
    }

    public function test_update_perjanjian_kinerja_fails_validation_without_alasan(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-AWAL',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $this->actingAs($this->perencanaan)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-PERBAIKAN-02',
                'tanggal_pk' => '2026-01-22',
                'alasan' => '',
            ])
            ->assertSessionHasErrors('alasan');
    }

    public function test_delete_lampiran_berkas_succeeds_when_jadwal_inactive(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);
        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/draft-pk',
            'nama_asli' => 'Draf PK',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Tautan draf tidak berlaku lagi',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSoftDeleted($berkas);
    }

    public function test_delete_lampiran_berkas_fails_when_jadwal_active(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);
        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/draft-pk',
            'nama_asli' => 'Draf PK',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Mencoba hapus dokumen saat jadwal aktif',
            ])
            ->assertSessionHasErrors('berkas');
    }

    public function test_delete_lampiran_berkas_succeeds_when_jadwal_in_draft_status(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-DRAFT',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);
        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/draft-pk-2',
            'nama_asli' => 'Draf PK 2',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'draft',
            'activated_at' => null,
        ]);

        $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Hapus draf berkas sebelum jadwal aktif',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSoftDeleted($berkas);
    }

    public function test_delete_lampiran_berkas_fails_when_jadwal_closed_after_active(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-CLOSED',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);
        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/closed-pk',
            'nama_asli' => 'Closed PK Doc',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'ditutup',
            'activated_at' => now()->subMonths(3),
            'closed_at' => now(),
        ]);

        $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Mencoba hapus dokumen setelah jadwal ditutup',
            ])
            ->assertSessionHasErrors('berkas');

        $this->assertDatabaseHas('berkas', [
            'id' => $berkas->id,
            'dihapus_pada' => null,
        ]);
    }

    public function test_download_lampiran_file(): void
    {
        $file = UploadedFile::fake()->create('dokumen_pk.pdf', 150, 'application/pdf');
        $path = $file->store('berkas/renstra_pk/test-download', 'local');

        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DOWNLOAD',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'file',
            'nama_asli' => 'dokumen_pk.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'ukuran_bytes' => $file->getSize(),
            'uploaded_by' => $this->perencanaan->id,
        ]);

        // Pegawai tanpa izin berkas:read ditolak
        $this->actingAs($this->pegawai)
            ->get("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}/unduh")
            ->assertForbidden();

        // Perencanaan dengan izin berkas:read berhasil
        $response = $this->actingAs($this->perencanaan)
            ->get("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}/unduh");

        $response->assertOk();
        $this->assertSame('attachment; filename=dokumen_pk.pdf', $response->headers->get('content-disposition'));
    }

    public function test_delete_lampiran_fails_if_berkas_belongs_to_different_pk(): void
    {
        $pkA = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-A',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $pkB = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2027,
            'nomor_pk' => 'PK-B',
            'tanggal_pk' => '2027-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkasB = $pkB->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/pk-b',
            'nama_asli' => 'Berkas PK B',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        // Mencoba menghapus berkas milik PK B lewat endpoint PK A ditolak 404 Not Found
        $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pkA->id}/berkas/{$berkasB->id}", [
                'alasan' => 'Hapus berkas silang',
            ])
            ->assertNotFound();

        // Mismatch kepemilikan tidak boleh dicatat sebagai audit otorisasi ditolak
        $this->assertDatabaseMissing('audit_log', [
            'objek_id' => $berkasB->id,
            'tindakan' => 'berkas.hapus_ditolak',
        ]);
    }

    public function test_index_filters_and_validates_query_parameters(): void
    {
        // Valid query parameters
        $this->actingAs($this->perencanaan)
            ->get("/perjanjian-kinerja?renstra_id={$this->renstra->id}&tahun=2026&q=PK")
            ->assertOk();

        // Invalid query parameter (tahun bukan integer) ditolak redirect dengan error validasi
        $this->actingAs($this->perencanaan)
            ->get('/perjanjian-kinerja?tahun=bukan-angka')
            ->assertSessionHasErrors('tahun');
    }

    public function test_post_perjanjian_kinerja_with_attachment_forbidden_if_berkas_upload_denied(): void
    {
        // Berikan explicit deny berkas:upload kepada perencanaan
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->perencanaan->id,
            'permission_id' => Permission::where('kode', 'berkas:upload')->value('id'),
            'unit_id' => null,
            'alasan' => 'Uji coba explicit deny berkas:upload',
            'ditetapkan_oleh' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // POST dengan lampiran harus ditolak 403 Forbidden
        $this->actingAs($this->perencanaan)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-DENY-UPLOAD',
                'tanggal_pk' => '2026-01-15',
                'lampiran' => [
                    [
                        'mode' => 'tautan',
                        'tautan' => 'https://example.com/pk-deny',
                    ],
                ],
            ])
            ->assertForbidden();

        // POST tanpa lampiran tetap diizinkan
        $this->actingAs($this->perencanaan)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-ALLOWED-WITHOUT-ATTACHMENT',
                'tanggal_pk' => '2026-01-15',
            ])
            ->assertRedirect();
    }

    public function test_put_perjanjian_kinerja_with_attachment_forbidden_if_berkas_upload_denied(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UPDATE-TEST',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        // Berikan explicit deny berkas:upload kepada perencanaan
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->perencanaan->id,
            'permission_id' => Permission::where('kode', 'berkas:upload')->value('id'),
            'unit_id' => null,
            'alasan' => 'Uji coba explicit deny berkas:upload',
            'ditetapkan_oleh' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // PUT dengan lampiran harus ditolak 403 Forbidden
        $this->actingAs($this->perencanaan)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-UPDATE-WITH-ATT',
                'tanggal_pk' => '2026-01-20',
                'alasan' => 'Mencoba tambah lampiran saat deny berkas:upload',
                'lampiran' => [
                    [
                        'mode' => 'tautan',
                        'tautan' => 'https://example.com/pk-update-deny',
                    ],
                ],
            ])
            ->assertForbidden();

        // PUT tanpa lampiran tetap diizinkan
        $this->actingAs($this->perencanaan)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-UPDATE-SUCCESS',
                'tanggal_pk' => '2026-01-20',
                'alasan' => 'Pembaruan metadata tanpa lampiran',
            ])
            ->assertRedirect();
    }

    public function test_post_perjanjian_kinerja_with_oversized_filename_rejected_by_validation(): void
    {
        $oversizedFilename = str_repeat('a', 260).'.pdf';
        $file = UploadedFile::fake()->create($oversizedFilename, 100, 'application/pdf');

        $this->actingAs($this->perencanaan)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-LONG-FILENAME',
                'tanggal_pk' => '2026-01-15',
                'lampiran' => [
                    [
                        'mode' => 'file',
                        'file' => $file,
                    ],
                ],
            ])
            ->assertSessionHasErrors('lampiran.0.file');
    }

    public function test_delete_lampiran_unauthorized_records_audit_log_penolakan(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DELETE-AUDIT',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/pk-delete-audit',
            'nama_asli' => 'Dokumen Delete Audit',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        // Pegawai tidak memiliki izin berkas:delete atau pk:update -> 403 Forbidden
        $this->actingAs($this->pegawai)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Mencoba menghapus tanpa hak akses',
            ])
            ->assertForbidden();

        // Audit log berkas.hapus_ditolak harus tercatat di database dengan alasan tidak_memiliki_izin
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'berkas.hapus_ditolak',
            'objek_tipe' => 'berkas',
            'objek_id' => $berkas->id,
            'actor_id' => $this->pegawai->id,
        ]);

        $audit = AuditLog::where('tindakan', 'berkas.hapus_ditolak')
            ->where('objek_id', $berkas->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('tidak_memiliki_izin', $audit->nilai_baru['alasan_penolakan'] ?? null);
    }

    public function test_delete_lampiran_forbidden_with_explicit_deny_records_audit_log(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DELETE-DENY',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/pk-delete-deny',
            'nama_asli' => 'Dokumen Delete Deny',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        // Berikan explicit deny berkas:delete kepada perencanaan
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->perencanaan->id,
            'permission_id' => Permission::where('kode', 'berkas:delete')->value('id'),
            'unit_id' => null,
            'alasan' => 'Uji coba explicit deny berkas:delete',
            'ditetapkan_oleh' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Mencoba menghapus dengan explicit deny',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'berkas.hapus_ditolak',
            'objek_tipe' => 'berkas',
            'objek_id' => $berkas->id,
            'actor_id' => $this->perencanaan->id,
        ]);
    }

    public function test_delete_lampiran_unauthorized_truncates_large_alasan_in_audit_log(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-LARGE-ALASAN',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/pk-large-alasan',
            'nama_asli' => 'Dokumen Large Alasan',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        $oversizedAlasan = str_repeat('A', 2500);

        $this->actingAs($this->pegawai)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => $oversizedAlasan,
            ])
            ->assertForbidden();

        $audit = AuditLog::where('tindakan', 'berkas.hapus_ditolak')
            ->where('objek_id', $berkas->id)
            ->latest('id')
            ->firstOrFail();

        // Alasan harus dibatasi maksimal 1000 karakter dan tidak membocorkan payload tak terbatas
        $this->assertSame(1000, mb_strlen($audit->alasan, 'UTF-8'));
        $this->assertSame(str_repeat('A', 1000), $audit->alasan);
    }

    public function test_delete_lampiran_unauthorized_with_empty_or_malformed_alasan_handles_safely(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-EMPTY-ALASAN',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/pk-empty-alasan',
            'nama_asli' => 'Dokumen Empty Alasan',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        $this->actingAs($this->pegawai)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => '   ',
            ])
            ->assertForbidden();

        $audit = AuditLog::where('tindakan', 'berkas.hapus_ditolak')
            ->where('objek_id', $berkas->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Tidak memiliki otorisasi', $audit->alasan);
    }
}
