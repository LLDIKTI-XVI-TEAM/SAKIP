<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
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

/**
 * Pengujian Akseptansi End-to-End untuk Pencatatan Perjanjian Kinerja & Lampiran Legal (ISS-02.08)
 *
 * TEST-1: Pencatatan PK Valid (Model, Timestamps, Audit Log)
 * TEST-2: Penolakan Duplikasi Tahun PK (Keunikan renstra_id + tahun)
 * TEST-3: Lampiran Generic Polimorfik 3 Mode (File, Tautan, Teks)
 * TEST-4: Penghapusan Lampiran Sebelum Jadwal Aktif (Soft delete, Audit)
 * TEST-5: Guard Imutabilitas Lampiran Saat Jadwal Aktif (Penolakan & Audit Ditolak)
 * TEST-6: Otorisasi & Kontrak Akses Dokumen (Role Perencanaan, Superadmin, Pegawai)
 */
class PerjanjianKinerjaAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $perencanaan;

    private User $pegawai;

    private Renstra $renstra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        Storage::fake('local');

        // Setup Superadmin
        $this->superadmin = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $this->superadmin->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);

        // Setup Perencanaan
        $this->perencanaan = User::factory()->create(['status' => 'aktif']);
        $perencanaanRole = Role::where('kode', 'perencanaan')->firstOrFail();
        $this->perencanaan->roles()->attach($perencanaanRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);

        // Setup Pegawai
        $this->pegawai = User::factory()->create(['status' => 'aktif']);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $this->pegawai->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);

        // Setup Renstra Induk
        $this->renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Rencana Strategis LLDIKTI XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);
    }

    /**
     * TEST-1: Pencatatan PK Valid
     * Memastikan renstra_pk tersimpan dengan relasi, timestamps aktif, dan tercatat jejak audit.
     */
    public function test_1_pencatatan_pk_valid(): void
    {
        $response = $this->actingAs($this->perencanaan)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK/LLDIKTI16/2026/001',
                'tanggal_pk' => '2026-01-15',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $pk = RenstraPk::where('nomor_pk', 'PK/LLDIKTI16/2026/001')->first();
        $this->assertNotNull($pk);
        $this->assertSame($this->renstra->id, $pk->renstra_id);
        $this->assertSame(2026, $pk->tahun);
        $this->assertSame('2026-01-15', Carbon::parse($pk->tanggal_pk)->toDateString());
        $this->assertSame($this->perencanaan->id, $pk->created_by);
        $this->assertNotNull($pk->created_at);
        $this->assertNotNull($pk->updated_at);

        // Verifikasi audit log renstra_pk.buat
        $audit = AuditLog::where('tindakan', 'renstra_pk.buat')
            ->where('objek_id', $pk->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('renstra_pk', $audit->objek_tipe);
        $this->assertSame($this->perencanaan->id, $audit->actor_id);
        $this->assertSame('PK/LLDIKTI16/2026/001', $audit->nilai_baru['nomor_pk']);
        $this->assertSame(2026, $audit->nilai_baru['tahun']);
    }

    /**
     * TEST-2: Penolakan Duplikasi Tahun PK
     * Memastikan keunikan renstra_id + tahun ditegakkan dan menolak duplikasi dengan ValidationException.
     */
    public function test_2_penolakan_duplikasi_tahun_pk(): void
    {
        RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-PERTAMA',
            'tanggal_pk' => '2026-01-10',
            'created_by' => $this->perencanaan->id,
        ]);

        $countBefore = RenstraPk::count();

        $response = $this->actingAs($this->perencanaan)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK-KEDUA-DUPLIKAT',
                'tanggal_pk' => '2026-01-20',
            ]);

        $response->assertSessionHasErrors('tahun');
        $this->assertSame($countBefore, RenstraPk::count());
    }

    /**
     * TEST-3: Lampiran Generic Polimorfik 3 Mode
     * Memastikan mode file, tautan, dan teks tersimpan pada tabel berkas dengan jenis_berkas_id = null.
     */
    public function test_3_lampiran_generic_polimorfik_3_mode(): void
    {
        $file = UploadedFile::fake()->create('naskah_pk_2026.pdf', 350, 'application/pdf');

        $response = $this->actingAs($this->perencanaan)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2026,
                'nomor_pk' => 'PK/3MODE/2026',
                'tanggal_pk' => '2026-01-15',
                'lampiran' => [
                    [
                        'mode' => 'file',
                        'file' => $file,
                    ],
                    [
                        'mode' => 'tautan',
                        'tautan' => 'https://cloud.lldikti16.kemdikbud.go.id/pk-2026',
                        'nama_asli' => 'Tautan Cloud Resmi',
                    ],
                    [
                        'mode' => 'teks',
                        'isi_teks' => 'Klausul komitmen pencapaian 12 IKU Lembaga',
                        'nama_asli' => 'Catatan Ringkasan Komitmen',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();

        $pk = RenstraPk::where('nomor_pk', 'PK/3MODE/2026')->firstOrFail();
        $this->assertCount(3, $pk->berkas);

        // 1. Mode File
        $berkasFile = $pk->berkas->firstWhere('mode', 'file');
        $this->assertNotNull($berkasFile);
        $this->assertSame('renstra_pk', $berkasFile->berkasable_type);
        $this->assertSame($pk->id, $berkasFile->berkasable_id);
        $this->assertNull($berkasFile->jenis_berkas_id);
        $this->assertSame('naskah_pk_2026.pdf', $berkasFile->nama_asli);
        $this->assertTrue(Storage::disk('local')->exists($berkasFile->path));

        // 2. Mode Tautan
        $berkasTautan = $pk->berkas->firstWhere('mode', 'tautan');
        $this->assertNotNull($berkasTautan);
        $this->assertSame('https://cloud.lldikti16.kemdikbud.go.id/pk-2026', $berkasTautan->tautan);
        $this->assertNull($berkasTautan->jenis_berkas_id);

        // 3. Mode Teks
        $berkasTeks = $pk->berkas->firstWhere('mode', 'teks');
        $this->assertNotNull($berkasTeks);
        $this->assertSame('Klausul komitmen pencapaian 12 IKU Lembaga', $berkasTeks->isi_teks);
        $this->assertNull($berkasTeks->jenis_berkas_id);

        // Verifikasi audit log unggah berkas
        $auditCount = AuditLog::where('tindakan', 'berkas.unggah')
            ->whereIn('objek_id', $pk->berkas->pluck('id'))
            ->count();
        $this->assertSame(3, $auditCount);
    }

    /**
     * TEST-4: Penghapusan Lampiran Sebelum Jadwal Aktif
     * Memastikan penghapusan lampiran berkas berhasil saat jadwal belum aktif, dengan soft delete & audit log.
     */
    public function test_4_penghapusan_lampiran_sebelum_jadwal_aktif(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-HAPUS-TEST',
            'tanggal_pk' => '2026-01-10',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/draft-pk',
            'nama_asli' => 'Draf Usulan PK',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Draf digantikan oleh naskah bertandatangan basah',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Berkas soft deleted
        $this->assertSoftDeleted($berkas);
        $this->assertDatabaseHas('berkas', [
            'id' => $berkas->id,
            'dihapus_oleh' => $this->perencanaan->id,
        ]);

        // Audit log berkas.hapus
        $audit = AuditLog::where('tindakan', 'berkas.hapus')
            ->where('objek_id', $berkas->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Draf digantikan oleh naskah bertandatangan basah', $audit->alasan);
    }

    /**
     * TEST-5: Guard Imutabilitas Lampiran Saat Jadwal Aktif
     * Memastikan penolakan penghapusan berkas saat jadwal tahunan aktif, berkas utuh, dan tercatat berkas.hapus_ditolak.
     */
    public function test_5_guard_imutabilitas_lampiran_saat_jadwal_aktif(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-LOCKED-TEST',
            'tanggal_pk' => '2026-01-10',
            'created_by' => $this->perencanaan->id,
        ]);

        $berkas = $pk->berkas()->create([
            'mode' => 'tautan',
            'tautan' => 'https://example.com/legal-pk',
            'nama_asli' => 'Dokumen Hukum Formal PK',
            'uploaded_by' => $this->perencanaan->id,
        ]);

        // Aktifkan Jadwal Tahunan
        JadwalTahunan::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($this->perencanaan)
            ->delete("/perjanjian-kinerja/{$pk->id}/berkas/{$berkas->id}", [
                'alasan' => 'Mencoba menghapus lampiran saat jadwal aktif',
            ]);

        $response->assertSessionHasErrors('berkas');

        // Berkas tetap tidak terhapus (dihapus_pada null)
        $this->assertDatabaseHas('berkas', [
            'id' => $berkas->id,
            'dihapus_pada' => null,
        ]);

        // Audit log penolakan tercatat
        $audit = AuditLog::where('tindakan', 'berkas.hapus_ditolak')
            ->where('objek_id', $berkas->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->perencanaan->id, $audit->actor_id);
        $this->assertSame('Mencoba menghapus lampiran saat jadwal aktif', $audit->alasan);
        $this->assertSame(['alasan_penolakan' => 'jadwal_tahunan_aktif'], $audit->nilai_baru);
    }

    /**
     * TEST-6: Otorisasi & Kontrak Akses Dokumen
     * Memastikan role pegawai ditolak mutasi (403) namun diizinkan melihat (200),
     * sedangkan role superadmin & perencanaan diizinkan penuh (200/redirect).
     */
    public function test_6_otorisasi_dan_kontrak_akses_dokumen(): void
    {
        $pk = RenstraPk::create([
            'renstra_id' => $this->renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-AUTH-TEST',
            'tanggal_pk' => '2026-01-10',
            'created_by' => $this->perencanaan->id,
        ]);

        // Pegawai: Melihat diizinkan
        $this->actingAs($this->pegawai)
            ->get('/perjanjian-kinerja')
            ->assertOk();

        $this->actingAs($this->pegawai)
            ->get("/perjanjian-kinerja/{$pk->id}")
            ->assertOk();

        // Pegawai: Mutasi ditolak 403 Forbidden
        $this->actingAs($this->pegawai)
            ->get('/perjanjian-kinerja/create')
            ->assertForbidden();

        $this->actingAs($this->pegawai)
            ->post('/perjanjian-kinerja', [
                'renstra_id' => $this->renstra->id,
                'tahun' => 2027,
                'nomor_pk' => 'PK-PEGAWAI-DENIED',
                'tanggal_pk' => '2027-01-10',
            ])
            ->assertForbidden();

        $this->actingAs($this->pegawai)
            ->get("/perjanjian-kinerja/{$pk->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->pegawai)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-PEGAWAI-DENIED-2',
                'tanggal_pk' => '2026-01-15',
                'alasan' => 'Alasan pegawai',
            ])
            ->assertForbidden();

        // Superadmin & Perencanaan: Diizinkan mutasi
        $this->actingAs($this->superadmin)
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => 'PK-SUPERADMIN-ALLOWED',
                'tanggal_pk' => '2026-01-20',
                'alasan' => 'Penyesuaian nomor oleh Superadmin',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('PK-SUPERADMIN-ALLOWED', $pk->fresh()->nomor_pk);
    }
}
