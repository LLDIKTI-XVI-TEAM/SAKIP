<?php

namespace Tests\Feature\TargetTahunan;

use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\TargetKinerja;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetTahunanTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    private function editor(User $actor, IndikatorKinerja $indicator, int $year = 2026): array
    {
        return app(ShowTargetTahunan::class)->handle($actor, $indicator->id, $year);
    }

    private function payload(User $actor, IndikatorKinerja $indicator, array $values = [], int $year = 2026): array
    {
        return ['baseline' => null, 'target_tahunan' => null, 'expected_state' => $this->editor($actor, $indicator, $year)['expected_state'], 'operation_id' => (string) Str::uuid(), ...$values];
    }

    private function save(User $actor, IndikatorKinerja $indicator, array $values = [], int $year = 2026): array
    {
        return app(SaveTargetTahunan::class)->handle($actor, $indicator->id, $year, $this->payload($actor, $indicator, $values, $year));
    }

    private function snapshot(IndikatorKinerja $indicator, int $year = 2026): JadwalSnapshot
    {
        $jadwal = JadwalTahunan::create(['renstra_id' => $indicator->sasaranStrategis->renstra_id, 'tahun' => $year, 'status' => 'ditutup', 'penutupan' => ($year + 1).'-01-19']);

        return JadwalSnapshot::create(['jadwal_id' => $jadwal->id, 'indikator_id' => $indicator->id, 'nomor_versi' => 1, 'periode_mulai_id' => $this->calendarMaster()->id, 'unit_id' => $indicator->unit_id, 'nama' => $indicator->nama, 'satuan' => 'nilai', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'baseline' => '74.2', 'target' => '76.25']);
    }

    public function test_http_editor_requires_read_and_returns_bounded_exact_context(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->actingAs($actor)->getJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026/editor')
            ->assertOk()->assertJsonPath('baseline_year', 2025)->assertJsonPath('can.update', true)
            ->assertJsonPath('baseline', null)->assertJsonMissingPath('actor');
        $this->getJson('/perencanaan/indikator/not-uuid/target-tahunan/2026/editor')->assertNotFound();
    }

    #[DataProvider('permissions')]
    public function test_read_requires_read_and_write_requires_both(array $permissions, bool $read, bool $write): void
    {
        $actor = $this->calendarActor($permissions);
        $indicator = $this->targetIndicator($actor);
        if (! $read) {
            $this->expectException(AuthorizationException::class);
        }
        $editor = $this->editor($actor, $indicator);
        $this->assertSame($write, $editor['can']['update']);
        if (! $write) {
            $this->expectException(AuthorizationException::class);
        }
        $this->save($actor, $indicator, ['target_tahunan' => '0']);
    }

    public static function permissions(): array
    {
        return [[[], false, false], [['target:update'], false, false], [['indikator:read'], true, false], [['indikator:read', 'target:update'], true, true]];
    }

    #[DataProvider('deniedPermissions')]
    public function test_explicit_deny_wins_at_mutation_boundary(string $permission): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $data = $this->payload($actor, $indicator);
        UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', $permission)->sole()->id, 'alasan' => 'Fixture penolakan', 'ditetapkan_oleh' => $actor->id]);
        $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', $data)->assertForbidden();
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public static function deniedPermissions(): array
    {
        return [['indikator:read'], ['target:update']];
    }

    #[DataProvider('values')]
    public function test_saves_nullable_pair_and_effective_year_without_pk(?string $baseline, ?string $target): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $result = $this->save($actor, $indicator, ['baseline' => $baseline, 'target_tahunan' => $target]);
        $this->assertTrue($result['changed']);
        $this->assertSame($actor->id, TargetKinerja::sole()->updated_by);
        $editor = $this->editor($actor, $indicator);
        $this->assertSame($baseline, $editor['baseline']);
        $this->assertSame($target, $editor['target_tahunan']);
        $this->assertSame(2025, $editor['baseline_year']);
        $same = $this->save($actor, $indicator, ['baseline' => $baseline, 'target_tahunan' => $target]);
        $this->assertFalse($same['changed']);
        $this->assertSame($result['target_id'], $same['target_id']);
        $this->assertSame(1, TargetKinerja::count());
        $this->assertSame(1, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan')->sole();
        $this->assertSame(['baseline' => $baseline, 'target_tahunan' => $target], array_intersect_key($audit->nilai_baru, array_flip(['baseline', 'target_tahunan'])));
        $this->assertArrayHasKey('indikator:read', $audit->dasar_izin);
        $this->assertArrayHasKey('target:update', $audit->dasar_izin);
    }

    public static function values(): array
    {
        return [['74.2', '76.25'], ['74.2345', null], [null, '0'], ['0', '124'], ['0', null], ['0', '0'], ['101.234567890123', '101.25']];
    }

    public function test_empty_first_noop_and_existing_clear_have_different_persistence(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $empty = $this->save($actor, $indicator);
        $this->assertFalse($empty['changed']);
        $this->assertNull($empty['target_id']);
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $saved = $this->save($actor, $indicator, ['target_tahunan' => '0']);
        $clear = $this->save($actor, $indicator);
        $this->assertTrue($clear['changed']);
        $this->assertSame($saved['target_id'], $clear['target_id']);
        $this->assertNull(TargetKinerja::sole()->target_tahunan);
        $before = TargetKinerja::sole()->getAttributes();
        $this->save($actor, $indicator);
        $this->assertSame($before, TargetKinerja::sole()->getAttributes());
        $this->assertSame(2, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public function test_post_snapshot_noop_needs_no_metadata_but_actual_change_does(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->save($actor, $indicator, ['target_tahunan' => '76.25']);
        $snapshot = $this->snapshot($indicator);
        $before = $snapshot->refresh()->getAttributes();
        $this->assertFalse($this->save($actor, $indicator, ['target_tahunan' => '76.2500'])['changed']);
        try {
            $this->save($actor, $indicator);
            $this->fail('Pengosongan aktual harus memerlukan rujukan.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Tuliskan alasan perubahan.'], $exception->errors()['alasan']);
            $this->assertSame(['Cantumkan rujukan sumber perubahan.'], $exception->errors()['rujukan_sumber']);
        }
        $this->save($actor, $indicator, ['baseline' => '74.2345', 'alasan' => 'Koreksi input', 'rujukan_sumber' => 'Dokumen fixture hal. 2']);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan')->where('alasan', 'Koreksi input')->sole();
        $this->assertSame('76.25', $audit->nilai_lama['target_tahunan']);
        $this->assertNull($audit->nilai_baru['target_tahunan']);
        $this->assertSame('Dokumen fixture hal. 2', $audit->nilai_baru['rujukan_sumber']);
    }

    public function test_absent_snapshot_noop_is_allowed_and_initial_value_requires_metadata(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->snapshot($indicator);
        $this->assertNull($this->save($actor, $indicator)['target_id']);
        $this->expectException(ValidationException::class);
        $this->save($actor, $indicator, ['target_tahunan' => '0']);
    }

    public function test_post_snapshot_correction_preserves_literal_zero_reason_in_audit(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->save($actor, $indicator, ['target_tahunan' => '76.25']);
        $snapshot = $this->snapshot($indicator);
        $before = $snapshot->refresh()->getAttributes();
        $data = $this->payload($actor, $indicator, ['target_tahunan' => '77', 'alasan' => '0', 'rujukan_sumber' => 'Dokumen fixture hal. 3']);

        $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', $data)
            ->assertOk()->assertJsonPath('changed', true);

        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan')->where('nilai_baru->target_tahunan', '77')->sole();
        $this->assertSame('0', $audit->alasan);
        $this->assertSame('76.25', $audit->nilai_lama['target_tahunan']);
        $this->assertSame('77', $audit->nilai_baru['target_tahunan']);
        $this->assertSame('Dokumen fixture hal. 3', $audit->nilai_baru['rujukan_sumber']);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
    }

    public function test_other_year_snapshot_does_not_require_correction_metadata(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->snapshot($indicator, 2027);
        $this->assertTrue($this->save($actor, $indicator, ['baseline' => '74.2'])['changed']);
    }

    public function test_preserves_unchanged_target_after_precision_lowered_but_rejects_new_precision(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->save($actor, $indicator, ['target_tahunan' => '90.25']);
        $old = $this->payload($actor, $indicator, ['target_tahunan' => '90.25']);
        $indicator->update(['presisi' => 1]);
        try {
            app(SaveTargetTahunan::class)->handle($actor, $indicator->id, 2026, $old);
            $this->fail('Token sebelum perubahan presisi harus stale.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('expected_state', $e->errors());
        }
        $this->assertSame('90.25', $this->editor($actor, $indicator)['target_tahunan']);
        $this->save($actor, $indicator, ['baseline' => '74.2345', 'target_tahunan' => '90.2500']);
        try {
            $this->save($actor, $indicator, ['target_tahunan' => '90.26']);
            $this->fail('Presisi baru wajib diperiksa.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target_tahunan', $e->errors());
        }
        $this->assertTrue($this->save($actor, $indicator, ['target_tahunan' => '90.3'])['changed']);
    }

    public function test_noop_does_not_bypass_status_year_state_or_malformed_metadata(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $data = $this->payload($actor, $indicator);
        foreach ([['expected_state' => str_repeat('a', 64)], ['alasan' => "bad\0text"], ['updated_by' => $actor->id], ['target_tahunan' => 0]] as $invalid) {
            $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [...$data, ...$invalid])->assertStatus(isset($invalid['expected_state']) ? 409 : 422);
        }
        foreach ([2025, 2030] as $year) {
            $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/'.$year, $data)->assertUnprocessable();
        }
        $indicator->update(['status' => 'arsip']);
        $this->assertFalse($this->editor($actor, $indicator)['can']['update']);
        $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', $data)->assertUnprocessable();
        $this->assertSame(0, TargetKinerja::count());
    }

    public function test_audit_failure_rolls_back_master(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $data = $this->payload($actor, $indicator, ['target_tahunan' => '0']);
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new RuntimeException('Audit unavailable'));
        try {
            app(SaveTargetTahunan::class)->handle($actor, $indicator->id, 2026, $data);
            $this->fail('Audit failure harus diteruskan.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit unavailable', $e->getMessage());
        }
        $this->assertSame(0, TargetKinerja::count());
    }

    public function test_oversized_year_is_rejected_at_route_boundary(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->actingAs($actor)->getJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/99999999999999999999999/editor')->assertNotFound();
    }

    public function test_inertia_noop_redirect_preserves_correlated_nullable_outcome(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $this->snapshot($indicator);
        $data = $this->payload($actor, $indicator);
        $this->actingAs($actor)->withHeader('X-Inertia', 'true')->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', $data)
            ->assertStatus(303)->assertRedirect(route('perencanaan.sasaran-indikator.index'))
            ->assertSessionHas('inertia.flash_data.target_tahunan', ['target_id' => null, 'indikator_id' => $indicator->id, 'tahun' => 2026, 'operation_id' => $data['operation_id'], 'changed' => false]);
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $this->from(route('perencanaan.sasaran-indikator.index'))->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [...$data, 'target_tahunan' => '0'])
            ->assertStatus(303)->assertSessionHasErrors(['alasan' => 'Tuliskan alasan perubahan.', 'rujukan_sumber' => 'Cantumkan rujukan sumber perubahan.']);
        $this->from(route('perencanaan.sasaran-indikator.index'))->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [...$data, 'expected_state' => str_repeat('a', 64)])
            ->assertStatus(303)->assertSessionHasErrors(['expected_state']);
    }

    public function test_inertia_redirect_can_be_forbidden_after_target_and_audit_are_saved(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $data = $this->payload($actor, $indicator, ['baseline' => '74.2', 'target_tahunan' => '76.25']);
        $response = $this->actingAs($actor)->withHeader('X-Inertia', 'true')
            ->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', $data)
            ->assertStatus(303)->assertRedirect(route('perencanaan.sasaran-indikator.index'));

        // Hak baca dapat dicabut sesudah PUT selesai, sebelum Inertia mengikuti GET redirect.
        UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'indikator:read')->sole()->id, 'alasan' => 'Fixture penolakan setelah simpan', 'ditetapkan_oleh' => $actor->id]);
        $this->withHeader('X-Inertia-Version', Inertia::getVersion())
            ->get($response->headers->get('Location'))->assertForbidden();

        $target = TargetKinerja::sole();
        $this->assertSame('74.200000000000', $target->baseline);
        $this->assertSame('76.250000000000', $target->target_tahunan);
        $this->assertSame($actor->id, $target->updated_by);
        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan')->sole();
        $this->assertSame($target->id, $audit->objek_id);
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame('76.25', $audit->nilai_baru['target_tahunan']);
    }

    public function test_input_errors_are_indonesian_for_http_and_direct_action(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $valid = $this->payload($actor, $indicator);
        $cases = [
            ['baseline', null, 'Data baseline belum lengkap. Muat ulang form.', true],
            ['target_tahunan', null, 'Data target belum lengkap. Muat ulang form.', true],
            ['baseline', 0, 'Isi baseline dengan angka yang valid.'],
            ['target_tahunan', 0, 'Isi target dengan angka yang valid.'],
            ['baseline', str_repeat('1', 1001), 'Angka baseline terlalu panjang.'],
            ['target_tahunan', str_repeat('1', 1001), 'Angka target terlalu panjang.'],
            ['baseline', '-1', 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.'],
            ['target_tahunan', '-1', 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.'],
            ['baseline', 'NaN', 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.'],
            ['target_tahunan', 'NaN', 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.'],
            ['baseline', 'Infinity', 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.'],
            ['target_tahunan', 'Infinity', 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.'],
            ['expected_state', null, 'Data form tidak valid. Muat ulang form.'],
            ['expected_state', [], 'Data form tidak valid. Muat ulang form.'],
            ['expected_state', 'invalid', 'Data form tidak valid. Muat ulang form.'],
            ['operation_id', null, 'Permintaan simpan tidak valid. Muat ulang form.'],
            ['operation_id', 'invalid', 'Permintaan simpan tidak valid. Muat ulang form.'],
            ['alasan', [], 'Alasan harus berupa teks.'],
            ['rujukan_sumber', [], 'Rujukan sumber harus berupa teks.'],
            ['alasan', str_repeat('a', 1001), 'Alasan maksimal 1000 karakter.'],
            ['rujukan_sumber', str_repeat('a', 1001), 'Rujukan sumber maksimal 1000 karakter.'],
        ];
        foreach ($cases as $case) {
            [$field, $value, $message] = $case;
            $data = [...$valid, $field => $value];
            if ($case[3] ?? false) {
                unset($data[$field]);
            }
            $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', $data)
                ->assertUnprocessable()->assertJsonPath('errors.'.$field.'.0', $message);
            try {
                app(SaveTargetTahunan::class)->handle($actor, $indicator->id, 2026, $data);
                $this->fail('Input tidak valid harus ditolak.');
            } catch (ValidationException $exception) {
                $this->assertSame($message, $exception->errors()[$field][0]);
            }
        }
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public function test_nonactive_renstra_is_read_only_even_for_empty_noop(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $indicator->sasaranStrategis->renstra->update(['status' => 'nonaktif']);
        $this->assertFalse($this->editor($actor, $indicator)['can']['update']);
        $this->expectException(ValidationException::class);
        $this->save($actor, $indicator);
    }
}
