<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterUnitOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $admin;

    protected User $pegawai;

    protected UnitKerja $unitInduk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-03-20 10:00:00'));

        foreach (['superadmin', 'admin', 'perencanaan', 'pimpinan', 'pegawai'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->unitInduk = UnitKerja::create([
            'kode' => 'LLDIKTI16',
            'nama' => 'Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
            'singkatan' => 'LLDIKTI XVI',
            'is_active' => true,
        ]);

        $this->superadmin = User::create([
            'name' => 'Superadmin Test',
            'email' => 'superadmin@example.test',
            'password' => 'password',
            'unit_kerja_id' => $this->unitInduk->id,
        ]);
        $this->superadmin->assignRole('superadmin');

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@example.test',
            'password' => 'password',
            'unit_kerja_id' => $this->unitInduk->id,
        ]);
        $this->admin->assignRole('admin');

        $this->pegawai = User::create([
            'name' => 'Pegawai Biasa Test',
            'email' => 'pegawai@example.test',
            'password' => 'password',
            'unit_kerja_id' => $this->unitInduk->id,
        ]);
        $this->pegawai->assignRole('pegawai');
    }

    /**
     * AC-1 / TEST-1: Admin dapat membuat unit organisasi dengan data valid,
     * status default aktif, dan tercatat di audit_log.
     */
    public function test_admin_can_create_unit_and_records_audit_log(): void
    {
        $payload = [
            'kode' => 'POKJA-SDPT',
            'nama' => 'Kelompok Kerja Sumber Daya Perguruan Tinggi',
            'singkatan' => 'Pokja SDPT',
            'parent_id' => $this->unitInduk->id,
            'urutan' => 3,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->post('/unit', $payload);

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('unit_kerjas', [
            'kode' => 'POKJA-SDPT',
            'nama' => 'Kelompok Kerja Sumber Daya Perguruan Tinggi',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $unit = UnitKerja::where('kode', 'POKJA-SDPT')->first();
        $this->assertNotNull($unit);

        // Pastikan tercatat di audit log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'tindakan' => 'unit.create',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * AC-2 / TEST-2: Unit yang masih memiliki relasi ke indikator kinerja ditolak dihapus.
     */
    public function test_delete_unit_linked_to_indicator_is_rejected(): void
    {
        $unit = UnitKerja::create([
            'kode' => 'POKJA-AK',
            'nama' => 'Pokja Akademik',
            'is_active' => true,
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

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-TEST',
            'nama' => 'Indikator Test',
            'satuan' => '%',
            'tipe_perhitungan' => 'naik_baik',
        ]);

        PenugasanIndikator::create([
            'indikator_kinerja_id' => $indikator->id,
            'unit_kerja_id' => $unit->id,
            'tahun' => 2026,
            'is_active' => true,
        ]);

        // Superadmin mencoba menghapus unit yang ada indikatornya
        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}");

        // Ditolak oleh Policy (403)
        $response->assertStatus(403);
        $this->assertDatabaseHas('unit_kerjas', ['id' => $unit->id]);
    }

    /**
     * AC-3 / TEST-3: Superadmin dapat menghapus unit yang benar-benar kosong,
     * dan tercatat di audit_log beserta alasan.
     */
    public function test_superadmin_can_delete_empty_unit_and_records_audit(): void
    {
        $unit = UnitKerja::create([
            'kode' => 'UNIT-KOSONG',
            'nama' => 'Unit Kosong Eksperimen',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unit->id}", [
            'alasan' => 'Unit salah dibuat dan tidak pernah dipakai',
        ]);

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('unit_kerjas', ['id' => $unit->id]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->superadmin->id,
            'tindakan' => 'unit.delete',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
            'alasan' => 'Unit salah dibuat dan tidak pernah dipakai',
        ]);
    }

    /**
     * AC-3: Admin biasa TIDAK berwenang menghapus unit kosong sekalipun.
     */
    public function test_admin_cannot_delete_empty_unit(): void
    {
        $unit = UnitKerja::create([
            'kode' => 'UNIT-KOSONG-2',
            'nama' => 'Unit Kosong Lain',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/unit/{$unit->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('unit_kerjas', ['id' => $unit->id]);
    }

    /**
     * AC-4 / TEST-4: Admin dapat mengubah dan menonaktifkan unit tanpa menghapus data historis.
     */
    public function test_admin_can_update_and_deactivate_unit(): void
    {
        $unit = UnitKerja::create([
            'kode' => 'BAG-UMUM',
            'nama' => 'Bagian Umum Lama',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'kode' => 'BAG-UMUM',
            'nama' => 'Bagian Umum Baru',
            'singkatan' => 'Bag. Umum',
            'is_active' => false,
            'alasan' => 'Penyesuaian tata kelola dan penonaktifan sementara',
        ]);

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $unit->refresh();
        $this->assertEquals('Bagian Umum Baru', $unit->nama);
        $this->assertFalse($unit->is_active);

        // Pastikan tercatat di audit log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'tindakan' => 'unit.update',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
            'alasan' => 'Penyesuaian tata kelola dan penonaktifan sementara',
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
            'kode' => 'ILLEGAL',
            'nama' => 'Illegal Unit',
        ]);
        $createResponse->assertStatus(403);
    }
}
