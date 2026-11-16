<?php

namespace Tests\Feature\Pengukuran;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Capability `can.buka_kembali` pada antrean reviu `/verifikasi` (Plan 6.3): tautan ke
 * antrean Disahkan hanya boleh tampil bila izin baca DAN izin buka-kembali efektif.
 * Gerbang 403 halaman ini tetap ditentukan izin reviu, bukan capability tautan.
 */
class VerifikasiIndexCapabilityTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private const HALAMAN = '/verifikasi';

    public function test_izin_baca_dan_buka_kembali_pada_peran_membuka_capability_tautan(): void
    {
        $this->actingAs($this->userWithRole('perencanaan'))->get(self::HALAMAN)
            ->assertOk()->assertInertia(fn ($page) => $page->component('Verifikasi/Index')
            ->where('can.buka_kembali', true));
    }

    public function test_tanpa_izin_buka_kembali_pada_peran_halaman_tetap_terbuka_dengan_capability_false(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');

        // Pembanding: kedua izin efektif, sehingga capability aktif sebelum izin buka-kembali dicabut.
        $this->actingAs($perencanaan)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->where('can.buka_kembali', true));

        // Cabut hanya izin buka-kembali dari peran; izin reviu dan baca tetap ada sehingga halaman tetap 200.
        DB::table('role_permissions')
            ->where('role_id', Role::where('kode', 'perencanaan')->value('id'))
            ->where('permission_id', Permission::where('kode', 'pengukuran:buka_kembali')->value('id'))
            ->delete();

        $this->actingAs($perencanaan)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Verifikasi/Index')->where('can.buka_kembali', false));
    }

    public function test_deny_global_buka_kembali_menutup_capability_tanpa_menutup_halaman(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');

        // Pembanding: belum ada deny, capability mengikuti allow peran.
        $this->actingAs($perencanaan)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->where('can.buka_kembali', true));

        // Deny global (unit NULL) menang atas allow peran, tetapi tidak mencabut izin reviu halaman.
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $perencanaan->id,
            'permission_id' => Permission::where('kode', 'pengukuran:buka_kembali')->value('id'), 'unit_id' => null,
            'alasan' => 'Penolakan sintetis capability tautan', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $this->actingAs($perencanaan)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Verifikasi/Index')->where('can.buka_kembali', false));
    }
}
