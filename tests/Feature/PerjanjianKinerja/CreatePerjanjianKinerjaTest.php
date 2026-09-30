<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\CreatePerjanjianKinerja;
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
use Tests\TestCase;

class CreatePerjanjianKinerjaTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Renstra $renstra;

    private CreatePerjanjianKinerja $action;

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

        $this->action = app(CreatePerjanjianKinerja::class);
    }

    public function test_creates_renstra_pk_with_file_attachment_and_records_audit_logs(): void
    {
        $file = UploadedFile::fake()->create('perjanjian_kinerja_2026.pdf', 300, 'application/pdf');

        $pk = $this->action->handle([
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
        $pk = $this->action->handle([
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
        $this->action->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-ORIGINAL',
            'tanggal_pk' => '2026-01-10',
        ], $this->actor);

        $this->expectException(ValidationException::class);
        $this->action->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DUPLICATE',
            'tanggal_pk' => '2026-02-10',
        ], $this->actor);
    }

    public function test_year_outside_renstra_range_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->action->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2030, // Di luar rentang 2025-2029
            'nomor_pk' => 'PK-OUT-OF-BOUNDS',
            'tanggal_pk' => '2030-01-10',
        ], $this->actor);
    }

    public function test_create_pk_rejected_when_actor_lacks_pk_create(): void
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
        $this->action->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-NOAUTH',
            'tanggal_pk' => '2026-01-10',
        ], $pegawai);
    }

    public function test_simpan_lampiran_rejected_when_actor_lacks_berkas_upload(): void
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
        $this->action->handle([
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
            $this->action->handle([
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
            $this->assertDatabaseHas('audit_log', [
                'tindakan' => 'renstra_pk.buat_ditolak',
                'actor_id' => $concurrentActor->id,
            ]);
            $this->assertDatabaseMissing('audit_log', [
                'tindakan' => 'renstra_pk.buat',
            ]);
        }
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
            $this->action->handle([
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

    public function test_create_pk_with_nul_byte_in_text_lampiran_throws_validation_exception_defensively(): void
    {
        $this->expectException(ValidationException::class);
        $this->action->handle([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DEFENSIVE-NUL',
            'tanggal_pk' => '2026-01-10',
            'lampiran' => [
                [
                    'mode' => 'teks',
                    'isi_teks' => "Teks catatan dengan byte NUL \0 tidak diizinkan",
                    'nama_asli' => 'Catatan Rusak NUL',
                ],
            ],
        ], $this->actor);
    }
}
