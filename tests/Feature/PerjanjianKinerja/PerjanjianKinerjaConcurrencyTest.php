<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\CreatePerjanjianKinerja;
use App\Actions\PerjanjianKinerja\UpdatePerjanjianKinerja;
use App\Models\AuditLog;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PerjanjianKinerjaConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Renstra $renstra;

    private CreatePerjanjianKinerja $createAction;

    private UpdatePerjanjianKinerja $updateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        Storage::fake('local');

        $roleSuperadmin = Role::where('kode', 'superadmin')->firstOrFail();

        $this->userA = User::factory()->create(['status' => 'aktif', 'nama' => 'User A']);
        $this->userA->roles()->attach($roleSuperadmin->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->userA->id,
            'created_at' => now(),
        ]);

        $this->userB = User::factory()->create(['status' => 'aktif', 'nama' => 'User B']);
        $this->userB->roles()->attach($roleSuperadmin->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->userA->id,
            'created_at' => now(),
        ]);

        $this->renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->userA->id,
        ]);

        $this->createAction = app(CreatePerjanjianKinerja::class);
        $this->updateAction = app(UpdatePerjanjianKinerja::class);
    }

    /**
     * Case A: Action-level missing expected version ditolak.
     */
    public function test_case_a_action_rejects_missing_expected_updated_at(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-CASE-A-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->userA);

        try {
            $this->updateAction->handle($pk, [
                'nomor_pk' => 'PK-CASE-A-MUTATED',
                'tanggal_pk' => '2026-01-20',
                // expected_updated_at sengaja tidak disertakan
            ], 'Update tanpa expected_updated_at', $this->userA);
            $this->fail('Harus melempar ValidationException ketika expected_updated_at tidak disertakan pada level Action.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('expected_updated_at', $e->errors());
        }

        // Verifikasi database tidak termutasi
        $freshPk = $pk->fresh();
        $this->assertSame('PK-CASE-A-ORIG', $freshPk->nomor_pk);
        $this->assertSame('2026-01-10', $freshPk->tanggal_pk->toDateString());
    }

    /**
     * Case B: Action-level stale expected version (T1 vs T2) ditolak.
     * User A dan User B membuka PK pada versi yang sama (T1).
     * User A menyimpan perubahan (updated_at menjadi T2).
     * User B mengirim perubahan dengan expected_updated_at versi lama (T1).
     * User B ditolak dengan ValidationException 'konflik'.
     * Perubahan User A tetap tersimpan dan tidak ada last-write-wins overwrite.
     */
    public function test_case_b_concurrent_update_rejected_via_action_when_expected_updated_at_is_stale(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-CONCURRENCY-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->userA);

        // Kedua user membaca timestamp T1
        $versionT1 = $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString();

        // Pastikan ada jeda waktu agar timestamp update berbeda
        Carbon::setTestNow(now()->addSeconds(5));

        // User A menyimpan perubahan lebih dulu
        $updatedA = $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-UPDATE-BY-USER-A',
            'expected_updated_at' => $versionT1,
        ], 'Perubahan oleh User A', $this->userA);

        $this->assertSame('PK-UPDATE-BY-USER-A', $updatedA->nomor_pk);
        $versionT2 = $updatedA->updated_at->toISOString();
        $this->assertNotSame($versionT1, $versionT2);

        // User B mencoba submit perubahan dengan snapshot versi T1 yang sudah usang (stale)
        try {
            $this->updateAction->handle($pk, [
                'nomor_pk' => 'PK-OVERWRITE-BY-USER-B',
                'expected_updated_at' => $versionT1,
            ], 'Perubahan oleh User B yang stale', $this->userB);
            $this->fail('Harus melempar ValidationException karena data telah stale.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('konflik', $e->errors());
            $this->assertSame(
                'Data Perjanjian Kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                $e->errors()['konflik'][0]
            );
        }

        // Verifikasi state akhir: perubahan User A tetap utuh, User B tidak menimpa diam-diam
        $freshPk = $pk->fresh();
        $this->assertSame('PK-UPDATE-BY-USER-A', $freshPk->nomor_pk);

        // Verifikasi audit log: hanya tindakan User A yang tercatat berhasil
        $auditA = AuditLog::where('tindakan', 'renstra_pk.ubah')
            ->where('objek_id', $pk->id)
            ->where('actor_id', $this->userA->id)
            ->count();
        $this->assertSame(1, $auditA);

        $auditB = AuditLog::where('tindakan', 'renstra_pk.ubah')
            ->where('objek_id', $pk->id)
            ->where('actor_id', $this->userB->id)
            ->count();
        $this->assertSame(0, $auditB);

        Carbon::setTestNow();
    }

    /**
     * Case C: Action dengan current expected version (T2) berhasil.
     */
    public function test_case_c_action_succeeds_with_current_expected_updated_at(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-CASE-C-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->userA);

        $versionT1 = $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString();

        Carbon::setTestNow(now()->addSeconds(5));

        // Update pertama: T1 -> T2
        $updatedT2 = $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-CASE-C-T2',
            'expected_updated_at' => $versionT1,
        ], 'Update pertama ke versi T2', $this->userA);

        $versionT2 = $updatedT2->updated_at->toISOString();
        $this->assertSame('PK-CASE-C-T2', $updatedT2->nomor_pk);

        Carbon::setTestNow(now()->addSeconds(5));

        // Update kedua dengan expected_updated_at versi T2 yang valid dan terkini -> Berhasil
        $updatedT3 = $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-CASE-C-T3',
            'tanggal_pk' => '2026-01-25',
            'expected_updated_at' => $versionT2,
        ], 'Update kedua dengan token versi T2 terkini', $this->userB);

        $this->assertSame('PK-CASE-C-T3', $updatedT3->nomor_pk);
        $this->assertSame('2026-01-25', $updatedT3->tanggal_pk->toDateString());
        $this->assertSame('PK-CASE-C-T3', $pk->fresh()->nomor_pk);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'renstra_pk.ubah',
            'objek_id' => $pk->id,
            'actor_id' => $this->userB->id,
            'alasan' => 'Update kedua dengan token versi T2 terkini',
        ]);

        Carbon::setTestNow();
    }

    /**
     * Case D: Stale update tidak mengubah nomor_pk, tidak mengubah tanggal_pk,
     * tidak menambah attachment, dan tidak membuat success audit.
     */
    public function test_case_d_stale_update_does_not_mutate_pk_or_add_attachment_or_audit_success(): void
    {
        Storage::fake('local');

        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-CASE-D-ORIG',
            'tanggal_pk' => '2026-01-10',
        ], $this->userA);

        $initialBerkasCount = $pk->berkas()->count();
        $versionT1 = $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString();

        Carbon::setTestNow(now()->addSeconds(5));

        // User A memajukan versi ke T2
        $this->updateAction->handle($pk, [
            'nomor_pk' => 'PK-CASE-D-ADVANCED-BY-A',
            'expected_updated_at' => $versionT1,
        ], 'User A memajukan versi', $this->userA);

        $pkFreshBeforeB = $pk->fresh();
        $this->assertSame('PK-CASE-D-ADVANCED-BY-A', $pkFreshBeforeB->nomor_pk);
        $this->assertSame('2026-01-10', $pkFreshBeforeB->tanggal_pk->toDateString());

        // User B mencoba update dengan versi stale T1 dan menyertakan lampiran baru
        $fakeFile = UploadedFile::fake()->create('stale-attachment.pdf', 100, 'application/pdf');

        try {
            $this->updateAction->handle($pk, [
                'nomor_pk' => 'PK-CASE-D-OVERWRITE-ATTEMPT',
                'tanggal_pk' => '2026-02-15',
                'expected_updated_at' => $versionT1, // stale!
                'lampiran' => [
                    [
                        'mode' => 'file',
                        'file' => $fakeFile,
                        'nama_asli' => 'stale-attachment.pdf',
                    ],
                ],
            ], 'Mencoba overwrite stale dengan lampiran', $this->userB);
            $this->fail('Harus melempar ValidationException konflik.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('konflik', $e->errors());
        }

        // Verifikasi menyeluruh terhadap state database:
        $freshPk = $pk->fresh(['berkas']);

        // 1. nomor_pk tidak berubah
        $this->assertSame('PK-CASE-D-ADVANCED-BY-A', $freshPk->nomor_pk);
        // 2. tanggal_pk tidak berubah
        $this->assertSame('2026-01-10', $freshPk->tanggal_pk->toDateString());
        // 3. tidak menambah attachment di database
        $this->assertSame($initialBerkasCount, $freshPk->berkas->count());
        $this->assertDatabaseMissing('berkas', [
            'nama_asli' => 'stale-attachment.pdf',
        ]);
        // 4. file fisik tidak tersimpan di storage
        Storage::disk('local')->assertMissing('berkas/renstra_pk/stale-attachment.pdf');
        // 5. tidak membuat success audit untuk User B
        $auditCountUserB = AuditLog::where('actor_id', $this->userB->id)
            ->where('objek_id', $pk->id)
            ->count();
        $this->assertSame(0, $auditCountUserB);

        Carbon::setTestNow();
    }

    /**
     * Case E: Membuktikan skenario HTTP PUT:
     * Request membawa expected_updated_at stale.
     * Mismatch menghasilkan 422 / session error 'konflik'.
     */
    public function test_case_e_concurrent_update_rejected_via_http_put_when_expected_updated_at_is_stale(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-HTTP-CONCURRENCY-ORIG',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->userA->id,
        ]);

        $versionT1 = $pk->updated_at?->toISOString() ?? $pk->created_at->toISOString();

        Carbon::setTestNow(now()->addSeconds(10));

        // User A mengirim update sukses
        $responseA = $this->actingAs($this->userA)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-HTTP-UPDATE-USER-A',
                'tanggal_pk' => '2026-01-20',
                'alasan' => 'Update penomoran resmi oleh User A',
                'expected_updated_at' => $versionT1,
            ]);

        $responseA->assertSessionHasNoErrors();
        $responseA->assertRedirect();
        $this->assertSame('PK-HTTP-UPDATE-USER-A', $pk->fresh()->nomor_pk);

        // User B mengirim update HTML form dengan token versi lama T1 -> Redirect dengan error konflik
        $responseB = $this->actingAs($this->userB)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-HTTP-OVERWRITE-USER-B',
                'tanggal_pk' => '2026-01-25',
                'alasan' => 'Mencoba menimpa data User A',
                'expected_updated_at' => $versionT1,
            ]);

        $responseB->assertSessionHasErrors('konflik');
        $this->assertSame('PK-HTTP-UPDATE-USER-A', $pk->fresh()->nomor_pk);

        // User B mengirim update via JSON request -> 422 Unprocessable Entity
        $responseBJson = $this->actingAs($this->userB)
            ->putJson("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-HTTP-OVERWRITE-USER-B-JSON',
                'tanggal_pk' => '2026-01-25',
                'alasan' => 'Mencoba menimpa data User A via JSON',
                'expected_updated_at' => $versionT1,
            ]);

        $responseBJson->assertStatus(422);
        $responseBJson->assertJsonValidationErrors(['konflik']);
        $this->assertSame('PK-HTTP-UPDATE-USER-A', $pk->fresh()->nomor_pk);

        Carbon::setTestNow();
    }

    /**
     * Membuktikan bahwa expected_updated_at dengan format tidak valid ditolak.
     */
    public function test_update_rejected_when_expected_updated_at_is_invalid_format(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-INVALID-TIMESTAMP',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->userA->id,
        ]);

        $this->actingAs($this->userA)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-MUTATED',
                'tanggal_pk' => '2026-01-20',
                'alasan' => 'Coba update dengan timestamp rusak',
                'expected_updated_at' => 'format-bukan-tanggal-valid',
            ])
            ->assertSessionHasErrors('expected_updated_at');

        $this->assertSame('PK-INVALID-TIMESTAMP', $pk->fresh()->nomor_pk);
    }

    /**
     * Membuktikan bahwa expected_updated_at wajib disertakan pada request update HTTP.
     */
    public function test_update_rejected_when_expected_updated_at_is_missing(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-MISSING-TIMESTAMP',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $this->userA->id,
        ]);

        $this->actingAs($this->userA)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-MUTATED',
                'tanggal_pk' => '2026-01-20',
                'alasan' => 'Coba update tanpa timestamp',
            ])
            ->assertSessionHasErrors('expected_updated_at');

        $this->assertSame('PK-MISSING-TIMESTAMP', $pk->fresh()->nomor_pk);
    }
}
