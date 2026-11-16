<?php

namespace Tests\Feature\Dashboard;

use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DashboardAksesTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function jadikanPic(User $user): User
    {
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $user->id,
            'tanggal_mulai_berlaku' => '2026-03-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $user;
    }

    public function test_pic_tanpa_peran_dashboard_ditolak(): void
    {
        $pic = $this->jadikanPic(User::factory()->create(['status' => 'aktif']));

        $this->actingAs($pic)->get('/dashboard')->assertForbidden();
    }

    public function test_pic_dengan_peran_pegawai_diizinkan(): void
    {
        $pic = $this->jadikanPic($this->userWithRole('pegawai'));

        $this->actingAs($pic)->get('/dashboard')->assertOk();
    }

    public function test_deny_global_dashboard_read_menolak_pegawai(): void
    {
        $pegawai = $this->userWithRole('pegawai');
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $pegawai->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
            'unit_id' => null, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $this->actingAs($pegawai)->get('/dashboard')->assertForbidden();
    }

    public function test_admin_dan_pimpinan_diizinkan(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/dashboard')->assertOk();
        $this->actingAs($this->userWithRole('pimpinan'))->get('/dashboard')->assertOk();
    }
}
