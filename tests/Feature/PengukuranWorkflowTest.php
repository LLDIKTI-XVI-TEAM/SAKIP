<?php

namespace Tests\Feature;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class PengukuranWorkflowTest extends TestCase
{
    use RefreshDatabase,\Tests\Concerns\CreatesPengukuranFixture;

    public function test_superadmin_cannot_edit_a_ratified_measurement(): void
    {
        $this->pengukuran->update(['status_alur' => 'disahkan']);
        $this->assertFalse(Gate::forUser($this->actor)->allows('update', $this->pengukuran));
    }

    public function test_superadmin_cannot_skip_verification(): void
    {
        $this->pengukuran->update(['status_alur' => 'diajukan']);
        $this->assertFalse(Gate::forUser($this->actor)->allows('ratify', $this->pengukuran));
    }

    public function test_matching_deny_blocks_superadmin_mutation(): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->assertFalse(Gate::forUser($this->actor)->allows('update', $this->pengukuran));
    }

    public function test_perencanaan_cannot_edit_submitted_content_directly(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');
        $this->pengukuran->update(['status_alur' => 'diajukan']);
        $this->assertFalse(Gate::forUser($perencanaan)->allows('update', $this->pengukuran));
    }

    public function test_dashboard_does_not_publish_raw_evidence_or_average_heterogeneous_values(): void
    {
        $this->pengukuran->update(['status_alur' => 'diajukan', 'nilai' => 999, 'status_perhitungan' => 'terhitung']);
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->missing('stats.rata_rata_capaian')->missing('pengukurans.0.bukti_dukungs')->missing('pengukurans.0.penugasan_indikator.pic.email'));
    }

    public function test_dashboard_permission_deny_is_enforced(): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
            'unit_id' => null, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->get('/dashboard')->assertForbidden();
    }

    public function test_normal_submission_requires_real_schedule_snapshot_and_ratified_action_plan(): void
    {
        $this->plan->update(['status_alur' => 'draft']);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])->assertSessionHasErrors('pengajuan');
        $this->assertStringContainsString('Rencana aksi', session('errors')->first('pengajuan'));
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame('draft', $this->pengukuran->fresh()->status_alur);
    }
}
