<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
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
        $adminGrants = collect($adminIndex->viewData('page')['props']['grants']['data'] ?? $adminIndex->viewData('page')['props']['grants'])->keyBy('id');

        $this->assertFalse($adminGrants[$adminGrant->id]['can_revoke']);
        $this->assertTrue($adminGrants[$pegawaiGrant->id]['can_revoke']);

        // Aktor Superadmin: kedua grant bisa dicabut (can_revoke = true)
        $superIndex = $this->actingAs($this->superadminUser)->get('/akses/grant');
        $superGrants = collect($superIndex->viewData('page')['props']['grants']['data'] ?? $superIndex->viewData('page')['props']['grants'])->keyBy('id');

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
            ->has('grants.data', 1)
            ->where('grants.data.0.id', $unitGrant->id)
            ->where('grants.data.0.unit_id', $this->unitA->id)
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

    /**
     * Codex Review 1: Tolak penerima grant yang sudah nonaktif.
     */
    public function test_cannot_grant_permission_to_inactive_user(): void
    {
        $inactiveUser = User::factory()->create([
            'nama' => 'User Nonaktif',
            'email' => 'nonaktif@sakip.test',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $inactiveUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Mencoba memberikan izin ke akun nonaktif',
            ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertDatabaseMissing('user_permission_granted', [
            'user_id' => $inactiveUser->id,
        ]);
    }

    /**
     * Codex Review 2: Tolak pembuatan grant untuk unit nonaktif.
     */
    public function test_cannot_grant_permission_for_inactive_unit(): void
    {
        $inactiveUnit = Unit::create([
            'nama' => 'Unit Nonaktif Uji',
            'status' => 'nonaktif',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $inactiveUnit->id,
                'alasan' => 'Mencoba memberikan izin pada unit nonaktif',
            ]);

        $response->assertSessionHasErrors('unit_id');
        $this->assertDatabaseMissing('user_permission_granted', [
            'unit_id' => $inactiveUnit->id,
        ]);
    }

    /**
     * Codex Review 4: Catat penolakan aksi sensitif sebelum mengembalikan 403 saat aktor memiliki explicit deny.
     */
    public function test_explicit_deny_on_akses_update_records_denial_audit_before_403(): void
    {
        $perm = Permission::where('kode', 'akses:update')->firstOrFail();

        // Admin di-deny untuk akses:update
        UserPermissionDeny::create([
            'user_id' => $this->adminUser->id,
            'permission_id' => $perm->id,
            'unit_id' => null,
            'alasan' => 'Larangan eksplisit kelola akses',
            'ditetapkan_oleh' => $this->superadminUser->id,
        ]);

        // 1. Coba Store Grant
        $responseStore = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Percobaan store grant saat di-deny',
            ]);

        $responseStore->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'alasan' => 'Anda tidak berwenang mengelola pemberian izin unit.',
        ]);

        // 2. Coba Revoke Grant
        $grant = UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Grant awal oleh superadmin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $responseRevoke = $this->actingAs($this->adminUser)
            ->delete("/akses/grant/{$grant->id}", [
                'alasan' => 'Percobaan revoke saat di-deny',
            ]);

        $responseRevoke->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'alasan' => 'Anda tidak berwenang mengelola pencabutan izin unit.',
        ]);
    }

    /**
     * Codex Review 4: Penolakan Admin mengelola grant Admin/Superadmin dicatat di audit log.
     */
    public function test_admin_granting_or_revoking_admin_records_denial_audit(): void
    {
        // 1. Admin mencoba grant ke admin lain
        $responseStore = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->otherAdminUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Admin mencoba memberi grant ke admin lain',
            ]);

        $responseStore->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'objek_tipe' => 'users',
            'objek_id' => $this->otherAdminUser->id,
        ]);

        // 2. Admin mencoba revoke grant milik admin lain
        $grantAdmin = UserPermissionGrant::create([
            'user_id' => $this->otherAdminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Diberikan oleh superadmin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $responseRevoke = $this->actingAs($this->adminUser)
            ->delete("/akses/grant/{$grantAdmin->id}", [
                'alasan' => 'Admin mencoba mencabut grant admin lain',
            ]);

        $responseRevoke->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'objek_tipe' => 'user_permission_granted',
            'objek_id' => (string) $grantAdmin->id,
        ]);
    }

    /**
     * Codex Review: Index grant mendukung server pagination dan filtering (search dan unit_id).
     */
    public function test_grant_index_supports_server_pagination_and_filtering(): void
    {
        $pegawai2 = User::factory()->create([
            'nama' => 'Budi Santoso',
            'email' => 'budi@sakip.test',
            'is_active' => true,
        ]);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $pegawai2->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $grant1 = UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Grant untuk pegawai staf di unit A',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        $grant2 = UserPermissionGrant::create([
            'user_id' => $pegawai2->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitB->id,
            'alasan' => 'Grant untuk budi di unit B',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        // 1. Filter pencarian nama
        $resSearch = $this->actingAs($this->adminUser)
            ->get('/akses/grant?search=Budi');
        $resSearch->assertOk();
        $resSearch->assertInertia(fn ($page) => $page
            ->component('Akses/GrantIndex')
            ->has('grants.data', 1)
            ->where('grants.data.0.id', $grant2->id)
            ->where('filters.search', 'Budi')
        );

        // 2. Filter unit A
        $resUnit = $this->actingAs($this->adminUser)
            ->get("/akses/grant?unit_id={$this->unitA->id}");
        $resUnit->assertOk();
        $resUnit->assertInertia(fn ($page) => $page
            ->component('Akses/GrantIndex')
            ->has('grants.data', 1)
            ->where('grants.data.0.id', $grant1->id)
            ->where('filters.unit_id', $this->unitA->id)
        );
    }

    /**
     * Codex Review: Pencegahan TOCTOU jika pengguna atau unit dinonaktifkan di dalam transaksi.
     */
    public function test_grant_creation_fails_if_target_becomes_inactive_during_transaction(): void
    {
        // 1. Target user berstatus nonaktif
        $userTarget = User::factory()->create([
            'nama' => 'Target Nonaktif Konkuren',
            'email' => 'target.nonaktif@sakip.test',
            'is_active' => true,
        ]);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $userTarget->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        // Simulasi status user menjadi nonaktif tepat sebelum lock didapat
        $userTarget->update(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $userTarget->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Pemberian izin ke pengguna yang nonaktif',
            ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertDatabaseMissing('user_permission_granted', [
            'user_id' => $userTarget->id,
        ]);

        // 2. Unit berstatus nonaktif
        $unitNonaktif = Unit::create([
            'nama' => 'Unit Nonaktif Konkuren',
            'status' => 'nonaktif',
            'created_by' => $this->superadminUser->id,
        ]);

        $responseUnit = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $unitNonaktif->id,
                'alasan' => 'Pemberian izin ke unit yang dinonaktifkan',
            ]);

        $responseUnit->assertSessionHasErrors('unit_id');
        $this->assertDatabaseMissing('user_permission_granted', [
            'unit_id' => $unitNonaktif->id,
        ]);
    }

    /**
     * Codex Review: Request dengan unit_id non-UUID ditangani secara aman tanpa SQL error 500.
     */
    public function test_index_grant_handles_non_uuid_unit_id_gracefully(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/akses/grant?unit_id=not-a-uuid');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Akses/GrantIndex')
            ->where('filters.unit_id', 'all')
        );
    }

    /**
     * Codex Review: Request dengan alasan hanya berisi spasi ditolak dengan validasi 422, bukan 500.
     */
    public function test_grant_creation_and_revocation_rejects_whitespace_only_reason(): void
    {
        // 1. Store grant dengan alasan spasi saja
        $responseStore = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => '     ',
            ]);

        $responseStore->assertSessionHasErrors('alasan');
        $this->assertDatabaseMissing('user_permission_granted', [
            'user_id' => $this->pegawaiUser->id,
            'unit_id' => $this->unitA->id,
        ]);

        // 2. Revoke grant dengan alasan spasi saja
        $grant = UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Alasan awal valid',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        $responseRevoke = $this->actingAs($this->adminUser)
            ->delete("/akses/grant/{$grant->id}", [
                'alasan' => '     ',
            ]);

        $responseRevoke->assertSessionHasErrors('alasan');
        $this->assertDatabaseHas('user_permission_granted', [
            'id' => $grant->id,
        ]);
    }

    /**
     * Codex Review: Pemeriksaan hierarki mengabaikan role yang nonaktif (roles.aktif = false).
     */
    public function test_inactive_role_is_ignored_in_hierarchy_and_access_checks(): void
    {
        $inactiveRole = Role::create([
            'kode' => 'admin_nonaktif',
            'nama' => 'Admin Nonaktif',
            'keterangan' => 'Peran nonaktif untuk pengujian',
            'is_sistem' => false,
            'urutan' => 99,
            'aktif' => false,
        ]);

        $testUser = User::factory()->create([
            'nama' => 'Pengguna Role Nonaktif',
            'email' => 'user.inactive.role@sakip.test',
            'is_active' => true,
        ]);

        $testUser->roles()->attach($inactiveRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // hasRole dan hasAnyRole harus mengabaikan role nonaktif
        $this->assertFalse($testUser->hasRole('admin_nonaktif'));
        $this->assertFalse($testUser->hasAnyRole(['admin_nonaktif']));

        // Ketika role dimuat eager load
        $testUser->load('roles');
        $this->assertFalse($testUser->hasRole('admin_nonaktif'));
        $this->assertFalse($testUser->hasAnyRole(['admin_nonaktif']));
    }

    /**
     * Codex Review: Otorisasi ulang aktor di dalam transaksi grant menolak mutasi jika izin dicabut saat transaksi.
     */
    public function test_grant_creation_reauthorizes_actor_inside_transaction(): void
    {
        // Berikan deny eksplisit pada aktor untuk akses:update
        UserPermissionDeny::create([
            'user_id' => $this->adminUser->id,
            'permission_id' => Permission::where('kode', 'akses:update')->firstOrFail()->id,
            'unit_id' => null,
            'alasan' => 'Pencabutan wewenang kelola akses',
            'ditetapkan_oleh' => $this->superadminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Mencoba membuat grant saat izin dicabut',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('user_permission_granted', [
            'user_id' => $this->pegawaiUser->id,
            'unit_id' => $this->unitA->id,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'objek_tipe' => 'user_permission_granted',
        ]);
    }

    /**
     * Codex Review: Kunci dan validasi ulang permission di dalam transaksi menolak permission yang dinonaktifkan dengan 422.
     */
    public function test_grant_creation_locks_and_revalidates_permission_inside_transaction(): void
    {
        // Nonaktifkan permission
        $this->unitPermission->update(['aktif' => false]);

        $response = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->pegawaiUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Mencoba memberi permission nonaktif',
            ]);

        $response->assertSessionHasErrors('permission_id');
        $this->assertDatabaseMissing('user_permission_granted', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
        ]);
    }

    /**
     * Codex Review: Penolakan otorisasi di dalam transaksi tetap mempertahankan catatan audit di luar transaksi.
     */
    public function test_grant_creation_and_revocation_preserves_rejection_audit_in_database(): void
    {
        // 1. StoreGrant: Admin mencoba memberi izin kepada Superadmin (ditolak hierarki)
        $responseStore = $this->actingAs($this->adminUser)
            ->post('/akses/grant', [
                'user_id' => $this->superadminUser->id,
                'permission_id' => $this->unitPermission->id,
                'unit_id' => $this->unitA->id,
                'alasan' => 'Admin mencoba memberi izin ke Superadmin',
            ]);

        $responseStore->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'objek_tipe' => 'users',
            'objek_id' => (string) $this->superadminUser->id,
        ]);

        // 2. RevokeGrant: Admin mencoba mencabut izin milik Superadmin (ditolak hierarki)
        $superadminGrant = UserPermissionGrant::create([
            'user_id' => $this->superadminUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Izin awal superadmin',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $responseRevoke = $this->actingAs($this->adminUser)
            ->delete("/akses/grant/{$superadminGrant->id}", [
                'alasan' => 'Admin mencoba mencabut izin Superadmin',
            ]);

        $responseRevoke->assertStatus(403);
        $this->assertDatabaseHas('user_permission_granted', [
            'id' => $superadminGrant->id,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'objek_tipe' => 'user_permission_granted',
            'objek_id' => (string) $superadminGrant->id,
        ]);

        // 3. RevokeGrant: Otorisasi ulang aktor gagal di dalam transaksi
        UserPermissionDeny::create([
            'user_id' => $this->adminUser->id,
            'permission_id' => Permission::where('kode', 'akses:update')->firstOrFail()->id,
            'unit_id' => null,
            'alasan' => 'Pencabutan akses kelola izin',
            'ditetapkan_oleh' => $this->superadminUser->id,
        ]);

        $pegawaiGrant = UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Izin awal pegawai',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $responseRevokeDenied = $this->actingAs($this->adminUser)
            ->delete("/akses/grant/{$pegawaiGrant->id}", [
                'alasan' => 'Mencoba mencabut saat wewenang dicabut',
            ]);

        $responseRevokeDenied->assertStatus(403);
        $this->assertDatabaseHas('user_permission_granted', [
            'id' => $pegawaiGrant->id,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'objek_tipe' => 'user_permission_granted',
            'objek_id' => (string) $pegawaiGrant->id,
        ]);
    }

    /**
     * Codex Review: Batasi grant pada kode permission yang masih ada di katalog UNIT_SCOPED.
     */
    public function test_grant_creation_and_dropdown_rejects_unit_permission_not_in_catalog_scoped(): void
    {
        $nonCatalogPermission = Permission::create([
            'id' => (string) Str::uuid(),
            'kode' => 'custom_entity:custom_action',
            'entitas' => 'custom_entity',
            'aksi' => 'custom_action',
            'keterangan' => 'Izin uji di luar PermissionCatalog::UNIT_SCOPED',
            'butuh_scope' => Permission::SCOPE_UNIT,
            'aktif' => true,
        ]);

        // 1. Dropdown di IndexGrant tidak boleh memuat permission ini
        $responseIndex = $this->actingAs($this->adminUser)->get('/akses/grant');
        $responseIndex->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Akses/GrantIndex')
            ->where('unitPermissions', fn ($permissions) => ! collect($permissions)->pluck('kode')->contains('custom_entity:custom_action'))
        );

        // 2. StoreGrant menolak permission ini dengan validasi 422
        $responseStore = $this->actingAs($this->adminUser)->post('/akses/grant', [
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $nonCatalogPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Mencoba memberikan izin unit di luar katalog resmi',
        ]);

        $responseStore->assertSessionHasErrors('permission_id');
        $this->assertDatabaseMissing('user_permission_granted', [
            'permission_id' => $nonCatalogPermission->id,
        ]);
    }

    /**
     * Codex Review: Jangan aktifkan ulang permission melalui seeder katalog saat dijalankan ulang.
     */
    public function test_permission_catalog_seeder_does_not_reactivate_deactivated_permission(): void
    {
        // Nonaktifkan salah satu permission secara sengaja
        $permission = Permission::where('kode', 'pengukuran:create')->firstOrFail();
        $permission->update(['aktif' => false]);
        $this->assertFalse($permission->fresh()->aktif);

        // Jalankan ulang seeder PermissionCatalogSeeder
        $this->seed(PermissionCatalogSeeder::class);

        // Status aktif harus tetap false (tidak dipaksa aktif kembali)
        $this->assertFalse($permission->fresh()->aktif);
    }

    /**
     * Codex Review: Kunci pengguna target sebelum memeriksa hierarki pencabutan agar menghormati promosi peran konkuren.
     */
    public function test_revoke_grant_locks_target_and_prevents_admin_from_revoking_promoted_user_grant(): void
    {
        // Target awalnya pegawai biasa yang menerima grant
        $targetUser = User::factory()->create([
            'nama' => 'Pegawai Calon Admin',
            'email' => 'calon.admin@sakip.test',
            'is_active' => true,
        ]);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $targetUser->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $grant = UserPermissionGrant::create([
            'user_id' => $targetUser->id,
            'permission_id' => $this->unitPermission->id,
            'unit_id' => $this->unitA->id,
            'alasan' => 'Izin awal sebelum promosi',
            'diberikan_oleh' => $this->adminUser->id,
        ]);

        // Target dipromosikan menjadi Admin
        $adminRole = Role::where('kode', 'admin')->firstOrFail();
        $targetUser->roles()->detach();
        $targetUser->roles()->attach($adminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // Admin biasa mencoba mencabut grant milik pengguna yang sekarang sudah berstatus Admin
        $response = $this->actingAs($this->adminUser)->delete("/akses/grant/{$grant->id}", [
            'alasan' => 'Mencoba cabut grant pengguna yang sudah dipromosikan',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('user_permission_granted', [
            'id' => $grant->id,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->adminUser->id,
            'tindakan' => 'user_permission_granted.ditolak',
            'objek_tipe' => 'user_permission_granted',
            'objek_id' => (string) $grant->id,
        ]);
    }
}
