<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GrantIzinTambahanUnitTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $superadminUser;

    protected User $otherAdminUser;

    protected User $pegawaiUser;

    protected User $pimpinanUser;

    protected Unit $unitA;

    protected Unit $unitB;

    protected Permission $unitPermission;

    protected Permission $globalPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogSeeder::class);

        $this->adminUser = User::factory()->create([
            'nama' => 'Admin Pengelola',
            'email' => 'admin@sakip.test',
            'is_active' => true,
        ]);

        $this->superadminUser = User::factory()->create([
            'nama' => 'Superadmin Utama',
            'email' => 'superadmin@sakip.test',
            'is_active' => true,
        ]);

        $this->otherAdminUser = User::factory()->create([
            'nama' => 'Admin Kedua',
            'email' => 'admin2@sakip.test',
            'is_active' => true,
        ]);

        $this->pegawaiUser = User::factory()->create([
            'nama' => 'Pegawai Staf',
            'email' => 'pegawai@sakip.test',
            'is_active' => true,
        ]);

        $this->pimpinanUser = User::factory()->create([
            'nama' => 'Pimpinan Lembaga',
            'email' => 'pimpinan@sakip.test',
            'is_active' => true,
        ]);

        $adminRole = Role::where('kode', 'admin')->firstOrFail();
        $this->adminUser->roles()->attach($adminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $this->otherAdminUser->roles()->attach($adminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $this->superadminUser->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $aksesUpdatePerm = Permission::where('kode', 'akses:update')->firstOrFail();
        $adminRole->permissions()->attach($aksesUpdatePerm->id, [
            'id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
        $superadminRole->permissions()->attach($aksesUpdatePerm->id, [
            'id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);

        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $this->pegawaiUser->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $pimpinanRole = Role::where('kode', 'pimpinan')->firstOrFail();
        $this->pimpinanUser->roles()->attach($pimpinanRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $this->unitA = Unit::create([
            'nama' => 'Pokja Kelembagaan',
            'status' => 'aktif',
            'created_by' => $this->adminUser->id,
        ]);

        $this->unitB = Unit::create([
            'nama' => 'Pokja Akademik',
            'status' => 'aktif',
            'created_by' => $this->adminUser->id,
        ]);

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

        $grant = UserPermissionGrant::where('user_id', $this->pegawaiUser->id)
            ->where('permission_id', $this->unitPermission->id)
            ->first();

        $this->assertNotNull($grant);
        $this->assertEquals($this->unitB->id, $grant->unit_id);

        $this->assertDatabaseHas('audit_log', [
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
        UserPermissionGrant::create([
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
        $grant = UserPermissionGrant::create([
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

        $this->assertDatabaseHas('audit_log', [
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

        $grant = UserPermissionGrant::create([
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

    /**
     * Temuan Review 8: Parameter route non-UUID pada revoke grant menghasilkan 404 bukan 500.
     */
    public function test_revoke_grant_with_invalid_uuid_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser)->delete('/akses/grant/bukan-uuid', [
            'alasan' => 'Alasan pencabutan izin valid',
        ]);
        $response->assertStatus(404);
    }

    /**
     * Aturan Hierarki: Admin TIDAK BISA memberikan izin unit kepada Admin atau Superadmin.
     */
    public function test_admin_cannot_grant_permission_to_admin_or_superadmin(): void
    {
        // Admin mencoba memberi grant ke sesama Admin
        $responseAdmin = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->otherAdminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Mencoba memberi izin ke sesama Admin',
        ]);
        $responseAdmin->assertStatus(403);

        // Admin mencoba memberi grant ke Superadmin
        $responseSuperadmin = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->superadminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Mencoba memberi izin ke Superadmin',
        ]);
        $responseSuperadmin->assertStatus(403);

        $this->assertDatabaseCount('user_permission_granted', 0);
    }

    /**
     * Aturan Hierarki: Admin TIDAK BISA mencabut izin unit milik Admin atau Superadmin.
     */
    public function test_admin_cannot_revoke_grant_belonging_to_admin_or_superadmin(): void
    {
        $adminGrant = UserPermissionGrant::create([
            'user_id' => $this->otherAdminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Izin unit untuk Admin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $superadminGrant = UserPermissionGrant::create([
            'user_id' => $this->superadminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
            'alasan' => 'Izin unit untuk Superadmin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        // Admin mencoba mencabut izin Admin lain
        $revokeAdmin = $this->actingAs($this->adminUser)->delete("/akses/grant/{$adminGrant->id}", [
            'alasan' => 'Mencoba cabut grant admin',
        ]);
        $revokeAdmin->assertStatus(403);

        // Admin mencoba mencabut izin Superadmin
        $revokeSuperadmin = $this->actingAs($this->adminUser)->delete("/akses/grant/{$superadminGrant->id}", [
            'alasan' => 'Mencoba cabut grant superadmin',
        ]);
        $revokeSuperadmin->assertStatus(403);

        $this->assertDatabaseHas('user_permission_granted', ['id' => $adminGrant->id]);
        $this->assertDatabaseHas('user_permission_granted', ['id' => $superadminGrant->id]);
    }

    /**
     * Aturan Hierarki: Superadmin BISA memberikan izin unit kepada Admin dan Superadmin.
     */
    public function test_superadmin_can_grant_permission_to_admin_and_superadmin(): void
    {
        // Superadmin memberi grant ke Admin
        $responseAdmin = $this->actingAs($this->superadminUser)->post('/akses/grant', [
            'user_id' => $this->adminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Penugasan khusus unit oleh Superadmin untuk Admin',
        ]);
        $responseAdmin->assertRedirect('/akses/grant');
        $responseAdmin->assertSessionHas('success');

        $this->assertDatabaseHas('user_permission_granted', [
            'user_id' => $this->adminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        // Superadmin memberi grant ke sesama Superadmin
        $anotherSuperadmin = User::factory()->create(['is_active' => true]);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $anotherSuperadmin->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        $responseSuper = $this->actingAs($this->superadminUser)->post('/akses/grant', [
            'user_id' => $anotherSuperadmin->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
            'alasan' => 'Penugasan khusus unit untuk sesama Superadmin',
        ]);
        $responseSuper->assertRedirect('/akses/grant');

        $this->assertDatabaseHas('user_permission_granted', [
            'user_id' => $anotherSuperadmin->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
        ]);
    }

    /**
     * Aturan Hierarki: Superadmin BISA mencabut izin unit milik Admin dan Superadmin.
     */
    public function test_superadmin_can_revoke_grant_belonging_to_admin_and_superadmin(): void
    {
        $adminGrant = UserPermissionGrant::create([
            'user_id' => $this->adminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Izin unit untuk Admin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $superadminGrant = UserPermissionGrant::create([
            'user_id' => $this->superadminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
            'alasan' => 'Izin unit untuk Superadmin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        // Superadmin mencabut izin Admin
        $revokeAdmin = $this->actingAs($this->superadminUser)->delete("/akses/grant/{$adminGrant->id}", [
            'alasan' => 'Pencabutan wewenang unit oleh Superadmin',
        ]);
        $revokeAdmin->assertRedirect('/akses/grant');
        $this->assertDatabaseMissing('user_permission_granted', ['id' => $adminGrant->id]);

        // Superadmin mencabut izin Superadmin
        $revokeSuper = $this->actingAs($this->superadminUser)->delete("/akses/grant/{$superadminGrant->id}", [
            'alasan' => 'Pencabutan wewenang unit superadmin',
        ]);
        $revokeSuper->assertRedirect('/akses/grant');
        $this->assertDatabaseMissing('user_permission_granted', ['id' => $superadminGrant->id]);
    }

    /**
     * Dropdown Pengguna: Admin hanya melihat user non-Admin & non-Superadmin; Superadmin melihat semua user.
     */
    public function test_index_filters_target_users_by_actor_role(): void
    {
        // Saat diakses Admin
        $adminIndex = $this->actingAs($this->adminUser)->get('/akses/grant');
        $adminIndex->assertStatus(200);
        $adminUsers = collect($adminIndex->viewData('page')['props']['users']);

        $this->assertFalse($adminUsers->contains('id', $this->adminUser->id));
        $this->assertFalse($adminUsers->contains('id', $this->otherAdminUser->id));
        $this->assertFalse($adminUsers->contains('id', $this->superadminUser->id));
        $this->assertTrue($adminUsers->contains('id', $this->pegawaiUser->id));
        $this->assertTrue($adminUsers->contains('id', $this->pimpinanUser->id));

        // Saat diakses Superadmin
        $superIndex = $this->actingAs($this->superadminUser)->get('/akses/grant');
        $superIndex->assertStatus(200);
        $superUsers = collect($superIndex->viewData('page')['props']['users']);

        $this->assertTrue($superUsers->contains('id', $this->adminUser->id));
        $this->assertTrue($superUsers->contains('id', $this->otherAdminUser->id));
        $this->assertTrue($superUsers->contains('id', $this->superadminUser->id));
        $this->assertTrue($superUsers->contains('id', $this->pegawaiUser->id));
    }

    /**
     * Visibilitas Tombol Cabut: can_revoke bernilai false untuk Admin yang melihat grant Admin/Superadmin.
     */
    public function test_index_marks_can_revoke_appropriately(): void
    {
        $adminGrant = UserPermissionGrant::create([
            'user_id' => $this->otherAdminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Grant admin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $pegawaiGrant = UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
            'alasan' => 'Grant pegawai',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        // Aktor Admin: grant admin terkunci (can_revoke = false), grant pegawai bisa dicabut (can_revoke = true)
        $adminIndex = $this->actingAs($this->adminUser)->get('/akses/grant');
        $adminGrants = collect($adminIndex->viewData('page')['props']['grants'])->keyBy('id');

        $this->assertFalse($adminGrants[$adminGrant->id]['can_revoke']);
        $this->assertTrue($adminGrants[$pegawaiGrant->id]['can_revoke']);

        // Aktor Superadmin: kedua grant bisa dicabut (can_revoke = true)
        $superIndex = $this->actingAs($this->superadminUser)->get('/akses/grant');
        $superGrants = collect($superIndex->viewData('page')['props']['grants'])->keyBy('id');

        $this->assertTrue($superGrants[$adminGrant->id]['can_revoke']);
        $this->assertTrue($superGrants[$pegawaiGrant->id]['can_revoke']);
    }

    /**
     * Temuan 1: Tolak pencabutan grant berscope global melalui endpoint unit (HTTP 422).
     */
    public function test_cannot_revoke_global_grant_via_unit_grant_endpoint(): void
    {
        $globalPerm = Permission::where('kode', 'pengguna:read')->firstOrFail();

        // Buat grant global langsung di database (unit_id = null)
        $globalGrant = UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $globalPerm->id,
            'unit_id' => null,
            'alasan' => 'Grant global pengujian',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete("/akses/grant/{$globalGrant->id}", [
                'alasan' => 'Mencoba mencabut grant global lewat endpoint unit.',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('user_permission_granted', ['id' => $globalGrant->id]);
    }

    /**
     * Temuan 5: Tolak permission nonaktif sebelum membuat grant (HTTP 422 Validation Error).
     */
    public function test_cannot_grant_inactive_permission(): void
    {
        // Nonaktifkan permission
        $this->unitPermission->update(['aktif' => false]);

        $response = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Mencoba memberikan izin yang nonaktif.',
            ]);

        $response->assertSessionHasErrors('permission_id');
        $this->assertDatabaseMissing('user_permission_granted', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
        ]);
    }

    /**
     * Temuan 3: Tangani benturan unik saat grant dibuat bersamaan (SQLSTATE 23505 -> Validation Error).
     */
    public function test_concurrent_grant_creation_handles_unique_violation_gracefully(): void
    {
        // Buat grant yang sama terlebih dahulu untuk mensimulasikan request konkuren yang telah menyelesaikan insert
        UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Grant pertama yang sudah tersimpan',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        // Request kedua yang mencoba membuat grant identik
        $response = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Grant kedua dari request paralel',
            ]);

        $response->assertSessionHasErrors('permission_id');
    }

    /**
     * Filter daftar agar hanya memuat grant unit (unit_id IS NOT NULL dan butuh_scope = 'unit').
     */
    public function test_grant_index_only_lists_unit_scoped_grants(): void
    {
        // 1. Grant unit valid
        $unitGrant = UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Grant unit valid',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        // 2. Grant global (unit_id = null)
        UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->globalPermission->id,
            'unit_id' => null,
            'alasan' => 'Grant global di luar cakupan unit',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/akses/grant');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Akses/GrantIndex')
            ->has('grants', 1)
            ->where('grants.0.id', $unitGrant->id)
            ->where('grants.0.unit_id', $this->unitA->id)
        );
    }

    /**
     * Codex Review: Request dengan user_id, permission_id, atau unit_id non-UUID ditolak dengan validasi 422.
     */
    public function test_grant_creation_rejects_non_uuid_identifiers_with_validation_error(): void
    {
        // 1. Non-UUID user_id
        $responseUser = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => 'bukan-sebuah-uuid',
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Uji validasi UUID user_id',
            ]);

        $responseUser->assertSessionHasErrors('user_id');
        $this->assertEquals(
            'Format ID pengguna tidak valid.',
            session('errors')->first('user_id')
        );

        // 2. Non-UUID permission_id
        $responsePerm = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => 'invalid-uuid-perm',
                'unit_id' => $this->unitA->id,
                'alasan' => 'Uji validasi UUID permission_id',
            ]);

        $responsePerm->assertSessionHasErrors('permission_id');
        $this->assertEquals(
            'Format ID permission tidak valid.',
            session('errors')->first('permission_id')
        );

        // 3. Non-UUID unit_id
        $responseUnit = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => '12345-not-uuid',
                'alasan' => 'Uji validasi UUID unit_id',
            ]);

        $responseUnit->assertSessionHasErrors('unit_id');
        $this->assertEquals(
            'Format ID unit tidak valid.',
            session('errors')->first('unit_id')
        );
    }
}
