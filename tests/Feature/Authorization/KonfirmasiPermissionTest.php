<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleCatalog;
use App\Services\Authorization\RolePermissionPresets;
use App\Support\PermissionCodes;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class KonfirmasiPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
    }

    /**
     * §5 Dokumen Konfirmasi Permission:
     * 15 kode permission utama wajib terdaftar di katalog database dan ber-scope global.
     */
    public function test_15_active_issue_permissions_exist_in_catalog_and_have_global_scope(): void
    {
        $activeCodes = PermissionCodes::activeIssueCodes();

        $this->assertCount(15, $activeCodes);

        $dbPermissions = Permission::whereIn('kode', $activeCodes)->get()->keyBy('kode');

        foreach ($activeCodes as $code) {
            $this->assertTrue($dbPermissions->has($code), "Permission '{$code}' wajib ada di database.");
            $this->assertSame('global', $dbPermissions[$code]->butuh_scope, "Permission '{$code}' wajib berscope global.");
            $this->assertTrue($dbPermissions[$code]->aktif, "Permission '{$code}' wajib aktif.");
        }
    }

    /**
     * §6 Dokumen Konfirmasi Permission:
     * 9 permission dengan scope unit untuk form grant (ISS-01.04).
     */
    public function test_9_unit_scoped_permissions_match_confirmation_document(): void
    {
        $unitCodes = PermissionCodes::unitScoped();

        $this->assertCount(9, $unitCodes);

        $expected = [
            'rencana_aksi:read',
            'rencana_aksi:create',
            'rencana_aksi:update',
            'rencana_aksi:ajukan',
            'kegiatan:read',
            'kegiatan:create',
            'kegiatan:update',
            'pengukuran:create',
            'pengukuran:update',
        ];

        $this->assertEqualsCanonicalizing($expected, $unitCodes);

        $unitPermissions = Permission::where('butuh_scope', 'unit')->pluck('kode')->all();
        $this->assertEqualsCanonicalizing($expected, $unitPermissions);
    }

    /**
     * §8.5 Dokumen Konfirmasi Permission:
     * Permission sensitif dalam scope 10 issue aktif.
     */
    public function test_sensitive_permissions_in_active_scope(): void
    {
        $sensitiveInScope = [
            'akses:update',
            'regulasi:update',
            'regulasi:delete',
            'pengaturan:update',
            'jenis_berkas:update',
            'jenis_berkas:delete',
        ];

        foreach ($sensitiveInScope as $code) {
            $permission = Permission::where('kode', $code)->firstOrFail();
            $this->assertTrue($permission->sensitif, "Permission '{$code}' wajib bertanda sensitif=true.");
        }

        // Non-sensitif di scope aktif
        $nonSensitiveInScope = [
            'pengguna:read',
            'unit:create',
            'unit:read',
            'unit:update',
            'regulasi:create',
            'regulasi:read',
            'jenis_berkas:create',
            'jenis_berkas:read',
        ];

        foreach ($nonSensitiveInScope as $code) {
            $permission = Permission::where('kode', $code)->firstOrFail();
            $this->assertFalse($permission->sensitif, "Permission '{$code}' wajib bertanda sensitif=false.");
        }
    }

    /**
     * §7 & §9 Dokumen Konfirmasi Permission:
     * Hak akses per role untuk Superadmin, Admin, Perencanaan, Pimpinan, Pegawai, dan PIC.
     */
    public function test_role_entitlements_match_confirmation_spec(): void
    {
        // 1. Superadmin memiliki akses ke semua 15 permission aktif
        $superadminPreset = RolePermissionPresets::forRole('superadmin');
        foreach (PermissionCodes::activeIssueCodes() as $code) {
            $this->assertContains($code, $superadminPreset, "Superadmin wajib memiliki '{$code}'.");
        }

        // 2. Admin memiliki akses pengelolaan akun, akses, unit, pengaturan, dan read regulasi/jenis_berkas
        $adminPreset = RolePermissionPresets::forRole('admin');
        $this->assertContains('pengguna:read', $adminPreset);
        $this->assertContains('akses:update', $adminPreset);
        $this->assertContains('unit:create', $adminPreset);
        $this->assertContains('unit:read', $adminPreset);
        $this->assertContains('unit:update', $adminPreset);
        $this->assertContains('regulasi:read', $adminPreset);
        $this->assertContains('jenis_berkas:read', $adminPreset);
        $this->assertContains('pengaturan:update', $adminPreset);
        // Admin tidak boleh mengelola substansi regulasi/jenis berkas
        $this->assertNotContains('regulasi:create', $adminPreset);
        $this->assertNotContains('regulasi:update', $adminPreset);
        $this->assertNotContains('regulasi:delete', $adminPreset);
        $this->assertNotContains('jenis_berkas:create', $adminPreset);

        // 3. Perencanaan memiliki CRUD Regulasi & Jenis Berkas, tetapi tidak mengelola user/unit
        $perencanaanPreset = RolePermissionPresets::forRole('perencanaan');
        $this->assertContains('regulasi:create', $perencanaanPreset);
        $this->assertContains('regulasi:read', $perencanaanPreset);
        $this->assertContains('regulasi:update', $perencanaanPreset);
        $this->assertContains('regulasi:delete', $perencanaanPreset);
        $this->assertContains('jenis_berkas:create', $perencanaanPreset);
        $this->assertContains('jenis_berkas:read', $perencanaanPreset);
        $this->assertContains('jenis_berkas:update', $perencanaanPreset);
        $this->assertContains('jenis_berkas:delete', $perencanaanPreset);
        $this->assertNotContains('pengguna:read', $perencanaanPreset);
        $this->assertNotContains('akses:update', $perencanaanPreset);
        $this->assertNotContains('unit:create', $perencanaanPreset);

        // 4. Pimpinan & Pegawai memiliki hak baca regulasi & jenis berkas
        $pimpinanPreset = RolePermissionPresets::forRole('pimpinan');
        $this->assertContains('regulasi:read', $pimpinanPreset);
        $this->assertContains('jenis_berkas:read', $pimpinanPreset);

        $pegawaiPreset = RolePermissionPresets::forRole('pegawai');
        $this->assertContains('regulasi:read', $pegawaiPreset);
        $this->assertContains('jenis_berkas:read', $pegawaiPreset);

        // 5. PIC adalah identitas resmi tanpa preset bawaan otomatis (§3, §8.1)
        $this->assertTrue(RoleCatalog::contains('pic'));
        $this->assertFalse(RolePermissionPresets::hasDefinedPreset('pic'));
    }

    /**
     * §4 Prinsip Akses:
     * Server-side Gate & PermissionResolver menguji allow, fail closed, dan deny-wins.
     */
    public function test_gates_and_resolver_enforce_server_side_authorization_and_deny_wins(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $unit = Unit::create(['nama' => 'Unit Uji Otorisasi', 'created_by' => $user->id]);

        $roleAdmin = Role::where('kode', 'admin')->firstOrFail();
        $user->roles()->attach($roleAdmin->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        $permAkses = Permission::where('kode', 'akses:update')->firstOrFail();
        $permPengguna = Permission::where('kode', 'pengguna:read')->firstOrFail();

        $roleAdmin->permissions()->syncWithoutDetaching([
            $permAkses->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
            $permPengguna->id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ]);

        $resolver = app(PermissionResolver::class);

        // Allow dari role via resolver & Gate
        $this->assertTrue($resolver->allows($user, PermissionCodes::AKSES_UPDATE));
        $this->assertTrue($resolver->allows($user, PermissionCodes::PENGGUNA_READ));
        $this->assertTrue(Gate::forUser($user)->allows(PermissionCodes::AKSES_UPDATE));
        $this->assertTrue(Gate::forUser($user)->allows(PermissionCodes::PENGGUNA_READ));

        // Fail closed untuk permission tidak dikenal
        $this->assertFalse($resolver->allows($user, 'unknown:action'));
        $this->assertFalse(Gate::forUser($user)->allows('unknown:action'));

        // Deny menang terhadap allow
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => $permAkses->id,
            'unit_id' => null,
            'alasan' => 'Pencabutan darurat hak akses',
            'ditetapkan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        $this->assertFalse($resolver->allows($user, PermissionCodes::AKSES_UPDATE));
        $this->assertFalse(Gate::forUser($user)->allows(PermissionCodes::AKSES_UPDATE));
        // Permission lain yang tidak di-deny tetap allow
        $this->assertTrue($resolver->allows($user, PermissionCodes::PENGGUNA_READ));
        $this->assertTrue(Gate::forUser($user)->allows(PermissionCodes::PENGGUNA_READ));
    }
}
