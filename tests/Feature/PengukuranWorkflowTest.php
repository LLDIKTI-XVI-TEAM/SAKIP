<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Permission;
use App\Models\Role;
use App\Policies\PengukuranKinerjaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_planning_path_follows_role_allow_source_not_role_code(): void
    {
        // Preset resmi tidak memberi pimpinan izin ini; fixture membuktikan jalur ikut sumber allow, bukan kode peran.
        $pimpinan = $this->userWithRole('pimpinan');
        DB::table('role_permissions')->insert(['id' => (string) Str::uuid(), 'role_id' => Role::where('kode', 'pimpinan')->value('id'),
            'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'), 'created_at' => now()]);
        $pegawai = $this->userWithRole('pegawai');
        $this->grant($pegawai, 'pengukuran:update');
        $policy = app(PengukuranKinerjaPolicy::class);

        $this->assertTrue($policy->usesPlanningPath($pimpinan, $this->pengukuran));
        $this->assertFalse($policy->usesPlanningPath($pegawai, $this->pengukuran));
    }

    public function test_role_allow_with_unit_grant_stays_planning_path(): void
    {
        // Perilaku saat ini; berubah hanya bila keputusan D5 (jalur ganda) menetapkan lain.
        $perencanaan = $this->userWithRole('perencanaan');
        $this->grant($perencanaan, 'pengukuran:update');

        $this->assertTrue(app(PengukuranKinerjaPolicy::class)->usesPlanningPath($perencanaan, $this->pengukuran));
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

    public function test_submit_capability_does_not_block_incomplete_saved_draft(): void
    {
        $this->actingAs($this->actor)->get('/pengukuran/'.$this->pengukuran->id.'/edit')
            ->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.prasyarat.siap', false)
            ->where('pengukuran.can.submit', true));
    }

    public function test_failed_submission_returns_all_attempt_reasons_separately_from_saved_draft(): void
    {
        $this->pengukuran->update(['nilai' => 75, 'status_perhitungan' => 'terhitung']);
        $this->plan->update(['status_alur' => 'draft']);
        $url = '/pengukuran/'.$this->pengukuran->id.'/edit';
        $reasons = ['Rencana aksi indikator/tahun ini harus disahkan sebelum pengajuan.', 'Nilai manual atau seluruh komponen snapshot wajib diisi.'];

        $this->actingAs($this->actor)->from($url)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => 1, 'action' => 'ajukan', 'nilai' => null])
            ->assertRedirect($url)->assertSessionHasErrors('pengajuan')
            ->assertSessionHas('hasil_pengajuan_terakhir.alasan', $reasons);

        $this->get($url)->assertInertia(fn ($page) => $page
            ->where('hasil_pengajuan_terakhir.alasan', $reasons)
            ->where('pengukuran.prasyarat.alasan', [$reasons[0]])
            ->where('pengukuran.nilai', '75.000000000000')
            ->where('pengukuran.selisih_target_pk', '5.000000000000'));
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.ajukan')->count());

        $this->postJson('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => null])
            ->assertUnprocessable()->assertJsonPath('errors.pengajuan', $reasons);
    }

    public function test_submission_feedback_does_not_cross_measurement_or_actor(): void
    {
        foreach (['pengukuran_id', 'actor_id'] as $differentKey) {
            $result = ['pengukuran_id' => $this->pengukuran->id, 'actor_id' => $this->actor->id, 'alasan' => ['Alasan percobaan pada konteks lain.']];
            $result[$differentKey] = (string) Str::uuid();
            $this->actingAs($this->actor)->withSession(['hasil_pengajuan_terakhir' => $result])
                ->get('/pengukuran/'.$this->pengukuran->id.'/edit')
                ->assertInertia(fn ($page) => $page->where('hasil_pengajuan_terakhir', null));
        }
    }

    public function test_submission_without_action_plan_keeps_all_reasons_and_rolls_back(): void
    {
        $indicator = $this->pengukuran->indikator->replicate();
        $indicator->kode = 'I-TANPA-RA';
        $indicator->save();
        $context = $this->context->replicate();
        $context->indikator_id = $indicator->id;
        $context->save();
        $measurement = PengukuranKinerja::create(['indikator_id' => $indicator->id, 'tahun' => 2026,
            'periode_id' => $this->pengukuran->periode_id, 'jadwal_snapshot_id' => $context->id, 'sumber_nilai' => 'manual',
            'progres_kegiatan' => 'Progres fixture.', 'kendala_masalah' => 'Kendala fixture.', 'strategi_tindak_lanjut' => 'Strategi fixture.', 'created_by' => $this->actor->id]);

        $this->actingAs($this->actor)->post('/pengukuran/'.$measurement->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => null])
            ->assertSessionHas('hasil_pengajuan_terakhir.alasan', [
                'Rencana aksi indikator/tahun ini harus disahkan sebelum pengajuan.', 'Nilai manual atau seluruh komponen snapshot wajib diisi.',
            ]);
        $measurement->refresh();
        $this->assertSame(['draft', 1, null], [$measurement->status_alur, $measurement->versi, $measurement->nilai]);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->where('objek_id', $measurement->id)->count());
    }

    public function test_successful_submission_appears_in_scoped_verification_queue(): void
    {
        $reviewer = $this->userWithRole('perencanaan');
        $this->actingAs($reviewer)->get('/verifikasi')->assertInertia(fn ($page) => $page->has('pengukurans', 0));
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])->assertRedirect('/pengukuran');
        $this->actingAs($reviewer)->get('/verifikasi')->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)->where('pengukurans.0.id', $this->pengukuran->id)->where('pengukurans.0.status', 'diajukan'));

        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $reviewer->id,
            'permission_id' => Permission::where('kode', 'pengukuran:read')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Unit tidak boleh dibaca.', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->get('/verifikasi')->assertInertia(fn ($page) => $page->has('pengukurans', 0));
    }

    public function test_direct_submission_with_matching_deny_is_rejected_and_audited(): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Pengajuan ditolak.', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])->assertForbidden();
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame('ajukan', $audit->nilai_baru['tindakan_diminta']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['reason']);
    }

    public function test_repeated_submission_keeps_one_frozen_version_and_reports_stale_token(): void
    {
        $payload = ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75];
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, $payload)->assertRedirect('/pengukuran');
        $this->post('/pengukuran/'.$this->pengukuran->id, $payload)->assertSessionHasErrors('versi')
            ->assertSessionHas('hasil_pengajuan_terakhir.alasan', fn ($reasons) => is_array($reasons) && count($reasons) > 1
                && in_array('Data telah berubah. Muat ulang sebelum mengulangi tindakan.', $reasons, true));
        $this->assertDatabaseCount('pengukuran_versi', 1);
        $this->assertSame(2, $this->pengukuran->fresh()->versi);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ajukan')->count());
    }

    #[DataProvider('pengajuanBelumDapatDiukur')]
    public function test_gerbang_final_belum_dapat_diukur(bool $final, ?string $missing, bool $normal): void
    {
        $this->pengukuran->periode->update(['is_nilai_akhir' => $final]);
        $payload = ['versi' => 1, 'action' => 'ajukan', 'nilai' => $normal ? 0 : null,
            'status_perhitungan' => $normal ? null : 'belum_dapat_diukur',
            'progres_kegiatan' => 'Nilai belum tersedia.', 'kendala_masalah' => 'Data masih diproses.',
            'strategi_tindak_lanjut' => 'Konfirmasi sumber data.', 'catatan' => null];
        if ($missing !== null) {
            $payload[$missing] = '   ';
        }
        $response = $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, $payload);
        if ($final || $missing !== null) {
            $response->assertUnprocessable()->assertJsonValidationErrors($final ? 'status_perhitungan' : $missing);
            $this->assertDatabaseCount('pengukuran_versi', 0);
            $this->assertSame(['draft', 1, null], [$this->pengukuran->fresh()->status_alur, $this->pengukuran->fresh()->versi, $this->pengukuran->fresh()->nilai]);
            $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
        } else {
            $response->assertRedirect('/pengukuran');
            $version = $this->pengukuran->fresh()->latestVersion;
            $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
            $this->assertSame($normal ? 'terhitung' : 'belum_dapat_diukur', $version->snapshot['status_perhitungan']);
            $this->assertSame($normal ? '0.000000000000' : null, $version->snapshot['nilai']);
            $this->assertSame($payload['progres_kegiatan'], $version->snapshot['progres_kegiatan']);
            $this->assertArrayHasKey('tindak_lanjut_rekomendasi', $version->snapshot);
            $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ajukan')->count());
        }
    }

    public static function pengajuanBelumDapatDiukur(): array
    {
        return ['narasi lengkap' => [false, null, false],
            'progres kosong' => [false, 'progres_kegiatan', false], 'kendala kosong' => [false, 'kendala_masalah', false],
            'strategi kosong' => [false, 'strategi_tindak_lanjut', false], 'periode nilai akhir' => [true, null, false],
            'kontrol nilai nol' => [false, null, true]];
    }

    public function test_pengajuan_membekukan_empat_narasi_dan_presenter_hanya_membaca_versi(): void
    {
        $narasi = ['progres_kegiatan' => 'Progres beku.', 'kendala_masalah' => 'Kendala beku.', 'strategi_tindak_lanjut' => 'Strategi beku.', 'tindak_lanjut_rekomendasi' => null];
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75, ...$narasi])->assertSessionHasNoErrors();
        $version = $this->pengukuran->fresh()->latestVersion;
        foreach ($narasi as $key => $value) {
            // JSONB tidak menjaga urutan key; null tetap harus tersimpan sebagai key.
            $this->assertArrayHasKey($key, $version->snapshot);
            $this->assertSame($value, $version->snapshot[$key]);
        }

        DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['progres_kegiatan' => 'Diubah langsung.', 'tindak_lanjut_rekomendasi' => 'Teks hidup.']);
        $this->pengukuran->periode->update(['is_nilai_akhir' => true]);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.progres_kegiatan', 'Progres beku.')->where('pengukuran.tindak_lanjut_rekomendasi', null)
            ->where('pengukuran.periode_jadwal.is_nilai_akhir', false));

        // Versi lama tanpa key narasi tidak mewarisi teks header hidup.
        KinerjaSnapshot::create([...$version->only(['pengukuran_id', 'rencana_aksi_versi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan']),
            'nomor' => 2, 'snapshot' => Arr::except($version->snapshot, array_keys($narasi))]);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.progres_kegiatan', null)->where('pengukuran.tindak_lanjut_rekomendasi', null));
    }
}
