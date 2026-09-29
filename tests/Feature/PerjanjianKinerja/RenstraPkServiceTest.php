<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Jobs\CleanupStorageFileJob;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use App\Services\RenstraPkService;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RenstraPkServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Renstra $renstra;

    private RenstraPkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        Storage::fake('local');

        $this->actor = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'superadmin')->firstOrFail();
        $this->actor->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->actor->id,
        ]);

        $this->service = app(RenstraPkService::class);
    }

    public function test_creates_renstra_pk_with_file_attachment_and_records_audit_logs(): void
    {
        $file = UploadedFile::fake()->create('perjanjian_kinerja_2026.pdf', 300, 'application/pdf');

        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK/LLDIKTI16/2026/001',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                [
                    'mode' => 'file',
                    'file' => $file,
                ],
            ],
        ], $this->actor);

        $this->assertDatabaseHas('renstra_pk', [
            'id' => $pk->id,
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK/LLDIKTI16/2026/001',
            'created_by' => $this->actor->id,
        ]);

        $this->assertCount(1, $pk->berkas);
        $berkas = $pk->berkas->first();
        $this->assertSame('file', $berkas->mode);
        $this->assertSame('renstra_pk', $berkas->berkasable_type);
        $this->assertSame('perjanjian_kinerja_2026.pdf', $berkas->nama_asli);
        $this->assertNull($berkas->jenis_berkas_id);
        $this->assertTrue(Storage::disk('local')->exists($berkas->path));

        // Audit log renstra_pk.buat
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'renstra_pk.buat',
            'objek_tipe' => 'renstra_pk',
            'objek_id' => $pk->id,
            'actor_id' => $this->actor->id,
        ]);

        // Audit log berkas.unggah
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'berkas.unggah',
            'objek_tipe' => 'berkas',
            'objek_id' => $berkas->id,
            'actor_id' => $this->actor->id,
        ]);
    }

    public function test_creates_renstra_pk_with_tautan_and_teks_attachments(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2027,
            'nomor_pk' => 'PK/LLDIKTI16/2027/002',
            'tanggal_pk' => '2027-01-15',
            'lampiran' => [
                [
                    'mode' => 'tautan',
                    'tautan' => 'https://drive.google.com/pk-2027',
                    'nama_asli' => 'Tautan Cloud PK 2027',
                ],
                [
                    'mode' => 'teks',
                    'isi_teks' => 'Ringkasan klausul komitmen kinerja 2027',
                    'nama_asli' => 'Catatan PK 2027',
                ],
            ],
        ], $this->actor);

        $this->assertCount(2, $pk->berkas);
        $this->assertDatabaseHas('berkas', [
            'berkasable_type' => 'renstra_pk',
            'berkasable_id' => $pk->id,
            'mode' => 'tautan',
            'tautan' => 'https://drive.google.com/pk-2027',
        ]);
        $this->assertDatabaseHas('berkas', [
            'berkasable_type' => 'renstra_pk',
            'berkasable_id' => $pk->id,
            'mode' => 'teks',
            'isi_teks' => 'Ringkasan klausul komitmen kinerja 2027',
        ]);
    }

    public function test_duplicate_renstra_and_year_throws_validation_exception(): void
    {
        $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-ORIGINAL',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $this->expectException(ValidationException::class);
        $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DUPLICATE',
            'tanggal_pk' => '2026-02-10',
        ], $this->actor);
    }

    public function test_year_outside_renstra_range_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2030, // Di luar rentang 2025-2029
            'nomor_pk' => 'PK-OUT-OF-BOUNDS',
            'tanggal_pk' => '2030-01-10',
        ], $this->actor);
    }

    public function test_update_renstra_pk_requires_alasan_and_records_audit(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-AWAL',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        // Update tanpa alasan harus melempar ValidationException
        try {
            $this->service->update($pk, [
                'nomor_pk' => 'PK-REVISI',
                'tanggal_pk' => '2026-01-20',
            ], '', $this->actor);
            $this->fail('Harus melempar ValidationException jika alasan kosong.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alasan', $e->errors());
        }

        // Update dengan alasan berhasil
        $updated = $this->service->update($pk, [
            'nomor_pk' => 'PK-REVISI',
            'tanggal_pk' => '2026-01-20',
        ], 'Koreksi nomor surat PK dari pimpinan', $this->actor);

        $this->assertSame('PK-REVISI', $updated->nomor_pk);
        $this->assertSame('2026-01-20', Carbon::parse($updated->tanggal_pk)->toDateString());

        // Audit log renstra_pk.ubah
        $audit = AuditLog::where('tindakan', 'renstra_pk.ubah')
            ->where('objek_id', $pk->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Koreksi nomor surat PK dari pimpinan', $audit->alasan);
        $this->assertSame('PK-AWAL', $audit->nilai_lama['nomor_pk']);
        $this->assertSame('PK-REVISI', $audit->nilai_baru['nomor_pk']);
    }

    public function test_delete_berkas_succeeds_when_jadwal_not_active(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran.pdf', 100);
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                ['mode' => 'file', 'file' => $file],
            ],
        ], $this->actor);

        $berkas = $pk->berkas->first();

        $this->service->deleteBerkas($pk, $berkas, 'File salah draf', $this->actor);

        // Berkas soft deleted
        $this->assertSoftDeleted($berkas);
        $this->assertDatabaseHas('berkas', [
            'id' => $berkas->id,
            'dihapus_oleh' => $this->actor->id,
        ]);

        // Audit log berkas.hapus
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'berkas.hapus',
            'objek_tipe' => 'berkas',
            'objek_id' => $berkas->id,
            'alasan' => 'File salah draf',
            'actor_id' => $this->actor->id,
        ]);
    }

    public function test_delete_berkas_rejected_when_jadwal_tahunan_active(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran.pdf', 100);
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                ['mode' => 'file', 'file' => $file],
            ],
        ], $this->actor);

        $berkas = $pk->berkas->first();

        // Aktifkan Jadwal Tahunan untuk tahun 2026 pada Renstra tersebut
        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        try {
            $this->service->deleteBerkas($pk, $berkas, 'Mencoba hapus saat jadwal aktif', $this->actor);
            $this->fail('Penolakan hapus lampiran PK harus melempar ValidationException ketika jadwal aktif.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('berkas', $e->errors());
        }

        // Berkas tidak terhapus
        $this->assertDatabaseHas('berkas', [
            'id' => $berkas->id,
            'dihapus_pada' => null,
        ]);

        // Audit log berkas.hapus_ditolak tercatat
        $audit = AuditLog::where('tindakan', 'berkas.hapus_ditolak')
            ->where('objek_id', $berkas->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame('Mencoba hapus saat jadwal aktif', $audit->alasan);
        $this->assertSame(['alasan_penolakan' => 'jadwal_tahunan_aktif'], $audit->nilai_baru);
    }

    public function test_delete_berkas_succeeds_when_jadwal_tahunan_in_draft_status(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran_draft.pdf', 100);
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-DRAFT',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                ['mode' => 'file', 'file' => $file],
            ],
        ], $this->actor);

        $berkas = $pk->berkas->first();

        // Buat Jadwal Tahunan berstatus draft yang belum pernah diaktifkan
        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'draft',
            'activated_at' => null,
        ]);

        $this->service->deleteBerkas($pk, $berkas, 'Hapus lampiran selagi jadwal masih draf', $this->actor);

        $this->assertSoftDeleted($berkas);
    }

    public function test_delete_berkas_rejected_when_jadwal_tahunan_closed_after_active(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran_closed.pdf', 100);
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-CLOSED',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                ['mode' => 'file', 'file' => $file],
            ],
        ], $this->actor);

        $berkas = $pk->berkas->first();

        // Jadwal Tahunan yang sudah ditutup setelah pernah aktif
        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'ditutup',
            'activated_at' => now()->subMonths(6),
            'closed_at' => now(),
        ]);

        try {
            $this->service->deleteBerkas($pk, $berkas, 'Mencoba hapus lampiran saat jadwal ditutup', $this->actor);
            $this->fail('Penolakan hapus lampiran PK harus melempar ValidationException ketika jadwal sudah pernah aktif dan ditutup.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('berkas', $e->errors());
        }

        // Berkas tetap tidak terhapus
        $this->assertDatabaseHas('berkas', [
            'id' => $berkas->id,
            'dihapus_pada' => null,
        ]);

        // Audit log penolakan tercatat
        $audit = AuditLog::where('tindakan', 'berkas.hapus_ditolak')
            ->where('objek_id', $berkas->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(['alasan_penolakan' => 'jadwal_tahunan_aktif'], $audit->nilai_baru);
    }

    public function test_create_pk_via_service_rejected_when_actor_lacks_pk_create(): void
    {
        $pegawai = User::factory()->create(['status' => 'aktif']);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $pegawai->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-NOAUTH',
            'tanggal_pk' => '2026-01-10',
        ], $pegawai);
    }

    public function test_update_pk_via_service_rejected_when_actor_lacks_pk_update(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-INIT',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $pegawai = User::factory()->create(['status' => 'aktif']);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $pegawai->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->update($pk, [
            'nomor_pk' => 'PK-MODIFIED',
        ], 'Alasan update', $pegawai);
    }

    public function test_delete_berkas_via_service_rejected_when_actor_lacks_pk_update_or_berkas_delete(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-BERKAS',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'nama_asli' => 'Tautan Draf',
            'tautan' => 'https://example.com/draft',
            'uploaded_by' => $this->actor->id,
        ]);

        // Pegawai tidak punya pk:update maupun berkas:delete
        $pegawai = User::factory()->create(['status' => 'aktif']);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $pegawai->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        try {
            $this->service->deleteBerkas($pk, $berkas, 'Coba hapus tanpa izin', $pegawai);
            $this->fail('Actor tanpa izin harus melempar AuthorizationException.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseHas('berkas', ['id' => $berkas->id, 'dihapus_pada' => null]);
        }

        // User dengan explicit deny berkas:delete ditolak meski punya pk:update
        $userDeny = User::factory()->create(['status' => 'aktif']);
        $perencanaanRole = Role::where('kode', 'perencanaan')->firstOrFail();
        $userDeny->roles()->attach($perencanaanRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userDeny->id,
            'permission_id' => Permission::where('kode', 'berkas:delete')->value('id'),
            'unit_id' => null,
            'alasan' => 'Deny delete berkas',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        try {
            $this->service->deleteBerkas($pk, $berkas, 'Coba hapus dengan explicit deny', $userDeny);
            $this->fail('Actor dengan explicit deny berkas:delete harus ditolak.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseHas('berkas', ['id' => $berkas->id, 'dihapus_pada' => null]);
        }
    }

    public function test_simpan_lampiran_via_service_rejected_when_actor_lacks_berkas_upload(): void
    {
        $userDenyUpload = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $userDenyUpload->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userDenyUpload->id,
            'permission_id' => Permission::where('kode', 'berkas:upload')->value('id'),
            'unit_id' => null,
            'alasan' => 'Deny upload berkas',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UPLOAD-DENY',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                [
                    'mode' => 'tautan',
                    'tautan' => 'https://example.com/doc',
                    'nama_asli' => 'Doc Tautan',
                ],
            ],
        ], $userDenyUpload);
    }

    public function test_rollback_upload_dispatches_cleanup_job_when_storage_delete_fails(): void
    {
        Queue::fake([CleanupStorageFileJob::class]);

        $fakeDisk = \Mockery::mock(Filesystem::class);
        $fakeDisk->shouldReceive('delete')->with('test-orphan.pdf')->andReturn(false);
        $fakeDisk->shouldReceive('exists')->with('test-orphan.pdf')->andReturn(true);
        Storage::set('local', $fakeDisk);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hapusFile');
        $method->invoke($this->service, ['test-orphan.pdf']);

        Queue::assertPushed(CleanupStorageFileJob::class, function ($job) {
            return $job->path === 'test-orphan.pdf' && $job->disk === 'local';
        });
    }

    public function test_cleanup_storage_job_does_not_delete_file_in_use_by_active_berkas(): void
    {
        Storage::clearResolvedInstances();
        Storage::fake('local');
        $path = 'berkas/renstra_pk/active-in-use.pdf';
        Storage::disk('local')->put($path, 'data');

        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-ACTIVE-BERKAS',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $berkas = $pk->berkas()->create([
            'mode' => 'file',
            'nama_asli' => 'active.pdf',
            'path' => $path,
            'uploaded_by' => $this->actor->id,
        ]);

        // Job dijalankan saat berkas masih aktif -> file tidak boleh dihapus
        $job = new CleanupStorageFileJob($path, 'local');
        $job->handle();
        Storage::disk('local')->assertExists($path);

        // Soft delete berkas
        $berkas->delete();

        // Job dijalankan saat berkas sudah terhapus -> file berhasil dihapus
        $job->handle();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_create_pk_rejected_when_actor_explicit_deny_added_concurrently_inside_transaction(): void
    {
        $concurrentActor = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $concurrentActor->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $injected = false;
        User::retrieved(function ($model) use ($concurrentActor, &$injected) {
            if (! $injected && $model->id === $concurrentActor->id) {
                $injected = true;
                DB::table('user_permission_denied')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $concurrentActor->id,
                    'permission_id' => Permission::where('kode', 'pk:create')->value('id'),
                    'unit_id' => null,
                    'alasan' => 'Deny concurrent saat transaksi berjalan',
                    'ditetapkan_oleh' => $this->actor->id,
                    'created_at' => now(),
                ]);
            }
        });

        try {
            $this->service->create([
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-CONCURRENT-CREATE',
                'tanggal_pk' => '2026-01-10',
            ], $concurrentActor);
            $this->fail('Harus melempar AuthorizationException karena permission ditolak di dalam transaksi.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseMissing('renstra_pk', [
                'nomor_pk' => 'PK-CONCURRENT-CREATE',
            ]);
        }
    }

    public function test_update_pk_rejected_when_actor_explicit_deny_added_concurrently_inside_transaction(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-CONCURRENT-UPDATE-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $concurrentActor = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $concurrentActor->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $injected = false;
        User::retrieved(function ($model) use ($concurrentActor, &$injected) {
            if (! $injected && $model->id === $concurrentActor->id) {
                $injected = true;
                DB::table('user_permission_denied')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $concurrentActor->id,
                    'permission_id' => Permission::where('kode', 'pk:update')->value('id'),
                    'unit_id' => null,
                    'alasan' => 'Deny concurrent update saat transaksi berjalan',
                    'ditetapkan_oleh' => $this->actor->id,
                    'created_at' => now(),
                ]);
            }
        });

        try {
            $this->service->update($pk, [
                'nomor_pk' => 'PK-CONCURRENT-UPDATE-MUTATED',
            ], 'Ubah nomor PK', $concurrentActor);
            $this->fail('Harus melempar AuthorizationException karena permission update ditolak di dalam transaksi.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseMissing('renstra_pk', [
                'nomor_pk' => 'PK-CONCURRENT-UPDATE-MUTATED',
            ]);
            $this->assertSame('PK-CONCURRENT-UPDATE-ORIG', $pk->fresh()->nomor_pk);
        }
    }

    public function test_delete_berkas_rejected_when_actor_explicit_deny_added_concurrently_inside_transaction(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-CONCURRENT-DELETE-BERKAS',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/test-concurrent-delete',
            'nama_asli' => 'Doc Test',
            'uploaded_by' => $this->actor->id,
        ]);

        $concurrentActor = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $concurrentActor->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $injected = false;
        User::retrieved(function ($model) use ($concurrentActor, &$injected) {
            if (! $injected && $model->id === $concurrentActor->id) {
                $injected = true;
                DB::table('user_permission_denied')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $concurrentActor->id,
                    'permission_id' => Permission::where('kode', 'berkas:delete')->value('id'),
                    'unit_id' => null,
                    'alasan' => 'Deny concurrent delete berkas saat transaksi berjalan',
                    'ditetapkan_oleh' => $this->actor->id,
                    'created_at' => now(),
                ]);
            }
        });

        try {
            $this->service->deleteBerkas($pk, $berkas, 'Hapus lampiran concurrent', $concurrentActor);
            $this->fail('Harus melempar AuthorizationException karena permission berkas:delete ditolak di dalam transaksi.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseHas('berkas', [
                'id' => $berkas->id,
                'dihapus_pada' => null,
            ]);
        }
    }

    public function test_update_pk_with_nul_character_in_alasan_sanitizes_safely(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-NUL-UPDATE-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $alasanWithNul = "Perubahan beralasan\0dengan karakter NUL";

        $updated = $this->service->update($pk, [
            'nomor_pk' => 'PK-NUL-UPDATE-NEW',
        ], $alasanWithNul, $this->actor);

        $this->assertSame('PK-NUL-UPDATE-NEW', $updated->nomor_pk);

        $audit = AuditLog::where('tindakan', 'renstra_pk.ubah')
            ->where('objek_id', $pk->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertStringNotContainsString("\0", $audit->alasan);
        $this->assertSame('Perubahan beralasandengan karakter NUL', $audit->alasan);
    }

    public function test_upload_berkas_dual_permission_and_composite_audit_basis(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UPLOAD-COMPOSITE',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $uploadedBerkas = $this->service->uploadBerkas($pk, [
            [
                'mode' => 'tautan',
                'tautan' => 'https://example.com/pk-composite-audit',
                'nama_asli' => 'Lampiran Composite Audit',
            ],
        ], $this->actor);

        $this->assertCount(1, $uploadedBerkas);
        $berkas = $uploadedBerkas[0];

        $audit = AuditLog::where('tindakan', 'berkas.unggah')
            ->where('objek_id', $berkas->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertIsArray($audit->dasar_izin);
        $this->assertSame('berkas:upload', $audit->dasar_izin['permission'] ?? null);
        $this->assertSame('pk:update', $audit->dasar_izin['parent_permission'] ?? null);
        $this->assertIsArray($audit->dasar_izin['basis_parent'] ?? null);
        $this->assertSame('pk:update', $audit->dasar_izin['basis_parent']['permission'] ?? null);
    }

    public function test_upload_berkas_fails_closed_when_pk_update_denied(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UPLOAD-DENY-PK-UPDATE',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $actor = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $actor->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        // Explicit deny pk:update
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'permission_id' => Permission::where('kode', 'pk:update')->value('id'),
            'unit_id' => null,
            'alasan' => 'Deny pk:update for upload test',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->uploadBerkas($pk, [
            [
                'mode' => 'tautan',
                'tautan' => 'https://example.com/should-fail',
                'nama_asli' => 'Should Fail',
            ],
        ], $actor);
    }

    public function test_upload_berkas_fails_closed_when_berkas_upload_denied(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UPLOAD-DENY-BERKAS-UPLOAD',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $actor = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $actor->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        // Explicit deny berkas:upload
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'permission_id' => Permission::where('kode', 'berkas:upload')->value('id'),
            'unit_id' => null,
            'alasan' => 'Deny berkas:upload for upload test',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->uploadBerkas($pk, [
            [
                'mode' => 'tautan',
                'tautan' => 'https://example.com/should-fail',
                'nama_asli' => 'Should Fail',
            ],
        ], $actor);
    }

    public function test_create_pk_locks_renstra_and_prevents_range_race(): void
    {
        $injected = false;
        User::retrieved(function ($model) use (&$injected) {
            if (! $injected && $model->id === $this->actor->id) {
                $injected = true;
                Renstra::where('id', $this->renstra->id)->update([
                    'tahun_selesai' => 2028,
                ]);
            }
        });

        try {
            $this->service->create([
                'renstra_id' => $this->renstra->id,
                'tahun' => 2029,
                'nomor_pk' => 'PK-RANGE-RACE',
                'tanggal_pk' => '2029-01-10',
            ], $this->actor);
            $this->fail('Harus melempar ValidationException karena Renstra direload dan divalidasi di bawah lock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tahun', $e->errors());
            $this->assertDatabaseMissing('renstra_pk', [
                'nomor_pk' => 'PK-RANGE-RACE',
            ]);
        }
    }

    public function test_concurrent_role_permission_revocation_inside_transaction(): void
    {
        $concurrentActor = User::factory()->create(['status' => 'aktif']);
        $role = Role::create([
            'kode' => 'perencana_khusus_'.Str::random(6),
            'nama' => 'Perencana Khusus',
            'keterangan' => 'Role uji concurrent revocation',
            'urutan' => 99,
            'aktif' => true,
        ]);

        $pkCreatePerm = Permission::where('kode', 'pk:create')->firstOrFail();
        DB::table('role_permissions')->insert([
            'id' => (string) Str::uuid(),
            'role_id' => $role->id,
            'permission_id' => $pkCreatePerm->id,
            'created_at' => now(),
        ]);

        $concurrentActor->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $injected = false;
        User::retrieved(function ($model) use ($concurrentActor, $role, $pkCreatePerm, &$injected) {
            if (! $injected && $model->id === $concurrentActor->id) {
                $injected = true;
                DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $pkCreatePerm->id)
                    ->delete();
            }
        });

        try {
            $this->service->create([
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-ROLE-REVOKED',
                'tanggal_pk' => '2026-01-10',
            ], $concurrentActor);
            $this->fail('Harus melempar AuthorizationException karena role permission dicabut sebelum resolusi.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseMissing('renstra_pk', [
                'nomor_pk' => 'PK-ROLE-REVOKED',
            ]);
        }
    }

    public function test_update_pk_sanitizes_malformed_utf8_and_long_alasan(): void
    {
        $pk = $this->service->create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-SANITY-ALASAN-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $oversizedAndMalformed = str_repeat('B', 1500)."\x80\x81"."\0";

        $updated = $this->service->update($pk, [
            'nomor_pk' => 'PK-SANITY-ALASAN-NEW',
        ], $oversizedAndMalformed, $this->actor);

        $this->assertSame('PK-SANITY-ALASAN-NEW', $updated->nomor_pk);

        $audit = AuditLog::where('tindakan', 'renstra_pk.ubah')
            ->where('objek_id', $pk->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertLessThanOrEqual(1000, mb_strlen($audit->alasan, 'UTF-8'));
        $this->assertStringNotContainsString("\0", $audit->alasan);
        $this->assertTrue(mb_check_encoding($audit->alasan, 'UTF-8'));
    }
}
