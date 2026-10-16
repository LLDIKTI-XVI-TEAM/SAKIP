<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Q54 §54.1: keputusan per unit dan filter daftar memakai definisi unit berizin yang sama. */
class UnitBerizinResolverTest extends TestCase
{
    use RefreshDatabase;

    private PermissionResolver $resolver;

    private Unit $unitA;

    private Unit $unitB;

    private Unit $unitNonaktif;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->resolver = app(PermissionResolver::class);
        $pembuat = $this->user('superadmin');
        $this->unitA = Unit::create(['nama' => 'Unit A', 'status' => 'aktif', 'created_by' => $pembuat->id]);
        $this->unitB = Unit::create(['nama' => 'Unit B', 'status' => 'aktif', 'created_by' => $pembuat->id]);
        $this->unitNonaktif = Unit::create(['nama' => 'Unit C', 'status' => 'nonaktif', 'created_by' => $pembuat->id]);
    }

    public function test_pegawai_tanpa_grant_tetap_membuka_halaman_tetapi_tidak_berizin_pada_unit(): void
    {
        $pegawai = $this->user('pegawai');
        foreach (['kegiatan:read', 'rencana_aksi:read'] as $kode) {
            $this->assertTrue($this->resolver->allows($pegawai, $kode));
            $keputusan = $this->resolver->decide($pegawai, $kode, $this->unitA->id);
            $this->assertFalse($keputusan['allowed']);
            $this->assertSame('unit_tidak_berizin', $keputusan['reason']);
            $this->assertNotEmpty($keputusan['roles']);
        }
    }

    public function test_grant_unit_aktif_modul_lain_membuka_unit_itu_saja(): void
    {
        $pegawai = $this->user('pegawai');
        $this->grant($pegawai, 'pengukuran:create', $this->unitA->id);
        foreach (['kegiatan:read', 'rencana_aksi:read'] as $kode) {
            $this->assertTrue($this->resolver->allows($pegawai, $kode, $this->unitA->id));
            $this->assertTrue($this->resolver->allows($pegawai, $kode, strtoupper($this->unitA->id)));
            $this->assertFalse($this->resolver->allows($pegawai, $kode, $this->unitB->id));
        }
    }

    public function test_izin_lain_tidak_terdampak_aturan_unit_berizin(): void
    {
        $pegawai = $this->user('pegawai');
        $this->assertTrue($this->resolver->allows($pegawai, 'pengukuran:read', $this->unitA->id));
    }

    public function test_decide_dan_filter_daftar_selaras_untuk_setiap_peran(): void
    {
        $pegawaiGrant = $this->user('pegawai');
        $this->grant($pegawaiGrant, 'kegiatan:create', $this->unitA->id);
        $this->grant($pegawaiGrant, 'rencana_aksi:update', $this->unitNonaktif->id);
        $pegawaiDeny = $this->user('pegawai');
        $this->grant($pegawaiDeny, 'kegiatan:update', $this->unitA->id);
        $this->grant($pegawaiDeny, 'kegiatan:update', $this->unitB->id);
        $adminGrant = $this->user('admin');
        $this->grant($adminGrant, 'kegiatan:create', $this->unitA->id);
        $perencanaanDeny = $this->user('perencanaan');
        $semua = [$this->unitA->id, $this->unitB->id, $this->unitNonaktif->id];

        $harapan = [
            [$this->user('pegawai'), []],
            [$pegawaiGrant, [$this->unitA->id]],
            [$pegawaiDeny, [$this->unitB->id]],
            [$adminGrant, []],
            [$this->user('pimpinan'), $semua],
            [$this->user('superadmin'), $semua],
            [$perencanaanDeny, [$this->unitA->id, $this->unitNonaktif->id]],
        ];
        foreach (['kegiatan:read', 'rencana_aksi:read'] as $kode) {
            $this->deny($pegawaiDeny, $kode, $this->unitA->id);
            $this->deny($perencanaanDeny, $kode, $this->unitB->id);
        }

        foreach ($harapan as [$user, $unitIds]) {
            foreach (['kegiatan:read', 'rencana_aksi:read'] as $kode) {
                $keputusan = $this->resolver->decideManyForUnits($user, [$kode], $semua);
                $diizinkan = array_values(array_filter($semua, fn (string $id) => $keputusan[$id][$kode]['allowed']));
                $this->assertEqualsCanonicalizing($unitIds, $diizinkan, "decide {$kode}");
                // Admin tanpa izin baca sudah ditolak gerbang halaman; filter daftar hanya berlaku setelah gerbang itu.
                if ($this->resolver->allows($user, $kode)) {
                    $this->assertEqualsCanonicalizing($unitIds, $this->unitDaftar($user, $kode), "daftar {$kode}");
                }
            }
        }
    }

    /** @return list<string> */
    private function unitDaftar(User $user, string $kode): array
    {
        return Unit::query()->whereNotIn('id', $this->resolver->unitDitolak($user, $kode))
            ->when($this->resolver->unitTerbaca($user, $kode), fn ($query, $terbaca) => $query->whereIn('id', $terbaca))
            ->pluck('id')->all();
    }

    private function user(string $peran): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $peran)->value('id'), ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }

    private function grant(User $user, string $kode, string $unitId): void
    {
        DB::table('user_permission_granted')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Fixture unit berizin', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
    }

    private function deny(User $user, string $kode, string $unitId): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Fixture deny unit', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
    }
}
