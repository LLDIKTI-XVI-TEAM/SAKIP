<?php

namespace Tests\Feature\Notifikasi;

use App\Actions\Auth\ProvisionKeycloakUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Akun SSO baru tanpa peran memberi tahu pengelola akses yang boleh membuka halaman peran. */
class NotifikasiAkunBaruTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
    }

    private function userWithRole(string $kode, string $status = 'aktif'): User
    {
        $user = User::factory()->create(['status' => $status]);
        $user->roles()->attach(Role::where('kode', $kode)->value('id'), ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }

    private function deny(User $user, string $kode): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => null, 'alasan' => 'Pengujian deny', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
    }

    public function test_login_sso_pertama_menulis_item_untuk_admin_dan_superadmin_yang_boleh_mengelola_peran(): void
    {
        $admin = $this->userWithRole('admin');
        $superadmin = $this->userWithRole('superadmin');
        $this->userWithRole('perencanaan');
        $this->deny($this->userWithRole('admin'), 'akses:update');
        $this->deny($this->userWithRole('admin'), 'pengguna:read');
        $this->userWithRole('admin', 'nonaktif');

        $baru = app(ProvisionKeycloakUser::class)->handle(['subject' => 'sso-baru', 'nama' => 'Pegawai Baru', 'email' => 'baru@example.test']);

        $items = DB::table('notifications')->get();
        $this->assertEqualsCanonicalizing([$admin->id, $superadmin->id], $items->pluck('notifiable_id')->all());
        $this->assertSame('pengguna.menunggu_peran', $items->first()->type);
        $this->assertSame(['judul' => 'Akun baru menunggu peran', 'ringkasan' => 'Pegawai Baru', 'route_name' => 'role-assignment.index', 'route_params' => ['user' => $baru->id]],
            json_decode($items->first()->data, true));
    }

    public function test_login_ulang_akun_lama_tidak_menulis_item(): void
    {
        $this->userWithRole('admin');
        $action = app(ProvisionKeycloakUser::class);
        $action->handle(['subject' => 'lama', 'nama' => 'Lama', 'email' => 'lama@example.test']);
        $this->assertSame(1, DB::table('notifications')->count());

        $action->handle(['subject' => 'lama', 'nama' => 'Lama Diperbarui', 'email' => 'lama@example.test']);

        $this->assertSame(1, DB::table('notifications')->count());
    }
}
