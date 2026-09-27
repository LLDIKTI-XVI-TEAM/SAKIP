<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use App\Services\RenstraPkService;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

        $this->actor = User::factory()->create(['is_active' => true]);
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
}
