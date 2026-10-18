<?php

namespace Tests\Feature\Pengukuran;

use App\Models\Permission;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class AntreanVerifikasiTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function ajukan(): void
    {
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => 1, 'nilai' => 80, 'action' => 'ajukan'])->assertSessionHasNoErrors();
    }

    public function test_default_diajukan_dan_diverifikasi_tetap_dapat_dipilih(): void
    {
        $this->ajukan();
        $this->get('/verifikasi')->assertInertia(fn ($page) => $page->has('pengukurans', 1)->where('filters.status', 'diajukan'));
        $this->post('/verifikasi/'.$this->pengukuran->id.'/verifikasi', ['versi' => 2])->assertSessionHasNoErrors();
        $this->get('/verifikasi')->assertInertia(fn ($page) => $page->has('pengukurans', 0));
        $this->get('/verifikasi?status=diverifikasi')->assertInertia(fn ($page) => $page->has('pengukurans', 1));
    }

    public function test_filter_server_dan_label_terpilih_mengikuti_antrean(): void
    {
        $this->ajukan();
        $filters = ['renstra_id' => $this->jadwal->renstra_id, 'tahun' => '2026',
            'periode_id' => $this->pengukuran->periode_id, 'unit_id' => $this->unit->id];
        $this->get('/verifikasi?'.http_build_query($filters))->assertInertia(fn ($page) => $page->has('pengukurans', 1)
            ->where('filters.tahun', '2026')->where('selected_filters.unit_id.id', $this->unit->id)
            ->where('selected_filters.renstra_id.id', $this->jadwal->renstra_id)
            ->where('selected_filters.periode_id.id', $this->pengukuran->periode_id));
        $this->get('/verifikasi?'.http_build_query([...$filters, 'tahun' => 2025]))
            ->assertInertia(fn ($page) => $page->has('pengukurans', 0));
        $this->get('/verifikasi?unit_id='.Str::uuid())->assertInertia(fn ($page) => $page
            ->has('pengukurans', 0)->where('selected_filters.unit_id', null));
    }

    public function test_lookup_hanya_menawarkan_konteks_yang_dapat_dibaca_dan_direviu(): void
    {
        $this->ajukan();
        Unit::create(['nama' => 'Unit tanpa antrean', 'created_by' => $this->actor->id]);
        foreach (['renstra' => $this->jadwal->renstra_id, 'periode' => $this->pengukuran->periode_id, 'unit' => $this->unit->id] as $jenis => $id) {
            $this->getJson('/verifikasi/pilihan?jenis='.$jenis)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $id)->assertJsonPath('page', 1)->assertJsonPath('has_more', false);
        }
        $this->getJson('/verifikasi/pilihan?jenis=unit&q=%25')->assertOk()->assertJsonCount(0, 'data');
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:read')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Penolakan sintetis', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->getJson('/verifikasi/pilihan?jenis=unit')->assertOk()->assertJsonCount(0, 'data');
        $this->get('/verifikasi?unit_id='.$this->unit->id)->assertInertia(fn ($page) => $page
            ->has('pengukurans', 0)->where('selected_filters.unit_id', null));
    }

    public function test_validasi_filter_dan_otorisasi_sebelum_validasi(): void
    {
        $this->actingAs($this->actor)->getJson('/verifikasi?status=draft&unit_id=tidak-sah&tahun=abc')->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'unit_id', 'tahun']);
        $this->getJson('/verifikasi/pilihan?jenis=tidak-sah&page=0')->assertUnprocessable()->assertJsonValidationErrors(['jenis', 'page']);
        $this->actingAs($this->userWithRole('pimpinan'))->getJson('/verifikasi/pilihan?jenis=tidak-sah')->assertForbidden();
        $this->getJson('/verifikasi?page=tidak-sah')->assertForbidden();
    }
}
