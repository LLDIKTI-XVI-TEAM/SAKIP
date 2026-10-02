<?php

namespace Tests\Feature\Perencanaan;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RolePermissionPresets;
use App\Services\Kinerja\IndikatorPerhitunganService;
use App\Services\Perencanaan\IndikatorArsipGuard;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SasaranIndikatorTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    private User $pegawai;

    private Renstra $renstra;

    private Unit $unit;

    private Regulasi $regulasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogSeeder::class);
        $this->pasangPresetRole('perencanaan');
        $this->pasangPresetRole('pegawai');

        $this->perencanaan = $this->buatUserDenganRole('perencanaan', 'perencanaan-test@sakip.test');
        $this->pegawai = $this->buatUserDenganRole('pegawai', 'pegawai-test@sakip.test');

        $this->renstra = Renstra::create([
            'kode' => 'RENSTRA-2025-2029',
            'nama' => 'Renstra LLDIKTI Wilayah XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $this->unit = Unit::create([
            'nama' => 'Bagian Tata Usaha',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $this->regulasi = Regulasi::create([
            'jenis' => 'kepmen',
            'nomor' => '358/M/KEP/2025',
            'tahun' => 2025,
            'tentang' => 'Indikator Kinerja Utama Perguruan Tinggi',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);
    }

    public function test_akses_halaman_index_sasaran_indikator(): void
    {
        $response = $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Perencanaan/SasaranIndikator/Index')
            ->has('renstras')
            ->has('sasarans')
            ->has('units')
            ->has('regulasis')
            ->has('can.komponen_read')
            ->where('can.sasaran_create', true)
            ->where('can.indikator_create', true)
        );
    }

    public function test_1_create_sasaran_valid_menghasilkan_data_dan_audit_log(): void
    {
        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Meningkatkan Tata Kelola Kelembagaan yang Akuntabel dan Efektif',
            'urutan' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sasaran_strategis', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Meningkatkan Tata Kelola Kelembagaan yang Akuntabel dan Efektif',
            'urutan' => 1,
        ]);

        $sasaran = SasaranStrategis::where('kode', 'SS-01')->firstOrFail();
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'sasaran.buat',
            'objek_tipe' => 'sasaran',
            'objek_id' => $sasaran->id,
            'actor_id' => $this->perencanaan->id,
        ]);
    }

    public function test_2_create_indikator_valid_dengan_seluruh_atribut_utama(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Strategis Pertama',
            'urutan' => 1,
        ]);

        // Opsi A atomik (R3-01): create nonmanual tanpa komponen ditolak
        // sehingga create valid memakai manual; transisi ke nonmanual hanya
        // via endpoint formula atomik (diuji pada test R3-01 tersendiri).
        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-01',
            'nama' => 'Persentase PTS Terakreditasi Unggul',
            'definisi_operasional' => 'Jumlah PTS akreditasi Unggul dibagi total PTS dikali 100%',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'presisi' => 2,
            'wajib_catatan' => true,
            'regulasi_id' => $this->regulasi->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-01',
            'nama' => 'Persentase PTS Terakreditasi Unggul',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'presisi' => 2,
            'wajib_catatan' => true,
            'regulasi_id' => $this->regulasi->id,
            'status' => 'aktif',
        ]);

        $indikator = IndikatorKinerja::where('kode', 'IKU-01')->firstOrFail();
        $this->assertSame(2025, $indikator->tahun_mulai_berlaku);
        $this->assertSame($this->perencanaan->id, $indikator->created_by);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.buat',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikator->id,
            'actor_id' => $this->perencanaan->id,
        ]);
    }

    public function test_3_indikator_tanpa_unit_id_ditolak_validasi(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Pertama',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-NO-UNIT',
            'nama' => 'Indikator Tanpa Unit',
            'satuan' => '%',
            'unit_id' => (string) Str::uuid(), // non-existent unit
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $response->assertSessionHasErrors(['unit_id']);
    }

    public function test_4_enum_arah_dan_tipe_perhitungan_invalid_ditolak(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Pertama',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-INVALID',
            'nama' => 'Indikator Invalid Enum',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'tidak_valid',
            'tipe_perhitungan' => 'rumus_acak',
        ]);

        $response->assertSessionHasErrors(['arah', 'tipe_perhitungan']);
    }

    public function test_5_created_by_role_terisi_otomatis_dari_role_aktif_pengguna(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Pertama',
            'urutan' => 1,
        ]);

        $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-ROLE-TEST',
            'nama' => 'Indikator Role Test',
            'satuan' => 'Dokumen',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $indikator = IndikatorKinerja::where('kode', 'IKU-ROLE-TEST')->firstOrFail();
        $this->assertSame('perencanaan', $indikator->created_by_role);
    }

    public function test_5b_created_by_role_mencatat_role_efektif_aktor_non_perencanaan(): void
    {
        $this->pasangPresetRole('superadmin');
        $superadmin = $this->buatUserDenganRole('superadmin', 'superadmin-test@sakip.test');

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SUPERADMIN',
            'deskripsi' => 'Sasaran Superadmin',
            'urutan' => 2,
        ]);

        $this->actingAs($superadmin)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SUPERADMIN-TEST',
            'nama' => 'Indikator Dibuat Superadmin',
            'satuan' => 'Laporan',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $indikator = IndikatorKinerja::where('kode', 'IKU-SUPERADMIN-TEST')->firstOrFail();
        $this->assertSame('superadmin', $indikator->created_by_role);
    }

    public function test_5c_omission_created_by_role_ditolak_fail_closed(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-OMISSION',
            'deskripsi' => 'Sasaran Omission Test',
            'urutan' => 3,
        ]);

        // Pembuatan model tanpa created_by_role harus gagal secara fail-closed
        $this->expectException(\InvalidArgumentException::class);
        IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-OMIT',
            'nama' => 'Indikator Tanpa Role',
            'satuan' => 'Poin',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => $this->renstra->tahun_mulai,
            'created_by' => $this->perencanaan->id,
        ]);
    }

    public function test_5d_role_tidak_valid_ditolak(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-INVALID-ROLE',
            'deskripsi' => 'Sasaran Invalid Role',
            'urutan' => 4,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-BAD-ROLE',
            'nama' => 'Indikator Bad Role',
            'satuan' => 'Poin',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => $this->renstra->tahun_mulai,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'role_khayalan',
        ]);
    }

    public function test_5e_provenance_legacy_unknown_ditolak_pada_setiap_insert_baru(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-LEGACY',
            'deskripsi' => 'Sasaran Legacy Test',
            'urutan' => 5,
        ]);

        // 1. Model baru menolak sentinel legacy_unknown
        $threwModel = false;
        try {
            IndikatorKinerja::create([
                'sasaran_strategis_id' => $sasaran->id,
                'kode' => 'IKU-LEGACY',
                'nama' => 'Indikator Legacy Sentinel',
                'satuan' => 'Poin',
                'unit_id' => $this->unit->id,
                'arah' => 'naik_baik',
                'tipe_perhitungan' => 'manual',
                'status' => 'aktif',
                'tahun_mulai_berlaku' => $this->renstra->tahun_mulai,
                'created_by' => $this->perencanaan->id,
                'created_by_role' => IndikatorKinerja::PROVENANCE_LEGACY_UNKNOWN,
            ]);
        } catch (\InvalidArgumentException $e) {
            $threwModel = true;
        }
        $this->assertTrue($threwModel, 'Model harus menolak pembuatan baru dengan sentinel legacy_unknown.');

        // 2. Query builder / direct SQL INSERT juga ditolak oleh trigger database
        $threwDb = false;
        try {
            DB::table('indikator_kinerjas')->insert([
                'id' => (string) Str::uuid(),
                'sasaran_strategis_id' => $sasaran->id,
                'kode' => 'IKU-LEGACY-OK',
                'nama' => 'Indikator Legacy Valid DB',
                'satuan' => 'Poin',
                'unit_id' => $this->unit->id,
                'arah' => 'naik_baik',
                'tipe_perhitungan' => 'manual',
                'status' => 'aktif',
                'tahun_mulai_berlaku' => $this->renstra->tahun_mulai,
                'created_by' => $this->perencanaan->id,
                'created_by_role' => IndikatorKinerja::PROVENANCE_LEGACY_UNKNOWN,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $threwDb = true;
            $this->assertSame('23514', $e->getCode());
        }
        $this->assertTrue($threwDb, 'Database trigger harus menolak INSERT baru dengan sentinel legacy_unknown.');
    }

    public function test_5f_created_by_role_bersifat_immutable_pada_model(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-IMMUTABLE',
            'deskripsi' => 'Sasaran Immutable Test',
            'urutan' => 6,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-IMMUTABLE',
            'nama' => 'Indikator Immutable Role',
            'satuan' => 'Poin',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $this->expectException(\LogicException::class);
        $indikator->update(['created_by_role' => 'admin']);
    }

    public function test_5g_created_by_role_bersifat_immutable_pada_database_trigger(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-IMMUTABLE-DB',
            'deskripsi' => 'Sasaran Immutable DB Test',
            'urutan' => 7,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-IMMUTABLE-DB',
            'nama' => 'Indikator Immutable DB Role',
            'satuan' => 'Poin',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $this->expectException(QueryException::class);
        DB::table('indikator_kinerjas')->where('id', $indikator->id)->update(['created_by_role' => 'admin']);
    }

    public function test_6_regulasi_id_nullable_dan_perubahan_rujukan_dicatat_pada_audit(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Pertama',
            'urutan' => 1,
        ]);

        $regulasiBaru = Regulasi::create([
            'jenis' => 'permen',
            'nomor' => '10/2026',
            'tahun' => 2026,
            'tentang' => 'Standar Nasional Pendidikan Tinggi',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-TEST',
            'nama' => 'Indikator Regulasi Test',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Update indikator mengganti regulasi_id
        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-TEST',
            'nama' => 'Indikator Regulasi Test Diperbarui',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $regulasiBaru->id,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'id' => $indikator->id,
            'regulasi_id' => $regulasiBaru->id,
        ]);

        $audit = AuditLog::where('objek_id', $indikator->id)
            ->where('tindakan', 'indikator.ubah')
            ->latest('waktu')
            ->firstOrFail();

        $this->assertStringContainsString('Perubahan regulasi_id', $audit->alasan);

        // Update indikator menjadi tanpa regulasi (null)
        $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-TEST',
            'nama' => 'Indikator Regulasi Test Null',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => null,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $this->assertDatabaseHas('indikator_kinerjas', [
            'id' => $indikator->id,
            'regulasi_id' => null,
        ]);
    }

    public function test_7_unauthorized_direct_request_ditolak_403(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Pertama',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-AUTH-TEST',
            'nama' => 'Indikator Auth Test',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Pegawai tidak memiliki permission sasaran:create
        $this->actingAs($this->pegawai)
            ->post('/perencanaan/sasaran', [
                'renstra_id' => $this->renstra->id,
                'kode' => 'SS-FORBIDDEN',
                'deskripsi' => 'Terlarang',
                'urutan' => 99,
            ])
            ->assertForbidden();

        // Pegawai tidak memiliki permission indikator:create
        $this->actingAs($this->pegawai)
            ->post('/perencanaan/indikator', [
                'sasaran_strategis_id' => $sasaran->id,
                'kode' => 'IKU-FORBIDDEN',
                'nama' => 'Terlarang',
                'satuan' => '%',
                'unit_id' => $this->unit->id,
                'arah' => 'naik_baik',
                'tipe_perhitungan' => 'manual',
            ])
            ->assertForbidden();

        // Pegawai tidak memiliki permission sasaran:delete
        $this->actingAs($this->pegawai)
            ->delete("/perencanaan/sasaran/{$sasaran->id}", [
                'alasan' => 'Mencoba menghapus sasaran secara ilegal',
            ])
            ->assertForbidden();

        // Pegawai tidak memiliki permission indikator:delete
        $this->actingAs($this->pegawai)
            ->delete("/perencanaan/indikator/{$indikator->id}", [
                'alasan' => 'Mencoba menghapus indikator secara ilegal',
            ])
            ->assertForbidden();
    }

    public function test_8_destroy_indikator_selalu_mengarsipkan_tanpa_hapus_fisik(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Pertama',
            'urutan' => 1,
        ]);

        $indikatorDenganTarget = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-HAS-TARGET',
            'nama' => 'Indikator dengan Dependensi Target',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        TargetKinerja::create([
            'indikator_kinerja_id' => $indikatorDenganTarget->id,
            'tahun' => 2025,
            'target_tahunan' => 85.00,
            'target_tw1' => 20.00,
            'target_tw2' => 40.00,
            'target_tw3' => 60.00,
            'target_tw4' => 85.00,
        ]);

        // Arsipkan indikator yang punya dependensi: baris dipertahankan dengan status = arsip
        $response = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikatorDenganTarget->id}", [
            'alasan' => 'Indikator diarsipkan karena perubahan struktur Renstra',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'id' => $indikatorDenganTarget->id,
            'status' => 'arsip',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.arsipkan',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorDenganTarget->id,
            'actor_id' => $this->perencanaan->id,
        ]);

        // Indikator tanpa dependensi pun diarsipkan (never-delete mutlak), bukan hapus fisik
        $indikatorPolos = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-POLOS',
            'nama' => 'Indikator Polos Tanpa Dependensi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $deleteResponse = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikatorPolos->id}", [
            'alasan' => 'Indikator polos diarsipkan oleh perencanaan',
        ]);

        $deleteResponse->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'id' => $indikatorPolos->id,
            'status' => 'arsip',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.arsipkan',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorPolos->id,
            'actor_id' => $this->perencanaan->id,
        ]);
        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.hapus',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorPolos->id,
        ]);
    }

    public function test_guard_arsip_menolak_pembuatan_pengukuran_dan_rencana_aksi_baru(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-ARSIP-GUARD',
            'deskripsi' => 'Sasaran Guard Arsip',
            'urutan' => 1,
        ]);

        $indikatorAktif = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-ARSIP-AKTIF',
            'nama' => 'Indikator Aktif Untuk Guard',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $indikatorArsip = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-ARSIP',
            'nama' => 'Indikator Diarsipkan Untuk Guard',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'status' => 'arsip',
        ]);

        $guard = app(IndikatorArsipGuard::class);

        // Indikator aktif lolos untuk kedua jenis pembuatan baru
        $guard->pastikanDapatDibuatkan($indikatorAktif, 'pengukuran');
        $guard->pastikanDapatDibuatkan($indikatorAktif, 'rencana_aksi');

        // Indikator arsip ditolak untuk kedua jenis, tanpa pengecualian peran
        foreach (['pengukuran', 'rencana_aksi'] as $jenis) {
            try {
                $guard->pastikanDapatDibuatkan($indikatorArsip, $jenis);
                $this->fail("Guard harus menolak pembuatan {$jenis} baru untuk indikator arsip.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('indikator_id', $e->errors());
                $this->assertStringContainsString('diarsipkan', $e->errors()['indikator_id'][0]);
            }
        }

        // Guard tidak memutasi baris indikator
        $this->assertSame('arsip', $indikatorArsip->fresh()->status);
    }

    public function test_pencegahan_hapus_sasaran_yang_memiliki_indikator(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-WITH-CHILD',
            'deskripsi' => 'Sasaran dengan Indikator Anak',
            'urutan' => 1,
        ]);

        $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-CHILD',
            'nama' => 'Indikator Anak',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/perencanaan/sasaran/{$sasaran->id}", [
            'alasan' => 'Mencoba menghapus sasaran yang masih memiliki indikator',
        ]);

        $response->assertSessionHasErrors(['sasaran']);
        $this->assertDatabaseHas('sasaran_strategis', [
            'id' => $sasaran->id,
        ]);

        $audit = AuditLog::where('tindakan', 'sasaran.hapus_ditolak')
            ->where('objek_id', (string) $sasaran->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('sasaran', $audit->objek_tipe);
        $this->assertSame('sasaran.hapus_ditolak', $audit->tindakan);
        $this->assertSame('diizinkan', $audit->dasar_izin['keputusan'] ?? null);
    }

    public function test_explicit_deny_regulasi_read_hides_catalog_and_relation_details(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-REG-DENY',
            'deskripsi' => 'Sasaran Regulasi Deny Test',
            'urutan' => 1,
        ]);

        $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'regulasi_id' => $this->regulasi->id,
            'kode' => 'IKU-REG-DENY',
            'nama' => 'Indikator dengan Regulasi Rujukan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Berikan explicit deny regulasi:read pada user perencanaan
        $regulasiPermission = Permission::where('kode', 'regulasi:read')->firstOrFail();
        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $regulasiPermission->id,
            'unit_id' => null,
            'alasan' => 'Dilarang melihat regulasi untuk pengujian',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Perencanaan/SasaranIndikator/Index')
            ->where('can.regulasi_read', false)
            ->where('regulasis', [])
            ->where('sasarans.0.indikator_kinerjas.0.regulasi', null)
            ->where('sasarans.0.indikator_kinerjas.0.regulasi_id', null)
        );
    }

    public function test_update_indikator_preserves_unsubmitted_fields(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-PRESERVE',
            'deskripsi' => 'Sasaran Preserve Test',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PRESERVE',
            'nama' => 'Nama Awal',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'presisi' => 3,
            'desimal_tampilan' => 4,
            'wajib_catatan' => true,
            'jenis_agregasi' => 'rata_rata',
            'status' => 'arsip',
            'created_by_role' => 'perencanaan',
        ]);

        // Submit pembaruan tanpa menyertakan desimal_tampilan, jenis_agregasi, maupun status
        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PRESERVE',
            'nama' => 'Nama Baru Diperbarui',
            'satuan' => 'Dokumen',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $indikator->refresh();

        $this->assertSame('Nama Baru Diperbarui', $indikator->nama);
        $this->assertSame('Dokumen', $indikator->satuan);
        $this->assertSame('arsip', $indikator->status, 'status harus tetap arsip dan tidak diaktifkan ulang secara otomatis');
        $this->assertSame(4, $indikator->desimal_tampilan, 'desimal_tampilan harus dipertahankan');
        $this->assertSame('rata_rata', $indikator->jenis_agregasi, 'jenis_agregasi harus dipertahankan');
        $this->assertTrue($indikator->wajib_catatan, 'wajib_catatan harus dipertahankan');
    }

    public function test_destroy_indikator_with_extended_dependencies_mengarsipkan_dengan_aman(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-DEP-EXT',
            'deskripsi' => 'Sasaran Dependency Extended Test',
            'urutan' => 1,
        ]);

        // 1. Dependensi penanggung_jawab
        $indikatorPic = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PIC',
            'nama' => 'Indikator dengan PIC',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        DB::table('penanggung_jawab')->insert([
            'id' => (string) Str::uuid(),
            'indikator_id' => $indikatorPic->id,
            'user_id' => $this->perencanaan->id,
            'tanggal_mulai_berlaku' => now()->toDateString(),
            'ditetapkan_oleh' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $responsePic = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikatorPic->id}", [
            'alasan' => 'Mencoba hapus indikator yang memiliki penanggung jawab',
        ]);

        $responsePic->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'id' => $indikatorPic->id,
            'status' => 'arsip',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.arsipkan',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorPic->id,
        ]);

        // 2. Dependensi jenis_berkas
        $indikatorBerkas = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-BERKAS',
            'nama' => 'Indikator dengan Jenis Berkas',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        DB::table('jenis_berkas')->insert([
            'id' => (string) Str::uuid(),
            'nama' => 'Laporan Hasil Evaluasi',
            'tahap' => 'pengukuran',
            'indikator_id' => $indikatorBerkas->id,
            'created_by' => $this->perencanaan->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $responseBerkas = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikatorBerkas->id}", [
            'alasan' => 'Mencoba hapus indikator yang memiliki jenis berkas',
        ]);

        $responseBerkas->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'id' => $indikatorBerkas->id,
            'status' => 'arsip',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.arsipkan',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorBerkas->id,
        ]);
    }

    public function test_inactive_unit_rejected_on_store_and_update_transfer(): void
    {
        $inactiveUnit = Unit::create([
            'nama' => 'Unit Nonaktif',
            'status' => 'nonaktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-UNIT-TEST',
            'deskripsi' => 'Sasaran Unit Test',
            'urutan' => 1,
        ]);

        // 1. Create indikator dengan unit nonaktif harus ditolak
        $createResponse = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-BAD-UNIT',
            'nama' => 'Indikator Unit Nonaktif',
            'satuan' => '%',
            'unit_id' => $inactiveUnit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $createResponse->assertSessionHasErrors(['unit_id']);

        // 2. Buat indikator dengan unit aktif terlebih dahulu
        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UNIT-VALID',
            'nama' => 'Indikator Awal',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // 3. Update memindahkan kepemilikan ke unit nonaktif harus ditolak
        $updateTransferResponse = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UNIT-VALID',
            'nama' => 'Indikator Dipindah ke Nonaktif',
            'satuan' => '%',
            'unit_id' => $inactiveUnit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $updateTransferResponse->assertSessionHasErrors(['unit_id']);

        // 4. Update tanpa memindahkan unit tetap diperbolehkan
        $updateSameResponse = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UNIT-VALID',
            'nama' => 'Indikator Nama Baru',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $updateSameResponse->assertRedirect();
        $indikator->refresh();
        $this->assertSame('Indikator Nama Baru', $indikator->nama);
    }

    public function test_destroy_request_validates_minimum_reason_length(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-REASON-TEST',
            'deskripsi' => 'Sasaran Reason Test',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REASON-TEST',
            'nama' => 'Indikator Reason Test',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Hapus indikator tanpa alasan atau alasan pendek (< 10 karakter)
        $shortIndikator = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikator->id}", [
            'alasan' => 'pendek',
        ]);
        $shortIndikator->assertSessionHasErrors(['alasan']);

        // Hapus sasaran tanpa alasan atau alasan pendek (< 10 karakter)
        $shortSasaran = $this->actingAs($this->perencanaan)->delete("/perencanaan/sasaran/{$sasaran->id}", [
            'alasan' => 'pendek',
        ]);
        $shortSasaran->assertSessionHasErrors(['alasan']);
    }

    public function test_denied_create_logs_audit_with_correlation_id_and_basis(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-DENIED-TEST',
            'deskripsi' => 'Sasaran Denied Test',
            'urutan' => 1,
        ]);

        // Berikan explicit deny indikator:create ke user perencanaan
        $permIndikatorCreate = Permission::where('kode', 'indikator:create')->firstOrFail();
        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $permIndikatorCreate->id,
            'unit_id' => null,
            'alasan' => 'Deny pembuatan indikator untuk pengujian audit',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);

        $responseIndikator = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-DENIED',
            'nama' => 'Indikator Denied',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $responseIndikator->assertForbidden();

        $auditIndikator = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->first();
        $this->assertNotNull($auditIndikator);
        $this->assertSame('indikator', $auditIndikator->objek_tipe);
        $this->assertTrue(Str::isUuid($auditIndikator->objek_id));
        $this->assertNotNull($auditIndikator->dasar_izin);
        $this->assertSame('ditolak', $auditIndikator->dasar_izin['keputusan'] ?? null);

        // Berikan explicit deny sasaran:create ke user perencanaan
        $permSasaranCreate = Permission::where('kode', 'sasaran:create')->firstOrFail();
        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $permSasaranCreate->id,
            'unit_id' => null,
            'alasan' => 'Deny pembuatan sasaran untuk pengujian audit',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);

        $responseSasaran = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-DENIED',
            'deskripsi' => 'Sasaran Denied',
            'urutan' => 2,
        ]);

        $responseSasaran->assertForbidden();

        $auditSasaran = AuditLog::where('tindakan', 'sasaran.buat_ditolak')->latest('waktu')->first();
        $this->assertNotNull($auditSasaran);
        $this->assertSame('sasaran', $auditSasaran->objek_tipe);
        $this->assertTrue(Str::isUuid($auditSasaran->objek_id));
        $this->assertNotNull($auditSasaran->dasar_izin);
        $this->assertSame('ditolak', $auditSasaran->dasar_izin['keputusan'] ?? null);
    }

    public function test_store_indikator_reauthorizes_actor_inside_transaction(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-CONCURRENT',
            'deskripsi' => 'Sasaran Concurrent Test',
            'urutan' => 8,
        ]);

        $realResolver = app(PermissionResolver::class);
        $mockResolver = \Mockery::mock($realResolver)->makePartial();

        $callCount = 0;
        $mockResolver->shouldReceive('resolve')
            ->with(\Mockery::any(), PermissionCodes::INDIKATOR_CREATE)
            ->andReturnUsing(function ($user, $code) use (&$callCount, $realResolver) {
                $callCount++;
                if ($callCount === 1) {
                    return $realResolver->resolve($user, $code);
                }

                return new PermissionDecision(false, $code, [
                    'alasan' => 'concurrent_revocation',
                    'sumber_allow' => ['roles' => [], 'grants' => []],
                    'deny' => [],
                ]);
            });

        $this->app->instance(PermissionResolver::class, $mockResolver);

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-CONCURRENT',
            'nama' => 'Indikator Concurrent Revocation',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('indikator_kinerjas', [
            'kode' => 'IKU-CONCURRENT',
        ]);

        $auditDenied = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->first();
        $this->assertNotNull($auditDenied);
        $this->assertSame('concurrent_revocation', $auditDenied->dasar_izin['alasan'] ?? null);
    }

    public function test_index_sasaran_indikator_validates_renstra_id_and_handles_invalid_gracefully(): void
    {
        // 1. Query string tanpa renstra_id fallback ke Renstra aktif
        $responseNoParam = $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator');
        $responseNoParam->assertOk();

        // 2. Query string dengan format bukan UUID ditolak 404
        $responseBadFormat = $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator?renstra_id=bukan-uuid');
        $responseBadFormat->assertNotFound();

        // 3. Query string dengan parameter eksplisit tetapi kosong atau hanya spasi ditolak 404 (tidak fallback diam-diam)
        $responseEmpty = $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator?renstra_id=');
        $responseEmpty->assertNotFound();

        $responseSpaces = $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator?renstra_id=%20%20');
        $responseSpaces->assertNotFound();

        // 4. Query string dengan UUID yang tidak ada di basis data ditolak 404
        $randomUuid = (string) Str::uuid();
        $responseNotFound = $this->actingAs($this->perencanaan)->get("/perencanaan/sasaran-indikator?renstra_id={$randomUuid}");
        $responseNotFound->assertNotFound();
    }

    public function test_update_indikator_lintas_renstra_ditolak_validasi(): void
    {
        $renstraLain = Renstra::create([
            'kode' => 'RENSTRA-LAIN',
            'nama' => 'Renstra Lain 2030-2035',
            'tahun_mulai' => 2030,
            'tahun_selesai' => 2035,
            'is_aktif' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaranAsal = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-ASAL',
            'deskripsi' => 'Sasaran Asal',
            'urutan' => 1,
        ]);

        $sasaranLain = SasaranStrategis::create([
            'renstra_id' => $renstraLain->id,
            'kode' => 'SS-LAIN',
            'deskripsi' => 'Sasaran Renstra Lain',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaranAsal->id,
            'kode' => 'IKU-LINTAS',
            'nama' => 'Indikator Uji Lintas Renstra',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaranLain->id,
            'kode' => 'IKU-LINTAS',
            'nama' => 'Indikator Uji Lintas Renstra',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['sasaran_strategis_id']);
        $this->assertSame($sasaranAsal->id, $indikator->fresh()->sasaran_strategis_id);
    }

    public function test_update_umum_menolak_perubahan_unit_dan_mengarahkan_ke_endpoint_pindah_unit(): void
    {
        $unitBaru = Unit::create(['nama' => 'Unit Baru Transfer', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-TRANSFER',
            'deskripsi' => 'Sasaran Transfer Unit',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-TRANSFER',
            'nama' => 'Indikator Transfer Unit',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        // Perpindahan unit via jalur edit umum ditolak dan mengarahkan ke endpoint khusus
        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-TRANSFER',
            'nama' => 'Indikator Transfer Unit',
            'satuan' => '%',
            'unit_id' => $unitBaru->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);
        $response->assertSessionHasErrors(['unit_id']);

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('pindah unit', (string) $errors->getBag('default')->first('unit_id'));

        $this->assertSame($this->unit->id, $indikator->fresh()->unit_id);
        $this->assertSame(0, AuditLog::where('tindakan', 'indikator.pindah_unit')->where('objek_id', (string) $indikator->id)->count());
    }

    public function test_pindah_unit_via_endpoint_sukses_mencatat_audit_tunggal_tepat(): void
    {
        $unitBaru = Unit::create(['nama' => 'Unit Baru Pindah Khusus', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-PINDAH',
            'deskripsi' => 'Sasaran Pindah Unit',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PINDAH',
            'nama' => 'Indikator Pindah Unit',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $ubahSebelum = AuditLog::where('tindakan', 'indikator.ubah')->where('objek_id', (string) $indikator->id)->count();
        $alasan = 'Pemindahan tupoksi indikator ke unit baru hasil restrukturisasi.';

        $response = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/pindah-unit", [
            'unit_id' => $unitBaru->id,
            'alasan' => $alasan,
        ]);
        $response->assertRedirect();

        $fresh = $indikator->fresh();
        $this->assertSame($unitBaru->id, $fresh->unit_id);
        $this->assertSame($sasaran->id, $fresh->sasaran_strategis_id);
        $this->assertSame('IKU-PINDAH', $fresh->kode);

        $auditPindah = AuditLog::where('tindakan', 'indikator.pindah_unit')
            ->where('objek_id', (string) $indikator->id)
            ->latest('waktu')
            ->first();
        $this->assertNotNull($auditPindah);
        $this->assertSame($this->unit->id, $auditPindah->nilai_lama['unit_id']);
        $this->assertSame($this->unit->nama, $auditPindah->nilai_lama['unit_nama']);
        $this->assertSame($unitBaru->id, $auditPindah->nilai_baru['unit_id']);
        $this->assertSame($unitBaru->nama, $auditPindah->nilai_baru['unit_nama']);
        $this->assertSame($alasan, $auditPindah->alasan);

        // Delta unit tidak ditulis sebagai audit indikator.ubah
        $this->assertSame($ubahSebelum, AuditLog::where('tindakan', 'indikator.ubah')->where('objek_id', (string) $indikator->id)->count());
    }

    public function test_pindah_unit_menolak_target_nonaktif(): void
    {
        $unitNonaktif = Unit::create([
            'nama' => 'Unit Nonaktif Tujuan Pindah',
            'status' => 'nonaktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-PINDAH-NONAKTIF',
            'deskripsi' => 'Sasaran Pindah Nonaktif',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PINDAH-NONAKTIF',
            'nama' => 'Indikator Pindah Nonaktif',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $response = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/pindah-unit", [
            'unit_id' => $unitNonaktif->id,
            'alasan' => 'Mencoba pindah ke unit yang sudah nonaktif.',
        ]);

        $response->assertSessionHasErrors(['unit_id']);
        $this->assertSame($this->unit->id, $indikator->fresh()->unit_id);
        $this->assertSame(0, AuditLog::where('tindakan', 'indikator.pindah_unit')->where('objek_id', (string) $indikator->id)->count());
    }

    public function test_pindah_unit_menolak_tanpa_alasan_atau_alasan_pendek(): void
    {
        $unitBaru = Unit::create(['nama' => 'Unit Baru Tanpa Alasan', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-PINDAH-ALASAN',
            'deskripsi' => 'Sasaran Pindah Alasan',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PINDAH-ALASAN',
            'nama' => 'Indikator Pindah Alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        // Tanpa alasan ditolak validasi
        $responseTanpaAlasan = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/pindah-unit", [
            'unit_id' => $unitBaru->id,
        ]);
        $responseTanpaAlasan->assertSessionHasErrors(['alasan']);

        // Alasan < 10 karakter ditolak validasi
        $responsePendek = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/pindah-unit", [
            'unit_id' => $unitBaru->id,
            'alasan' => 'pendek',
        ]);
        $responsePendek->assertSessionHasErrors(['alasan']);

        $this->assertSame($this->unit->id, $indikator->fresh()->unit_id);
        $this->assertSame(0, AuditLog::where('tindakan', 'indikator.pindah_unit')->where('objek_id', (string) $indikator->id)->count());
    }

    public function test_store_indikator_fails_closed_when_allowed_only_by_direct_grant_without_role(): void
    {
        $this->pasangPresetRole('pegawai');
        $pegawai = $this->buatUserDenganRole('pegawai', 'pegawai-grant@sakip.test');

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-GRANT-ONLY',
            'deskripsi' => 'Sasaran Grant Only',
            'urutan' => 1,
        ]);

        $permIndikatorCreate = Permission::where('kode', PermissionCodes::INDIKATOR_CREATE)->firstOrFail();

        // Berikan direct grant indikator:create ke pegawai (yang role aslinya tidak punya izin ini)
        DB::table('user_permission_granted')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $pegawai->id,
            'permission_id' => $permIndikatorCreate->id,
            'unit_id' => null,
            'alasan' => 'Direct grant uji coba tanpa role yang sah',
            'diberikan_oleh' => $pegawai->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($pegawai)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-GRANT-ONLY',
            'nama' => 'Indikator Grant Only',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('indikator_kinerjas', ['kode' => 'IKU-GRANT-ONLY']);

        $audit = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->first();
        $this->assertNotNull($audit);
        $this->assertSame('diizinkan', $audit->dasar_izin['keputusan'] ?? null);
        $this->assertSame(PermissionCodes::INDIKATOR_CREATE, $audit->dasar_izin['permission'] ?? null);
        $this->assertNotEmpty($audit->dasar_izin['sumber_allow']['grants'] ?? []);
        $this->assertStringContainsString('tidak bersumber dari peran resmi', $audit->dasar_izin['penolakan_provenance'] ?? '');
        $this->assertStringContainsString('tidak bersumber dari peran resmi', $audit->alasan ?? '');
    }

    public function test_store_dan_update_menolak_pengguna_nonaktif_di_dalam_transaksi(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-INACTIVE-USER',
            'deskripsi' => 'Sasaran Inactive User',
            'urutan' => 2,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-INACTIVE-USER',
            'nama' => 'Indikator Inactive User',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        // Simulasikan race condition: pengguna aktif saat lolos middleware awal,
        // namun baris pengguna di basis data dinonaktifkan sebelum transaksi memperoleh lock.
        $this->withoutMiddleware(EnsureUserIsActive::class);
        DB::table('users')->where('id', $this->perencanaan->id)->update(['status' => 'nonaktif']);
        $this->perencanaan->status = 'aktif';

        // Store ditolak di dalam transaksi karena query lock memuat ulang status = nonaktif
        $responseStore = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-INACTIVE-NEW',
            'nama' => 'Indikator Inactive New',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);
        $responseStore->assertForbidden();

        // Update ditolak di dalam transaksi karena query lock memuat ulang status = nonaktif
        $responseUpdate = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-INACTIVE-EDIT',
            'nama' => 'Indikator Inactive Edit',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);
        $responseUpdate->assertForbidden();

        // Kembalikan status akun di basis data
        DB::table('users')->where('id', $this->perencanaan->id)->update(['status' => 'aktif']);
        $this->perencanaan->refresh();
    }

    public function test_database_trigger_menolak_pemindahan_indikator_lintas_renstra(): void
    {
        $renstraLain = Renstra::create([
            'kode' => 'RENSTRA-TRIGGER',
            'nama' => 'Renstra Trigger Test',
            'tahun_mulai' => 2035,
            'tahun_selesai' => 2040,
            'is_aktif' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaranAsal = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-ASAL-TRIG',
            'deskripsi' => 'Sasaran Asal Trigger',
            'urutan' => 1,
        ]);

        $sasaranLain = SasaranStrategis::create([
            'renstra_id' => $renstraLain->id,
            'kode' => 'SS-LAIN-TRIG',
            'deskripsi' => 'Sasaran Lain Trigger',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaranAsal->id,
            'kode' => 'IKU-TRIG-LINTAS',
            'nama' => 'Indikator Trigger Lintas',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $threw = false;
        try {
            DB::table('indikator_kinerjas')
                ->where('id', $indikator->id)
                ->update(['sasaran_strategis_id' => $sasaranLain->id]);
        } catch (QueryException $e) {
            $threw = true;
            $this->assertSame('23514', $e->getCode());
        }

        $this->assertTrue($threw, 'Trigger check_indikator_same_renstra harus melempar 23514 pada update lintas Renstra.');
    }

    public function test_update_umum_menolak_perubahan_ke_unit_tujuan_nonaktif(): void
    {
        $unitNonaktif = Unit::create([
            'nama' => 'Unit Nonaktif Target',
            'status' => 'nonaktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-UNIT-NONAKTIF',
            'deskripsi' => 'Sasaran Unit Nonaktif',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UNIT-NONAKTIF',
            'nama' => 'Indikator Unit Nonaktif',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UNIT-NONAKTIF',
            'nama' => 'Indikator Unit Nonaktif',
            'satuan' => '%',
            'unit_id' => $unitNonaktif->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['unit_id']);
        $this->assertSame($this->unit->id, $indikator->fresh()->unit_id);
    }

    public function test_update_umum_menolak_edit_gabungan_yang_memuat_perubahan_unit(): void
    {
        $unitBaru = Unit::create([
            'nama' => 'Unit Baru Gabungan',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-MERGED',
            'deskripsi' => 'Sasaran Merged Edit',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-MERGED',
            'nama' => 'Nama Awal Sebelum Gabungan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-MERGED-NEW',
            'nama' => 'Nama Baru Setelah Gabungan',
            'satuan' => '%',
            'unit_id' => $unitBaru->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        // Edit gabungan yang memuat perubahan unit ditolak utuh: tidak ada mutasi parsial dan tidak ada audit
        $response->assertSessionHasErrors(['unit_id']);

        $fresh = $indikator->fresh();
        $this->assertSame('IKU-MERGED', $fresh->kode);
        $this->assertSame('Nama Awal Sebelum Gabungan', $fresh->nama);
        $this->assertSame($this->unit->id, $fresh->unit_id);
        $this->assertSame(0, AuditLog::where('tindakan', 'indikator.pindah_unit')->where('objek_id', (string) $indikator->id)->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'indikator.ubah')->where('objek_id', (string) $indikator->id)->count());
    }

    public function test_update_indikator_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-UPDATE-DENIED',
            'deskripsi' => 'Sasaran Update Denied',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UPDATE-DENIED',
            'nama' => 'Nama Asal Tidak Boleh Berubah',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $callCount = 0;
        $mockResolver = $this->createMock(PermissionResolver::class);
        $mockResolver->method('resolve')->willReturnCallback(function ($user, $code, $unitId = null) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // Resolusi awal pada Gate / Policy diizinkan sehingga request berhasil masuk ke controller
                return new PermissionDecision(true, $code, [
                    'alasan' => 'allow',
                    'sumber_allow' => ['roles' => ['role-perencanaan-test'], 'grants' => []],
                    'deny' => [],
                ]);
            }

            // Resolusi kedua di dalam transaksi UpdateIndikator ditolak (simulasi wewenang dicabut saat transaksi)
            return new PermissionDecision(false, $code, [
                'alasan' => 'revoked_inside_transaction',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]);
        });
        $this->app->instance(PermissionResolver::class, $mockResolver);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UPDATE-DENIED-MOD',
            'nama' => 'Nama Berubah Yang Harus Ditolak',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertForbidden();
        $this->assertGreaterThanOrEqual(2, $callCount, 'PermissionResolver harus dipanggil kembali di dalam transaksi untuk otorisasi ulang.');
        $this->assertSame('Nama Asal Tidak Boleh Berubah', $indikator->fresh()->nama);

        $auditDenied = AuditLog::where('tindakan', 'indikator.ubah_ditolak')
            ->where('objek_id', (string) $indikator->id)
            ->latest('waktu')
            ->first();
        $this->assertNotNull($auditDenied);
        $this->assertSame('revoked_inside_transaction', $auditDenied->dasar_izin['alasan'] ?? null);
        $this->assertSame('ditolak', $auditDenied->dasar_izin['keputusan'] ?? null);
        $this->assertStringContainsString('wewenang tidak lagi berlaku saat transaksi', $auditDenied->alasan ?? '');
    }

    public function test_store_sasaran_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak(): void
    {
        $callCount = 0;
        $mockResolver = $this->createMock(PermissionResolver::class);
        $mockResolver->method('resolve')->willReturnCallback(function ($user, $code, $unitId = null) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // Resolusi awal pada Gate / Policy diizinkan sehingga request berhasil masuk ke controller
                return new PermissionDecision(true, $code, [
                    'alasan' => 'allow',
                    'sumber_allow' => ['roles' => ['role-perencanaan-test'], 'grants' => []],
                    'deny' => [],
                ]);
            }

            // Resolusi kedua di dalam transaksi StoreSasaran ditolak (simulasi wewenang dicabut saat transaksi)
            return new PermissionDecision(false, $code, [
                'alasan' => 'revoked_inside_transaction',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]);
        });
        $this->app->instance(PermissionResolver::class, $mockResolver);

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-STORE-DENIED',
            'deskripsi' => 'Sasaran Yang Harus Ditolak',
            'urutan' => 1,
        ]);

        $response->assertForbidden();
        $this->assertGreaterThanOrEqual(2, $callCount, 'PermissionResolver harus dipanggil kembali di dalam transaksi untuk otorisasi ulang.');
        $this->assertDatabaseMissing('sasaran_strategis', ['kode' => 'SS-STORE-DENIED']);

        $auditDenied = AuditLog::where('tindakan', 'sasaran.buat_ditolak')
            ->latest('waktu')
            ->first();
        $this->assertNotNull($auditDenied);
        $this->assertSame('revoked_inside_transaction', $auditDenied->dasar_izin['alasan'] ?? null);
        $this->assertSame('ditolak', $auditDenied->dasar_izin['keputusan'] ?? null);
        $this->assertStringContainsString('wewenang tidak lagi berlaku saat transaksi', $auditDenied->alasan ?? '');
    }

    public function test_update_sasaran_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-UPDATE-DENIED',
            'deskripsi' => 'Deskripsi Asal Tidak Boleh Berubah',
            'urutan' => 1,
        ]);

        $callCount = 0;
        $mockResolver = $this->createMock(PermissionResolver::class);
        $mockResolver->method('resolve')->willReturnCallback(function ($user, $code, $unitId = null) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // Resolusi awal pada Gate / Policy diizinkan sehingga request berhasil masuk ke controller
                return new PermissionDecision(true, $code, [
                    'alasan' => 'allow',
                    'sumber_allow' => ['roles' => ['role-perencanaan-test'], 'grants' => []],
                    'deny' => [],
                ]);
            }

            // Resolusi kedua di dalam transaksi UpdateSasaran ditolak (simulasi wewenang dicabut saat transaksi)
            return new PermissionDecision(false, $code, [
                'alasan' => 'revoked_inside_transaction',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]);
        });
        $this->app->instance(PermissionResolver::class, $mockResolver);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/sasaran/{$sasaran->id}", [
            'kode' => 'SS-UPDATE-DENIED-MOD',
            'deskripsi' => 'Deskripsi Berubah Yang Harus Ditolak',
            'urutan' => 2,
            'expected_updated_at' => $sasaran->fresh()->updated_at?->toISOString() ?? $sasaran->fresh()->created_at->toISOString(),
        ]);

        $response->assertForbidden();
        $this->assertGreaterThanOrEqual(2, $callCount, 'PermissionResolver harus dipanggil kembali di dalam transaksi untuk otorisasi ulang.');
        $this->assertSame('Deskripsi Asal Tidak Boleh Berubah', $sasaran->fresh()->deskripsi);

        $auditDenied = AuditLog::where('tindakan', 'sasaran.ubah_ditolak')
            ->where('objek_id', (string) $sasaran->id)
            ->latest('waktu')
            ->first();
        $this->assertNotNull($auditDenied);
        $this->assertSame('revoked_inside_transaction', $auditDenied->dasar_izin['alasan'] ?? null);
        $this->assertSame('ditolak', $auditDenied->dasar_izin['keputusan'] ?? null);
        $this->assertStringContainsString('wewenang tidak lagi berlaku saat transaksi', $auditDenied->alasan ?? '');
    }

    public function test_destroy_sasaran_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-DESTROY-DENIED',
            'deskripsi' => 'Sasaran Tanpa Anak Yang Harus Tetap Ada',
            'urutan' => 1,
        ]);

        $callCount = 0;
        $mockResolver = $this->createMock(PermissionResolver::class);
        $mockResolver->method('resolve')->willReturnCallback(function ($user, $code, $unitId = null) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // Resolusi awal pada Gate / Policy diizinkan sehingga request berhasil masuk ke controller
                return new PermissionDecision(true, $code, [
                    'alasan' => 'allow',
                    'sumber_allow' => ['roles' => ['role-perencanaan-test'], 'grants' => []],
                    'deny' => [],
                ]);
            }

            // Resolusi kedua di dalam transaksi DestroySasaran ditolak (simulasi wewenang dicabut saat transaksi)
            return new PermissionDecision(false, $code, [
                'alasan' => 'revoked_inside_transaction',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]);
        });
        $this->app->instance(PermissionResolver::class, $mockResolver);

        $response = $this->actingAs($this->perencanaan)->delete("/perencanaan/sasaran/{$sasaran->id}", [
            'alasan' => 'Alasan penghapusan yang cukup panjang untuk validasi.',
        ]);

        $response->assertForbidden();
        $this->assertGreaterThanOrEqual(2, $callCount, 'PermissionResolver harus dipanggil kembali di dalam transaksi untuk otorisasi ulang.');
        $this->assertDatabaseHas('sasaran_strategis', ['id' => $sasaran->id]);

        $auditDenied = AuditLog::where('tindakan', 'sasaran.hapus_ditolak')
            ->where('objek_id', (string) $sasaran->id)
            ->latest('waktu')
            ->first();
        $this->assertNotNull($auditDenied);
        $this->assertSame('revoked_inside_transaction', $auditDenied->dasar_izin['alasan'] ?? null);
        $this->assertSame('ditolak', $auditDenied->dasar_izin['keputusan'] ?? null);
        $this->assertStringContainsString('wewenang tidak lagi berlaku saat transaksi', $auditDenied->alasan ?? '');
    }

    public function test_arsip_indikator_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-ARSIP-DENIED',
            'deskripsi' => 'Sasaran Arsip Denied',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-ARSIP-DENIED',
            'nama' => 'Indikator Yang Harus Tetap Aktif',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        $callCount = 0;
        $mockResolver = $this->createMock(PermissionResolver::class);
        $mockResolver->method('resolve')->willReturnCallback(function ($user, $code, $unitId = null) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // Resolusi awal pada Gate / Policy diizinkan sehingga request berhasil masuk ke controller
                return new PermissionDecision(true, $code, [
                    'alasan' => 'allow',
                    'sumber_allow' => ['roles' => ['role-perencanaan-test'], 'grants' => []],
                    'deny' => [],
                ]);
            }

            // Resolusi kedua di dalam transaksi DestroyIndikator ditolak (simulasi wewenang dicabut saat transaksi)
            return new PermissionDecision(false, $code, [
                'alasan' => 'revoked_inside_transaction',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]);
        });
        $this->app->instance(PermissionResolver::class, $mockResolver);

        $response = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikator->id}", [
            'alasan' => 'Alasan pengarsipan yang cukup panjang untuk validasi.',
        ]);

        $response->assertForbidden();
        $this->assertGreaterThanOrEqual(2, $callCount, 'PermissionResolver harus dipanggil kembali di dalam transaksi untuk otorisasi ulang.');
        $this->assertSame('aktif', $indikator->fresh()->status);
        $this->assertSame(0, AuditLog::where('tindakan', 'indikator.arsipkan')->where('objek_id', (string) $indikator->id)->count());

        $auditDenied = AuditLog::where('tindakan', 'indikator.arsipkan_ditolak')
            ->where('objek_id', (string) $indikator->id)
            ->latest('waktu')
            ->first();
        $this->assertNotNull($auditDenied);
        $this->assertSame('revoked_inside_transaction', $auditDenied->dasar_izin['alasan'] ?? null);
        $this->assertSame('ditolak', $auditDenied->dasar_izin['keputusan'] ?? null);
        $this->assertStringContainsString('wewenang tidak lagi berlaku saat transaksi', $auditDenied->alasan ?? '');
    }

    public function test_update_indikator_mengizinkan_edit_biasa_saat_unit_saat_ini_sudah_nonaktif(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-OLD-UNIT-INACTIVE',
            'deskripsi' => 'Sasaran Old Unit Inactive',
            'urutan' => 1,
        ]);

        $unitLama = Unit::create([
            'nama' => 'Unit Lama Akan Nonaktif',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-OLD-UNIT',
            'nama' => 'Nama Sebelum Edit Saat Unit Nonaktif',
            'satuan' => '%',
            'unit_id' => $unitLama->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        // Nonaktifkan unit pemilik indikator
        DB::table('unit')->where('id', $unitLama->id)->update(['status' => 'nonaktif']);

        // 1. Edit biasa tanpa memindahkan unit_id harus diizinkan walau unit lama sudah nonaktif
        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-OLD-UNIT-EDITED',
            'nama' => 'Nama Berhasil Diedit Walau Unit Nonaktif',
            'satuan' => '%',
            'unit_id' => $unitLama->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $this->assertSame('Nama Berhasil Diedit Walau Unit Nonaktif', $indikator->fresh()->nama);
        $this->assertSame('IKU-OLD-UNIT-EDITED', $indikator->fresh()->kode);

        // 2. Tetapi memindahkan ke unit LAIN yang nonaktif harus tetap ditolak
        $unitLainNonaktif = Unit::create([
            'nama' => 'Unit Lain Nonaktif',
            'status' => 'nonaktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $responseTransferDenied = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-OLD-UNIT-EDITED',
            'nama' => 'Nama Transfer Gagal',
            'satuan' => '%',
            'unit_id' => $unitLainNonaktif->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $responseTransferDenied->assertSessionHasErrors(['unit_id']);
        $this->assertSame($unitLama->id, $indikator->fresh()->unit_id);
    }

    public function test_sasaran_dengan_indikator_tidak_boleh_dipindahkan_lintas_renstra(): void
    {
        $renstraLain = Renstra::create([
            'kode' => 'RENSTRA-SASARAN-GUARD',
            'nama' => 'Renstra Sasaran Guard Test',
            'tahun_mulai' => 2040,
            'tahun_selesai' => 2045,
            'is_aktif' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-IMMUTABLE-RENSTRA',
            'deskripsi' => 'Sasaran Immutable Renstra',
            'urutan' => 1,
        ]);

        $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-IMMUTABLE-RENSTRA',
            'nama' => 'Indikator di Bawah Sasaran',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
        ]);

        // 1. Eloquent model booted guard melempar InvalidArgumentException
        $eloquentThrew = false;
        try {
            $sasaran->update(['renstra_id' => $renstraLain->id]);
        } catch (\InvalidArgumentException $e) {
            $eloquentThrew = true;
            $this->assertStringContainsString('tidak boleh dipindahkan ke Renstra lain', $e->getMessage());
        }
        $this->assertTrue($eloquentThrew, 'Eloquent model guard harus menolak pemindahan sasaran yang memiliki indikator.');

        // 2. Database trigger guard melempar QueryException 23514 pada query builder langsung
        $dbThrew = false;
        try {
            DB::table('sasaran_strategis')
                ->where('id', $sasaran->id)
                ->update(['renstra_id' => $renstraLain->id]);
        } catch (QueryException $e) {
            $dbThrew = true;
            $this->assertSame('23514', $e->getCode());
        }
        $this->assertTrue($dbThrew, 'Trigger sasaran_strategis_renstra_guard harus melempar 23514.');
    }

    public function test_store_mengabaikan_jenis_agregasi_dari_request(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-AGGR-IGN',
            'deskripsi' => 'Sasaran Agregasi Freeze',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-AGGR-IGN',
            'nama' => 'Indikator Agregasi Diabaikan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'jenis_agregasi' => 'formula_acak',
        ]);

        $response->assertRedirect();
        $indikator = IndikatorKinerja::where('kode', 'IKU-AGGR-IGN')->firstOrFail();
        $this->assertNotSame('formula_acak', $indikator->jenis_agregasi, 'jenis_agregasi dari request harus diabaikan (beku MVP)');
    }

    public function test_update_mengabaikan_jenis_agregasi_dari_request(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-AGGR-UPD',
            'deskripsi' => 'Sasaran Agregasi Update',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-AGGR-UPD',
            'nama' => 'Nama Awal',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'jenis_agregasi' => 'rata_rata',
            'created_by_role' => 'perencanaan',
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-AGGR-UPD',
            'nama' => 'Nama Baru',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'jenis_agregasi' => 'diubah_acak',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $indikator->refresh();
        $this->assertSame('Nama Baru', $indikator->nama);
        $this->assertSame('rata_rata', $indikator->jenis_agregasi, 'jenis_agregasi harus dipertahankan dan tidak diubah dari request');
    }

    public function test_regulasi_read_denied_blocks_store_and_update_with_audit(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-REG-GUARD',
            'deskripsi' => 'Sasaran Regulasi Guard',
            'urutan' => 1,
        ]);

        $indikatorExisting = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-GUARD-EXIST',
            'nama' => 'Indikator Existing Tanpa Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Berikan explicit deny regulasi:read pada user perencanaan (tetap punya indikator:create/update)
        $regulasiPermission = Permission::where('kode', 'regulasi:read')->firstOrFail();
        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $regulasiPermission->id,
            'unit_id' => null,
            'alasan' => 'Dilarang menautkan regulasi untuk pengujian guard Q3',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);

        // 1. Store dengan regulasi_id valid harus ditolak 403 + audit
        $responseStore = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-DENIED',
            'nama' => 'Indikator Tebak Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
        ]);

        $responseStore->assertForbidden();
        $this->assertDatabaseMissing('indikator_kinerjas', ['kode' => 'IKU-REG-DENIED']);

        $auditBuat = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->first();
        $this->assertNotNull($auditBuat);
        $this->assertSame('regulasi:read', $auditBuat->dasar_izin['permission'] ?? null);
        $this->assertSame('ditolak', $auditBuat->dasar_izin['keputusan'] ?? null);
        $this->assertStringContainsString('membaca data regulasi', $auditBuat->alasan ?? '');

        // 2. Update dengan regulasi_id valid harus ditolak 403 + audit
        $responseUpdate = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikatorExisting->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-GUARD-EXIST',
            'nama' => 'Indikator Existing Coba Taut Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'expected_updated_at' => $indikatorExisting->fresh()->updated_at?->toISOString() ?? $indikatorExisting->fresh()->created_at->toISOString(),
        ]);

        $responseUpdate->assertForbidden();
        $this->assertNull($indikatorExisting->fresh()->regulasi_id);

        $auditUbah = AuditLog::where('tindakan', 'indikator.ubah_ditolak')
            ->where('objek_id', (string) $indikatorExisting->id)
            ->latest('waktu')
            ->first();
        $this->assertNotNull($auditUbah);
        $this->assertSame('regulasi:read', $auditUbah->dasar_izin['permission'] ?? null);
        $this->assertSame('ditolak', $auditUbah->dasar_izin['keputusan'] ?? null);
        $this->assertStringContainsString('membaca data regulasi', $auditUbah->alasan ?? '');
    }

    public function test_regulasi_nonaktif_ditolak_validasi(): void
    {
        $regulasiNonaktif = Regulasi::create([
            'jenis' => 'permen',
            'nomor' => '99/2024',
            'tahun' => 2024,
            'tentang' => 'Regulasi Nonaktif Untuk Pengujian',
            'aktif' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-REG-AKTIF',
            'deskripsi' => 'Sasaran Regulasi Aktif Guard',
            'urutan' => 1,
        ]);

        // 1. Store dengan regulasi nonaktif ditolak 422
        $responseStore = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-NONAKTIF',
            'nama' => 'Indikator Regulasi Nonaktif',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $regulasiNonaktif->id,
        ]);

        $responseStore->assertSessionHasErrors(['regulasi_id']);
        $this->assertDatabaseMissing('indikator_kinerjas', ['kode' => 'IKU-REG-NONAKTIF']);

        // 2. Update dengan regulasi nonaktif ditolak 422
        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-AKTIF-EDIT',
            'nama' => 'Indikator Edit Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $responseUpdate = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-AKTIF-EDIT',
            'nama' => 'Indikator Edit Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $regulasiNonaktif->id,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $responseUpdate->assertSessionHasErrors(['regulasi_id']);
        $this->assertNull($indikator->fresh()->regulasi_id);
    }

    public function test_komponen_read_denied_hides_capability_and_blocks_route(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-KOMP-DENY',
            'deskripsi' => 'Sasaran Komponen Deny Test',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-KOMP-DENY',
            'nama' => 'Indikator Rasio Untuk Uji Komponen Read',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Berikan explicit deny komponen:read pada user perencanaan
        $komponenPermission = Permission::where('kode', 'komponen:read')->firstOrFail();
        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $komponenPermission->id,
            'unit_id' => null,
            'alasan' => 'Dilarang membaca komponen untuk pengujian regresi Q10',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);

        // 1. Props index tidak lagi mengklaim can.komponen_read=true
        $responseIndex = $this->actingAs($this->perencanaan)->get('/perencanaan/sasaran-indikator');
        $responseIndex->assertOk();
        $responseIndex->assertInertia(fn (Assert $page) => $page
            ->where('can.komponen_read', false)
        );

        // 2. Route komponen langsung tetap ditolak 403 sesuai kontrak
        $responseKomponen = $this->actingAs($this->perencanaan)->get("/indikator/{$indikator->id}/komponen");
        $responseKomponen->assertForbidden();
    }

    public function test_r217_deny_null_diabaikan_nilai_lama_bertahan(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R217-DENY-NULL',
            'deskripsi' => 'Sasaran R2-17 Deny Null',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R217-DENY-NULL',
            'nama' => 'Indikator R2-17 Dengan Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $regulasiPermission = Permission::where('kode', 'regulasi:read')->firstOrFail();
        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $regulasiPermission->id,
            'unit_id' => null,
            'alasan' => 'Dilarang membaca regulasi untuk pengujian R2-17 lepas-rujuk',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);

        // Update melepas rujukan (null) tanpa izin baca: diabaikan,
        // nilai lama bertahan, request tetap 302 sukses.
        $responseUpdate = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R217-DENY-NULL',
            'nama' => 'Indikator R2-17 Diperbarui Tanpa Lepas',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => null,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $responseUpdate->assertRedirect();
        $this->assertSame('Indikator R2-17 Diperbarui Tanpa Lepas', $indikator->fresh()->nama);
        $this->assertSame($this->regulasi->id, $indikator->fresh()->regulasi_id);

        // Store tanpa rujukan (null) tanpa izin baca: tetap sukses dengan null.
        $responseStore = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R217-DENY-NULL-BARU',
            'nama' => 'Indikator Baru Tanpa Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => null,
        ]);

        $responseStore->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'kode' => 'IKU-R217-DENY-NULL-BARU',
            'regulasi_id' => null,
        ]);
    }

    public function test_r217_regulasi_dinonaktifkan_via_db_ditolak_422(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R217-NONAKTIF',
            'deskripsi' => 'Sasaran R2-17 Nonaktif Flag',
            'urutan' => 1,
        ]);

        // Nonaktifkan flag langsung di DB lalu request (TOCTOU lapis transaksi).
        Regulasi::whereKey($this->regulasi->id)->update(['aktif' => false]);

        $responseStore = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R217-NONAKTIF',
            'nama' => 'Indikator Regulasi Dimatikan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
        ]);

        $responseStore->assertSessionHasErrors(['regulasi_id']);
        $this->assertDatabaseMissing('indikator_kinerjas', ['kode' => 'IKU-R217-NONAKTIF']);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R217-NONAKTIF-EDIT',
            'nama' => 'Indikator Edit Regulasi Mati',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $responseUpdate = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R217-NONAKTIF-EDIT',
            'nama' => 'Indikator Edit Regulasi Mati',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $responseUpdate->assertSessionHasErrors(['regulasi_id']);
        $this->assertNull($indikator->fresh()->regulasi_id);
    }

    public function test_r224_regulasi_lama_nonaktif_tetap_dipertahankan_saat_edit_nama(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R224-SAMA',
            'deskripsi' => 'Sasaran R2-24 Grandfather Sama',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R224-SAMA',
            'nama' => 'Indikator R2-24 Nama Lama',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Regulasi lama dinonaktifkan belakangan via DB langsung.
        Regulasi::whereKey($this->regulasi->id)->update(['aktif' => false]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R224-SAMA',
            'nama' => 'Indikator R2-24 Nama Baru',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('Indikator R2-24 Nama Baru', $indikator->fresh()->nama);
        $this->assertSame($this->regulasi->id, $indikator->fresh()->regulasi_id);
    }

    public function test_r224_ganti_ke_regulasi_nonaktif_lain_ditolak_422(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R224-BARU',
            'deskripsi' => 'Sasaran R2-24 Grandfather Baru',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R224-BARU',
            'nama' => 'Indikator R2-24 Rujukan Lama',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $regulasiLainNonaktif = Regulasi::create([
            'jenis' => 'permen',
            'nomor' => '77/R224/2024',
            'tahun' => 2024,
            'tentang' => 'Regulasi Lain Nonaktif R2-24',
            'aktif' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        // Regulasi lama ikut dinonaktifkan agar jelas: yang sama
        // dipertahankan, yang BARU nonaktif tetap ditolak.
        Regulasi::whereKey($this->regulasi->id)->update(['aktif' => false]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R224-BARU',
            'nama' => 'Indikator R2-24 Rujukan Lama',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $regulasiLainNonaktif->id,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['regulasi_id']);
        $this->assertSame($this->regulasi->id, $indikator->fresh()->regulasi_id);
        $this->assertSame('Indikator R2-24 Rujukan Lama', $indikator->fresh()->nama);
    }

    public function test_r220_store_sasaran_gagal_terkontrol_saat_renstra_dihapus_tengah_jalan(): void
    {
        $renstraId = $this->renstra->id;

        // Simulasi hapus Renstra konkuren langsung via DB TEPAT setelah
        // validasi pra-transaksi lolos: hapus saat Action mengunci aktor di
        // dalam transaksi (retrieval User pertama request ini — actingAs
        // melewati DB), sebelum cek Renstra terkunci. Tanpa cek ulang di
        // transaksi, INSERT berikut melanggar FK → 500.
        $terhapusTengahJalan = false;
        User::retrieved(function ($model) use (&$terhapusTengahJalan, $renstraId) {
            if (! $terhapusTengahJalan && $model->id === $this->perencanaan->id) {
                $terhapusTengahJalan = true;
                DB::table('renstras')->where('id', $renstraId)->delete();
            }
        });

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $renstraId,
            'kode' => 'SS-R220-RACE-LOST',
            'deskripsi' => 'Sasaran yang induknya hilang tengah jalan',
            'urutan' => 1,
        ]);

        $this->assertTrue($terhapusTengahJalan, 'Simulasi hapus konkuren harus berjalan di dalam transaksi.');
        $this->assertNotEquals(500, $response->getStatusCode(), 'Hapus Renstra konkuren wajib gagal terkontrol, bukan 500.');

        // Gagal terkontrol: cek ulang transaksi menolak parent hilang
        // (302 + errors = padanan 422 web, atau 404/422 langsung).
        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['renstra_id']);
        } else {
            $this->assertContains($response->getStatusCode(), [404, 422]);
        }

        $this->assertDatabaseMissing('sasaran_strategis', ['kode' => 'SS-R220-RACE-LOST']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'sasaran.buat']);
    }

    public function test_r225_ubah_tipe_rasio_berkomponen_ke_manual_ditolak_422(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R225-TOLAK',
            'deskripsi' => 'Sasaran R2-25 Tolak Ubah Tipe',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R225-TOLAK',
            'nama' => 'Indikator R2-25 Rasio Berkomponen',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $pembilang = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang R2-25',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'satuan' => 'dokumen',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $penyebut = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 't',
            'label' => 'Penyebut R2-25',
            'peran' => 'penyebut',
            'bobot' => 1.0,
            'urutan' => 2,
            'satuan' => 'dokumen',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R225-TOLAK',
            'nama' => 'Indikator R2-25 Rasio Berkomponen',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['tipe_perhitungan']);
        $this->assertSame('rasio_persen', $indikator->fresh()->tipe_perhitungan);
        $this->assertSame('Indikator R2-25 Rasio Berkomponen', $indikator->fresh()->nama);
        $this->assertTrue($pembilang->fresh()->aktif);
        $this->assertTrue($penyebut->fresh()->aktif);
        $this->assertSame('pembilang', $pembilang->fresh()->peran);
        $this->assertSame('penyebut', $penyebut->fresh()->peran);
        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $indikator->id,
        ]);
    }

    public function test_r225_ubah_tipe_rasio_tanpa_komponen_ke_manual_lolos(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R225-LOLOS',
            'deskripsi' => 'Sasaran R2-25 Lolos Ubah Tipe',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R225-LOLOS',
            'nama' => 'Indikator R2-25 Nama Lama',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R225-LOLOS',
            'nama' => 'Indikator R2-25 Nama Baru',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('manual', $indikator->fresh()->tipe_perhitungan);
        $this->assertSame('Indikator R2-25 Nama Baru', $indikator->fresh()->nama);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $indikator->id,
        ]);
    }

    /**
     * Opsi A atomik (R3-01): manual→rasio via edit umum tanpa komponen
     * DITOLAK 422 — tanpa transien invalid yang persisted. Tipe lama utuh
     * dan tanpa audit `indikator.ubah`. Transisi sah hanya via jalur atomik
     * PATCH /perencanaan/indikator/{indikator}/formula.
     */
    public function test_r226_manual_ke_rasio_lolos_lalu_tambah_komponen_menjadi_valid(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R226-A',
            'deskripsi' => 'Sasaran R2-26 Manual Ke Rasio',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-A',
            'nama' => 'Indikator R2-26 Manual',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // manual→rasio_persen via edit umum tanpa komponen DITOLAK 422.
        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-A',
            'nama' => 'Indikator R2-26 Manual',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['tipe_perhitungan']);
        $this->assertSame('manual', $indikator->fresh()->tipe_perhitungan);
        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $indikator->id,
        ]);

        // Contract manual tanpa komponen tetap valid; tidak ada transien
        // nonmanual-invalid yang persisted.
        $kontrak = app(IndikatorPerhitunganService::class)->getFormulaContract($indikator->fresh()->load('komponen'));
        $this->assertTrue($kontrak['is_valid']);
        $this->assertSame('manual', $kontrak['tipe_perhitungan']);
    }

    /**
     * Opsi A atomik (R3-01): manual→penjumlahan via edit umum tanpa
     * komponen DITOLAK 422 — tanpa transien invalid yang persisted.
     */
    public function test_r226_manual_ke_penjumlahan_lolos_lalu_tambah_penjumlah_menjadi_valid(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R226-B',
            'deskripsi' => 'Sasaran R2-26 Manual Ke Penjumlahan',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-B',
            'nama' => 'Indikator R2-26 Manual',
            'satuan' => 'poin',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // manual→penjumlahan via edit umum tanpa komponen DITOLAK 422.
        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-B',
            'nama' => 'Indikator R2-26 Manual',
            'satuan' => 'poin',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['tipe_perhitungan']);
        $this->assertSame('manual', $indikator->fresh()->tipe_perhitungan);
        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $indikator->id,
        ]);
    }

    public function test_r226_rasio_berkomponen_ke_manual_tetap_ditolak_422(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R226-C',
            'deskripsi' => 'Sasaran R2-26 Tolak Ke Manual',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-C',
            'nama' => 'Indikator R2-26 Rasio Berkomponen',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang R2-26',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'satuan' => 'dokumen',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 't',
            'label' => 'Penyebut R2-26',
            'peran' => 'penyebut',
            'bobot' => 1.0,
            'urutan' => 2,
            'satuan' => 'dokumen',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-C',
            'nama' => 'Indikator R2-26 Rasio Berkomponen',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['tipe_perhitungan']);

        // Pesan 422 mengarahkan ke alur Kelola Komponen.
        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('Kelola Komponen', implode(' ', $errors->get('tipe_perhitungan')));

        $this->assertSame('rasio_persen', $indikator->fresh()->tipe_perhitungan);
        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $indikator->id,
        ]);
    }

    /**
     * R3-01 Opsi A: create rasio_persen tanpa komponen DITOLAK 422 via
     * validasi domain yang sama (baris baru selalu tanpa komponen) + tanpa
     * baris tersimpan. Pesan mengarahkan buat manual dulu lalu transisi
     * atomik.
     */
    public function test_r301_create_rasio_tanpa_komponen_ditolak_422(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R301-CREATE',
            'deskripsi' => 'Sasaran R3-01 Tolak Create Rasio',
            'urutan' => 1,
        ]);

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R301-CREATE',
            'nama' => 'Indikator R3-01 Rasio Tanpa Komponen',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
        ]);

        $response->assertSessionHasErrors(['tipe_perhitungan']);

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('atomik', implode(' ', $errors->get('tipe_perhitungan')));

        $this->assertDatabaseMissing('indikator_kinerjas', ['kode' => 'IKU-R301-CREATE']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'indikator.buat']);
    }

    /**
     * R3-01 Opsi A: transisi atomik manual→rasio + pembilang + penyebut
     * sukses + contract valid dalam SATU commit (tipe + 2 komponen +
     * audit indikator.ubah + 2 audit komponen.buat).
     */
    public function test_r301_transisi_atomik_manual_ke_rasio_berhasil(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R301-ATOM-R',
            'deskripsi' => 'Sasaran R3-01 Atomik Rasio',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R301-ATOM-R',
            'nama' => 'Indikator R3-01 Manual',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => [
                ['kode' => 'n', 'label' => 'Pembilang R3-01', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
                ['kode' => 't', 'label' => 'Penyebut R3-01', 'peran' => 'penyebut', 'bobot' => 1.0, 'urutan' => 2, 'aktif' => true],
            ],
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $fresh = $indikator->fresh()->load('komponen');
        $this->assertSame('rasio_persen', $fresh->tipe_perhitungan);

        $kontrak = app(IndikatorPerhitunganService::class)->getFormulaContract($fresh);
        $this->assertTrue($kontrak['is_valid']);
        $this->assertSame([], $kontrak['messages']);

        $this->assertDatabaseHas('indikator_komponen', ['indikator_id' => $indikator->id, 'kode' => 'n']);
        $this->assertDatabaseHas('indikator_komponen', ['indikator_id' => $indikator->id, 'kode' => 't']);
        $this->assertDatabaseHas('audit_log', ['tindakan' => 'indikator.ubah', 'objek_id' => $indikator->id]);
        $this->assertSame(2, AuditLog::where('tindakan', 'komponen.buat')->whereIn('objek_id', IndikatorKomponen::where('indikator_id', $indikator->id)->pluck('id'))->count());
    }

    /**
     * R3-01 Opsi A: transisi atomik manual→penjumlahan + penjumlah sukses +
     * contract valid.
     */
    public function test_r301_transisi_atomik_manual_ke_penjumlahan_berhasil(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R301-ATOM-J',
            'deskripsi' => 'Sasaran R3-01 Atomik Penjumlahan',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R301-ATOM-J',
            'nama' => 'Indikator R3-01 Manual',
            'satuan' => 'poin',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/formula", [
            'tipe_perhitungan' => 'penjumlahan',
            'komponen' => [
                ['kode' => 'jml', 'label' => 'Penjumlah R3-01', 'peran' => 'penjumlah', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
            ],
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $fresh = $indikator->fresh()->load('komponen');
        $this->assertSame('penjumlahan', $fresh->tipe_perhitungan);

        $kontrak = app(IndikatorPerhitunganService::class)->getFormulaContract($fresh);
        $this->assertTrue($kontrak['is_valid']);
    }

    /**
     * R3-01 Opsi A: transisi atomik dengan payload tak lengkap DITOLAK 422
     * tanpa mutasi/audit sukses (fail-closed, atomik).
     */
    public function test_r301_transisi_atomik_tak_lengkap_ditolak_tanpa_mutasi(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R301-ATOM-G',
            'deskripsi' => 'Sasaran R3-01 Atomik Gagal',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R301-ATOM-G',
            'nama' => 'Indikator R3-01 Manual',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        // Hanya pembilang tanpa penyebut → invalid.
        $response = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => [
                ['kode' => 'n', 'label' => 'Pembilang Saja', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
            ],
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertSessionHasErrors(['tipe_perhitungan']);
        $this->assertSame('manual', $indikator->fresh()->tipe_perhitungan);
        $this->assertDatabaseMissing('indikator_komponen', ['indikator_id' => $indikator->id, 'kode' => 'n']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'indikator.ubah', 'objek_id' => $indikator->id]);
    }

    /**
     * R3-01 Opsi A: transisi atomik menolak payload usang 409 tanpa mutasi
     * (stale-token fail-closed seperti UpdateIndikator).
     */
    public function test_r301_transisi_atomik_menolak_payload_usang_409(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R301-STALE',
            'deskripsi' => 'Sasaran R3-01 Stale Atomik',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R301-STALE',
            'nama' => 'Indikator R3-01 Manual',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $tokenLama = $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString();

        // Tab pertama sukses lebih dulu via atomik.
        $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => [
                ['kode' => 'n', 'label' => 'Pembilang', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
                ['kode' => 't', 'label' => 'Penyebut', 'peran' => 'penyebut', 'bobot' => 1.0, 'urutan' => 2, 'aktif' => true],
            ],
            'expected_updated_at' => $tokenLama,
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Tab kedua memakai token lama → 409 tanpa mutasi tambahan.
        $responseUsang = $this->actingAs($this->perencanaan)->patch("/perencanaan/indikator/{$indikator->id}/formula", [
            'tipe_perhitungan' => 'penjumlahan',
            'komponen' => [
                ['kode' => 'j2', 'label' => 'Penjumlah Susulan', 'peran' => 'penjumlah', 'bobot' => 1.0, 'urutan' => 3, 'aktif' => true],
            ],
            'expected_updated_at' => $tokenLama,
        ]);

        $this->assertContains($responseUsang->getStatusCode(), [409, 302]);
        if ($responseUsang->getStatusCode() === 302) {
            $responseUsang->assertSessionHasErrors(['konflik']);
        }
        $this->assertSame('rasio_persen', $indikator->fresh()->tipe_perhitungan);
        $this->assertDatabaseMissing('indikator_komponen', ['indikator_id' => $indikator->id, 'kode' => 'j2']);
    }

    /**
     * Serialisasi tipe-diubah vs komponen-ditambah: kedua jalur mengunci
     * parent IndikatorKinerja FOR UPDATE dalam urutan parent→child yang sama
     * (UpdateIndikator + IndikatorKomponenController store/update/destroy),
     * sehingga pemenang pertama ter-commit dan jalur kedua menilainya —
     * tak ada kombinasi invalid yang bertahan.
     *
     * Batas bukti: orkestrasi sekuensial satu-proses (bukan dua proses
     * paralel); kunci baris + SQLSTATE-nya nyata di produksi, tetapi race
     * 2-proses sejati tak direproduksi di sini (preseden batas: R2-21).
     */
    public function test_r226_konkurensi_tipe_vs_komponen_tak_hasilkan_kombinasi_invalid(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R226-D',
            'deskripsi' => 'Sasaran R2-26 Konkurensi',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-D',
            'nama' => 'Indikator R2-26 Rasio',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
        ]);

        $pembilang = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang R2-26',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'satuan' => 'dokumen',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $penyebut = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 't',
            'label' => 'Penyebut R2-26',
            'peran' => 'penyebut',
            'bobot' => 1.0,
            'urutan' => 2,
            'satuan' => 'dokumen',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $payloadUbahKeManual = fn (): array => [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R226-D',
            'nama' => 'Indikator R2-26 Rasio',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ];

        // Urutan serialisasi 1 (tambah-dulu-menang): ubah-tipe melihat
        // komponen yang sudah ter-commit → ditolak 422, kombinasi tetap valid.
        $this->actingAs($this->perencanaan)
            ->put("/perencanaan/indikator/{$indikator->id}", $payloadUbahKeManual())
            ->assertSessionHasErrors(['tipe_perhitungan']);
        $this->assertSame('rasio_persen', $indikator->fresh()->tipe_perhitungan);
        $this->assertTrue(app(IndikatorPerhitunganService::class)->validateDefinisiKomponen($indikator->fresh()->load('komponen'))['is_valid']);

        // Deaktivasi satu per satu merusak rasio dan wajib ditolak.
        foreach ([$pembilang, $penyebut] as $urutan => $komponen) {
            $this->actingAs($this->perencanaan)->put("/indikator/{$indikator->id}/komponen/{$komponen->id}", [
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1.0,
                'urutan' => $urutan + 1,
                'aktif' => false,
                'alasan' => 'Menonaktifkan komponen sebelum beralih ke tipe manual.',
            ])->assertRedirect()->assertSessionHasErrors(['komponen']);
            $this->assertTrue($komponen->fresh()->aktif);
        }

        // Transisi atomik menonaktifkan kedua child dan mengubah tipe sekaligus.
        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikator->id}/formula", [
                'tipe_perhitungan' => 'manual',
                'komponen' => [],
                'expected_updated_at' => $indikator->fresh()->updated_at->toISOString(),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->perencanaan)
            ->put("/perencanaan/indikator/{$indikator->id}", $payloadUbahKeManual())
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('manual', $indikator->fresh()->tipe_perhitungan);

        // Urutan serialisasi 2 (ubah-dulu-menang): tambah-komponen melihat
        // tipe manual yang sudah ter-commit → ditolak, kombinasi tetap valid.
        $this->actingAs($this->perencanaan)->post("/indikator/{$indikator->id}/komponen", [
            'kode' => 'm_baru',
            'label' => 'Komponen susulan saat manual',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 3,
            'aktif' => true,
        ])->assertSessionHasErrors(['indikator']);
        $this->assertDatabaseMissing('indikator_komponen', [
            'indikator_id' => $indikator->id,
            'kode' => 'm_baru',
        ]);

        $this->assertTrue(app(IndikatorPerhitunganService::class)->validateDefinisiKomponen($indikator->fresh()->load('komponen'))['is_valid']);
    }

    /**
     * Membuat Indikator langsung via model dengan kolom lifecycle wajib
     * terisi (status + tahun_mulai_berlaku + created_by + created_by_role).
     * Nilai eksplisit pada $atribut menang atas bawaan.
     */
    private function buatIndikator(array $atribut): IndikatorKinerja
    {
        $tahunMulai = $this->renstra->tahun_mulai;
        $sasaranId = $atribut['sasaran_strategis_id'] ?? null;
        if (is_string($sasaranId)) {
            $renstraId = SasaranStrategis::whereKey($sasaranId)->value('renstra_id');
            if (is_string($renstraId)) {
                $tahun = Renstra::whereKey($renstraId)->value('tahun_mulai');
                if ($tahun !== null) {
                    $tahunMulai = (int) $tahun;
                }
            }
        }

        return IndikatorKinerja::create(array_merge([
            'status' => 'aktif',
            'tahun_mulai_berlaku' => $tahunMulai,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ], $atribut));
    }

    private function buatUserDenganRole(string $roleName, string $email): User
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => $email,
            'status' => 'aktif',
        ]);

        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function pasangPresetRole(string $roleName): void
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $permissionCodes = RolePermissionPresets::forRole($roleName);

        $permissionIds = Permission::whereIn('kode', $permissionCodes)->pluck('id');

        $role->permissions()->syncWithoutDetaching(
            $permissionIds->mapWithKeys(fn (string $id) => [
                $id => [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                ],
            ])->all()
        );
    }
}
