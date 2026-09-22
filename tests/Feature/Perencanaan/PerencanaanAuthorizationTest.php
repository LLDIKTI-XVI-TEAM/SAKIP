<?php

namespace Tests\Feature\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerencanaanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superadminUser;

    private User $perencanaanUser;

    private User $pegawaiUser;

    private Unit $unitKLSI;

    private Unit $unitUmum;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);

        $this->superadminUser = User::factory()->create([
            'nama' => 'Superadmin',
            'email' => 'superadmin@sakip.test',
            'is_active' => true,
        ]);

        $this->perencanaanUser = User::factory()->create([
            'nama' => 'Staf Perencanaan',
            'email' => 'perencanaan@sakip.test',
            'is_active' => true,
        ]);

        $this->pegawaiUser = User::factory()->create([
            'nama' => 'Staf Pegawai',
            'email' => 'pegawai@sakip.test',
            'is_active' => true,
        ]);

        // Setup Role Superadmin
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $this->superadminUser->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // Setup Role Perencanaan
        $perencanaanRole = Role::where('kode', 'perencanaan')->firstOrFail();
        $this->perencanaanUser->roles()->attach($perencanaanRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // Attach permissions to Perencanaan role
        $renstraRead = Permission::where('kode', 'renstra:read')->firstOrFail();
        $indikatorRead = Permission::where('kode', 'indikator:read')->firstOrFail();
        $rencanaAksiRead = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        $perencanaanRole->permissions()->attach([
            $renstraRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $indikatorRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $rencanaAksiRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ]);

        // Attach all to Superadmin
        $superadminRole->permissions()->attach([
            $renstraRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $indikatorRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $rencanaAksiRead->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ]);

        // Setup Role Pegawai (tidak memiliki permission perencanaan)
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $this->pegawaiUser->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadminUser->id,
            'created_at' => now(),
        ]);

        // Buat Unit di DB yang sesuai dengan mockup data
        $this->unitKLSI = Unit::create([
            'nama' => 'Pokja Kelembagaan dan Sistem Informasi',
            'status' => 'aktif',
            'created_by' => $this->superadminUser->id,
        ]);

        $this->unitUmum = Unit::create([
            'nama' => 'Bagian Umum',
            'status' => 'aktif',
            'created_by' => $this->superadminUser->id,
        ]);
    }

    public function test_guest_is_redirected_to_login_for_planning_routes(): void
    {
        $this->get('/renstra')->assertRedirect('/login');
        $this->get('/indikator')->assertRedirect('/login');
        $this->get('/rencana-aksi')->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_renstra(): void
    {
        $this->actingAs($this->pegawaiUser)
            ->get('/renstra')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_renstra(): void
    {
        $this->actingAs($this->perencanaanUser)
            ->get('/renstra')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Renstra/Index'));
    }

    public function test_user_without_permission_cannot_access_indikator(): void
    {
        $this->actingAs($this->pegawaiUser)
            ->get('/indikator')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_indikator(): void
    {
        $this->actingAs($this->perencanaanUser)
            ->get('/indikator')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Indikator/Index'));
    }

    public function test_user_without_permission_cannot_access_rencana_aksi(): void
    {
        $this->actingAs($this->pegawaiUser)
            ->get('/rencana-aksi')
            ->assertForbidden();
    }

    public function test_user_with_role_can_access_rencana_aksi_and_sees_data(): void
    {
        $this->actingAs($this->perencanaanUser)
            ->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Index')
                ->has('rencanaAksiList', 5)
            );
    }

    public function test_user_with_global_deny_cannot_access_rencana_aksi(): void
    {
        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        UserPermissionDeny::create([
            'user_id' => $this->perencanaanUser->id,
            'permission_id' => $perm->id,
            'unit_id' => null,
            'alasan' => 'Dilarang akses perencanaan secara global.',
            'ditetapkan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->perencanaanUser)
            ->get('/rencana-aksi')
            ->assertForbidden();
    }

    public function test_user_with_unit_grant_can_access_rencana_aksi_and_data_is_filtered(): void
    {
        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        // Pegawai diberikan grant unit khusus Pokja KLSI
        UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $perm->id,
            'unit_id' => $this->unitKLSI->id,
            'alasan' => 'Penugasan penyusunan rencana aksi Pokja KLSI.',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->pegawaiUser)
            ->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Index')
                ->has('rencanaAksiList', 2) // Hanya 2 item Pokja KLSI di mockup
                ->where('rencanaAksiList.0.unit_nama', 'Pokja Kelembagaan dan Sistem Informasi')
                ->where('rencanaAksiList.1.unit_nama', 'Pokja Kelembagaan dan Sistem Informasi')
            );
    }

    public function test_user_with_role_and_unit_deny_has_denied_unit_filtered_out(): void
    {
        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();

        // Staf Perencanaan di-deny untuk Bagian Umum
        UserPermissionDeny::create([
            'user_id' => $this->perencanaanUser->id,
            'permission_id' => $perm->id,
            'unit_id' => $this->unitUmum->id,
            'alasan' => 'Konflik kepentingan pada Bagian Umum.',
            'ditetapkan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->perencanaanUser)
            ->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Index')
                ->has('rencanaAksiList', 4) // Total 5 dikurangi 1 item Bagian Umum
            );
    }

    public function test_user_cannot_access_other_unit_record_with_same_name_and_normalizes_nullable_uraian(): void
    {
        $renstra = Renstra::create([
            'kode' => 'R-TEST',
            'nama' => 'Renstra Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'S-TEST',
            'deskripsi' => 'Sasaran Test',
        ]);
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-TEST',
            'tanggal_pk' => '2026-01-01',
            'created_by' => $this->superadminUser->id,
        ]);
        $periode = Periode::create([
            'nama' => 'Triwulan I',
            'urutan' => 1,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        // Dua unit dengan nama identik
        $unitA = Unit::create(['nama' => 'Unit Kembar', 'status' => 'aktif', 'created_by' => $this->superadminUser->id]);
        $unitB = Unit::create(['nama' => 'Unit Kembar', 'status' => 'aktif', 'created_by' => $this->superadminUser->id]);

        $indikatorA = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unitA->id,
            'kode' => 'I-A',
            'nama' => 'Indikator A',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
        ]);
        $indikatorB = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unitB->id,
            'kode' => 'I-B',
            'nama' => 'Indikator B',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
        ]);

        $contextA = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikatorA->id,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $unitA->id,
            'nama' => 'Indikator A',
            'definisi' => 'Definisi A',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 70,
        ]);
        $contextB = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikatorB->id,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $unitB->id,
            'nama' => 'Indikator B',
            'definisi' => 'Definisi B',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 70,
        ]);

        // RA untuk Unit A memiliki uraian = null (menguji normalisasi uraian nullable)
        RencanaAksi::create([
            'indikator_id' => $indikatorA->id,
            'tahun' => 2026,
            'unit_id' => $unitA->id,
            'jadwal_tahunan_id' => $jadwal->id,
            'jadwal_snapshot_id' => $contextA->id,
            'penanggung_jawab_id' => $this->superadminUser->id,
            'created_by' => $this->superadminUser->id,
            'uraian' => null,
            'status_alur' => 'draft',
        ]);

        // RA untuk Unit B
        RencanaAksi::create([
            'indikator_id' => $indikatorB->id,
            'tahun' => 2026,
            'unit_id' => $unitB->id,
            'jadwal_tahunan_id' => $jadwal->id,
            'jadwal_snapshot_id' => $contextB->id,
            'penanggung_jawab_id' => $this->superadminUser->id,
            'created_by' => $this->superadminUser->id,
            'uraian' => 'Uraian Unit B',
            'status_alur' => 'draft',
        ]);

        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();
        // Pegawai hanya diberi grant ke Unit A
        UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $perm->id,
            'unit_id' => $unitA->id,
            'alasan' => 'Izin khusus Unit A saja',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->pegawaiUser)
            ->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Index')
                ->has('rencanaAksiList', 1)
                ->where('rencanaAksiList.0.unit_id', $unitA->id)
                ->where('rencanaAksiList.0.uraian', '')
                ->where('rencanaAksiList.0.nama_rencana_aksi', '-')
            );
    }

    public function test_shared_inertia_capabilities_hide_planning_for_unauthorized_users(): void
    {
        $dashPerm = Permission::where('kode', 'dashboard:read')->firstOrFail();
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $pegawaiRole->permissions()->syncWithoutDetaching([
            $dashPerm->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ]);
        $perencanaanRole = Role::where('kode', 'perencanaan')->firstOrFail();
        $perencanaanRole->permissions()->syncWithoutDetaching([
            $dashPerm->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ]);

        // 1. Pegawai biasa tanpa hak akses perencanaan
        $this->actingAs($this->pegawaiUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.renstra', false)
                ->where('auth.can.indikator', false)
                ->where('auth.can.rencanaAksi', false)
            );

        // 2. Pengguna dengan peran perencanaan
        $this->actingAs($this->perencanaanUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.renstra', true)
                ->where('auth.can.indikator', true)
                ->where('auth.can.rencanaAksi', true)
            );

        // 3. Pegawai dengan grant unit rencana aksi
        $perm = Permission::where('kode', 'rencana_aksi:read')->firstOrFail();
        UserPermissionGrant::create([
            'user_id' => $this->pegawaiUser->id,
            'permission_id' => $perm->id,
            'unit_id' => $this->unitKLSI->id,
            'alasan' => 'Penugasan khusus unit KLSI',
            'diberikan_oleh' => $this->superadminUser->id,
        ]);

        $this->actingAs($this->pegawaiUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.renstra', false)
                ->where('auth.can.indikator', false)
                ->where('auth.can.rencanaAksi', true)
            );
    }
}
