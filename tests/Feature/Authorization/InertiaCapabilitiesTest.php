<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePermissionPolicy;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Capability navigasi yang dibagikan ke Inertia harus sama persis dengan
 * keputusan resolver per izin, dan dihitung dengan jumlah query tetap.
 */
class InertiaCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $deny  Izin yang di-deny global untuk pengguna.
     */
    #[DataProvider('kasusPengguna')]
    public function test_capabilities_sama_dengan_keputusan_per_izin(string $peran, array $deny): void
    {
        $user = $this->pengguna($peran, $deny);

        $this->assertSame($this->oracle($user), $this->capabilities($user));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function kasusPengguna(): array
    {
        return [
            'superadmin' => ['superadmin', []],
            'admin' => ['admin', []],
            'perencanaan' => ['perencanaan', []],
            'pimpinan' => ['pimpinan', []],
            'pegawai' => ['pegawai', []],
            'perencanaan dengan deny' => ['perencanaan', ['regulasi:read', 'pengukuran:read', 'periode:create']],
            'superadmin dengan deny' => ['superadmin', ['akses:update', 'pengaturan:update', 'pk:create']],
        ];
    }

    public function test_capabilities_memakai_query_konstan(): void
    {
        $user = $this->pengguna('superadmin', []);

        DB::enableQueryLog();
        $this->capabilities($user);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Satu keputusan batch untuk izin langsung ditambah keputusan Policy
        // yang tetap dihitung lewat Policy masing-masing.
        $this->assertLessThanOrEqual(30, $jumlah);
    }

    /**
     * @return array<string, bool>
     */
    private function capabilities(User $user): array
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        return (app(HandleInertiaRequests::class)->share($request)['can'])();
    }

    /**
     * Keputusan per izin lewat `allows()` satu per satu sebagai pembanding.
     *
     * @return array<string, bool>
     */
    private function oracle(User $user): array
    {
        $resolver = app(PermissionResolver::class);
        $a = fn (string $kode): bool => $resolver->allows($user, $kode);

        return [
            'dashboard' => $a('dashboard:read'),
            'pengukuran' => $a('pengukuran:read'),
            'verifikasi' => $a('pengukuran:read') && ($a('pengukuran:verifikasi') || $a('pengukuran:sahkan') || $a('pengukuran:kembalikan')),
            'aktivasi' => $a('pengguna:read'),
            'assignRole' => $a('pengguna:read') && $a('akses:update'),
            'manageDeny' => $a('akses:update'),
            'unit' => $a('unit:read'),
            'grant' => $a('delegasi:update'),
            'viewRolePermissions' => app(RolePermissionPolicy::class)->decide($user)['allowed'],
            'viewEffectivePermissions' => $a('pengguna:read'),
            'regulasi' => $a('regulasi:read'),
            'regulasi:create' => $a('regulasi:create'),
            'regulasi:read' => $a('regulasi:read'),
            'regulasi:update' => $a('regulasi:update'),
            'regulasi:delete' => $a('regulasi:delete'),
            'renstra' => $a('renstra:read'),
            'renstra:create' => $a('renstra:create'),
            'renstra:read' => $a('renstra:read'),
            'renstra:update' => $a('renstra:update'),
            'renstra:delete' => $a('renstra:delete'),
            'berkas:delete' => $a('berkas:delete'),
            'pengaturan' => $a('pengaturan:update'),
            'pengaturan:update' => $a('pengaturan:update'),
            'jenisBerkas' => $a('jenis_berkas:read'),
            'storagePolicy' => $a('pengaturan:update') || $a('jenis_berkas:read'),
            'storagePolicyUpdate' => $a('pengaturan:update'),
            'sasaranIndikator' => $a('indikator:read'),
            'rencanaAksi' => $user->can('viewAny', RencanaAksi::class),
            'pk' => $user->can('viewAny', RenstraPk::class),
            'periode' => $a('periode:create') || $a('periode:update'),
            'jadwal' => $user->can('viewAny', JadwalTahunan::class),
            'pk:create' => $a('pk:create'),
            'pk:update' => $a('pk:update'),
        ];
    }

    /**
     * @param  list<string>  $deny
     */
    private function pengguna(string $peran, array $deny): User
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $peran)->value('id'), ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        foreach ($deny as $kode) {
            DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
                'unit_id' => null, 'alasan' => 'Fixture deny', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
        }

        return $user;
    }
}
