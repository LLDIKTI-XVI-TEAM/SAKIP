<?php

namespace Tests\Feature;

use App\Actions\Access\AssignRole;
use App\Actions\Access\CreateDeny;
use App\Actions\Access\RevokeDeny;
use App\Models\AuditLog;
use App\Models\IndikatorKomponen;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class VerticalSlice1Test extends TestCase
{
    use CreatesPengukuranFixture,RefreshDatabase;

    private function preparePic(): User
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $pic->id, 'tanggal_mulai_berlaku' => '2026-03-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $pic;
    }

    public function test_review_lateness_is_frozen_at_each_transition_using_the_wita_deadline(): void
    {
        $this->submit($this->actor);
        $this->travelTo(Carbon::parse('2026-04-15 15:59:59', 'UTC'));
        $this->review($this->actor, 'verifikasi')->assertSessionHasNoErrors();
        $verification = AuditLog::where('tindakan', 'pengukuran.verifikasi')->sole();
        $this->assertFalse($verification->nilai_baru['reviu_terlambat'] ?? null);
        $this->travelTo(Carbon::parse('2026-04-15 16:00:00', 'UTC'));
        $this->review($this->actor, 'sahkan')->assertSessionHasNoErrors();
        $audit = AuditLog::where('tindakan', 'pengukuran.sahkan')->sole();
        $this->assertTrue($audit->nilai_baru['reviu_terlambat']);
        $this->assertSame('2026-04-15', $audit->nilai_baru['reviu_selesai']);
        $this->assertSame('2026-04-16', $audit->nilai_baru['tanggal_reviu']);
        $this->assertSame($this->pengukuran->latestVersion->id, $audit->nilai_baru['versi_pengajuan']['id']);
        PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->update(['reviu_selesai' => '2026-05-01']);
        $this->assertSame($audit->nilai_baru, $audit->fresh()->nilai_baru);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page->where('pengukuran.reviu_terlambat', true));
        $this->get('/pengukuran')->assertInertia(fn ($page) => $page->where('pengukurans.0.reviu_terlambat', true));
        $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('pengukurans.0.reviu_terlambat', true));
    }

    public function test_new_submission_does_not_inherit_the_previous_late_review_marker(): void
    {
        $this->submit($this->actor);
        $this->travelTo(Carbon::parse('2026-04-16 00:00:00', 'Asia/Makassar'));
        $this->review($this->actor, 'verifikasi')->assertSessionHasNoErrors();
        $audit = AuditLog::where('tindakan', 'pengukuran.verifikasi')->sole();
        $this->assertTrue($audit->nilai_baru['reviu_terlambat'] ?? null);
        $this->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan', ['versi' => $this->pengukuran->versi, 'catatan' => 'Perlu koreksi.'])->assertSessionHasNoErrors();
        $this->submit($this->actor);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page->where('pengukuran.reviu_terlambat', false));
        $this->assertTrue($audit->fresh()->nilai_baru['reviu_terlambat']);
    }

    private function deny(User $user, string $code, ?string $unit = null): string
    {
        $id = (string) Str::uuid();
        DB::table('user_permission_denied')->insert(['id' => $id, 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $code)->value('id'),
            'unit_id' => $unit ?? $this->unit->id, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $id;
    }

    private function submit(User $user, array $extra = []): void
    {
        $this->actingAs($user)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => $this->pengukuran->fresh()->versi, 'action' => 'ajukan', 'nilai' => 85, ...$extra])->assertSessionHasNoErrors()->assertRedirect('/pengukuran');
        $this->pengukuran->refresh();
    }

    private function review(User $user, string $command): TestResponse
    {
        $response = $this->actingAs($user)->post('/verifikasi/'.$this->pengukuran->id.'/'.$command, ['versi' => $this->pengukuran->fresh()->versi]);
        $this->pengukuran->refresh();

        return $response;
    }

    public function test_admin_without_substantive_grants_cannot_mutate_performance_data(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 75])->assertForbidden();
        $this->review($admin, 'sahkan')->assertForbidden();
        $this->assertDatabaseHas('audit_log', ['actor_id' => $admin->id, 'objek_id' => $this->pengukuran->id, 'tindakan' => 'pengukuran.ditolak']);
    }

    public function test_pic_can_submit_with_frozen_canonical_version_and_audit(): void
    {
        $pic = $this->preparePic();
        $this->submit($pic, ['bukti' => ['mode' => 'tautan', 'tautan' => 'https://example.test/bukti']]);
        $version = $this->pengukuran->latestVersion;
        $this->assertTrue(Str::isUuid($this->pengukuran->id));
        $this->assertSame('diajukan', $this->pengukuran->status_alur);
        $this->assertSame($pic->id, $version->diajukan_by);
        $this->assertSame('pic', $version->jalur_pengajuan);
        $this->assertSame($this->planVersion->id, $version->rencana_aksi_versi_id);
        $this->assertSame(2, $this->pengukuran->versi);
        $this->assertSame(1, $version->nomor);
        $audit = AuditLog::where('tindakan', 'pengukuran.ajukan')->firstOrFail();
        $this->assertSame($version->id, $audit->nilai_baru['versi_pengajuan']['id']);
        $this->assertNotEmpty($audit->dasar_izin['grants']);
    }

    public function test_perencanaan_can_return_submission_with_mandatory_notes(): void
    {
        $this->submit($this->preparePic());
        $reviewer = $this->userWithRole('perencanaan');
        $url = '/verifikasi/'.$this->pengukuran->id.'/kembalikan';
        $this->actingAs($reviewer)->post($url, ['versi' => 2])->assertSessionHasErrors('catatan');
        $this->actingAs($reviewer)->post($url, ['versi' => 2, 'catatan' => 'Lengkapi penjelasan sumber.'])->assertRedirect('/verifikasi');
        $this->assertSame('dikembalikan', $this->pengukuran->fresh()->status_alur);
    }

    public function test_verification_then_ratification_uses_frozen_submission(): void
    {
        $this->submit($this->preparePic());
        $this->pengukuran->indikator->update(['nama' => 'Master berubah']);
        $reviewer = $this->userWithRole('perencanaan');
        $this->review($reviewer, 'sahkan')->assertSessionHasErrors('versi');
        $this->review($reviewer, 'verifikasi')->assertSessionHasNoErrors()->assertRedirect();
        $this->review($reviewer, 'sahkan')->assertSessionHasNoErrors()->assertRedirect('/verifikasi');
        $version = $this->pengukuran->latestVersion;
        $this->assertSame('Indikator Uji', $version->snapshot['indikator']['nama']);
        $this->assertSame(85.0, (float) $version->snapshot['nilai']);
        $this->assertNotNull($version->disahkan_at);
        $this->review($reviewer, 'sahkan')->assertSessionHasErrors('versi');
        $this->assertDatabaseCount('pengukuran_versi', 1);
    }

    public function test_f1_keeps_blocking_pic_submitter_after_role_and_assignment_change(): void
    {
        $pic = $this->preparePic();
        $this->submit($pic);
        $reviewer = $this->userWithRole('perencanaan');
        $role = Role::where('kode', 'perencanaan')->firstOrFail();
        $this->deny($pic, 'dashboard:read');
        $tables = ['user_permission_granted', 'user_permission_denied', 'penanggung_jawab', 'rencana_aksi_versi', 'pengukuran_versi', 'role_permissions', 'auth_bootstraps'];
        $preserved = [];
        foreach ($tables as $table) {
            $preserved[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->assertDatabaseCount('user_permission_granted', 1);
        $this->assertDatabaseCount('user_permission_denied', 1);
        $auditIds = AuditLog::pluck('id');
        $history = DB::table('audit_log')->whereIn('id', $auditIds)->orderBy('id')->get()->toJson();
        $token = (array) DB::table('user_roles')->where('user_id', $pic->id)->first(['id', 'role_id', 'audit_id']);
        $this->assertSame('changed', app(AssignRole::class)->handle($this->actor, $pic->id, $role->id, 'Penyesuaian peran tanpa mengubah histori', $token));
        foreach ($tables as $table) {
            $this->assertSame($preserved[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
        $this->assertSame($history, DB::table('audit_log')->whereIn('id', $auditIds)->orderBy('id')->get()->toJson());
        $this->assertTrue($pic->fresh()->is_active);
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $reviewer->id, 'tanggal_mulai_berlaku' => '2026-03-10', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->review($pic, 'verifikasi')->assertSessionHasErrors('versi');
        $this->review($reviewer, 'verifikasi')->assertSessionHasNoErrors()->assertRedirect();
        $this->review($pic, 'sahkan')->assertSessionHasErrors('versi');
        $this->assertSame($pic->id, $this->pengukuran->latestVersion->diajukan_by);
    }

    public function test_f2_marks_self_approval_but_deny_still_wins(): void
    {
        $reviewer = $this->userWithRole('perencanaan');
        $this->submit($reviewer);
        $this->review($reviewer, 'verifikasi')->assertRedirect();
        $this->deny($reviewer, 'pengukuran:sahkan');
        $this->review($reviewer, 'sahkan')->assertForbidden();
        DB::table('user_permission_denied')->where('user_id', $reviewer->id)->delete();
        $this->review($reviewer, 'sahkan')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(AuditLog::where('tindakan', 'pengukuran.sahkan')->firstOrFail()->nilai_baru['self_approval']);
    }

    public function test_deny_create_and_revoke_preserve_access_assignment_and_submission_provenance(): void
    {
        $pic = $this->preparePic();
        $this->submit($pic);
        $otherDeny = $this->deny($pic, 'dashboard:read');
        $tables = ['users', 'user_roles', 'user_permission_granted', 'role_permissions', 'penanggung_jawab', 'auth_bootstraps', 'rencana_aksi_versi', 'pengukuran_versi', 'pengukuran_kinerjas'];
        $preserved = [];
        foreach ($tables as $table) {
            $preserved[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $auditIds = AuditLog::pluck('id');
        $history = DB::table('audit_log')->whereIn('id', $auditIds)->orderBy('id')->get()->toJson();
        $other = DB::table('user_permission_denied')->where('id', $otherDeny)->first();
        $deny = app(CreateDeny::class)->handle($this->actor, $pic->id, Permission::where('kode', 'pengukuran:update')->value('id'), $this->unit->id, 'Evaluasi akses');
        foreach (['created', 'revoked'] as $phase) {
            if ($phase === 'revoked') {
                app(RevokeDeny::class)->handle($this->actor, $deny->id, 'Evaluasi selesai');
                $this->assertDatabaseMissing('user_permission_denied', ['id' => $deny->id]);
            }
            foreach ($tables as $table) {
                $this->assertSame($preserved[$table], DB::table($table)->orderBy('id')->get()->toJson(), $phase.':'.$table);
            }
            $this->assertSame($history, DB::table('audit_log')->whereIn('id', $auditIds)->orderBy('id')->get()->toJson());
            $this->assertEquals($other, DB::table('user_permission_denied')->where('id', $otherDeny)->first());
            $this->assertSame($pic->id, $this->pengukuran->fresh()->latestVersion->diajukan_by);
        }
    }

    public function test_stale_update_is_rejected_and_audited(): void
    {
        $url = '/pengukuran/'.$this->pengukuran->id;
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', 'nilai' => 75])->assertSessionHasNoErrors();
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', 'nilai' => 99])->assertSessionHasErrors('versi');
        $this->assertSame('75.000000000000', $this->pengukuran->fresh()->nilai);
        $this->assertDatabaseHas('audit_log', ['objek_id' => $this->pengukuran->id, 'tindakan' => 'pengukuran.ditolak']);
    }

    public function test_closed_year_blocks_superadmin_and_pic(): void
    {
        $pic = $this->preparePic();
        $this->jadwal->update(['status' => 'ditutup']);
        foreach ([$this->actor, $pic] as $actor) {
            $this->actingAs($actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 75])->assertSessionHasErrors('versi');
        }
    }

    public function test_grant_does_not_replace_effective_pic(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        $this->actingAs($pic)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 75])->assertSessionHasErrors('versi');
    }

    public function test_private_evidence_requires_authorized_parent(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $pic = $this->preparePic();
        $this->submit($pic, ['bukti' => ['mode' => 'file', 'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')]]);
        $bukti = $this->pengukuran->buktiDukungs()->firstOrFail();
        Storage::disk('local')->assertExists($bukti->path);
        Storage::disk('public')->assertMissing($bukti->path);
        $this->actingAs($pic)->get(route('pengukuran.bukti', ['id' => $this->pengukuran->id, 'buktiId' => $bukti->id]))->assertOk();
        $this->actingAs($this->userWithRole('pegawai'))->get(route('pengukuran.bukti', ['id' => $this->pengukuran->id, 'buktiId' => $bukti->id]))->assertForbidden();
    }

    public function test_resubmission_preserves_previous_version_and_audit(): void
    {
        $pic = $this->preparePic();
        $this->submit($pic);
        $reviewer = $this->userWithRole('perencanaan');
        $this->actingAs($reviewer)->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan', ['versi' => 2, 'catatan' => 'Koreksi sumber.'])->assertRedirect();
        $this->submit($pic, ['nilai' => 91]);
        $versions = $this->pengukuran->versions()->get();
        $this->assertCount(2, $versions);
        $this->assertSame(85.0, (float) $versions[0]->snapshot['nilai']);
        $this->assertSame(91.0, (float) $versions[1]->snapshot['nilai']);
        $this->assertSame(2, AuditLog::where('tindakan', 'pengukuran.ajukan')->count());
    }

    public function test_client_provenance_and_invalid_id_are_rejected(): void
    {
        $this->actingAs($this->actor)->get('/pengukuran/not-a-uuid/edit')->assertNotFound();
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75, 'diajukan_by' => $this->actor->id])->assertSessionHasErrors('diajukan_by');
        $this->assertSame('draft', $this->pengukuran->fresh()->status_alur);
    }

    public function test_frozen_unit_deny_survives_master_unit_change(): void
    {
        $this->submit($this->preparePic());
        $reviewer = $this->userWithRole('perencanaan');
        $unit = Unit::create(['nama' => 'Unit Tujuan', 'created_by' => $this->actor->id]);
        $this->pengukuran->indikator->update(['unit_id' => $unit->id]);
        $this->deny($reviewer, 'pengukuran:read');
        $this->actingAs($reviewer)->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertForbidden();
        foreach (['/pengukuran', '/verifikasi'] as $url) {
            $this->actingAs($reviewer)->get($url)->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 0));
        }
        $this->deny($reviewer, 'pengukuran:verifikasi');
        $this->review($reviewer, 'verifikasi')->assertForbidden();
    }

    public function test_upload_capability_is_independent_of_evidence_read(): void
    {
        $pic = $this->preparePic();
        $this->deny($pic, 'berkas:read');
        $this->actingAs($pic)->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertOk()->assertInertia(fn ($page) => $page->where('pengukuran.can.evidence', false)->where('pengukuran.can.uploadEvidence', true));
        $this->submit($pic, ['bukti' => ['mode' => 'tautan', 'tautan' => 'https://example.test/bukti']]);
    }

    public function test_draft_evidence_metadata_is_audited_without_text_or_private_path(): void
    {
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 75, 'bukti' => ['mode' => 'teks', 'isi_teks' => 'Narasi bukti sintetis']])->assertSessionHasNoErrors();
        $audit = AuditLog::where('tindakan', 'pengukuran.draft')->firstOrFail();
        $this->assertSame([], $audit->nilai_lama['bukti_dukungs']);
        $this->assertSame(21, $audit->nilai_baru['bukti_dukungs'][0]['panjang_teks']);
        $this->assertArrayNotHasKey('isi_teks', $audit->nilai_baru['bukti_dukungs'][0]);
        $this->assertArrayNotHasKey('path', $audit->nilai_baru['bukti_dukungs'][0]);
    }

    public function test_upload_denial_records_actual_denied_permission(): void
    {
        $deny = $this->deny($this->actor, 'berkas:upload');
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 75, 'bukti' => ['mode' => 'tautan', 'tautan' => 'https://example.test/bukti']])->assertForbidden();
        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->firstOrFail();
        $this->assertSame('berkas:upload', $audit->dasar_izin['permission']);
        $this->assertSame([$deny], $audit->dasar_izin['denies']);
        $this->assertDatabaseCount('berkas', 0);
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
    }

    public function test_ratified_version_rejects_raw_snapshot_update(): void
    {
        $this->submit($this->actor);
        $this->review($this->actor, 'verifikasi')->assertSessionHasNoErrors();
        $this->review($this->actor, 'sahkan')->assertSessionHasNoErrors();
        $this->expectException(QueryException::class);
        DB::table('pengukuran_versi')->update(['snapshot' => '{}']);
    }

    public function test_ratified_version_rejects_raw_delete(): void
    {
        $this->submit($this->actor);
        $this->review($this->actor, 'verifikasi')->assertSessionHasNoErrors();
        $this->review($this->actor, 'sahkan')->assertSessionHasNoErrors();
        $this->expectException(QueryException::class);
        DB::table('pengukuran_versi')->delete();
    }

    public function test_database_rejects_truncation_of_submission_versions_and_audit(): void
    {
        $this->submit($this->actor);
        $migration = require database_path('migrations/2026_09_20_000001_prevent_immutable_record_truncation.php');
        $before = [];
        foreach (['pengukuran_versi', 'rencana_aksi_versi', 'audit_log'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $migration->down();
        $migration->up();
        foreach (['pengukuran_versi', 'rencana_aksi_versi', 'audit_log'] as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
            $ids = DB::table($table)->orderBy('id')->pluck('id')->all();
            $this->assertNotEmpty($ids);
            try {
                DB::transaction(fn () => DB::statement("TRUNCATE TABLE {$table} CASCADE"));
                $this->fail('Versi pengajuan dan audit tidak boleh dikosongkan.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', (string) $exception->getCode());
            }
            $this->assertSame($ids, DB::table($table)->orderBy('id')->pluck('id')->all());
        }
    }

    public function test_workflow_date_boundaries_follow_wita_at_real_utc_instants(): void
    {
        $pic = $this->preparePic();
        $policy = app(PengukuranKinerjaPolicy::class);
        foreach ([
            ['2026-02-28 15:59:59', false],
            ['2026-02-28 16:00:00', true],
            ['2026-03-15 15:59:59', true],
            ['2026-03-15 16:00:00', false],
        ] as [$instant, $open]) {
            $this->travelTo(Carbon::parse($instant, 'UTC'));
            $this->assertSame($open, $policy->update($pic, $this->pengukuran->fresh())->allowed(), $instant);
        }

        $this->submit($this->actor);
        $this->assertSame(now()->timestamp, $this->pengukuran->latestVersion->diajukan_at->timestamp);
        $this->assertSame(now()->timestamp, AuditLog::where('tindakan', 'pengukuran.ajukan')->firstOrFail()->waktu->timestamp);

        foreach ([['2026-03-14 15:59:59', false], ['2026-03-14 16:00:00', true]] as [$instant, $open]) {
            $this->travelTo(Carbon::parse($instant, 'UTC'));
            $errors = $policy->businessErrors($this->actor, $this->pengukuran->fresh(), 'verifikasi');
            $this->assertSame($open, ! in_array('Jendela reviu periode ini belum dimulai.', $errors, true));
        }

        $this->pengukuran->update(['status_alur' => 'dikembalikan']);
        foreach ([['2026-12-31 15:59:59', true], ['2026-12-31 16:00:00', false]] as [$instant, $open]) {
            $this->travelTo(Carbon::parse($instant, 'UTC'));
            $this->assertSame($open, $policy->update($this->actor, $this->pengukuran->fresh())->allowed(), $instant);
        }
    }

    public function test_referenced_schedule_snapshot_rejects_new_component_definition(): void
    {
        $component = IndikatorKomponen::create(['indikator_id' => $this->pengukuran->indikator_id, 'kode' => 'n', 'label' => 'Pembilang',
            'peran' => 'pembilang', 'bobot' => 1, 'urutan' => 1, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table('jadwal_snapshot_komponen')->insert(['id' => (string) Str::uuid(), 'jadwal_snapshot_id' => $this->context->id,
            'komponen_id' => $component->id, 'kode' => 'n', 'label' => 'Pembilang', 'peran' => 'pembilang', 'bobot' => 1, 'urutan' => 1]);
    }

    public function test_schedule_context_cannot_reference_another_renstra(): void
    {
        $other = Renstra::create(['kode' => 'R-LAIN', 'nama' => 'Renstra lain', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029]);
        $this->jadwal->update(['renstra_id' => $other->id]);
        $this->assertFalse(Gate::forUser($this->actor)->allows('update', $this->pengukuran->fresh()));
    }

    public function test_verification_cannot_start_before_review_window(): void
    {
        PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->update(['reviu_mulai' => today()->addDay()]);
        $errors = app(PengukuranKinerjaPolicy::class)->businessErrors($this->actor, $this->pengukuran, 'verifikasi');
        $this->assertContains('Jendela reviu periode ini belum dimulai.', $errors);
    }

    /**
     * Review Codex: Guard bisnis menolak mutasi pengukuran saat unit berstatus nonaktif.
     */
    public function test_pengukuran_mutation_rejected_by_business_errors_when_unit_is_inactive(): void
    {
        $policy = app(PengukuranKinerjaPolicy::class);

        // Saat unit masih aktif, error nonaktif tidak ada
        $errorsAktif = $policy->businessErrors($this->actor, $this->pengukuran->fresh(), 'draft');
        $this->assertNotContains('Unit organisasi pengukuran berstatus nonaktif.', $errorsAktif);

        // Nonaktifkan unit organisasi pengukuran
        $unit = Unit::findOrFail($this->context->unit_id);
        $unit->update(['status' => 'nonaktif']);

        // Guard bisnis mengembalikan pesan penolakan
        $errorsNonaktif = $policy->businessErrors($this->actor, $this->pengukuran->fresh(), 'draft');
        $this->assertContains('Unit organisasi pengukuran berstatus nonaktif.', $errorsNonaktif);

        // Capability update ditolak dengan pesan yang sesuai
        $response = $policy->update($this->actor, $this->pengukuran->fresh());
        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('Unit organisasi pengukuran berstatus nonaktif.', (string) $response->message());
    }
}
