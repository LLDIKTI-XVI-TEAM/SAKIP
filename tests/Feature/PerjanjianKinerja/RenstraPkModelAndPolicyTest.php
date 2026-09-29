<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\User;
use App\Policies\RenstraPkPolicy;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RenstraPkModelAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_renstra_pk_model_attributes_casts_and_relationships(): void
    {
        $user = User::factory()->create();
        $renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'deskripsi' => 'Rencana Strategis',
            'is_aktif' => true,
            'created_by' => $user->id,
        ]);

        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-LLDIKTI16-2026-001',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($pk->id);
        $this->assertNotNull($pk->created_at);
        $this->assertNotNull($pk->updated_at);
        $this->assertSame(2026, $pk->tahun);
        $this->assertSame('2026-01-15', Carbon::parse($pk->tanggal_pk)->toDateString());
        $this->assertTrue($pk->renstra->is($renstra));
        $this->assertTrue($pk->creator->is($user));
        $this->assertTrue($renstra->perjanjianKinerja->contains($pk));

        $berkas = $pk->berkas()->create([
            'jenis_berkas_id' => null,
            'mode' => 'teks',
            'isi_teks' => 'Keterangan dokumen fisik PK 2026',
            'uploaded_by' => $user->id,
        ]);

        $this->assertCount(1, $pk->fresh()->berkas);
        $this->assertTrue($pk->fresh()->berkas->first()->is($berkas));
        $this->assertArrayNotHasKey('path', $berkas->toArray());
    }

    public function test_renstra_pk_policy_authorization(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $superadmin = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $superadmin->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);

        $pegawai = User::factory()->create(['status' => 'aktif']);
        $pegawaiRole = Role::where('kode', 'pegawai')->firstOrFail();
        $pegawai->roles()->attach($pegawaiRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);

        $inactiveUser = User::factory()->create(['status' => 'nonaktif']);

        $renstra = Renstra::create([
            'kode' => 'REN-2025-2029',
            'nama' => 'Renstra LLDIKTI XVI',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $superadmin->id,
        ]);
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-2026',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $superadmin->id,
        ]);

        $policy = app(RenstraPkPolicy::class);

        // Superadmin dengan izin pk:create, pk:update, berkas:delete, berkas:read
        $this->assertTrue($policy->viewAny($superadmin));
        $this->assertTrue($policy->view($superadmin, $pk));
        $this->assertTrue($policy->create($superadmin));
        $this->assertTrue($policy->update($superadmin, $pk));
        $this->assertTrue($policy->deleteBerkas($superadmin, $pk));
        $this->assertTrue($policy->downloadBerkas($superadmin, $pk));

        // Pegawai tanpa izin pk:create, pk:update, berkas:delete, berkas:read
        $this->assertTrue($policy->viewAny($pegawai));
        $this->assertTrue($policy->view($pegawai, $pk));
        $this->assertFalse($policy->create($pegawai));
        $this->assertFalse($policy->update($pegawai, $pk));
        $this->assertFalse($policy->deleteBerkas($pegawai, $pk));
        $this->assertFalse($policy->downloadBerkas($pegawai, $pk));

        // Inactive user ditolak fail-closed
        $this->assertFalse($policy->viewAny($inactiveUser));
        $this->assertFalse($policy->view($inactiveUser, $pk));
        $this->assertFalse($policy->create($inactiveUser));
        $this->assertFalse($policy->update($inactiveUser, $pk));
        $this->assertFalse($policy->deleteBerkas($inactiveUser, $pk));
        $this->assertFalse($policy->downloadBerkas($inactiveUser, $pk));

        $this->assertTrue($policy->uploadBerkas($superadmin, $pk));
        $this->assertFalse($policy->uploadBerkas($pegawai, $pk));
        $this->assertFalse($policy->uploadBerkas($inactiveUser, $pk));

        // User dengan izin pk:create tapi explicit deny berkas:upload ditolak
        $userWithDenyUpload = User::factory()->create(['status' => 'aktif']);
        $perencanaanRole = Role::where('kode', 'perencanaan')->firstOrFail();
        $userWithDenyUpload->roles()->attach($perencanaanRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);
        $this->assertTrue($policy->create($userWithDenyUpload));
        $this->assertTrue($policy->uploadBerkas($userWithDenyUpload));

        // Tambah explicit deny berkas:upload -> deny wins
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userWithDenyUpload->id,
            'permission_id' => Permission::where('kode', 'berkas:upload')->value('id'),
            'unit_id' => null,
            'alasan' => 'Pembatasan hak upload berkas',
            'ditetapkan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);
        $this->assertFalse($policy->uploadBerkas($userWithDenyUpload));

        // User tanpa role aktif ditolak fail-closed
        $userWithoutActiveRole = User::factory()->create(['status' => 'aktif']);
        $inactiveRole = Role::create([
            'kode' => 'role_non_aktif',
            'nama' => 'Role Non Aktif',
            'aktif' => false,
            'urutan' => 99,
        ]);
        $userWithoutActiveRole->roles()->attach($inactiveRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);
        $this->assertFalse($policy->viewAny($userWithoutActiveRole));
        $this->assertFalse($policy->view($userWithoutActiveRole, $pk));
    }

    public function test_legacy_morph_discriminator_migrated_and_loaded_by_relation(): void
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $renstra = Renstra::create([
            'kode' => 'REN-LEGACY',
            'nama' => 'Renstra Legacy Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $user->id,
        ]);
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-LEGACY',
            'tanggal_pk' => '2026-01-10',
            'created_by' => $user->id,
        ]);

        // Simulasikan baris legacy sebelum migrasi (berkasable_type = App\Models\RenstraPk)
        $berkasId = (string) Str::uuid();
        DB::table('berkas')->insert([
            'id' => $berkasId,
            'jenis_berkas_id' => null,
            'berkasable_type' => 'App\\Models\\RenstraPk',
            'berkasable_id' => $pk->id,
            'mode' => 'tautan',
            'nama_asli' => 'Dokumen PK Legacy',
            'tautan' => 'https://example.com/pk-legacy',
            'uploaded_by' => $user->id,
            'created_at' => now(),
        ]);

        // Sebelum migrasi dijalankan, baris masih bertipe FQCN
        $this->assertDatabaseHas('berkas', [
            'id' => $berkasId,
            'berkasable_type' => 'App\\Models\\RenstraPk',
        ]);

        // Jalankan migrasi penyelarasan discriminator morf legacy
        $migration = require database_path('migrations/2026_09_28_000001_migrate_renstra_pk_berkasable_type.php');
        $migration->up();

        // Setelah migrasi, discriminator diselaraskan menjadi alias renstra_pk
        $this->assertDatabaseHas('berkas', [
            'id' => $berkasId,
            'berkasable_type' => 'renstra_pk',
        ]);

        // Relasi berkas dan withCount memuat lampiran legacy dengan tepat
        $pkFresh = RenstraPk::withCount('berkas')->findOrFail($pk->id);
        $this->assertSame(1, $pkFresh->berkas_count);
        $this->assertCount(1, $pkFresh->berkas);
        $this->assertSame($berkasId, $pkFresh->berkas->first()->id);
    }

    public function test_handle_inertia_requests_aligns_pk_menu_capability_with_view_any_policy(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $superadmin = User::factory()->create(['status' => 'aktif']);
        $superadminRole = Role::where('kode', 'superadmin')->firstOrFail();
        $superadmin->roles()->attach($superadminRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);

        $userWithoutActiveRole = User::factory()->create(['status' => 'aktif']);
        $inactiveRole = Role::create([
            'kode' => 'role_inaktif_menu',
            'nama' => 'Role Inaktif',
            'aktif' => false,
            'urutan' => 99,
        ]);
        $userWithoutActiveRole->roles()->attach($inactiveRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $superadmin->id,
            'created_at' => now(),
        ]);

        $middleware = app(HandleInertiaRequests::class);
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('capabilities');

        // User tanpa role aktif mendapatkan pk = false (menu tersembunyi, selaras dengan viewAny 403)
        $capabilitiesWithoutActiveRole = $method->invoke($middleware, $userWithoutActiveRole);
        $this->assertFalse($capabilitiesWithoutActiveRole['pk']);

        // User dengan role aktif mendapatkan pk = true
        $capabilitiesSuperadmin = $method->invoke($middleware, $superadmin);
        $this->assertTrue($capabilitiesSuperadmin['pk']);
    }

    public function test_migration_backfills_null_timestamps_and_enforces_not_null(): void
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $renstra = Renstra::create([
            'kode' => 'REN-TS-TEST',
            'nama' => 'Renstra Timestamp Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $user->id,
        ]);

        $migration = require database_path('migrations/2026_09_28_000001_migrate_renstra_pk_berkasable_type.php');

        // Rollback migrasi sementara untuk menguji skenario legacy dengan kolom timestamp nullable
        $migration->down();

        $pk1Id = (string) Str::uuid();
        $pk2Id = (string) Str::uuid();

        // Baris legacy 1: created_at & updated_at NULL, memiliki riwayat audit renstra_pk.buat
        DB::table('renstra_pk')->insert([
            'id' => $pk1Id,
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-LEGACY-01',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $user->id,
            'created_at' => null,
            'updated_at' => null,
        ]);

        $auditTime = Carbon::parse('2026-01-16 08:30:00');
        DB::table('audit_log')->insert([
            'id' => (string) Str::uuid(),
            'tindakan' => 'renstra_pk.buat',
            'objek_tipe' => 'renstra_pk',
            'objek_id' => $pk1Id,
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'sumber' => 'manual',
            'waktu' => $auditTime,
        ]);

        // Baris legacy 2: created_at & updated_at NULL, tanpa riwayat audit (fallback ke tanggal_pk)
        DB::table('renstra_pk')->insert([
            'id' => $pk2Id,
            'renstra_id' => $renstra->id,
            'tahun' => 2027,
            'nomor_pk' => 'PK-LEGACY-02',
            'tanggal_pk' => '2027-02-10',
            'created_by' => $user->id,
            'created_at' => null,
            'updated_at' => null,
        ]);

        // Jalankan migrasi
        $migration->up();

        $row1 = DB::table('renstra_pk')->where('id', $pk1Id)->first();
        $this->assertNotNull($row1->created_at);
        $this->assertNotNull($row1->updated_at);
        $this->assertSame($auditTime->toDateTimeString(), Carbon::parse($row1->created_at)->toDateTimeString());

        $row2 = DB::table('renstra_pk')->where('id', $pk2Id)->first();
        $this->assertNotNull($row2->created_at);
        $this->assertNotNull($row2->updated_at);
        $this->assertSame('2027-02-10 00:00:00', Carbon::parse($row2->created_at)->toDateTimeString());

        // Verifikasi bahwa kolom sekarang NOT NULL di database level (menolak insert NULL)
        $this->expectException(QueryException::class);
        DB::table('renstra_pk')->insert([
            'id' => (string) Str::uuid(),
            'renstra_id' => $renstra->id,
            'tahun' => 2028,
            'nomor_pk' => 'PK-FAIL-NULL-TS',
            'tanggal_pk' => '2028-01-01',
            'created_by' => $user->id,
            'created_at' => null,
            'updated_at' => null,
        ]);
    }

    public function test_jadwal_tahunan_status_and_is_terkunci_behavior(): void
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $renstra = Renstra::create([
            'kode' => 'REN-JADWAL',
            'nama' => 'Renstra Jadwal Test',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $user->id,
        ]);

        // Skenario 1: Status draft dan activated_at null -> belum aktif, tidak terkunci
        $jadwalDraft = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2025,
            'penutupan' => '2025-12-31',
            'status' => 'draft',
            'activated_at' => null,
            'created_by' => $user->id,
        ]);
        $this->assertSame('draft', $jadwalDraft->status);
        $this->assertFalse($jadwalDraft->is_terkunci);

        // Skenario 2: Status aktif -> terkunci
        $jadwalAktif = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
            'created_by' => $user->id,
        ]);
        $this->assertSame('aktif', $jadwalAktif->status);
        $this->assertTrue($jadwalAktif->is_terkunci);

        // Skenario 3: Status ditutup -> terkunci
        $jadwalDitutup = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2027,
            'penutupan' => '2027-12-31',
            'status' => 'ditutup',
            'activated_at' => now()->subMonths(6),
            'created_by' => $user->id,
        ]);
        $this->assertSame('ditutup', $jadwalDitutup->status);
        $this->assertTrue($jadwalDitutup->is_terkunci);

        // Skenario 4: activated_at tidak null meskipun status draft -> tetap terkunci
        $jadwalPernahAktif = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2028,
            'penutupan' => '2028-12-31',
            'status' => 'draft',
            'activated_at' => now()->subDays(10),
            'created_by' => $user->id,
        ]);
        $this->assertSame('draft', $jadwalPernahAktif->status);
        $this->assertTrue($jadwalPernahAktif->is_terkunci);

        // Uji relasi dan guard RenstraPk: pemisahan status aktif vs terkunci
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-LOCK-TEST',
            'tanggal_pk' => '2026-01-15',
            'created_by' => $user->id,
        ]);
        $this->assertTrue($pk->isJadwalTerkunci());
        $this->assertTrue($pk->isJadwalAktif());
        $this->assertSame('aktif', $pk->jadwalStatus());

        $pkDitutup = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2027,
            'nomor_pk' => 'PK-DITUTUP-TEST',
            'tanggal_pk' => '2027-01-15',
            'created_by' => $user->id,
        ]);
        $this->assertTrue($pkDitutup->isJadwalTerkunci());
        $this->assertFalse($pkDitutup->isJadwalAktif());
        $this->assertSame('ditutup', $pkDitutup->jadwalStatus());

        $pkDraft = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2025,
            'nomor_pk' => 'PK-DRAFT-TEST',
            'tanggal_pk' => '2025-01-15',
            'created_by' => $user->id,
        ]);
        $this->assertFalse($pkDraft->isJadwalTerkunci());
        $this->assertFalse($pkDraft->isJadwalAktif());
        $this->assertSame('draft', $pkDraft->jadwalStatus());
    }
}
