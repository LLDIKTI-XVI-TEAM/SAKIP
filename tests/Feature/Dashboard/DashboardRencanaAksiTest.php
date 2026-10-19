<?php

namespace Tests\Feature\Dashboard;

use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\SasaranStrategis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DashboardRencanaAksiTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    /** @return array<string, int> */
    private function progres(string $query = ''): array
    {
        return $this->actingAs($this->actor)->get('/dashboard'.$query)->assertOk()->viewData('page')['props']['progresRencanaAksi'];
    }

    private function rencanaAksi(string $indikatorId, string $status): void
    {
        RencanaAksi::create([...$this->plan->only(['tahun', 'unit_id', 'jadwal_tahunan_id', 'penanggung_jawab_id', 'created_by']), 'indikator_id' => $indikatorId, 'status_alur' => $status]);
    }

    public function test_indikator_tanpa_rencana_aksi_dihitung_belum_disusun(): void
    {
        $this->indikatorBaru('I-DUA');

        $this->assertSame(['belum_disusun' => 1, 'draft' => 0, 'diajukan' => 0, 'diverifikasi' => 0, 'dikembalikan' => 0, 'disahkan' => 1], $this->progres());
    }

    public function test_menghitung_lima_status_alur(): void
    {
        foreach (['draft', 'diajukan', 'diverifikasi', 'dikembalikan'] as $i => $status) {
            $this->rencanaAksi($this->indikatorBaru('I-RA'.$i)->indikator_id, $status);
        }

        $this->assertSame(['belum_disusun' => 0, 'draft' => 1, 'diajukan' => 1, 'diverifikasi' => 1, 'dikembalikan' => 1, 'disahkan' => 1], $this->progres());
    }

    public function test_indikator_arsip_tanpa_rencana_aksi_tidak_dihitung(): void
    {
        $dua = $this->indikatorBaru('I-DUA');
        IndikatorKinerja::whereIn('id', [$dua->indikator_id, $this->pengukuran->indikator_id])->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        $this->assertSame(['belum_disusun' => 0, 'disahkan' => 1], array_intersect_key($this->progres(), ['belum_disusun' => 0, 'disahkan' => 0]));
    }

    public function test_unit_ditolak_serta_filter_unit_dan_sasaran_menyaring(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $sasaran = SasaranStrategis::create(['renstra_id' => $this->jadwal->renstra_id, 'kode' => 'S-DUA', 'deskripsi' => 'Sasaran Dua']);
        $this->indikatorBaru('I-DUA', indikator: ['unit_id' => $unitB->id, 'sasaran_strategis_id' => $sasaran->id], snapshot: ['unit_id' => $unitB->id]);
        $pilih = fn (array $progres) => [$progres['belum_disusun'], $progres['disahkan']];

        $this->assertSame([1, 1], $pilih($this->progres()));
        $this->assertSame([0, 1], $pilih($this->progres('?unit_id='.$this->unit->id)));
        $this->assertSame([1, 0], $pilih($this->progres('?sasaran_id='.$sasaran->id)));

        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
            'unit_id' => $unitB->id, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->assertSame([0, 1], $pilih($this->progres()));
    }
}
