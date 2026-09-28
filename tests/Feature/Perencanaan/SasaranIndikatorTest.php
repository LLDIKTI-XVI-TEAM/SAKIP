<?php

namespace Tests\Feature\Perencanaan;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-01',
            'nama' => 'Persentase PTS Terakreditasi Unggul',
            'definisi_operasional' => 'Jumlah PTS akreditasi Unggul dibagi total PTS dikali 100%',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
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
            'tipe_perhitungan' => 'rasio_persen',
            'presisi' => 2,
            'wajib_catatan' => true,
            'regulasi_id' => $this->regulasi->id,
            'is_aktif' => true,
        ]);

        $indikator = IndikatorKinerja::where('kode', 'IKU-01')->firstOrFail();
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

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REG-TEST',
            'nama' => 'Indikator Regulasi Test',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-AUTH-TEST',
            'nama' => 'Indikator Auth Test',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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

    public function test_8_pencegahan_hard_delete_indikator_dengan_dependensi(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Sasaran Pertama',
            'urutan' => 1,
        ]);

        $indikatorDenganTarget = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-HAS-TARGET',
            'nama' => 'Indikator dengan Dependensi Target',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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

        // Hapus indikator yang punya dependensi: tidak hard-delete, tapi is_aktif = false
        $response = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikatorDenganTarget->id}", [
            'alasan' => 'Indikator dinonaktifkan karena perubahan struktur Renstra',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('indikator_kinerjas', [
            'id' => $indikatorDenganTarget->id,
            'is_aktif' => false,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.nonaktifkan',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorDenganTarget->id,
            'actor_id' => $this->perencanaan->id,
        ]);

        // Indikator tanpa dependensi: hard delete berhasil
        $indikatorPolos = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-POLOS',
            'nama' => 'Indikator Polos Tanpa Dependensi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
        ]);

        $deleteResponse = $this->actingAs($this->perencanaan)->delete("/perencanaan/indikator/{$indikatorPolos->id}", [
            'alasan' => 'Indikator polos dihapus permanen oleh perencanaan',
        ]);

        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('indikator_kinerjas', [
            'id' => $indikatorPolos->id,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.hapus',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorPolos->id,
            'actor_id' => $this->perencanaan->id,
        ]);
    }

    public function test_pencegahan_hapus_sasaran_yang_memiliki_indikator(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-WITH-CHILD',
            'deskripsi' => 'Sasaran dengan Indikator Anak',
            'urutan' => 1,
        ]);

        IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-CHILD',
            'nama' => 'Indikator Anak',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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

        IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'regulasi_id' => $this->regulasi->id,
            'kode' => 'IKU-REG-DENY',
            'nama' => 'Indikator dengan Regulasi Rujukan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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

        $indikator = IndikatorKinerja::create([
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
            'is_aktif' => false,
            'created_by_role' => 'perencanaan',
        ]);

        // Submit pembaruan tanpa menyertakan desimal_tampilan, jenis_agregasi, maupun is_aktif
        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PRESERVE',
            'nama' => 'Nama Baru Diperbarui',
            'satuan' => 'Dokumen',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $response->assertRedirect();
        $indikator->refresh();

        $this->assertSame('Nama Baru Diperbarui', $indikator->nama);
        $this->assertSame('Dokumen', $indikator->satuan);
        $this->assertFalse($indikator->is_aktif, 'is_aktif harus tetap false dan tidak diaktifkan ulang secara otomatis');
        $this->assertSame(4, $indikator->desimal_tampilan, 'desimal_tampilan harus dipertahankan');
        $this->assertSame('rata_rata', $indikator->jenis_agregasi, 'jenis_agregasi harus dipertahankan');
        $this->assertTrue($indikator->wajib_catatan, 'wajib_catatan harus dipertahankan');
    }

    public function test_destroy_indikator_with_extended_dependencies_deactivates_safely(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-DEP-EXT',
            'deskripsi' => 'Sasaran Dependency Extended Test',
            'urutan' => 1,
        ]);

        // 1. Dependensi penanggung_jawab
        $indikatorPic = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-PIC',
            'nama' => 'Indikator dengan PIC',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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
            'is_aktif' => false,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.nonaktifkan',
            'objek_tipe' => 'indikator',
            'objek_id' => $indikatorPic->id,
        ]);

        // 2. Dependensi jenis_berkas
        $indikatorBerkas = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-BERKAS',
            'nama' => 'Indikator dengan Jenis Berkas',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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
            'is_aktif' => false,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'indikator.nonaktifkan',
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
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-UNIT-VALID',
            'nama' => 'Indikator Awal',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-REASON-TEST',
            'nama' => 'Indikator Reason Test',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
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

    public function test_seed_demo_pengukuran_creates_component_snapshots_and_rejects_closed_schedule(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-FORMULA',
            'deskripsi' => 'Sasaran Formula Test',
            'urutan' => 1,
        ]);

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-FORMULA',
            'nama' => 'Indikator Rasio Persen',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'created_by_role' => 'perencanaan',
            'is_aktif' => true,
        ]);

        $komponen = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'K1',
            'label' => 'Komponen Pembilang',
            'peran' => 'pembilang',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Jalankan command demo pengukuran
        $exitCode = $this->artisan('sakip:seed-demo-pengukuran')->run();
        $this->assertSame(0, $exitCode);

        // Verifikasi komponen aktif disalin ke jadwal_snapshot_komponen
        $snapshotKomponen = JadwalSnapshotKomponen::where('komponen_id', $komponen->id)->first();
        $this->assertNotNull($snapshotKomponen);
        $this->assertSame('K1', $snapshotKomponen->kode);

        // Verifikasi pengukuran dibuat dengan sumber_nilai = komponen
        $pengukuran = PengukuranKinerja::where('indikator_id', $indikator->id)->first();
        $this->assertNotNull($pengukuran);
        $this->assertSame('komponen', $pengukuran->sumber_nilai);

        // Tutup jadwal tahunan 2026
        $jadwal = JadwalTahunan::where('renstra_id', $this->renstra->id)->where('tahun', 2026)->firstOrFail();
        $jadwal->update(['status' => 'ditutup']);

        // Jalankan command lagi, harus menolak membuka kembali jadwal tertutup
        $exitCodeClosed = $this->artisan('sakip:seed-demo-pengukuran')->run();
        $this->assertSame(1, $exitCodeClosed);

        $jadwal->refresh();
        $this->assertSame('ditutup', $jadwal->status);
    }

    private function buatUserDenganRole(string $roleName, string $email): User
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
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
