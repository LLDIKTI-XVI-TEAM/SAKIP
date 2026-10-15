<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KinerjaSnapshot;
use App\Models\Permission;
use App\Models\Role;
use App\Policies\PengukuranKinerjaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
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

    public function test_pengajuan_belum_dapat_diukur_ditolak_sementara_tanpa_versi_dan_presenter_menutup_ajukan(): void
    {
        $url = '/pengukuran/'.$this->pengukuran->id;
        $message = 'Pengajuan belum tersedia untuk status Belum dapat diukur. Simpan sebagai draft.';
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', 'nilai' => 60])->assertSessionHasNoErrors();

        $this->post($url, ['versi' => 2, 'action' => 'ajukan', 'status_perhitungan' => 'belum_dapat_diukur'])->assertSessionHasErrors(['pengajuan' => $message]);

        $this->assertDatabaseCount('pengukuran_versi', 0);
        $draft = $this->pengukuran->fresh();
        $this->assertSame(['draft', 2, 'terhitung', '60.000000000000'], [$draft->status_alur, $draft->versi, $draft->status_perhitungan, $draft->nilai]);
        $this->assertSame('ajukan', AuditLog::where('tindakan', 'pengukuran.ditolak')->sole()->nilai_baru['tindakan_diminta']);

        $this->post($url, ['versi' => 2, 'action' => 'draft', 'status_perhitungan' => 'belum_dapat_diukur', 'progres_kegiatan' => 'Belum ada data.'])->assertSessionHasNoErrors();
        $this->get($url.'/edit')->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.status_perhitungan', 'belum_dapat_diukur')->where('pengukuran.can.submit', false)
            ->where('pengukuran.prasyarat.alasan', fn ($alasan) => collect($alasan)->contains($message))
            ->where('pengukuran.progres_kegiatan', 'Belum ada data.')->where('pengukuran.kendala_masalah', null)
            ->where('pengukuran.periode_jadwal.is_nilai_akhir', false)->missing('pengukuran.rekomendasi_sebelumnya'));
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
