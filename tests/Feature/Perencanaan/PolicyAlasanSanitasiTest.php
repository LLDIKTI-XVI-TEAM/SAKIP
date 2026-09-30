<?php

namespace Tests\Feature\Perencanaan;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\RolePermissionPresets;
use App\Support\AlasanAudit;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanitasi alasan audit penolakan di Policy Sasaran/Indikator.
 *
 * Policy membaca `request()->input('alasan')` mentah sebelum validasi
 * selesai; audit append-only wajib menerima nilai yang sudah dirapikan
 * (trim + batas 1000 karakter + fallback generik). Perilaku allow/deny
 * tidak berubah — setiap skenario tetap menegaskan respons 403.
 */
class PolicyAlasanSanitasiTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    private Renstra $renstra;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogSeeder::class);
        $this->pasangPresetRole('perencanaan');

        $this->perencanaan = $this->buatUserDenganRole('perencanaan', 'sanitasi-test@sakip.test');

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
    }

    public function test_sasaran_buat_alasan_panjang_dibatasi_seribu_karakter(): void
    {
        $this->tolakIzin('sasaran:create');

        $mentah = '  '.str_repeat('a', 2500).'  ';

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
            'alasan' => $mentah,
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'sasaran.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame(AlasanAudit::BATAS_MAKS, mb_strlen($audit->alasan, 'UTF-8'));
        $this->assertSame(mb_substr(trim($mentah), 0, AlasanAudit::BATAS_MAKS, 'UTF-8'), $audit->alasan);
    }

    public function test_sasaran_buat_alasan_kosong_memakai_fallback_generik(): void
    {
        $this->tolakIzin('sasaran:create');

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
            'alasan' => '   ',
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'sasaran.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame(
            'Percobaan membuat sasaran strategis ditolak oleh sistem otorisasi.',
            $audit->alasan
        );
    }

    public function test_sasaran_buat_alasan_valid_tersimpan_utuh(): void
    {
        $this->tolakIzin('sasaran:create');

        $alasan = 'Percobaan sadar tanpa hak sasaran:create untuk uji audit.';

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
            'alasan' => $alasan,
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'sasaran.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame($alasan, $audit->alasan);
    }

    public function test_sasaran_ubah_alasan_panjang_dibatasi(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $this->tolakIzin('sasaran:update');

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/sasaran/{$sasaran->id}", [
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 2,
            'alasan' => str_repeat('b', 1500),
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'sasaran.ubah_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame(AlasanAudit::BATAS_MAKS, mb_strlen($audit->alasan, 'UTF-8'));
        $this->assertSame(str_repeat('b', AlasanAudit::BATAS_MAKS), $audit->alasan);
    }

    public function test_indikator_buat_alasan_panjang_multibyte_dibatasi_per_karakter(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $this->tolakIzin('indikator:create');

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'alasan' => str_repeat('é', 1500),
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame(AlasanAudit::BATAS_MAKS, mb_strlen($audit->alasan, 'UTF-8'));
        $this->assertSame(str_repeat('é', AlasanAudit::BATAS_MAKS), $audit->alasan);
    }

    public function test_indikator_buat_tanpa_alasan_memakai_fallback_generik(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $this->tolakIzin('indikator:create');

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame(
            'Percobaan membuat indikator kinerja ditolak oleh sistem otorisasi.',
            $audit->alasan
        );
    }

    public function test_indikator_buat_alasan_valid_tersimpan_utuh(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $this->tolakIzin('indikator:create');

        $alasan = 'Percobaan sadar tanpa hak indikator:create untuk uji audit.';

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'alasan' => $alasan,
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame($alasan, $audit->alasan);
    }

    public function test_indikator_ubah_alasan_spasi_memakai_fallback(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => $this->renstra->tahun_mulai,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);
        $this->tolakIzin('indikator:update');

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'alasan' => '   ',
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame(
            'Percobaan indikator.ubah_ditolak ditolak oleh sistem otorisasi.',
            $audit->alasan
        );
    }

    public function test_sasaran_buat_alasan_nul_tetap_403_audit_tersimpan(): void
    {
        $this->tolakIzin('sasaran:create');

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
            'alasan' => "upaya\0tanpa\0izin",
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'sasaran.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame('upayatanpaizin', $audit->alasan);
        $this->assertStringNotContainsString("\0", $audit->alasan);
    }

    public function test_indikator_buat_alasan_nul_tetap_403_audit_tersimpan(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $this->tolakIzin('indikator:create');

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/indikator', [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'alasan' => "coba\0alasan",
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'indikator.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame('cobaalasan', $audit->alasan);
        $this->assertStringNotContainsString("\0", $audit->alasan);
    }

    public function test_sasaran_ubah_alasan_nul_tetap_403_audit_tersimpan(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $this->tolakIzin('sasaran:update');

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/sasaran/{$sasaran->id}", [
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 2,
            'alasan' => "  ubah\0sasaran  ",
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'sasaran.ubah_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame('ubahsasaran', $audit->alasan);
        $this->assertStringNotContainsString("\0", $audit->alasan);
    }

    public function test_indikator_ubah_alasan_nul_tetap_403_audit_tersimpan(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
        ]);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => $this->renstra->tahun_mulai,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);
        $this->tolakIzin('indikator:update');

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SANITASI',
            'nama' => 'Indikator uji sanitasi alasan',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'alasan' => "alasan\0indikator",
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame('alasanindikator', $audit->alasan);
        $this->assertStringNotContainsString("\0", $audit->alasan);
    }

    public function test_sasaran_buat_alasan_invalid_utf8_memakai_fallback_generik(): void
    {
        $this->tolakIzin('sasaran:create');

        $response = $this->actingAs($this->perencanaan)->post('/perencanaan/sasaran', [
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-SANITASI',
            'deskripsi' => 'Sasaran uji sanitasi alasan',
            'urutan' => 1,
            'alasan' => "\xFF\xFE",
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'sasaran.buat_ditolak')->latest('waktu')->firstOrFail();
        $this->assertSame(
            'Percobaan membuat sasaran strategis ditolak oleh sistem otorisasi.',
            $audit->alasan
        );
    }

    private function tolakIzin(string $kodePermission): void
    {
        $permission = Permission::where('kode', $kodePermission)->firstOrFail();

        UserPermissionDeny::create([
            'user_id' => $this->perencanaan->id,
            'permission_id' => $permission->id,
            'unit_id' => null,
            'alasan' => "Deny {$kodePermission} untuk pengujian sanitasi alasan audit",
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);
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
