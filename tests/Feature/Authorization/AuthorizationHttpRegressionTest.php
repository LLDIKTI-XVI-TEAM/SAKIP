<?php

namespace Tests\Feature\Authorization;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Regression authorization pada boundary HTTP nyata sesuai Plan 1.19:
 * request melewati route, auth/active middleware, Policy/Gate, PermissionResolver, dan DB.
 * Test tidak mengimplementasikan authorization kedua; fixture disusun lalu perilaku diamati.
 * Kode di luar PermissionCatalog dievaluasi lewat route/Gate khusus runtime test saja,
 * bukan route produksi.
 */
class AuthorizationHttpRegressionTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function assign(User $user, string $roleCode): void
    {
        $role = Role::where('kode', $roleCode)->sole();
        $user->roles()->sync([$role->id => ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]]);
    }

    private function giveRolePermission(string $roleCode, string $permissionCode): void
    {
        $role = Role::where('kode', $roleCode)->sole();
        $role->permissions()->syncWithoutDetaching([Permission::where('kode', $permissionCode)->sole()->id => ['id' => (string) Str::uuid(), 'created_at' => now()]]);
    }

    private function deny(User $user, string $permissionCode, ?string $unitId = null): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permissionCode)->sole()->id, 'unit_id' => $unitId,
            'alasan' => 'Fixture pencabutan', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
    }

    private function registerPermissionProbe(string $permissionCode): void
    {
        // Route test-only tanpa Gate::define: ability dievaluasi lewat wiring Gate
        // production dari AppServiceProvider, sehingga kode yang tidak terdaftar
        // memang tidak pernah mendapat definisi Gate dari test.
        Route::middleware(['web', 'auth', 'active'])
            ->get('/__test/authorization/'.md5($permissionCode), function () use ($permissionCode) {
                Gate::authorize($permissionCode);

                return response()->noContent();
            });
    }

    public function test_matching_global_deny_overrides_role_allow_through_http(): void
    {
        $this->giveRolePermission('admin', 'unit:read');
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'admin');

        $this->actingAs($user)->get('/unit')->assertOk();

        $this->deny($user, 'unit:read');

        $this->actingAs($user)->get('/unit')->assertForbidden();
    }

    public function test_inactive_permission_fails_closed_through_http(): void
    {
        $this->giveRolePermission('admin', 'unit:read');
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'admin');

        Permission::where('kode', 'unit:read')->sole()->update(['aktif' => false]);

        $this->actingAs($user)->get('/unit')->assertForbidden();
    }

    public function test_direct_url_without_permission_is_forbidden_through_http(): void
    {
        // Pegawai tidak membawa unit:create dari presetnya; tombol create disembunyikan via can.*.
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'pegawai');

        $this->actingAs($user)->post('/unit', ['nama' => 'Unit Percobaan', 'status' => 'aktif'])->assertForbidden();
        $this->assertDatabaseMissing('unit', ['nama' => 'Unit Percobaan']);
    }

    public function test_matching_unit_deny_overrides_grant_only_allow_through_http(): void
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'pegawai');
        $this->grant($user, 'pengukuran:update');
        // Assignment test dimulai 2026-02-01, lebih baru dari fixture 2026-01-01,
        // sehingga effectivePic() deterministik memilih aktor test, bukan tie acak.
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $user->id,
            'tanggal_mulai_berlaku' => '2026-02-01', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);

        $this->actingAs($user)
            ->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 10])
            ->assertRedirect(route('pengukuran.index'));

        $this->deny($user, 'pengukuran:update', $this->unit->id);

        $this->actingAs($user)
            ->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 2, 'action' => 'draft', 'nilai' => 11])
            ->assertForbidden();

        $this->assertSame(10.0, (float) $this->pengukuran->fresh()->nilai);
    }

    public function test_unit_grant_allows_matching_unit_and_denies_other_unit_through_http(): void
    {
        // Pegawai tanpa allow global `pengukuran:update`; hak isi dibentuk semata lewat Grant Unit A.
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'pegawai');
        $this->grant($user, 'pengukuran:update');
        // Assignment test dimulai 2026-02-01, lebih baru dari fixture 2026-01-01,
        // sehingga effectivePic() deterministik memilih aktor test, bukan tie acak.
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $user->id,
            'tanggal_mulai_berlaku' => '2026-02-01', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);

        $response = $this->actingAs($user)
            ->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 10]);
        $response->assertRedirect(route('pengukuran.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(10.0, (float) $this->pengukuran->fresh()->nilai);
        $this->assertSame(2, $this->pengukuran->fresh()->versi);

        // Unit B valid di semua prasyarat bisnis; satu-satunya perbedaan adalah grant tidak mencakupnya.
        $otherUnit = Unit::create(['nama' => 'Unit Tetangga', 'created_by' => $this->actor->id]);
        $sasaranB = SasaranStrategis::create(['renstra_id' => $this->jadwal->renstra_id, 'kode' => 'S-TTG', 'deskripsi' => 'Sasaran Tetangga']);
        $indikatorB = IndikatorKinerja::create(['sasaran_strategis_id' => $sasaranB->id, 'unit_id' => $otherUnit->id, 'kode' => 'I-TTG', 'nama' => 'Indikator Tetangga', 'satuan' => 'poin', 'tipe_perhitungan' => 'manual', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025, 'created_by' => $this->actor->id, 'created_by_role' => $this->actor->roles->first()?->kode ?? 'superadmin']);
        $contextB = JadwalSnapshot::create(['jadwal_id' => $this->jadwal->id, 'indikator_id' => $indikatorB->id, 'periode_mulai_id' => $this->pengukuran->periode_id, 'unit_id' => $otherUnit->id,
            'nama' => 'Indikator Tetangga', 'definisi' => 'Konteks tetangga.', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        RencanaAksi::create(['indikator_id' => $indikatorB->id, 'tahun' => 2026, 'unit_id' => $otherUnit->id, 'jadwal_tahunan_id' => $this->jadwal->id, 'jadwal_snapshot_id' => $contextB->id,
            'penanggung_jawab_id' => $user->id, 'created_by' => $user->id, 'status_alur' => 'disahkan', 'disahkan_by' => $user->id, 'disahkan_at' => now()]);
        PenugasanIndikator::create(['indikator_id' => $indikatorB->id, 'user_id' => $user->id, 'tanggal_mulai_berlaku' => '2026-02-01', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
        $otherMeasurement = PengukuranKinerja::create(['indikator_id' => $indikatorB->id, 'tahun' => $this->pengukuran->tahun,
            'periode_id' => $this->pengukuran->periode_id, 'jadwal_snapshot_id' => $contextB->id, 'sumber_nilai' => 'manual', 'created_by' => $user->id]);

        $before = $otherMeasurement->fresh()->only(['nilai', 'versi']);
        $this->actingAs($user)
            ->post('/pengukuran/'.$otherMeasurement->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 10])
            ->assertForbidden();
        // 403 saja belum cukup: tolakan tidak boleh menyisakan mutasi parsial.
        $this->assertSame($before, $otherMeasurement->fresh()->only(['nilai', 'versi']));
    }

    public function test_same_date_pic_replacement_moves_measurement_rights_to_latest_assignment(): void
    {
        [$old, $new] = [$this->userWithRole('pegawai'), $this->userWithRole('pegawai')];
        foreach ([$old, $new] as $user) {
            $this->grant($user, 'pengukuran:update');
        }
        // Tanggal dan created_at sama dengan fixture; hanya urutan penugasan yang membedakan.
        $assign = fn (User $user) => PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $user->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $assign($old);
        $this->actingAs($old)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 10])->assertSessionHasNoErrors();
        $assign($new);
        $this->assertSame($new->id, $this->pengukuran->fresh()->effectivePic()->user_id);
        $this->actingAs($old)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 2, 'action' => 'draft', 'nilai' => 11])
            ->assertSessionHasErrors(['versi' => 'Tindakan ini memerlukan penugasan PIC yang efektif.']);
        $this->actingAs($new)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 2, 'action' => 'draft', 'nilai' => 12])->assertSessionHasNoErrors();
        $this->assertSame(12.0, (float) $this->pengukuran->fresh()->nilai);
    }

    public function test_unknown_permission_fails_closed_through_http(): void
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'pegawai');
        $this->registerPermissionProbe('tidak:ada');

        $this->actingAs($user)->get('/__test/authorization/'.md5('tidak:ada'))->assertForbidden();
    }

    public function test_out_of_catalog_database_permission_fails_closed_through_http(): void
    {
        $legacy = Permission::create(['kode' => 'legacy:akses', 'entitas' => 'legacy', 'aksi' => 'akses', 'aktif' => true, 'butuh_scope' => 'global']);
        $this->assertNotContains($legacy->kode, PermissionCatalog::codes());
        // Allow yang secara basis data tampak valid terhadap role superadmin.
        $this->giveRolePermission('superadmin', 'legacy:akses');
        $this->assertDatabaseHas('role_permissions', ['permission_id' => $legacy->id]);
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'superadmin');
        $this->registerPermissionProbe('legacy:akses');

        $this->actingAs($user)->get('/__test/authorization/'.md5('legacy:akses'))->assertForbidden();

        // Supporting assertion pada resolver untuk memastikan reason tetap fail-closed.
        $decision = app(PermissionResolver::class)->decide($user, 'legacy:akses');
        $this->assertFalse($decision['allowed']);
        $this->assertSame('unknown_permission', $decision['reason']);
    }

    public function test_undefined_gate_ability_is_rejected(): void
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $this->assign($user, 'pegawai');

        // Ability tanpa definisi dievaluasi sebagai user terautentikasi, bukan guest,
        // agar pass/fail test tidak bergantung pada state Gate default.
        $this->expectException(AuthorizationException::class);
        Gate::forUser($user)->authorize('legacy:gate');
    }
}
