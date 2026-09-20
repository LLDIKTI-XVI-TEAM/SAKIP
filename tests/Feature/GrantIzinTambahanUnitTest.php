<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPermissionGranted;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GrantIzinTambahanUnitTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $pegawaiUser;

    protected User $pimpinanUser;

    protected UnitKerja $unitA;

    protected UnitKerja $unitB;

    protected Permission $unitPermission;

    protected Permission $globalPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = ['superadmin', 'admin', 'perencanaan', 'pimpinan', 'pegawai'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->seed(PermissionCatalogSeeder::class);

        $this->unitA = UnitKerja::create([
            'kode' => 'UNIT-A',
            'nama' => 'Pokja Kelembagaan',
            'singkatan' => 'Kelembagaan',
            'is_active' => true,
        ]);

        $this->unitB = UnitKerja::create([
            'kode' => 'UNIT-B',
            'nama' => 'Pokja Akademik',
            'singkatan' => 'Akademik',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin Pengelola',
            'email' => 'admin@sakip.test',
            'password' => 'secret123',
            'unit_kerja_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('admin');

        $this->pegawaiUser = User::create([
            'name' => 'Pegawai Staf',
            'email' => 'pegawai@sakip.test',
            'password' => 'secret123',
            'unit_kerja_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $this->pegawaiUser->assignRole('pegawai');

        $this->pimpinanUser = User::create([
            'name' => 'Pimpinan Lembaga',
            'email' => 'pimpinan@sakip.test',
            'password' => 'secret123',
            'unit_kerja_id' => $this->unitA->id,
            'is_active' => true,
        ]);
        $this->pimpinanUser->assignRole('pimpinan');

        $this->unitPermission = Permission::where('kode', 'pengukuran:create')->firstOrFail();
        $this->globalPermission = Permission::where('kode', 'pengaturan:update')->firstOrFail();
    }

    /**
     * TEST-1 / AC-1: Given permission butuh_scope = unit, when grant dibuat dengan user, unit, dan alasan valid,
     * then user_permission_granted terbentuk dengan unit_id terisi dan peristiwa diaudit.
     */
    public function test_grant_unit_scoped_permission_creates_record_and_audit_log(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
            'alasan' => 'Penugasan khusus bantuan penyusunan capaian Pokja Akademik',
        ]);

        $response->assertRedirect('/akses/grant');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_permission_granted', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
            'diberikan_oleh' => $this->adminUser->id,
            'alasan' => 'Penugasan khusus bantuan penyusunan capaian Pokja Akademik',
        ]);

        $grant = UserPermissionGranted::where('user_id', $this->pegawaiUser->id)
            ->where('permission_id', $this->unitPermission->id)
            ->first();

        $this->assertNotNull($grant);
        $this->assertEquals($this->unitB->id, $grant->unit_id);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.tambah',
            'objek_tipe' => 'user_permission_granted',
            'objek_id' => (string) $grant->id,
        ]);
    }

    /**
     * TEST-2 / AC-2: Given permission global, when dicoba diberikan melalui Form Grant Unit,
     * then validasi menolak (422).
     */
    public function test_granting_global_permission_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->globalPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Mencoba memberi izin global via form unit',
        ]);

        $response->assertSessionHasErrors('permission_id');
        $this->assertDatabaseCount('user_permission_granted', 0);
    }

    /**
     * TEST-3 / AC-3: Given permission unit-scoped tanpa unit, when submit dilakukan,
     * then validasi menolak (422).
     */
    public function test_granting_unit_scoped_permission_without_unit_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => null,
            'alasan' => 'Mencoba submit tanpa unit',
        ]);

        $response->assertSessionHasErrors('unit_id');
        $this->assertDatabaseCount('user_permission_granted', 0);
    }

    /**
     * TEST-4 / AC-4: Given kombinasi user-permission-unit identik sudah ada, when disimpan ulang,
     * then duplikasi ditolak.
     */
    public function test_duplicate_grant_is_rejected(): void
    {
        UserPermissionGranted::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Pemberian izin pertama',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Mencoba duplikasi izin yang sama',
        ]);

        $response->assertSessionHasErrors('permission_id');
        $this->assertDatabaseCount('user_permission_granted', 1);
    }

    /**
     * TEST-5 / AC-5: Given target user bukan role Pegawai (misal Pimpinan), when Admin memberi grant eksplisit valid,
     * then grant tetap dapat disimpan; guard role target tidak memblokir.
     */
    public function test_grant_to_non_pegawai_user_is_allowed(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->pimpinanUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Pimpinan didelegasikan izin pengisian unit perintis',
        ]);

        $response->assertRedirect('/akses/grant');
        $this->assertDatabaseHas('user_permission_granted', [
            'user_id' => $this->pimpinanUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
        ]);
    }

    /**
     * TEST-6 / AC-6: Given grant dicabut, when pencabutan selesai,
     * then audit mencatat aktor, alasan, dan grant yang dicabut.
     */
    public function test_revoking_grant_deletes_record_and_creates_audit_log(): void
    {
        $grant = UserPermissionGranted::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Penugasan sementara',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/akses/grant/{$grant->id}", [
            'alasan' => 'Penugasan sementara selesai dan masa berlaku berakhir',
        ]);

        $response->assertRedirect('/akses/grant');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('user_permission_granted', [
            'id' => $grant->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.hapus',
            'objek_tipe' => 'user_permission_granted',
            'objek_id' => (string) $grant->id,
            'alasan' => 'Penugasan sementara selesai dan masa berlaku berakhir',
        ]);

        $log = AuditLog::where('objek_id', (string) $grant->id)
            ->where('tindakan', 'user_permission_granted.hapus')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->pegawaiUser->id, $log->nilai_lama['user_id']);
        $this->assertEquals($this->unitPermission->kode, $log->nilai_lama['permission_kode']);
    }

    /**
     * TEST-7 / AC-5 & Security: Given user tanpa izin akses:update (misal Pegawai),
     * when memanggil endpoint grant, then server menghasilkan 403.
     */
    public function test_unauthorized_user_cannot_access_or_mutate_grants(): void
    {
        // Akses index ditolak 403
        $indexResponse = $this->actingAs($this->pegawaiUser)->get('/akses/grant');
        $indexResponse->assertStatus(403);

        // Akses store ditolak 403
        $storeResponse = $this->actingAs($this->pegawaiUser)->post('/akses/grant', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Mencoba grant mandiri tanpa izin',
        ]);
        $storeResponse->assertStatus(403);

        $grant = UserPermissionGranted::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Existing grant',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        // Akses revoke ditolak 403
        $revokeResponse = $this->actingAs($this->pegawaiUser)->delete("/akses/grant/{$grant->id}", [
            'alasan' => 'Mencoba cabut grant tanpa izin',
        ]);
        $revokeResponse->assertStatus(403);
    }
}
