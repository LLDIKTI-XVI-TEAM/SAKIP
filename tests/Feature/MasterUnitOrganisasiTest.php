<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionDecision;
use Carbon\Carbon;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
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
            'status' => 'aktif',
        ]);

        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $this->superadmin->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);

        $this->admin = User::factory()->create([
            'nama' => 'Admin Test',
            'email' => 'admin@example.test',
            'status' => 'aktif',
        ]);

        $adminRole = Role::where('kode', 'admin')->firstOrFail();
        $this->admin->roles()->attach($adminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->superadmin->id,
            'created_at' => now(),
        ]);

        $this->pegawai = User::factory()->create([
            'nama' => 'Pegawai Biasa Test',
            'email' => 'pegawai@example.test',
            'status' => 'aktif',
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

    #[DataProvider('unitMutations')]
    public function test_unit_denied_malformed_input_preserves_authorization_order(string $operation): void
    {
        $response = $this->actingAs($this->pegawai)->call(
            $operation === 'delete' ? 'DELETE' : 'POST',
            $operation === 'create' ? '/unit' : '/unit/'.$this->unitInduk->id,
            ['nama' => [], 'status' => 'invalid', 'alasan' => []],
        );

        $response->assertForbidden();
        $this->assertSame($operation === 'delete' ? 1 : 0, AuditLog::where('tindakan', 'like', 'unit.%')->count());
        $this->assertDatabaseHas('unit', ['id' => $this->unitInduk->id, 'nama' => $this->unitInduk->nama]);
    }

    #[DataProvider('unitMutations')]
    public function test_unit_mutation_rolls_back_when_success_audit_fails(string $operation): void
    {
        $event = ['create' => 'unit.tambah', 'update' => 'unit.ubah', 'delete' => 'unit.hapus'][$operation];
        $unit = $this->unitInduk;
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andReturnUsing(
            function ($actor, $tindakan) use ($event, $unit, $operation): never {
                $this->assertSame($event, $tindakan);
                // Kegagalan terjadi sesudah write domain, sebelum transaksi boleh commit.
                $this->assertSame($operation !== 'delete', Unit::whereKey($unit->id)->exists());
                if ($operation !== 'delete') {
                    $this->assertTrue(Unit::where('nama', 'Mutasi Sebelum Audit')->exists());
                }
                throw new RuntimeException('Audit fixture gagal.');
            },
        );
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->superadmin)->call(
                $operation === 'delete' ? 'DELETE' : 'POST',
                $operation === 'create' ? '/unit' : '/unit/'.$unit->id,
                ['nama' => 'Mutasi Sebelum Audit', 'status' => 'aktif', 'version_token' => $unit->getVersionToken(), 'alasan' => 'Alasan penghapusan fixture'],
            );
            $this->fail('Kegagalan audit harus diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit fixture gagal.', $exception->getMessage());
        }
        $this->assertDatabaseHas('unit', ['id' => $unit->id, 'nama' => $unit->nama]);
        $this->assertDatabaseMissing('unit', ['nama' => 'Mutasi Sebelum Audit']);
        $this->assertSame(0, AuditLog::where('tindakan', $event)->count());
    }

    public static function unitMutations(): array
    {
        return [['create'], ['update'], ['delete']];
    }

    public function test_delete_denial_keeps_single_initial_allow_decision_when_superadmin_guard_rejects(): void
    {
        $decision = new PermissionDecision(true, 'unit:delete', [
            'alasan' => 'allow', 'sumber_allow' => ['roles' => ['fixture-role'], 'grants' => []], 'deny' => [],
        ]);
        $this->mock(PermissionResolver::class)->shouldReceive('resolve')->once()
            ->with(\Mockery::on(fn ($actor) => $actor->id === $this->admin->id), 'unit:delete')->andReturn($decision);

        $this->actingAs($this->admin)->delete('/unit/'.$this->unitInduk->id, ['alasan' => []])->assertForbidden();

        $audit = AuditLog::where('tindakan', 'unit.hapus_ditolak')->sole();
        $this->assertEquals($decision->toAuditBasis(), $audit->dasar_izin);
        $this->assertSame('Hanya peran Superadmin yang berwenang menghapus unit organisasi.', $audit->alasan);
        $this->assertDatabaseHas('unit', ['id' => $this->unitInduk->id]);
    }

    public function test_unit_audit_preserves_permission_basis(): void
    {
        $this->actingAs($this->superadmin)->post('/unit', ['nama' => 'Unit Provenance'])->assertRedirect('/unit');
        $audit = AuditLog::where('tindakan', 'unit.tambah')->sole();
        $roleId = Role::where('kode', 'superadmin')->value('id');
        $this->assertEquals([
            'permission' => 'unit:create', 'keputusan' => 'diizinkan', 'alasan' => 'allow',
            'sumber_allow' => ['roles' => [$roleId], 'grants' => []], 'deny' => [],
        ], $audit->dasar_izin);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('manual', $audit->sumber);

        $deny = UserPermissionDeny::create([
            'user_id' => $this->superadmin->id, 'permission_id' => Permission::where('kode', 'unit:delete')->value('id'),
            'unit_id' => null, 'alasan' => 'Fixture explicit deny', 'ditetapkan_oleh' => $this->superadmin->id,
        ]);
        $this->delete('/unit/'.$this->unitInduk->id, ['alasan' => 'Alasan penghapusan'])->assertForbidden();
        $denial = AuditLog::where('tindakan', 'unit.hapus_ditolak')->sole();
        $this->assertEquals([
            'permission' => 'unit:delete', 'keputusan' => 'ditolak', 'alasan' => 'explicit_deny',
            'sumber_allow' => ['roles' => [$roleId], 'grants' => []], 'deny' => [$deny->id],
        ], $denial->dasar_izin);
    }

    public function test_delete_foreign_key_audit_preserves_initial_decision(): void
    {
        $initial = new PermissionDecision(true, 'unit:delete', [
            'alasan' => 'allow', 'sumber_allow' => ['roles' => ['initial-role'], 'grants' => []], 'deny' => [],
        ]);
        $current = new PermissionDecision(true, 'unit:delete', [
            'alasan' => 'allow', 'sumber_allow' => ['roles' => ['current-role'], 'grants' => []], 'deny' => [],
        ]);
        $this->mock(PermissionResolver::class)->shouldReceive('resolve')->twice()->andReturn($initial, $current);
        Event::listen('eloquent.deleting: '.Unit::class, function (Unit $unit): void {
            // Relasi muncul sesudah guard; FK PostgreSQL tetap menjadi pertahanan terakhir.
            UserPermissionDeny::create([
                'user_id' => $this->pegawai->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
                'unit_id' => $unit->id, 'alasan' => 'Fixture relasi sebelum delete', 'ditetapkan_oleh' => $this->superadmin->id,
            ]);
        });
        try {
            $this->actingAs($this->superadmin)->delete('/unit/'.$this->unitInduk->id, ['alasan' => 'Alasan penghapusan'])->assertForbidden();
        } finally {
            Event::forget('eloquent.deleting: '.Unit::class);
        }
        $audit = AuditLog::where('tindakan', 'unit.hapus_ditolak')->sole();
        $this->assertEquals($initial->toAuditBasis(), $audit->dasar_izin);
        $this->assertSame('Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan relasi data.', $audit->alasan);
        $this->assertDatabaseHas('unit', ['id' => $this->unitInduk->id]);
        $this->assertDatabaseMissing('user_permission_denied', ['unit_id' => $this->unitInduk->id]);
    }

    #[DataProvider('versionAliases')]
    public function test_update_unit_preserves_version_alias_precedence(array $input, bool $stale): void
    {
        $unit = $this->unitInduk;
        foreach ($input as $key => $value) {
            if ($value === 'CURRENT_TOKEN') {
                $input[$key] = $unit->getVersionToken();
            } elseif ($value === 'CURRENT_SNAPSHOT') {
                $input[$key] = ['nama' => $unit->nama, 'status' => $unit->status];
            } elseif ($value === 'CURRENT_JSON') {
                $input[$key] = json_encode(['nama' => $unit->nama, 'status' => $unit->status]);
            }
        }
        $response = $this->actingAs($this->admin)->post('/unit/'.$unit->id, [
            'nama' => 'Unit Sesudah Alias', 'status' => 'aktif', ...$input,
        ]);
        if ($stale) {
            $response->assertSessionHasErrors(['nama', 'status', 'version_token', 'snapshot', 'expected_state', 'konflik']);
            $this->assertSame($unit->nama, $unit->fresh()->nama);
        } else {
            $response->assertSessionHasNoErrors()->assertRedirect('/unit');
            $this->assertSame('Unit Sesudah Alias', $unit->fresh()->nama);
        }
    }

    public static function versionAliases(): array
    {
        return [
            'versi_token' => [['versi_token' => 'CURRENT_TOKEN'], false],
            'token' => [['token' => 'CURRENT_TOKEN'], false],
            'expected_state' => [['expected_state' => 'CURRENT_TOKEN'], false],
            'first token wins' => [['version_token' => 'stale', 'versi_token' => 'CURRENT_TOKEN'], true],
            'snapshot overrides direct values' => [['expected_nama' => 'stale', 'expected_status' => 'nonaktif', 'snapshot' => 'CURRENT_SNAPSHOT'], false],
            'json expected snapshot' => [['expected_snapshot' => 'CURRENT_JSON'], false],
            'invalid first snapshot retained' => [['version_token' => 'CURRENT_TOKEN', 'snapshot' => 'invalid-json', 'expected_snapshot' => ['nama' => 'stale', 'status' => 'nonaktif']], true],
        ];
    }

    /** Admin dapat membuat unit dengan data valid dan status default aktif. */
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
     * Unit yang masih memiliki relasi ke indikator kinerja ditolak dihapus.
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
            'created_by' => $this->superadmin->id,
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
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->superadmin->id,
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
     * Unit yang memiliki relasi ke user_permission_denied ditolak dihapus.
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
     * Review Codex: Unit yang pernah dibekukan di jadwal_snapshot dilarang dihapus
     * sekalipun indikator induknya telah dipindahkan ke unit lain.
     */
    public function test_delete_unit_linked_to_jadwal_snapshot_is_rejected(): void
    {
        $unitAwal = Unit::create([
            'nama' => 'Unit Pemilik Awal',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $unitBaru = Unit::create([
            'nama' => 'Unit Pemilik Baru',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $renstra = Renstra::create([
            'kode' => 'RENSTRA-SNAP-TEST',
            'created_by' => $this->superadmin->id,
            'nama' => 'Renstra Snapshot Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-SNAP',
            'deskripsi' => 'Sasaran Snapshot',
        ]);

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-SNAP',
            'nama' => 'Indikator Snapshot',
            'satuan' => '%',
            'unit_id' => $unitAwal->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->superadmin->id,
        ]);

        $periode = Periode::create([
            'nama' => 'Triwulan I Snapshot',
            'urutan' => 1,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);

        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'nomor_versi' => 1,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $unitAwal->id,
            'nama' => 'Indikator Snapshot Terbekukan',
            'satuan' => '%',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 85,
        ]);

        // Pindahkan indikator induk ke unit baru
        $indikator->update(['unit_id' => $unitBaru->id]);

        // Verifikasi bahwa unit awal tidak memiliki relasi lain
        $this->assertSame(0, $unitAwal->indikators()->count());
        $this->assertSame(0, $unitAwal->rencanaAksis()->count());
        $this->assertSame(0, $unitAwal->kegiatans()->count());
        $this->assertSame(0, $unitAwal->permissionGrants()->count());
        $this->assertSame(0, $unitAwal->permissionDenies()->count());

        // Namun unit awal tetap terikat oleh snapshot jadwal historis
        $this->assertSame(1, $unitAwal->jadwalSnapshots()->count());
        $this->assertFalse($unitAwal->isDeletable());

        // Superadmin mencoba menghapus unit awal
        $response = $this->actingAs($this->superadmin)->delete("/unit/{$unitAwal->id}", [
            'alasan' => 'Mencoba menghapus unit yang pernah masuk jadwal snapshot',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('unit', ['id' => $unitAwal->id]);
        $this->assertDatabaseHas('audit_log', [
            'actor_id' => $this->superadmin->id,
            'tindakan' => 'unit.hapus_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unitAwal->id,
        ]);
    }

    /**
     * Review Codex: Foreign key constraint di database mencegah penghapusan unit
     * yang dirujuk oleh jadwal_snapshot.
     */
    public function test_foreign_key_constraint_prevents_raw_deletion_of_unit_in_jadwal_snapshot(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Uji Constraint FK',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $renstra = Renstra::create([
            'kode' => 'RENSTRA-FK-TEST',
            'created_by' => $this->superadmin->id,
            'nama' => 'Renstra FK Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-FK',
            'deskripsi' => 'Sasaran FK',
        ]);

        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-FK',
            'nama' => 'Indikator FK',
            'satuan' => '%',
            'unit_id' => $unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'created_by_role' => 'perencanaan',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->superadmin->id,
        ]);

        $periode = Periode::create([
            'nama' => 'Triwulan I FK',
            'urutan' => 1,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);

        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'nomor_versi' => 1,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $unit->id,
            'nama' => 'Indikator Snapshot Terbekukan',
            'satuan' => '%',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 90,
        ]);

        // Percobaan penghapusan langsung di level basis data harus ditolak oleh foreign key constraint
        $this->expectException(QueryException::class);
        DB::table('unit')->where('id', $unit->id)->delete();
    }

    /**
     * Superadmin dapat menghapus unit yang benar-benar kosong dengan alasan tertulis.
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
     * Penghapusan unit wajib mencantumkan alasan minimal 5 karakter.
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
     * Admin biasa TIDAK berwenang menghapus unit kosong sekalipun.
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
     * Admin dapat mengubah dan menonaktifkan unit tanpa menghapus data historis.
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
            'version_token' => $unit->getVersionToken(),
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
     * Pengguna tanpa hak akses unit:* menghasilkan 403 Forbidden.
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
     * Parameter route non-UUID menghasilkan 404 bukan 500.
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
     * Input nama unit yang hanya berisi spasi ditolak pada pembuatan.
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
     * Input nama unit yang hanya berisi spasi ditolak pada pembaruan.
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
            'version_token' => $unit->getVersionToken(),
        ]);

        $response->assertSessionHasErrors('nama');
        $unit->refresh();
        $this->assertSame('Unit Valid Awal', $unit->nama);
    }

    /**
     * Penghapusan unit dengan alasan hanya berisi spasi ditolak dengan validasi 422.
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
     * Otorisasi ulang aktor di dalam transaksi penghapusan unit mencatat audit penolakan di luar transaksi.
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
     * Penambahan unit menolak duplikasi nama tanpa membedakan kapitalisasi (case-insensitive).
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
     * Pembaruan unit menolak duplikasi nama dari unit lain tanpa membedakan kapitalisasi.
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
            'version_token' => $unitB->getVersionToken(),
        ]);

        $responseDuplicate->assertSessionHasErrors('nama');

        // Unit B memperbarui namanya sendiri dengan perubahan kapitalisasi diperbolehkan
        $responseSelf = $this->actingAs($this->admin)->post("/unit/{$unitB->id}", [
            'nama' => 'BAGIAN UMUM',
            'status' => 'aktif',
            'version_token' => $unitB->getVersionToken(),
        ]);

        $responseSelf->assertSessionHasNoErrors();
        $unitB->refresh();
        $this->assertSame('BAGIAN UMUM', $unitB->nama);
    }

    /**
     * Penambahan unit mengevaluasi ulang izin aktor di dalam transaksi dan mencatat penolakan di luar transaksi.
     */
    public function test_store_unit_reauthorizes_actor_inside_transaction_and_preserves_rejection_audit(): void
    {
        $mockResolver = $this->mock(PermissionResolver::class);
        // Keputusan awal Gate/UnitPolicy tetap mengizinkan; hanya evaluasi ulang terkunci di Action yang menolak.
        $mockResolver->shouldReceive('allows')->with(\Mockery::any(), 'unit:create')->once()->andReturnTrue();
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
     * Pembaruan unit mengevaluasi ulang izin aktor di dalam transaksi dan mencatat penolakan di luar transaksi.
     */
    public function test_update_unit_reauthorizes_actor_inside_transaction_and_preserves_rejection_audit(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Awal Update Reotorisasi',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        $mockResolver = $this->mock(PermissionResolver::class);
        // Keputusan awal Gate/UnitPolicy tetap mengizinkan; hanya evaluasi ulang terkunci di Action yang menolak.
        $mockResolver->shouldReceive('allows')->with(\Mockery::any(), 'unit:update')->once()->andReturnTrue();
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
            'version_token' => $unit->getVersionToken(),
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

    /**
     * Penonaktifan unit ditolak jika masih memiliki grant izin aktif.
     */
    public function test_update_unit_cannot_deactivate_unit_with_active_grants(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Dengan Grant Aktif',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        $perm = Permission::where('kode', 'pengukuran:create')->firstOrFail();

        UserPermissionGrant::create([
            'user_id' => $this->pegawai->id,
            'permission_id' => $perm->id,
            'unit_id' => $unit->id,
            'alasan' => 'Grant aktif penugasan unit',
            'diberikan_oleh' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Dengan Grant Aktif',
            'status' => 'nonaktif',
            'version_token' => $unit->getVersionToken(),
        ]);

        $response->assertSessionHasErrors('status');
        $unit->refresh();
        $this->assertSame('aktif', $unit->status);
    }

    /**
     * Migrasi membersihkan duplikasi nama unit (case-insensitive) sebelum membuat indeks unik.
     */
    public function test_migration_resolves_duplicate_names_before_creating_unique_index(): void
    {
        // Drop unique index sementara untuk mensimulasikan database warisan dengan duplikasi kapitalisasi
        DB::statement('DROP INDEX IF EXISTS unit_nama_lower_unique');

        // Buat duplikasi nama dengan kapitalisasi berbeda
        $unit1 = Unit::create([
            'nama' => 'Pusat Bahasa',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
            'created_at' => now()->subMinute(),
        ]);

        $unit2 = Unit::create([
            'nama' => 'pusat bahasa',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
            'created_at' => now(),
        ]);

        // Jalankan migrasi
        $migration = require database_path('migrations/2026_09_23_000003_add_unique_lower_nama_to_unit_table.php');
        $migration->up();

        $unit1->refresh();
        $unit2->refresh();

        $this->assertSame('Pusat Bahasa', $unit1->nama);
        $this->assertStringStartsWith('pusat bahasa (Duplikat ', $unit2->nama);

        // Verifikasi bahwa indeks unik aktif dan mencegah duplikasi baru
        $this->expectException(QueryException::class);
        Unit::create([
            'nama' => 'PUSAT BAHASA',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);
    }

    /**
     * Kunci role sumber sebelum otorisasi ulang mutasi unit (StoreUnit, UpdateUnit, DestroyUnit).
     */
    public function test_unit_mutation_locks_active_source_roles_of_actor(): void
    {
        // 1. StoreUnit mengunci role aktif dan berhasil menyimpan unit
        $responseStore = $this->actingAs($this->admin)->post('/unit', [
            'nama' => 'Unit Uji Kunci Role Sumber',
            'status' => 'aktif',
        ]);

        $responseStore->assertRedirect('/unit');
        $this->assertDatabaseHas('unit', ['nama' => 'Unit Uji Kunci Role Sumber']);

        $createdUnit = Unit::where('nama', 'Unit Uji Kunci Role Sumber')->firstOrFail();

        // 2. UpdateUnit mengunci role aktif dan berhasil memperbarui unit
        $responseUpdate = $this->actingAs($this->admin)->post("/unit/{$createdUnit->id}", [
            'nama' => 'Unit Uji Kunci Role Sumber Diperbarui',
            'status' => 'aktif',
            'version_token' => $createdUnit->getVersionToken(),
        ]);

        $responseUpdate->assertRedirect('/unit');
        $createdUnit->refresh();
        $this->assertSame('Unit Uji Kunci Role Sumber Diperbarui', $createdUnit->nama);

        // 3. DestroyUnit mengunci role aktif dan berhasil menghapus unit kosong
        $responseDestroy = $this->actingAs($this->superadmin)->delete("/unit/{$createdUnit->id}", [
            'alasan' => 'Penghapusan unit uji kunci role sumber',
        ]);

        $responseDestroy->assertRedirect('/unit');
        $this->assertDatabaseMissing('unit', ['id' => $createdUnit->id]);
    }

    /**
     * Review Codex: Tolak pembaruan unit bila status unit telah berubah (snapshot/token usang).
     */
    public function test_update_unit_rejects_stale_request_when_status_was_changed_concurrently(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Kepegawaian Concurrency',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        // Admin A memuat form dan mencatat token versi awal
        $tokenAwal = $unit->getVersionToken();

        // Admin B mendahului menonaktifkan unit
        $responseB = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Kepegawaian Concurrency',
            'status' => 'nonaktif',
            'version_token' => $tokenAwal,
        ]);
        $responseB->assertRedirect('/unit');
        $unit->refresh();
        $this->assertSame('nonaktif', $unit->status);

        // Admin A yang masih memegang modal lama mencoba menyimpan perubahan nama dan status aktif
        $responseA = $this->actingAs($this->superadmin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Kepegawaian Concurrency Diubah',
            'status' => 'aktif',
            'version_token' => $tokenAwal,
            'expected_status' => 'aktif',
            'expected_nama' => 'Unit Kepegawaian Concurrency',
        ]);

        // Request harus ditolak karena snapshot usang
        $responseA->assertSessionHasErrors();

        // Unit di basis data tidak boleh tertimpa / teraktifkan kembali secara tidak sengaja
        $unit->refresh();
        $this->assertSame('nonaktif', $unit->status);
        $this->assertSame('Unit Kepegawaian Concurrency', $unit->nama);

        // Penolakan dicatat di audit log
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'unit.ubah_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * Review Codex: Tolak pembaruan unit bila nama unit telah diubah pengguna lain (snapshot usang).
     */
    public function test_update_unit_rejects_stale_request_when_name_was_changed_concurrently(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Humas Concurrency',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        // Admin A membaca nilai nama awal
        $namaAwal = $unit->nama;

        // Admin B mengubah nama unit
        $responseB = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Hubungan Masyarakat Concurrency',
            'status' => 'aktif',
            'version_token' => $unit->getVersionToken(),
        ]);
        $responseB->assertRedirect('/unit');
        $unit->refresh();
        $this->assertSame('Unit Hubungan Masyarakat Concurrency', $unit->nama);

        // Admin A yang masih memegang form lama mencoba menonaktifkan unit dengan snapshot lengkap awal
        $responseA = $this->actingAs($this->superadmin)->post("/unit/{$unit->id}", [
            'nama' => $namaAwal,
            'status' => 'nonaktif',
            'expected_nama' => $namaAwal,
            'expected_status' => 'aktif',
        ]);

        $responseA->assertSessionHasErrors();

        // Nama tidak boleh ter-revert kembali ke nama lama
        $unit->refresh();
        $this->assertSame('Unit Hubungan Masyarakat Concurrency', $unit->nama);
        $this->assertSame('aktif', $unit->status);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'unit.ubah_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * Review Codex: Tolak pembaruan bila array snapshot nilai awal tidak cocok.
     */
    public function test_update_unit_rejects_stale_snapshot_array_payload(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit TI Asli',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit TI Baru',
            'status' => 'aktif',
            'snapshot' => [
                'nama' => 'Unit TI Usang',
                'status' => 'aktif',
            ],
        ]);

        $response->assertSessionHasErrors();
        $unit->refresh();
        $this->assertSame('Unit TI Asli', $unit->nama);
    }

    /**
     * Review Codex: Pembaruan berhasil jika token versi dan snapshot cocok (fresh).
     */
    public function test_update_unit_succeeds_when_token_and_snapshot_are_fresh(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Sarana Awal',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Sarana dan Prasarana',
            'status' => 'aktif',
            'version_token' => $unit->getVersionToken(),
            'expected_nama' => 'Unit Sarana Awal',
            'expected_status' => 'aktif',
            'snapshot' => $unit->toSnapshot(),
        ]);

        $response->assertRedirect('/unit');
        $response->assertSessionHas('success');

        $unit->refresh();
        $this->assertSame('Unit Sarana dan Prasarana', $unit->nama);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'unit.ubah',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }

    /**
     * Review Codex: Wajibkan token versi atau snapshot untuk setiap pembaruan unit.
     */
    public function test_update_unit_rejects_request_without_version_token_or_snapshot(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Tanpa Token Awal',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        // Request tanpa version_token, snapshot, atau expected values
        $response = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Tanpa Token Diubah',
            'status' => 'aktif',
        ]);

        $response->assertSessionHasErrors('version_token');
        $unit->refresh();
        $this->assertSame('Unit Tanpa Token Awal', $unit->nama);
    }

    /**
     * Review Codex: Batasi respons JSON 403 hanya untuk request mutasi Inertia, bukan kunjungan GET.
     */
    public function test_unauthorized_inertia_mutation_and_get_visit_exception_handling(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Uji Exception Inertia',
            'status' => 'aktif',
            'created_by' => $this->superadmin->id,
        ]);

        // 1. Mutasi POST dengan header X-Inertia yang ditolak menghasilkan respons JSON 403 dengan pesan
        $responseMutation = $this->actingAs($this->pegawai)
            ->withHeader('X-Inertia', 'true')
            ->post("/unit/{$unit->id}", [
                'nama' => 'Unit Diubah Ilegal',
                'status' => 'aktif',
                'version_token' => $unit->getVersionToken(),
            ]);

        $responseMutation->assertStatus(403);
        $this->assertTrue(
            str_contains((string) $responseMutation->headers->get('Content-Type'), 'application/json'),
            'Mutasi Inertia yang ditolak harus mengembalikan respons JSON.'
        );
        $this->assertNotEmpty($responseMutation->json('message'));

        // 2. Kunjungan GET dengan header X-Inertia yang ditolak TIDAK menghasilkan plain JSON 403
        $responseGet = $this->actingAs($this->pegawai)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
            ])
            ->get('/unit');

        $responseGet->assertStatus(403);
        $this->assertFalse(
            str_contains((string) $responseGet->headers->get('Content-Type'), 'application/json'),
            'Kunjungan GET Inertia yang ditolak tidak boleh mengembalikan respons JSON biasa.'
        );
    }

    /**
     * Review Codex: Wajibkan penanda versi yang mencakup seluruh state (tolak penanda parsial).
     */
    public function test_update_unit_rejects_partial_version_markers(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Parsial Marker',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        // 1. Hanya mengirim expected_nama tanpa expected_status
        $res1 = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Parsial Marker Diubah 1',
            'status' => 'aktif',
            'expected_nama' => 'Unit Parsial Marker',
        ]);
        $res1->assertSessionHasErrors('version_token');

        // 2. Hanya mengirim expected_status tanpa expected_nama
        $res2 = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Parsial Marker Diubah 2',
            'status' => 'aktif',
            'expected_status' => 'aktif',
        ]);
        $res2->assertSessionHasErrors('version_token');

        // 3. Snapshot array hanya memuat 'nama' tanpa 'status'
        $res3 = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Parsial Marker Diubah 3',
            'status' => 'aktif',
            'snapshot' => [
                'nama' => 'Unit Parsial Marker',
            ],
        ]);
        $res3->assertSessionHasErrors('version_token');

        // 4. Snapshot array hanya memuat 'status' tanpa 'nama'
        $res4 = $this->actingAs($this->admin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Parsial Marker Diubah 4',
            'status' => 'aktif',
            'snapshot' => [
                'status' => 'aktif',
            ],
        ]);
        $res4->assertSessionHasErrors('version_token');

        // Pastikan nama asli tidak berubah
        $unit->refresh();
        $this->assertSame('Unit Parsial Marker', $unit->nama);
        $this->assertSame('aktif', $unit->status);
    }

    /**
     * Review Codex: Deteksi perubahan status konkuren saat snapshot lengkap dikirimkan.
     */
    public function test_update_unit_detects_concurrent_status_change_with_complete_snapshot(): void
    {
        $unit = Unit::create([
            'nama' => 'Unit Status Concurrency Check',
            'status' => 'aktif',
            'created_by' => $this->admin->id,
        ]);

        // Admin B mengubah status menjadi nonaktif
        $unit->update(['status' => 'nonaktif']);

        // Admin A mencoba mengubah nama dengan snapshot awal saat unit masih aktif
        $responseA = $this->actingAs($this->superadmin)->post("/unit/{$unit->id}", [
            'nama' => 'Unit Status Concurrency Check Diubah',
            'status' => 'aktif',
            'snapshot' => [
                'nama' => 'Unit Status Concurrency Check',
                'status' => 'aktif',
            ],
        ]);

        $responseA->assertSessionHasErrors();

        // Data tidak tertimpa
        $unit->refresh();
        $this->assertSame('Unit Status Concurrency Check', $unit->nama);
        $this->assertSame('nonaktif', $unit->status);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'unit.ubah_ditolak',
            'objek_tipe' => 'unit',
            'objek_id' => (string) $unit->id,
        ]);
    }
}
