<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\PermissionResolver;
use App\Support\PermissionDecision;
use Carbon\Carbon;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasterUnitOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $admin;

    protected User $pegawai;

    protected Unit $unitInduk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-03-20 10:00:00'));

        $this->seed(AccessCatalogSeeder::class);

        $this->superadmin = User::factory()->create([
            'nama' => 'Superadmin Test',
            'email' => 'superadmin@example.test',
            'is_active' => true,
        ]);

        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $this->superadmin->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);
        foreach (['unit:read', 'unit:create', 'unit:update', 'unit:delete'] as $p) {
            $perm = Permission::where('kode', $p)->firstOrFail();
            $superadminRole->permissions()->attach($perm->id, ['id' => (string) Str::uuid(), 'created_at' => now()]);
        }

        $this->admin = User::factory()->create([
            'nama' => 'Admin Test',
            'email' => 'admin@example.test',
            'is_active' => true,
        ]);

        $adminRole = Role::where('kode', 'admin')->firstOrFail();
        $this->admin->roles()->attach($adminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);
        foreach (['unit:read', 'unit:create', 'unit:update'] as $p) {
            $perm = Permission::where('kode', $p)->firstOrFail();
            $adminRole->permissions()->attach($perm->id, ['id' => (string) Str::uuid(), 'created_at' => now()]);
        }

        $this->pegawai = User::factory()->create([
            'nama' => 'Pegawai Biasa Test',
            'email' => 'pegawai@example.test',
            'is_active' => true,
        ]);

        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $this->pegawai->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);

        $this->unitInduk = Unit::create([
            'nama' => 'Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);
    }

    /**
     * AC-1 / TEST-1: Admin dapat membuat unit organisasi dengan data valid dan status default aktif.
     */
    public function test_admin_can_create_unit(): void
    {
        $payload = [
            'nama' => 'Kelompok Kerja Sumber Daya Perguruan Tinggi',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post('/unit', $payload);

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('unit', [
            'nama' => 'Kelompok Kerja Sumber Daya Perguruan Tinggi',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->admin->id,
            'tindakan' => 'unit.tambah',
            'objek_tipe' => 'unit',
        ]);
    }

    /**
     * AC-2 / TEST-2: Unit yang masih memiliki relasi ke indikator kinerja ditolak dihapus.
     */
    public function test_delete_unit_linked_to_indicator_is_rejected(): void
    {
        $unit = Unit::create([
            'nama' => 'Pokja Akademik',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $renstra = Renstra::create([
            'kode' => 'RENSTRA-TEST',
            'nama' => 'Renstra Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-TEST',
            'deskripsi' => 'Sasaran Test',
        ]);

        IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-TEST',
            'nama' => 'Indikator Test',
            'satuan' => '%',
            'unit_id' => $unit->id,
            'arah' => 'naik_baik',
        ]);

        // Superadmin mencoba menghapus unit yang ada indikatornya
        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", [
            'alasan' => 'Mencoba hapus unit yang memiliki indikator',
        ]);

        // Ditolak dengan 403 dan dicatat di audit log
        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->superadmin->id,
            'tindakan' => 'unit.hapus_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * Temuan Review 2: Unit yang memiliki relasi ke user_permission_denied ditolak dihapus.
     */
    public function test_delete_unit_linked_to_user_permission_denied_is_rejected(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Denial Test',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $perm = Permission::where('butuh_scope', 'unit')->firstOrFail();

        UserPermissionDeny::create([
            'user_id' => $this->pegawai->id,
            'permission_id' => $perm->id,
            'unit_id' => $unit->id,
            'alasan' => 'Larangan penugasan khusus unit untuk pegawai',
            'ditetapkan_oleh' => $this->superadmin->id,
        ]);

        $this->assertFalse($unit->isDeletable());

        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", [
            'alasan' => 'Mencoba hapus unit yang memiliki deny',
        ]);

        // Ditolak dengan 403 dan dicatat di audit log
        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->superadmin->id,
            'tindakan' => 'unit.hapus_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * AC-3 / TEST-3: Superadmin dapat menghapus unit yang benar-benar kosong dengan alasan tertulis.
     */
    public function test_superadmin_can_delete_empty_unit(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Kosong Eksperimen',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $alasan = 'Unit kosong hasil uji coba dihapus secara administratif';

        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", [
            'alasan' => $alasan,
        ]);

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('unit', ['id' => $unit->id]);

        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->superadmin->id,
            'tindakan' => 'unit.hapus',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
            'alasan' => $alasan,
        ]);
    }

    /**
     * Codex Review 5: Penghapusan unit wajib mencantumkan alasan minimal 5 karakter.
     */
    public function test_delete_unit_requires_reason(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Uji Alasan',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        // 1. Tanpa alasan
        $responseMissing = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", []);
        $responseMissing->assertSessionHasErrors('alasan');

        // 2. Alasan terlalu pendek (< 5 karakter)
        $responseShort = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", [
            'alasan' => 'abc',
        ]);
        $responseShort->assertSessionHasErrors('alasan');

        // Unit tetap utuh
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
    }

    /**
     * AC-3: Admin biasa TIDAK berwenang menghapus unit kosong sekalipun.
     */
    public function test_admin_cannot_delete_empty_unit(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Kosong Lain',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete("/unit/{$unit->id}", [
            'alasan' => 'Admin mencoba menghapus unit',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->admin->id,
            'tindakan' => 'unit.hapus_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * AC-4 / TEST-4: Admin dapat mengubah dan menonaktifkan unit tanpa menghapus data historis.
     */
    public function test_admin_can_update_and_deactivate_unit(): void
    {
        $unit = Unit::create([
            'nama' => 'Bagian Umum Lama',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Bagian Umum Baru',
            'status' => 'nonaktif',
        ]);

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $unit->refresh();
        $this->assertEquals('Bagian Umum Baru', $unit->nama);
        $this->assertEquals('nonaktif', $unit->status);

        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->admin->id,
            'tindakan' => 'unit.ubah',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * AC-5 / TEST-5: Pengguna tanpa hak akses unit:* menghasilkan 403 Forbidden.
     */
    public function test_unauthorized_user_is_forbidden(): void
    {
        // Pegawai biasa mencoba akses index
        $response = $this->actingAs($this->pegawai)->get('/unit');
        $response->assertStatus(403);

        // Pegawai biasa mencoba create
        $createResponse = $this->actingAs($this->pegawai)->post('/unit', [
            'nama' => 'Illegal Unit',
        ]);
        $createResponse->assertStatus(403);
    }

    /**
     * Temuan Review 8: Parameter route non-UUID menghasilkan 404 bukan 500.
     */
    public function test_invalid_uuid_route_parameters_return_404(): void
    {
        $updateResponse = $this->actingAs($this->admin)->post('/unit/bukan-uuid', [
            'nama' => 'Invalid UUID Unit',
            'status' => 'aktif',
        ]);
        $updateResponse->assertStatus(404);

        $deleteResponse = $this->actingAs($this->superadmin)->delete('/unit/bukan-uuid');
        $deleteResponse->assertStatus(404);
    }

    /**
     * Codex Review: Input nama unit yang hanya berisi spasi ditolak pada pembuatan.
     */
    public function test_cannot_create_unit_with_whitespace_only_name(): void
    {
        $response = $this->actingAs($this->admin)->post('/unit', [
            'nama' => "   \t  \n  ",
            'status' => 'aktif',
        ]);

        $response->assertSessionHasErrors('nama');
        $this->assertDatabaseMissing('unit', [
            'status' => 'aktif',
            'nama' => '',
        ]);
    }

    /**
     * Codex Review: Input nama unit yang hanya berisi spasi ditolak pada pembaruan.
     */
    public function test_cannot_update_unit_with_whitespace_only_name(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Valid Awal',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => '     ',
            'status' => 'aktif',
        ]);

        $response->assertSessionHasErrors('nama');
        $unit->refresh();
        $this->assertSame('Unit Valid Awal', $unit->nama);
    }

    /**
     * Codex Review: Penghapusan unit dengan alasan hanya berisi spasi ditolak dengan validasi 422.
     */
    public function test_delete_unit_rejects_whitespace_only_reason(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Uji Alasan Spasi',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", [
            'alasan' => '     ',
        ]);

        $response->assertSessionHasErrors('alasan');
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
    }

    /**
     * Codex Review: Otorisasi ulang aktor di dalam transaksi penghapusan unit mencatat audit penolakan di luar transaksi.
     */
    public function test_delete_unit_reauthorizes_actor_inside_transaction_and_preserves_rejection_audit(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Uji Otorisasi Ulang',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        // Berikan deny eksplisit pada superadmin untuk unit:delete
        UserPermissionDeny::create([
            'user_id' => $this->superadmin->id,
            'permission_id' => Permission::where('kode', 'unit:delete')->firstOrFail()->id,
            'unit_id' => null,
            'alasan' => 'Pencabutan izin hapus unit',
            'ditetapkan_oleh' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", [
            'alasan' => 'Mencoba menghapus unit dengan izin dicabut',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->superadmin->id,
            'tindakan' => 'unit.hapus_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * Codex Review: Penambahan unit menolak duplikasi nama tanpa membedakan kapitalisasi (case-insensitive).
     */
    public function test_store_unit_rejects_duplicate_name_case_insensitive(): void
    {
        Unit::create([
            'nama' => 'Bagian Perencanaan dan Kerjasama',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        // Coba input dengan huruf kecil semua
        $responseLower = $this->actingAs($this->admin)->post('/unit', [
            'nama' => 'bagian perencanaan dan kerjasama',
            'status' => 'aktif',
        ]);

        $responseLower->assertSessionHasErrors('nama');

        // Coba input dengan huruf kapital semua dan spasi tambahan
        $responseUpper = $this->actingAs($this->admin)->post('/unit', [
            'nama' => '  BAGIAN PERENCANAAN DAN KERJASAMA  ',
            'status' => 'aktif',
        ]);

        $responseUpper->assertSessionHasErrors('nama');
    }

    /**
     * Codex Review: Pembaruan unit menolak duplikasi nama dari unit lain tanpa membedakan kapitalisasi.
     */
    public function test_update_unit_rejects_duplicate_name_case_insensitive(): void
    {
        $unitA = Unit::create([
            'nama' => 'Bagian Keuangan',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        $unitB = Unit::create([
            'nama' => 'Bagian Umum',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        // Unit B mencoba menggunakan nama Unit A dengan huruf kapital berbeda
        $responseDuplicate = $this->actingAs($this->admin)->post("/unit/{$unitB->id}", [
            'nama' => 'bagian keuangan',
            'status' => 'aktif',
        ]);

        $responseDuplicate->assertSessionHasErrors('nama');

        // Unit B memperbarui namanya sendiri dengan perubahan kapitalisasi diperbolehkan
        $responseSelf = $this->actingAs($this->admin)->post("/unit/{$unitB->id}", [
            'nama' => 'BAGIAN UMUM',
            'status' => 'aktif',
        ]);

        $responseSelf->assertSessionHasNoErrors();
        $unitB->refresh();
        $this->assertSame('BAGIAN UMUM', $unitB->nama);
    }

    /**
     * Codex Review: Penambahan unit mengevaluasi ulang izin aktor di dalam transaksi dan mencatat penolakan di luar transaksi.
     */
    public function test_store_unit_reauthorizes_actor_inside_transaction_and_preserves_rejection_audit(): void
    {
        $mockResolver = $this->mock(PermissionResolver::class);
        $mockResolver->shouldReceive('resolve')
            ->with(\Mockery::any(), 'unit:create')
            ->andReturn(new PermissionDecision(false, 'unit:create', [
                'alasan' => 'no_allow',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]));

        $response = $this->actingAs($this->admin)->post('/unit', [
            'nama' => 'Unit Uji Reotorisasi Store',
            'status' => 'aktif',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('unit', ['nama' => 'Unit Uji Reotorisasi Store']);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->admin->id,
            'tindakan' => 'unit.tambah_ditolak',
            'objek_tipe' => 'unit',
        ]);
    }

    /**
     * Codex Review: Pembaruan unit mengevaluasi ulang izin aktor di dalam transaksi dan mencatat penolakan di luar transaksi.
     */
    public function test_update_unit_reauthorizes_actor_inside_transaction_and_preserves_rejection_audit(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Awal Update Reotorisasi',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        $mockResolver = $this->mock(PermissionResolver::class);
        $mockResolver->shouldReceive('resolve')
            ->with(\Mockery::any(), 'unit:update')
            ->andReturn(new PermissionDecision(false, 'unit:update', [
                'alasan' => 'no_allow',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]));

        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Berubah Nama',
            'status' => 'aktif',
        ]);

        $response->assertStatus(403);
        $unit->refresh();
        $this->assertSame('Unit Awal Update Reotorisasi', $unit->nama);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->admin->id,
            'tindakan' => 'unit.ubah_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }
}
