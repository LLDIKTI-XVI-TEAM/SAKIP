<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\CreatePerjanjianKinerja;
use App\Actions\PerjanjianKinerja\UpdatePerjanjianKinerja;
use App\Jobs\CleanupStorageFileJob;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class UpdatePerjanjianKinerjaTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Renstra $renstra;

    private CreatePerjanjianKinerja $createAction;

    private UpdatePerjanjianKinerja $updateAction;

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

        $this->createAction = app(CreatePerjanjianKinerja::class);
        $this->updateAction = app(UpdatePerjanjianKinerja::class);
    }

    public function test_update_renstra_pk_requires_alasan_and_records_audit(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-AWAL',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $expectedUpdatedAt = $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString();

        // Update tanpa alasan harus melempar ValidationException
        try {
            $this->updateAction->handle($pk, [
                'nomor_pk' => 'PK-REVISI',
                'tanggal_pk' => '2026-01-20',
                'expected_updated_at' => $expectedUpdatedAt,
            ], '', $this->actor);
            $this->fail('Harus melempar ValidationException jika alasan kosong.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alasan', $e->errors());
        }

        // Update dengan alasan berhasil
        $updated = $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-REVISI',
            'tanggal_pk' => '2026-01-20',
            'expected_updated_at' => $expectedUpdatedAt,
        ], 'Perubahan nomor PK sesuai SK terbaru', $this->actor);

        $this->assertSame('PK-REVISI', $updated->nomor_pk);
        $this->assertSame('2026-01-20', $updated->tanggal_pk->toDateString());

        // Audit log renstra_pk.ubah
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'renstra_pk.ubah',
            'objek_tipe' => 'renstra_pk',
            'objek_id' => $pk->id,
            'actor_id' => $this->actor->id,
            'alasan' => 'Perubahan nomor PK sesuai SK terbaru',
        ]);
    }

    public function test_update_pk_rejected_when_actor_lacks_pk_update(): void
    {
        $pk = $this->createAction->handle([
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
        $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-MODIFIED',
            'expected_updated_at' => $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString(),
        ], 'Alasan update', $pegawai);
    }

    public function test_update_pk_rejected_when_actor_explicit_deny_added_concurrently_inside_transaction(): void
    {
        $pk = $this->createAction->handle([
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
            $this->updateAction->handle($pk, [
                'nomor_pk' => 'PK-CONCURRENT-UPDATE-MUTATED',
                'expected_updated_at' => $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString(),
            ], 'Ubah nomor PK', $concurrentActor);
            $this->fail('Harus melempar AuthorizationException karena permission update ditolak di dalam transaksi.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseMissing('renstra_pk', [
                'nomor_pk' => 'PK-CONCURRENT-UPDATE-MUTATED',
            ]);
            $this->assertSame('PK-CONCURRENT-UPDATE-ORIG', $pk->fresh()->nomor_pk);
        }
    }

    public function test_update_pk_with_nul_character_in_alasan_sanitizes_safely(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-NUL-UPDATE-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $alasanWithNul = "Perubahan beralasan\0dengan karakter NUL";

        $updated = $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-NUL-UPDATE-NEW',
            'expected_updated_at' => $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString(),
        ], $alasanWithNul, $this->actor);

        $this->assertSame('PK-NUL-UPDATE-NEW', $updated->nomor_pk);

        $audit = AuditLog::where('tindakan', 'renstra_pk.ubah')
            ->where('objek_id', $pk->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertStringNotContainsString("\0", $audit->alasan);
        $this->assertSame('Perubahan beralasandengan karakter NUL', $audit->alasan);
    }

    public function test_update_pk_sanitizes_malformed_utf8_and_long_alasan(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-SANITY-ALASAN-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $oversizedAndMalformed = str_repeat('B', 1500)."\x80\x81"."\0";

        $updated = $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-SANITY-ALASAN-NEW',
            'expected_updated_at' => $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString(),
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

    public function test_update_pk_with_lampiran_dual_permission_and_composite_audit_basis(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UPDATE-COMPOSITE-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $updated = $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-UPDATE-COMPOSITE-NEW',
            'expected_updated_at' => $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString(),
            'lampiran' => [
                [
                    'mode' => 'tautan',
                    'tautan' => 'https://example.com/pk-composite-audit',
                    'nama_asli' => 'Lampiran Composite Audit',
                ],
            ],
        ], 'Penambahan dokumen lampiran PK', $this->actor);

        $this->assertCount(1, $updated->berkas);
        $berkas = $updated->berkas->first();

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

    public function test_update_pk_with_lampiran_rejected_when_actor_lacks_berkas_upload(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UPDATE-DENY-UPLOAD',
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
            'alasan' => 'Deny berkas:upload saat update PK',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->updateAction->handle($pk, [
            'expected_updated_at' => $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString(),
            'lampiran' => [
                [
                    'mode' => 'tautan',
                    'tautan' => 'https://example.com/should-fail',
                    'nama_asli' => 'Should Fail',
                ],
            ],
        ], 'Alasan update', $actor);
    }

    public function test_rollback_upload_dispatches_cleanup_job_when_storage_delete_fails(): void
    {
        Queue::fake([CleanupStorageFileJob::class]);

        $fakeDisk = Mockery::mock(Filesystem::class);
        $fakeDisk->shouldReceive('delete')->with('test-orphan.pdf')->andReturn(false);
        $fakeDisk->shouldReceive('exists')->with('test-orphan.pdf')->andReturn(true);
        Storage::set('local', $fakeDisk);

        /** @var PerjanjianKinerjaAttachmentService $attachmentService */
        $attachmentService = app(PerjanjianKinerjaAttachmentService::class);
        $attachmentService->hapusFile(['test-orphan.pdf']);

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

        $pk = $this->createAction->handle([
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
}
