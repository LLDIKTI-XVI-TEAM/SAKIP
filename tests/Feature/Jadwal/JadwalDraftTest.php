<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Periode\SavePeriode;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RenstraPk;
use App\Models\UserPermissionDeny;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

class JadwalDraftTest extends TestCase
{
    use JadwalFixtures, RefreshDatabase;

    public function test_complete_draft_is_atomic_without_snapshot_and_noop_preserves_revision(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $periode = $this->calendarMaster();
        $data = $this->calendarPayload($renstra, $periode, 2030); // Draft tidak memindahkan gate rentang aktivasi.
        $this->actingAs($actor)->post('/jadwal', $data)->assertRedirect();
        $jadwal = JadwalTahunan::sole();
        $this->assertSame('draft', $jadwal->status);
        $this->assertNull($jadwal->renstra_pk_id);
        $this->assertNull($jadwal->activated_at);
        $this->assertNull($jadwal->closed_at);
        $this->assertFalse($jadwal->pakai_persetujuan_pimpinan);
        $this->assertNull($jadwal->persetujuan_mulai);
        $this->assertNull($jadwal->persetujuan_selesai);
        $this->assertSame(0, DB::table('jadwal_snapshot')->count());
        $this->assertSame(0, DB::table('jadwal_snapshot_komponen')->count());
        $this->assertSame(1, PeriodeJadwal::count());
        $childId = PeriodeJadwal::sole()->id;
        app(SaveJadwalDraft::class)->handle($actor, [...$data, 'revisi' => 1], $jadwal);
        $this->assertSame(1, $jadwal->fresh()->revisi);
        $this->assertSame($childId, PeriodeJadwal::sole()->id);
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.ubah')->count());
        $this->assertFalse(Schema::hasColumn('jadwal_tahunan', 'pengisian_mulai'));
    }

    #[DataProvider('unsupportedFields')]
    public function test_unsupported_fields_are_rejected_even_when_null(string $field): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $this->actingAs($actor)->post('/jadwal', [...$data, $field => null])->assertSessionHasErrors('jadwal');
        $this->assertSame(0, JadwalTahunan::count());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tambah_ditolak')->count());
    }

    public static function unsupportedFields(): array
    {
        return array_map(fn (string $field): array => [$field], ['status', 'renstra_pk_id', 'activated_at', 'closed_at', 'pakai_persetujuan_pimpinan', 'persetujuan_mulai', 'is_backfill', 'audit', 'actor_id', 'periode.*']);
    }

    public function test_missing_nested_dates_and_duplicate_periods_are_rejected(): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $data['periode'][] = $data['periode'][0];
        unset($data['rencana_aksi_selesai'], $data['periode'][1]['reviu_selesai']);
        $this->actingAs($actor)->post('/jadwal', $data)->assertSessionHasErrors(['rencana_aksi_selesai', 'periode.1.reviu_selesai', 'periode.0.periode_id']);
        $this->assertSame(0, JadwalTahunan::count());
    }

    public function test_calendar_form_validation_has_readable_indonesian_messages(): void
    {
        $actor = $this->calendarActor();
        $this->actingAs($actor)->post('/jadwal', [])->assertSessionHasErrors([
            'renstra_id' => 'Renstra wajib diisi.',
            'tahun' => 'Tahun wajib diisi.',
            'rencana_aksi_mulai' => 'Tanggal mulai Rencana Aksi wajib diisi.',
            'rencana_aksi_selesai' => 'Tanggal selesai Rencana Aksi wajib diisi.',
            'penutupan' => 'Tanggal penutupan wajib diisi.',
            'periode' => 'Periode pilihan wajib diisi.',
        ]);
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $data['tahun'] = 10000;
        $data['rencana_aksi_mulai'] = '2026-02-30';
        unset($data['periode'][0]['reviu_mulai']);
        $this->post('/jadwal', $data)->assertSessionHasErrors([
            'tahun' => 'Tahun harus berada antara 1 dan 9998.',
            'rencana_aksi_mulai' => 'Tanggal mulai Rencana Aksi harus berupa tanggal valid dengan format YYYY-MM-DD.',
            'periode.0.reviu_mulai' => 'Tanggal mulai review wajib diisi.',
        ]);
        $this->assertSame(0, JadwalTahunan::count());
    }

    public function test_inactive_references_are_retained_but_not_newly_selected_and_only_removed_links_are_deleted(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $this->calendarMaster();
        $periode = $this->calendarMaster(1, false);
        $data = $this->calendarPayload($renstra, $periode);
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        app(SavePeriode::class)->handle($actor, ['nama' => $periode->nama, 'urutan' => 1, 'aktif' => false, 'is_nilai_akhir' => false, 'revisi' => 1], $periode);
        $data['periode'][0]['periode_revisi'] = 2;
        app(SaveJadwalDraft::class)->handle($actor, [...$data, 'revisi' => 1], $jadwal);
        $this->actingAs($actor)->post('/jadwal', [...$data, 'renstra_id' => $this->calendarRenstra($actor)->id])->assertSessionHasErrors('periode.0.periode_id');
        $replacement = $this->calendarMaster(5, false);
        $new = $this->calendarPayload($renstra, $replacement);
        app(SaveJadwalDraft::class)->handle($actor, [...$new, 'revisi' => 1], $jadwal);
        $this->assertSame($replacement->id, PeriodeJadwal::sole()->periode_id);
        $this->assertFalse($periode->fresh()->aktif);
    }

    #[DataProvider('readOnlyStates')]
    public function test_parent_and_draft_state_are_rechecked(string $parentStatus, string $status, bool $activated): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $data = $this->calendarPayload($renstra, $this->calendarMaster());
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        $renstra->update(['status' => $parentStatus]);
        $jadwal->update(['status' => $status, 'activated_at' => $activated ? now() : null]);
        $before = $jadwal->fresh()->getAttributes();
        $this->actingAs($actor)->put('/jadwal/'.$jadwal->id, [...$data, 'revisi' => 1])->assertSessionHasErrors($parentStatus === 'draft' ? 'jadwal' : 'renstra_id');
        $this->assertSame($before, $jadwal->fresh()->getAttributes());
    }

    public static function readOnlyStates(): array
    {
        return [['nonaktif', 'draft', false], ['diarsipkan', 'draft', false], ['draft', 'aktif', false], ['draft', 'ditutup', false], ['draft', 'draft', true]];
    }

    public function test_denied_requests_do_not_reveal_target_and_actor_recheck_is_live(): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'jadwal:update')->sole()->id, 'ditetapkan_oleh' => $actor->id, 'alasan' => 'Fixture deny']);
        foreach ([$jadwal->id, (string) Str::uuid()] as $id) {
            $this->actingAs($actor)->put('/jadwal/'.$id, ['tahun' => 'invalid'])->assertForbidden();
        }
        $this->assertSame(2, AuditLog::where('tindakan', 'jadwal.ubah_ditolak')->count());
        $this->assertNull(AuditLog::where('tindakan', 'jadwal.ubah_ditolak')->firstOrFail()->nilai_lama);
        $actor->update(['status' => 'nonaktif']);
        $this->actingAs($actor)->post('/jadwal', $data)->assertRedirect(route('auth.pending'));
        $this->expectException(AuthorizationException::class);
        app(SaveJadwalDraft::class)->handle($actor->fresh(), $data);
    }

    public function test_create_and_update_permissions_do_not_imply_each_other(): void
    {
        $actor = $this->calendarActor(['jadwal:create']);
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        $this->actingAs($actor)->put('/jadwal/'.$jadwal->id, [...$data, 'revisi' => 1])->assertForbidden();
        $updateOnly = $this->calendarActor(['jadwal:update']);
        $this->actingAs($updateOnly)->post('/jadwal', $data)->assertForbidden();
    }

    public function test_stale_draft_and_master_revisions_reject_aba_without_overwrite(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $master = $this->calendarMaster();
        $data = $this->calendarPayload($renstra, $master);
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        $changed = [...$data, 'penutupan' => '2027-01-20'];
        app(SaveJadwalDraft::class)->handle($actor, [...$changed, 'revisi' => 1], $jadwal);
        app(SaveJadwalDraft::class)->handle($actor, [...$data, 'revisi' => 2], $jadwal);
        $this->actingAs($actor)->put('/jadwal/'.$jadwal->id, [...$changed, 'revisi' => 1])->assertSessionHasErrors('jadwal');
        app(SavePeriode::class)->handle($actor, ['nama' => 'Final koreksi', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1], $master);
        $this->actingAs($actor)->put('/jadwal/'.$jadwal->id, [...$changed, 'revisi' => 3])->assertSessionHasErrors('periode.0.periode_revisi');
        $this->assertSame(3, $jadwal->fresh()->revisi);
        $this->assertSame('2027-01-19', $jadwal->fresh()->penutupan->format('Y-m-d'));
    }

    public function test_audit_failure_rolls_back_parent_and_children(): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            app(SaveJadwalDraft::class)->handle($actor, $data);
            $this->fail('Audit failure harus membatalkan kalender.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertSame(0, JadwalTahunan::count());
        $this->assertSame(0, PeriodeJadwal::count());
    }

    public function test_draft_identity_correction_preserves_pk_reference(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $master = $this->calendarMaster();
        $data = $this->calendarPayload($renstra, $master);
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        $nextYear = $this->calendarPayload($renstra, $master, 2027);
        app(SaveJadwalDraft::class)->handle($actor, [...$nextYear, 'revisi' => 1], $jadwal);
        $this->assertNull($jadwal->fresh()->renstra_pk_id);
        $pk = RenstraPk::create(['renstra_id' => $renstra->id, 'tahun' => 2027, 'nomor_pk' => 'PK fixture', 'tanggal_pk' => '2027-01-01', 'created_by' => $actor->id]);
        $jadwal->refresh()->update(['renstra_pk_id' => $pk->id]);
        $this->actingAs($actor)->put('/jadwal/'.$jadwal->id, [...$data, 'revisi' => 2])->assertSessionHasErrors('tahun');
        app(SaveJadwalDraft::class)->handle($actor, [...$nextYear, 'penutupan' => '2028-01-20', 'revisi' => 2], $jadwal);
        $this->assertSame($pk->id, $jadwal->fresh()->renstra_pk_id);
        $this->assertSame(2027, $jadwal->fresh()->tahun);
    }

    public function test_same_pair_all_statuses_is_rejected_without_partial_success(): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        $jadwal->update(['status' => 'ditutup']);
        $this->actingAs($actor)->post('/jadwal', $data)->assertSessionHasErrors('jadwal');
        $this->assertSame(1, JadwalTahunan::count());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tambah')->count());
        $this->expectException(QueryException::class);
        DB::transaction(fn () => JadwalTahunan::create(['renstra_id' => $data['renstra_id'], 'tahun' => 2026, 'penutupan' => '2027-01-19']));
    }

    public function test_uppercase_uuids_are_normalized_and_nested_field_allowlist_is_enforced(): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $data['renstra_id'] = strtoupper($data['renstra_id']);
        $data['periode'][0]['periode_id'] = strtoupper($data['periode'][0]['periode_id']);
        $this->actingAs($actor)->post('/jadwal', $data)->assertRedirect();
        $jadwal = JadwalTahunan::sole();
        $data['periode'][0]['status'] = 'aktif';
        $this->put('/jadwal/'.$jadwal->id, [...$data, 'revisi' => 1])->assertSessionHasErrors('periode.0');
        $this->assertSame(1, $jadwal->fresh()->revisi);
    }

    public function test_equivalent_field_order_is_a_noop_and_only_child_changes_increment_parent(): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        $childId = PeriodeJadwal::sole()->id;
        $data['periode'][0] = array_reverse($data['periode'][0], true);
        app(SaveJadwalDraft::class)->handle($actor, [...$data, 'revisi' => 1], $jadwal);
        $this->assertSame(1, $jadwal->fresh()->revisi);
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.ubah')->count());
        $data['periode'][0]['reviu_mulai'] = '2027-01-13';
        app(SaveJadwalDraft::class)->handle($actor, [...$data, 'revisi' => 1], $jadwal);
        $this->assertSame(2, $jadwal->fresh()->revisi);
        $this->assertSame($childId, PeriodeJadwal::sole()->id);
    }

    public function test_postgresql_rejects_duplicate_final_and_invalid_dates(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $master = $this->calendarMaster();
        $data = $this->calendarPayload($renstra, $master);
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        $cases = [
            [fn () => $this->calendarMaster(5, true), 'periode_final_aktif_unik'],
            [fn () => PeriodeJadwal::sole()->update(['reviu_mulai' => '2027-01-01']), 'jadwal_periode_urutan_tanggal'],
            [fn () => $jadwal->update(['rencana_aksi_selesai' => '2026-01-01']), 'jadwal_tahunan_urutan_ra'],
        ];
        foreach ($cases as [$write, $constraint]) {
            try {
                DB::transaction($write);
                $this->fail('Constraint tidak menolak '.$constraint);
            } catch (QueryException $exception) {
                $this->assertStringContainsString($constraint, $exception->getMessage());
            }
        }
        $this->assertSame(1, $jadwal->fresh()->revisi);
    }
}
