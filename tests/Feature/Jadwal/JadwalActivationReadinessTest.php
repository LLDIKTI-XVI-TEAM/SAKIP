<?php

namespace Tests\Feature\Jadwal;

use App\Models\JadwalTahunan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class JadwalActivationReadinessTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 10:00', 'Asia/Makassar'));
    }

    private function readiness(?string $id = null): TestResponse
    {
        return $this->getJson('/jadwal/'.($id ?? $this->ready['jadwal']->id).'/kesiapan-aktivasi');
    }

    /** @return array<string, string> */
    private function gateStatuses(array $json): array
    {
        return collect($json['gates'])->mapWithKeys(fn (array $gate): array => [$gate['key'] => $gate['status']])->all();
    }

    public function test_activation_only_reads_list_detail_and_readiness_without_editor_or_options(): void
    {
        $this->readyJadwal();
        $actor = $this->activator();
        $this->actingAs($actor)->get('/jadwal')->assertInertia(fn (Assert $page) => $page->component('Jadwal/Index')
            ->where('can.create', false)->where('jadwal.data.0.can_update', false)->where('auth.can.jadwal', true));
        $this->get('/jadwal/'.$this->ready['jadwal']->id)->assertInertia(fn (Assert $page) => $page->component('Jadwal/Editor')
            ->where('can.update', false)->where('can.activate', true)->where('can.create', false));
        $this->readiness()->assertOk()->assertJsonPath('allowed', true);
        $this->get('/jadwal/create')->assertForbidden();
        $this->getJson('/jadwal/opsi/renstra')->assertForbidden();
        $this->getJson('/jadwal/opsi/periode')->assertForbidden();
        $this->put('/jadwal/'.$this->ready['jadwal']->id, [])->assertForbidden();
    }

    public function test_editor_without_activation_cannot_read_readiness_and_capability_is_separate(): void
    {
        $this->readyJadwal();
        $editor = $this->activator(['jadwal:update']);
        $this->actingAs($editor)->get('/jadwal/'.$this->ready['jadwal']->id)->assertInertia(fn (Assert $page) => $page->where('can.activate', false)->where('can.update', true));
        $this->readiness()->assertForbidden();
        $this->getJson('/jadwal/opsi/renstra')->assertOk();
    }

    public function test_missing_permission_or_deny_is_forbidden_before_lookup(): void
    {
        $this->readyJadwal();
        $none = $this->activator([]);
        $denied = $this->activator();
        $this->denyPermission($denied, 'jadwal:aktivasi');
        foreach ([$none, $denied] as $actor) {
            $this->actingAs($actor)->readiness()->assertForbidden();
            $this->readiness((string) Str::uuid())->assertForbidden();
            $this->get('/jadwal')->assertForbidden();
        }
        $this->actingAs($this->activator())->readiness((string) Str::uuid())->assertNotFound();
    }

    public function test_ready_payload_is_aggregate_only_and_get_writes_nothing(): void
    {
        $this->readyJadwal();
        $this->eligibleIndicator(target: '0', baseline: null, tipe: 'rasio_persen', komponen: [['kode' => 'A', 'peran' => 'pembilang'], ['kode' => 'B', 'peran' => 'penyebut']]);
        $this->uploadSetting(null);
        $tables = ['pengaturan', 'audit_log', 'jadwal_snapshot', 'jadwal_snapshot_komponen'];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
        $json = $this->actingAs($this->activator())->readiness()->assertOk()->json();
        $this->assertSame(['jadwal_id', 'checked_revisi', 'checked_at', 'allowed', 'blockers', 'gates', 'counts', 'periode_lampau_ids'], array_keys($json));
        $this->assertSame($this->ready['jadwal']->id, $json['jadwal_id']);
        $this->assertSame(1, $json['checked_revisi']);
        $this->assertTrue($json['allowed']);
        $this->assertSame([], $json['blockers']);
        $this->assertSame(['G1' => 'lolos', 'G2' => 'lolos', 'G3' => 'lolos', 'G4' => 'lolos'], $this->gateStatuses($json));
        foreach ($json['gates'] as $gate) {
            $this->assertSame(['key', 'status', 'message', 'count'], array_keys($gate));
        }
        $this->assertSame(['indikator_berlaku' => 2, 'target_belum_terisi' => 0, 'snapshot_existing' => 0, 'snapshot_baru' => 2, 'komponen_baru' => 2], $json['counts']);
        $this->assertSame([], $json['periode_lampau_ids']);
        $body = json_encode($json);
        foreach (['Indikator fixture', 'IKU-', '76.25', 'example.test', 'Komponen A', 'Definisi operasional'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
        $this->assertSame($before, collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all());
    }

    public function test_target_gate_requires_nonnull_value_for_every_eligible_indicator(): void
    {
        $this->readyJadwal();
        $this->eligibleIndicator(withTarget: false);
        $this->eligibleIndicator(target: null, baseline: '74.2');
        $this->eligibleIndicator(status: 'arsip', withTarget: false);
        $this->eligibleIndicator(mulai: 2027, withTarget: false);
        $json = $this->actingAs($this->activator())->readiness()->assertOk()->json();
        $this->assertFalse($json['allowed']);
        $this->assertSame('gagal', $this->gateStatuses($json)['G2']);
        $this->assertSame(2, $json['gates'][1]['count']);
        $this->assertSame(3, $json['counts']['indikator_berlaku']);
        $this->assertSame(2, $json['counts']['target_belum_terisi']);
    }

    public function test_pk_year_and_attachment_gates(): void
    {
        $this->readyJadwal();
        DB::table('berkas')->update(['dihapus_pada' => now()]);
        $actor = $this->activator();
        $this->assertSame('gagal', $this->gateStatuses($this->actingAs($actor)->readiness()->json())['G4']);
        $this->uploadSetting('false');
        $this->assertSame('pengecualian', $this->gateStatuses($this->readiness()->json())['G4']);
        $this->assertTrue($this->readiness()->json('allowed'));
        $this->uploadSetting('tidak');
        $this->assertSame('gagal', $this->gateStatuses($this->readiness()->json())['G4']);
        $this->uploadSetting('false');
        $this->pkAttachment('teks');
        $this->assertSame('lolos', $this->gateStatuses($this->readiness()->json())['G4']);

        $this->ready['renstra']->update(['tahun_selesai' => 2025]);
        $this->assertSame('gagal', $this->gateStatuses($this->readiness()->json())['G3']);
        $this->ready['renstra']->update(['tahun_selesai' => 2029]);
        $this->ready['pk']->delete();
        $json = $this->readiness()->json();
        $this->assertSame('gagal', $this->gateStatuses($json)['G1']);
        $this->assertSame('gagal', $this->gateStatuses($json)['G4']);
        $this->assertFalse($json['allowed']);
    }

    public function test_lifecycle_and_calendar_blockers(): void
    {
        $this->readyJadwal(withIndicator: false);
        $this->eligibleIndicator(status: 'arsip');
        $actor = $this->activator();
        $codes = fn (): array => array_column($this->readiness()->json('blockers'), 'code');
        $this->assertContains('indikator_kosong', $this->actingAs($actor)->readiness()->json('blockers.*.code'));
        $this->eligibleIndicator();
        $this->assertSame([], $codes());

        $this->ready['renstra']->update(['status' => 'draft']);
        $this->assertContains('renstra_tidak_aktif', $codes());
        $this->ready['renstra']->update(['status' => 'aktif']);

        $this->ready['periode']->update(['aktif' => false]);
        $this->assertContains('periode_nonaktif', $codes());
        $this->ready['periode']->update(['aktif' => true]);

        DB::table('jadwal_periode')->where('jadwal_id', $this->ready['jadwal']->id)->update(['reviu_selesai' => '2027-01-25']);
        $this->assertContains('kalender_tidak_valid', $codes());
        DB::table('jadwal_periode')->where('jadwal_id', $this->ready['jadwal']->id)->update(['reviu_selesai' => '2027-01-18']);

        JadwalTahunan::whereKey($this->ready['jadwal']->id)->update(['activated_at' => now()]);
        $this->assertContains('status', $codes());
        JadwalTahunan::whereKey($this->ready['jadwal']->id)->update(['status' => 'aktif']);
        $this->assertContains('sudah_aktif', $codes());
    }

    public function test_closing_day_and_past_periods_follow_business_timezone(): void
    {
        $this->readyJadwal(year: 2031);
        $actor = $this->activator();
        $this->travelTo(Carbon::parse('2032-01-11 23:59', 'Asia/Makassar'));
        $this->assertSame([], $this->actingAs($actor)->readiness()->json('periode_lampau_ids'));
        $this->travelTo(Carbon::parse('2032-01-12 00:00', 'Asia/Makassar'));
        $this->assertSame([$this->ready['periode']->id], $this->readiness()->json('periode_lampau_ids'));
        $this->assertTrue($this->readiness()->json('allowed'));
        $this->travelTo(Carbon::parse('2032-01-19 23:59', 'Asia/Makassar'));
        $this->assertTrue($this->readiness()->json('allowed'));
        $this->travelTo(Carbon::parse('2032-01-20 00:00', 'Asia/Makassar'));
        $this->assertContains('penutupan_lewat', $this->readiness()->json('blockers.*.code'));
    }

    public function test_source_name_longer_than_snapshot_column_is_reported_as_inconsistent_data(): void
    {
        $this->readyJadwal();
        $this->eligibleIndicator(nama: str_repeat('n', 256));
        $this->eligibleIndicator(tipe: 'penjumlahan');
        $json = $this->actingAs($this->activator())->readiness()->json();
        $this->assertFalse($json['allowed']);
        $blocker = collect($json['blockers'])->firstWhere('code', 'data_tidak_konsisten');
        $this->assertNotNull($blocker);
        $this->assertStringContainsString('2', $blocker['message']);
    }
}
