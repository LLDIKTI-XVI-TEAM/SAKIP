<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\CreatePerjanjianKinerja;
use App\Actions\PerjanjianKinerja\DeleteBerkasPerjanjianKinerja;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DeleteBerkasPerjanjianKinerjaTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Renstra $renstra;

    private CreatePerjanjianKinerja $createAction;

    private DeleteBerkasPerjanjianKinerja $deleteAction;

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
        $this->deleteAction = app(DeleteBerkasPerjanjianKinerja::class);
    }

    public function test_delete_berkas_succeeds_when_jadwal_not_active(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran_delete.pdf', 100);
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-DEL',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                ['mode' => 'file', 'file' => $file],
            ],
        ], $this->actor);

        $berkas = $pk->berkas->first();

        $this->deleteAction->handle($pk, $berkas, 'Hapus dokumen keliru upload', $this->actor);

        $this->assertSoftDeleted($berkas);
        $this->assertNotNull($berkas->fresh()->dihapus_pada);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'berkas.hapus',
            'objek_tipe' => 'berkas',
            'objek_id' => $berkas->id,
            'actor_id' => $this->actor->id,
            'alasan' => 'Hapus dokumen keliru upload',
        ]);
    }

    public function test_delete_berkas_rejected_when_jadwal_tahunan_active(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran_active.pdf', 100);
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-ACTIVE',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                ['mode' => 'file', 'file' => $file],
            ],
        ], $this->actor);

        $berkas = $pk->berkas->first();

        // Jadwal Tahunan aktif untuk PK ini
        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        try {
            $this->deleteAction->handle($pk, $berkas, 'Mencoba hapus lampiran saat jadwal aktif', $this->actor);
            $this->fail('Penolakan hapus lampiran PK harus melempar ValidationException ketika jadwal aktif.');
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

    public function test_delete_berkas_succeeds_when_jadwal_tahunan_in_draft_status(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran_draft.pdf', 100);
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026-DRAFT',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                ['mode' => 'file', 'file' => $file],
            ],
        ], $this->actor);

        $berkas = $pk->berkas->first();

        // Jadwal Tahunan masih berstatus 'draft' (belum pernah aktif)
        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'draft',
            'activated_at' => null,
        ]);

        $this->deleteAction->handle($pk, $berkas, 'Hapus lampiran selagi jadwal masih draf', $this->actor);

        $this->assertSoftDeleted($berkas);
    }

    public function test_delete_berkas_rejected_when_jadwal_tahunan_closed_after_active(): void
    {
        $file = UploadedFile::fake()->create('pk_lampiran_closed.pdf', 100);
        $pk = $this->createAction->handle([
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
            $this->deleteAction->handle($pk, $berkas, 'Mencoba hapus lampiran saat jadwal ditutup', $this->actor);
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

    public function test_delete_berkas_rejected_when_actor_lacks_pk_update_or_berkas_delete(): void
    {
        $pk = $this->createAction->handle([
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
            $this->deleteAction->handle($pk, $berkas, 'Coba hapus tanpa izin', $pegawai);
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
            $this->deleteAction->handle($pk, $berkas, 'Coba hapus dengan explicit deny', $userDeny);
            $this->fail('Actor dengan explicit deny berkas:delete harus ditolak.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseHas('berkas', ['id' => $berkas->id, 'dihapus_pada' => null]);
        }
    }

    public function test_delete_berkas_rejected_when_actor_explicit_deny_added_concurrently_inside_transaction(): void
    {
        $pk = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-CONCURRENT-DELETE-BERKAS',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'nama_asli' => 'Lampiran Delete Concurrent',
            'tautan' => 'https://example.com/delete-concurrent',
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
                    'alasan' => 'Deny concurrent delete saat transaksi berjalan',
                    'ditetapkan_oleh' => $this->actor->id,
                    'created_at' => now(),
                ]);
            }
        });

        try {
            $this->deleteAction->handle($pk, $berkas, 'Hapus lampiran concurrent', $concurrentActor);
            $this->fail('Harus melempar AuthorizationException karena permission berkas:delete ditolak di dalam transaksi.');
        } catch (AuthorizationException $e) {
            $this->assertDatabaseHas('berkas', [
                'id' => $berkas->id,
                'dihapus_pada' => null,
            ]);
            $this->assertDatabaseHas('audit_log', [
                'tindakan' => 'berkas.hapus_ditolak',
                'objek_id' => $berkas->id,
                'actor_id' => $concurrentActor->id,
            ]);
            $this->assertDatabaseMissing('audit_log', [
                'tindakan' => 'berkas.hapus',
                'objek_id' => $berkas->id,
            ]);
        }
    }

    public function test_delete_berkas_throws_when_berkas_belongs_to_different_pk(): void
    {
        $pk1 = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DIFF-1',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $pk2 = $this->createAction->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2027,
            'nomor_pk' => 'PK-DIFF-2',
            'tanggal_pk' => '2027-01-10',
        ], $this->actor);

        $berkas2 = $pk2->berkas()->create([
            'mode' => 'tautan',
            'nama_asli' => 'Lampiran PK 2',
            'tautan' => 'https://example.com/pk2',
            'uploaded_by' => $this->actor->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Berkas bukan merupakan lampiran dari Perjanjian Kinerja ini.');
        $this->deleteAction->handle($pk1, $berkas2, 'Hapus berkas silang', $this->actor);
    }
}
