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
        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}");

        // Ditolak oleh Policy (403)
        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
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

        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}");

        // Ditolak oleh Policy (403)
        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
    }

    /**
     * AC-3 / TEST-3: Superadmin dapat menghapus unit yang benar-benar kosong.
     */
    public function test_superadmin_can_delete_empty_unit(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Kosong Eksperimen',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}");

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('unit', ['id' => $unit->id]);

        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->superadmin->id,
            'tindakan' => 'unit.hapus',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
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

        $response = $this->actingAs($this->admin)->delete("/unit/{$unit->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unit->id]);
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
}
