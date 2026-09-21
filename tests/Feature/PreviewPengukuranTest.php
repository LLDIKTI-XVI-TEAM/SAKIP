<?php

namespace Tests\Feature;

use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PreviewPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_preview_uses_snapshot_formula_without_mutating_measurement_or_audit(): void
    {
        $context = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah']),
            'nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Fixture pratinjau', 'rujukan_koreksi' => 'Fixture', 'tipe_perhitungan' => 'rasio_persen']);
        $values = [];
        foreach (['pembilang' => 40, 'penyebut' => 100] as $role => $value) {
            $component = IndikatorKomponen::create(['indikator_id' => $this->pengukuran->indikator_id, 'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => 1, 'urutan' => 1, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
            JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $context->id, 'komponen_id' => $component->id, 'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => $role === 'pembilang' ? 2 : 1, 'urutan' => 1]);
            $values[] = ['komponen_id' => $component->id, 'nilai' => (string) $value];
        }
        $this->pengukuran->update(['jadwal_snapshot_id' => $context->id, 'sumber_nilai' => 'komponen']);
        $url = '/pengukuran/'.$this->pengukuran->id.'/pratinjau';
        $this->actingAs($this->actor)->postJson($url, ['komponen' => $values, 'nilai' => 999])
            ->assertOk()->assertJsonPath('nilai', '80.00')->assertJsonPath('status_perhitungan', 'terhitung');
        $values[1]['nilai'] = '0';
        $this->postJson($url, ['komponen' => $values])->assertOk()->assertJsonPath('nilai', null)->assertJsonPath('status_perhitungan', 'tidak_dapat_dihitung');
        $values[1]['nilai'] = null;
        $this->postJson($url, ['komponen' => $values])->assertOk()->assertJsonPath('status_perhitungan', 'belum_diisi');
        $values[1]['komponen_id'] = (string) Str::uuid();
        $this->postJson($url, ['komponen' => $values])->assertUnprocessable()->assertJsonValidationErrors('komponen');
        $this->assertDatabaseHas($this->pengukuran->getTable(), ['id' => $this->pengukuran->id, 'nilai' => null, 'versi' => 1, 'status_alur' => 'draft']);
        $this->assertDatabaseCount('pengukuran_komponen', 0);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_preview_requires_active_authorized_editor_and_honors_unit_deny(): void
    {
        $url = '/pengukuran/'.$this->pengukuran->id.'/pratinjau';
        $this->postJson($url, [])->assertUnauthorized();
        $this->actingAs($this->userWithRole('pegawai'))->postJson($url, [])->assertForbidden();
        $inactive = $this->userWithRole('superadmin');
        $inactive->update(['is_active' => false]);
        $this->actingAs($inactive)->postJson($url, [])->assertRedirect('/auth/pending');
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Fixture deny pratinjau', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->postJson($url, [])->assertForbidden();
    }
}
