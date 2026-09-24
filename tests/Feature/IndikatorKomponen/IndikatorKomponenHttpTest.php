<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndikatorKomponenHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    private User $pegawai;

    private IndikatorKinerja $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);

        $this->perencanaan = $this->userWithRole('perencanaan');
        $this->pegawai = $this->userWithRole('pegawai');

        $renstra = Renstra::create([
            'kode' => 'RENSTRA-HTTP-TEST',
            'nama' => 'Renstra Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-HTTP',
            'deskripsi' => 'Sasaran Test',
            'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit Perencanaan Test',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'IKU-TEST-HTTP',
            'nama' => 'Indikator HTTP Test',
            'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'is_aktif' => true,
        ]);
    }

    private function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('kode', $kode)->firstOrFail();
        if (RolePermissionPresets::hasDefinedPreset($kode)) {
            foreach (Permission::whereIn('kode', RolePermissionPresets::forRole($kode))->get() as $permission) {
                $role->permissions()->syncWithoutDetaching([
                    $permission->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
                ]);
            }
        }
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    /**
     * TEST-9: Percobaan insert duplikasi kode pada indikator yang sama ditolak (DB & Validasi).
     */
    public function test_duplicate_kode_komponen_ditolak_db_dan_validasi(): void
    {
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang Awal',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Submit komponen baru dengan kode yang sama 'n'
        $response = $this->actingAs($this->perencanaan)
            ->post("/indikator/{$this->indikator->id}/komponen", [
                'kode' => 'n',
                'label' => 'Pembilang Duplikat',
                'peran' => 'pembilang',
                'bobot' => 1.0,
                'urutan' => 2,
                'aktif' => true,
            ]);

        $response->assertSessionHasErrors(['kode']);

        // Direct DB attempt must also trigger QueryException / unique constraint violation
        $this->expectException(QueryException::class);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id,
            'kode' => 'n',
            'label' => 'Bypass Direct DB',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 3,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);
    }

    /**
     * TEST-10: Update dan delete menghasilkan catatan audit_log dengan dasar_izin dan alasan.
     */
    public function test_update_dan_delete_mencatat_audit_log_dasar_izin_dan_alasan(): void
    {
        $komponen = IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id,
            'kode' => 'sakip',
            'label' => 'Skor SAKIP Awal',
            'peran' => 'penjumlah',
            'bobot' => 1.0,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // 1. UPDATE SENSITIF
        $updateResponse = $this->actingAs($this->perencanaan)
            ->put("/indikator/{$this->indikator->id}/komponen/{$komponen->id}", [
                'kode' => 'sakip',
                'label' => 'Skor SAKIP Disesuaikan',
                'peran' => 'penjumlah',
                'bobot' => 0.5,
                'urutan' => 1,
                'satuan' => 'Skor',
                'aktif' => true,
                'alasan' => 'Penyesuaian bobot final IKU 3 menjadi 0.5 sesuai klarifikasi kementerian.',
            ]);

        $updateResponse->assertSessionHasNoErrors();
        $updateResponse->assertRedirect("/indikator/{$this->indikator->id}/komponen");

        $auditUpdate = AuditLog::where('tindakan', 'komponen.ubah')
            ->where('objek_id', $komponen->id)
            ->first();

        $this->assertNotNull($auditUpdate, 'Audit log untuk komponen.ubah harus tercatat.');
        $this->assertNotNull($auditUpdate->dasar_izin, 'Audit log komponen.ubah wajib memiliki dasar_izin.');
        $this->assertSame('Penyesuaian bobot final IKU 3 menjadi 0.5 sesuai klarifikasi kementerian.', $auditUpdate->alasan);
        $this->assertEquals(0.5, $komponen->fresh()->bobot);

        // 2. DELETE SENSITIF
        $deleteResponse = $this->actingAs($this->perencanaan)
            ->delete("/indikator/{$this->indikator->id}/komponen/{$komponen->id}", [
                'alasan' => 'Penghapusan komponen usang.',
            ]);

        $deleteResponse->assertSessionHasNoErrors();
        $deleteResponse->assertRedirect("/indikator/{$this->indikator->id}/komponen");

        $auditDelete = AuditLog::where('tindakan', 'komponen.hapus')
            ->where('objek_id', $komponen->id)
            ->first();

        $this->assertNotNull($auditDelete, 'Audit log untuk komponen.hapus harus tercatat.');
        $this->assertNotNull($auditDelete->dasar_izin, 'Audit log komponen.hapus wajib memiliki dasar_izin.');
        $this->assertSame('Penghapusan komponen usang.', $auditDelete->alasan);
        $this->assertDatabaseMissing('indikator_komponen', ['id' => $komponen->id]);
    }

    /**
     * TEST-11: Perubahan master komponen tidak memutasi snapshot fixture existing.
     */
    public function test_perubahan_master_tidak_memutasi_snapshot_historis(): void
    {
        $komponenMaster = IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id,
            'kode' => 'p1',
            'label' => 'Pembilang Master 1',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Simulasikan baris snapshot historis yang sudah terbentuk
        $jadwalId = (string) Str::uuid();
        $periodeId = (string) Str::uuid();
        $snapshotId = (string) Str::uuid();
        $snapshotKomponenId = (string) Str::uuid();

        DB::table('jadwal_tahunan')->insert([
            'id' => $jadwalId,
            'renstra_id' => $this->indikator->sasaranStrategis->renstra_id,
            'tahun' => 2025,
            'penutupan' => '2025-12-31',
            'status' => 'aktif',
        ]);

        DB::table('periode')->insert([
            'id' => $periodeId,
            'nama' => 'Triwulan I 2025',
            'urutan' => 1,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);

        DB::table('jadwal_snapshot')->insert([
            'id' => $snapshotId,
            'jadwal_id' => $jadwalId,
            'indikator_id' => $this->indikator->id,
            'nomor_versi' => 1,
            'periode_mulai_id' => $periodeId,
            'unit_id' => $this->indikator->unit_id,
            'nama' => $this->indikator->nama,
            'satuan' => $this->indikator->satuan,
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
        ]);

        DB::table('jadwal_snapshot_komponen')->insert([
            'id' => $snapshotKomponenId,
            'jadwal_snapshot_id' => $snapshotId,
            'komponen_id' => $komponenMaster->id,
            'kode' => 'p1',
            'label' => 'Label Asli Snapshot Beku',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
        ]);

        // Ubah definisi komponen master via HTTP
        $this->actingAs($this->perencanaan)
            ->put("/indikator/{$this->indikator->id}/komponen/{$komponenMaster->id}", [
                'kode' => 'p1',
                'label' => 'Label Baru Master Berubah',
                'peran' => 'pembilang',
                'bobot' => 2.5,
                'urutan' => 1,
                'aktif' => true,
                'alasan' => 'Perubahan master data tidak boleh menyentuh snapshot historis.',
            ])
            ->assertSessionHasNoErrors();

        // Verifikasi snapshot historis tetap utuh dan tidak termutasi
        $snapshotRow = DB::table('jadwal_snapshot_komponen')->where('id', $snapshotKomponenId)->first();
        $this->assertNotNull($snapshotRow);
        $this->assertSame('Label Asli Snapshot Beku', $snapshotRow->label);
        $this->assertEquals(1.0, (float) $snapshotRow->bobot);
    }

    /**
     * TEST-12: User dengan read-only permission (Pegawai) mendapat 403 Forbidden pada mutation.
     */
    public function test_read_only_user_mendapat_403_pada_mutasi(): void
    {
        $komponen = IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Pegawai dapat melihat halaman index
        $this->actingAs($this->pegawai)
            ->get("/indikator/{$this->indikator->id}/komponen")
            ->assertOk();

        // Pegawai mencoba CREATE -> 403
        $this->actingAs($this->pegawai)
            ->post("/indikator/{$this->indikator->id}/komponen", [
                'kode' => 't',
                'label' => 'Penyebut',
                'peran' => 'penyebut',
                'bobot' => 1.0,
                'urutan' => 2,
                'aktif' => true,
            ])
            ->assertForbidden();

        // Pegawai mencoba UPDATE -> 403
        $this->actingAs($this->pegawai)
            ->put("/indikator/{$this->indikator->id}/komponen/{$komponen->id}", [
                'kode' => 'n',
                'label' => 'Ubah Ilegal',
                'peran' => 'pembilang',
                'bobot' => 2.0,
                'urutan' => 1,
                'aktif' => true,
                'alasan' => 'Mencoba ubah tanpa hak.',
            ])
            ->assertForbidden();

        // Pegawai mencoba DELETE -> 403
        $this->actingAs($this->pegawai)
            ->delete("/indikator/{$this->indikator->id}/komponen/{$komponen->id}", [
                'alasan' => 'Mencoba hapus tanpa hak.',
            ])
            ->assertForbidden();
    }
}
