<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\RencanaAksiVersi;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * ISS-05.05: pengesahan diverifikasi → disahkan pada surface review #93 (D1):
 * gerbang bersama, token versi pengajuan, F1/F2, audit, dan guard konteks.
 */
class SahkanRencanaAksiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    private const F1 = 'F1: pengaju melalui jalur PIC tidak boleh memverifikasi atau mengesahkan pengajuannya sendiri.';

    private RencanaAksiVersi $versiPengajuan;

    private User $verifikator;

    private User $pengesah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->verifikator = $this->createUserWithRole('perencanaan');
        $this->pengesah = $this->createUserWithRole('perencanaan');
        // Versi 1 diajukan jalur PIC oleh aktor fixture (superadmin sebagai PJ).
        $this->versiPengajuan = $this->createRencanaAksiSubmission();
    }

    /** Versi tambahan dengan provenance tertentu, mengikuti bentuk snapshot versi 1 (versi beku tidak dapat diubah/dihapus). */
    private function ajukanUlang(User $pengaju, string $jalur, ?string $snapshotId = null): RencanaAksiVersi
    {
        return $this->versiPengajuan = $this->rencanaAksi->versions()->create(array_merge(
            $this->versiPengajuan->only(['jadwal_snapshot_id', 'snapshot', 'dasar_izin_pengajuan']),
            ['nomor' => $this->versiPengajuan->nomor + 1, 'diajukan_by' => $pengaju->id, 'diajukan_at' => now(), 'jalur_pengajuan' => $jalur],
            $snapshotId === null ? [] : ['jadwal_snapshot_id' => $snapshotId],
        ));
    }

    private function verifikasi(?User $verifikator = null): void
    {
        app(VerifikasiRencanaAksi::class)->handle($verifikator ?? $this->verifikator, $this->rencanaAksi->id, $this->tokens());
    }

    /** @return array{expected_versi: int, versi_pengajuan_id: string} */
    private function tokens(): array
    {
        return ['expected_versi' => $this->rencanaAksi->fresh()->versi, 'versi_pengajuan_id' => $this->versiPengajuan->id];
    }

    /** @param array<string, mixed>|null $data */
    private function sahkan(User $aktor, ?array $data = null): TestResponse
    {
        return $this->actingAs($aktor)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/sahkan", $data ?? $this->tokens());
    }

    private function assertBelumDisahkan(): void
    {
        $header = $this->rencanaAksi->fresh();
        $this->assertSame('diverifikasi', $header->status_alur);
        $this->assertNull($header->disahkan_by);
        $this->assertNull($this->versiPengajuan->fresh()->disahkan_at);
    }

    public function test_f1_menolak_pengesahan_oleh_pengaju_jalur_pic(): void
    {
        $this->verifikasi();

        $this->sahkan($this->actor)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [self::F1]);

        $this->assertBelumDisahkan();
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.sahkan_ditolak')->count());
    }

    public function test_perencana_lain_mengesahkan_pengajuan_pic_secara_atomik_dengan_audit(): void
    {
        $this->verifikasi();
        $beku = $this->versiPengajuan->fresh()->getRawOriginal('snapshot');

        $this->sahkan($this->pengesah)->assertOk()->assertJson(['status_alur' => 'disahkan', 'versi' => 3]);

        $header = $this->rencanaAksi->fresh();
        $versi = $this->versiPengajuan->fresh();
        $this->assertSame('disahkan', $header->status_alur);
        $this->assertSame(3, $header->versi);
        $this->assertSame($this->pengesah->id, $header->disahkan_by);
        $this->assertNotNull($header->disahkan_at);
        $this->assertSame($this->pengesah->id, $versi->disahkan_by);
        $this->assertNotNull($versi->disahkan_at);
        $this->assertSame($beku, $versi->getRawOriginal('snapshot'));
        $audit = AuditLog::where('tindakan', 'rencana_aksi.sahkan')->sole();
        $this->assertSame(PermissionCodes::RENCANA_AKSI_SAHKAN, $audit->dasar_izin['permission']);
        $this->assertSame('diizinkan', $audit->dasar_izin['keputusan']);
        $this->assertSame('disahkan', $audit->nilai_baru['status_alur']);
        $this->assertFalse($audit->nilai_baru['self_approval']);
        $this->assertNotEmpty($audit->alasan);
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('RencanaAksi/Review')->where('rencanaAksi.status_alur', 'disahkan')
            ->where('rencanaAksi.pengesahan.oleh', $this->pengesah->nama)->where('rencanaAksi.pengesahan.self_approval', false)
            ->where('rencanaAksi.can.sahkan', false));
        // Daftar disahkan untuk monitoring memakai antrean review yang sama.
        $this->get('/rencana-aksi/review?status=disahkan')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('daftar', 1)->where('daftar.0.id', $this->rencanaAksi->id)->where('daftar.0.status_alur', 'disahkan'));
    }

    public function test_f2_mengizinkan_self_approval_jalur_perencanaan_dan_menandainya(): void
    {
        $this->ajukanUlang($this->pengesah, 'perencanaan');
        $this->verifikasi();

        $this->sahkan($this->pengesah)->assertOk();

        $this->assertSame('disahkan', $this->rencanaAksi->fresh()->status_alur);
        $audit = AuditLog::where('tindakan', 'rencana_aksi.sahkan')->sole();
        $this->assertTrue($audit->nilai_baru['self_approval']);
        $this->assertTrue($audit->nilai_lama['self_approval']);
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rencanaAksi.pengesahan.oleh', $this->pengesah->nama)->where('rencanaAksi.pengesahan.self_approval', true));
    }

    public function test_penutupan_menolak_pengesahan_kecuali_sesi_koreksi_mencakup_rencana_aksi(): void
    {
        $this->verifikasi();
        $this->travelTo(now()->setDate(2027, 1, 5));
        $ditutup = 'Tahun jadwal telah ditutup; review memerlukan sesi koreksi resmi untuk indikator ini.';

        $this->sahkan($this->pengesah)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [$ditutup]);
        $this->jadwal->update(['koreksi_mulai' => now()->subHour(), 'koreksi_sampai' => now()->addHour(),
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->indikator->id], 'periode_ids' => [$this->snapshot->periode_mulai_id]]]);
        $this->sahkan($this->pengesah)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [$ditutup]);
        $this->assertBelumDisahkan();

        $this->jadwal->update(['lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$this->indikator->id], 'periode_ids' => [$this->snapshot->periode_mulai_id]]]);
        $this->sahkan($this->pengesah)->assertOk();
        $this->assertSame('disahkan', $this->rencanaAksi->fresh()->status_alur);
    }

    public function test_f2_tidak_melewati_deny_global_deny_unit_maupun_izin_yang_hilang(): void
    {
        $this->ajukanUlang($this->pengesah, 'perencanaan');
        $this->verifikasi();

        $this->denyPermission($this->pengesah, PermissionCodes::RENCANA_AKSI_SAHKAN);
        $this->sahkan($this->pengesah)->assertForbidden();
        $audit = AuditLog::where('tindakan', 'rencana_aksi.sahkan_ditolak')->sole();
        $this->assertSame(PermissionCodes::RENCANA_AKSI_SAHKAN, $audit->dasar_izin['permission']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);

        $lain = $this->createUserWithRole('perencanaan');
        $this->denyPermission($lain, PermissionCodes::RENCANA_AKSI_SAHKAN, $this->unit->id);
        $this->sahkan($lain)->assertForbidden();
        // Peran baca-saja dapat melihat review tetapi tidak dapat mengesahkan.
        $this->sahkan($this->createUserWithRole('pegawai'))->assertForbidden();
        $this->sahkan($this->createUserWithRole('pimpinan'))->assertForbidden();

        $this->assertBelumDisahkan();
        $this->assertSame(4, AuditLog::where('tindakan', 'rencana_aksi.sahkan_ditolak')->count());
    }

    public function test_versi_bukan_terbaru_dan_token_usang_ditolak_tanpa_transisi_ulang(): void
    {
        $this->verifikasi();
        $lama = $this->tokens();
        $this->ajukanUlang($this->actor, 'pic');

        // Payload mengacu versi pengajuan yang sudah tergantikan, lalu token header usang.
        $this->sahkan($this->pengesah, $lama)->assertConflict()->assertJsonValidationErrors('expected_versi');
        $this->sahkan($this->pengesah, ['expected_versi' => 1, 'versi_pengajuan_id' => $this->versiPengajuan->id])->assertConflict();
        $this->assertBelumDisahkan();

        $terbaru = $this->tokens();
        $this->sahkan($this->pengesah, $terbaru)->assertOk();
        $this->sahkan($this->createUserWithRole('perencanaan'), $terbaru)->assertConflict();
        $this->assertSame($this->pengesah->id, $this->rencanaAksi->fresh()->disahkan_by);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.sahkan')->count());
    }

    public function test_konteks_versi_tidak_selaras_dengan_header_ditolak(): void
    {
        $this->verifikasi();
        $unitLain = Unit::create(['nama' => 'Unit Lain', 'created_by' => $this->actor->id]);
        $snapshotLain = JadwalSnapshot::create(array_merge($this->snapshot->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'nama', 'definisi', 'satuan',
            'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']), ['unit_id' => $unitLain->id, 'nomor_versi' => 2]));
        // Versi terbaru merujuk snapshot unit lain.
        $this->ajukanUlang($this->actor, 'pic', $snapshotLain->id);
        $konteks = 'Konteks versi pengajuan belum lengkap atau tidak selaras; review ditolak.';

        $this->sahkan($this->pengesah)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [$konteks]);

        // Snapshot selaras kembali, tetapi header menunjuk jadwal tahunan lain.
        $this->ajukanUlang($this->actor, 'pic', $this->snapshot->id);
        $jadwalLain = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2027, 'renstra_pk_id' => $this->jadwal->renstra_pk_id,
            'penutupan' => '2027-12-31', 'status' => 'aktif', 'activated_at' => now()]);
        $this->rencanaAksi->update(['jadwal_tahunan_id' => $jadwalLain->id]);
        $this->sahkan($this->pengesah)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [$konteks]);

        $this->assertBelumDisahkan();
        $this->assertSame(2, AuditLog::where('tindakan', 'rencana_aksi.sahkan_ditolak')->count());
    }

    public function test_arsip_dan_unit_nonaktif_menolak_pengesahan_termasuk_f2_dan_action_langsung(): void
    {
        $this->ajukanUlang($this->pengesah, 'perencanaan');
        $this->verifikasi();
        $this->indikator->update(['status' => 'arsip']);
        $this->unit->update(['status' => 'nonaktif']);

        $this->sahkan($this->pengesah)->assertUnprocessable()->assertJsonCount(2, 'errors.businessErrors');
        try {
            app(SahkanRencanaAksi::class)->handle($this->pengesah, $this->rencanaAksi->id, $this->tokens());
            $this->fail('Pengesahan langsung pada konteks tidak aktif harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertCount(2, $e->errors()['businessErrors']);
        }

        $this->assertBelumDisahkan();
        $this->assertSame(2, AuditLog::where('tindakan', 'rencana_aksi.sahkan_ditolak')->count());
        $this->actingAs($this->pengesah)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rencanaAksi.can.sahkan', false)->has('rencanaAksi.businessErrors.sahkan', 2));
    }

    public function test_bukti_rujukan_versi_resmi_tidak_dapat_dihapus_setelah_disahkan(): void
    {
        $bukti = $this->createBuktiDukung(['mode' => 'teks', 'tautan' => null, 'isi_teks' => 'Bukti rujukan versi resmi.']);
        $this->verifikasi();
        $this->sahkan($this->pengesah)->assertOk();

        $this->actingAs($this->pengesah)->delete("/rencana-aksi/{$this->rencanaAksi->id}/bukti/{$bukti->id}", ['alasan' => 'Coba hapus bukti resmi.'])
            ->assertSessionHasErrors('status_alur');
        $this->assertNull($bukti->fresh()->dihapus_pada);
    }

    public function test_regresi_provenance_beku_setelah_perubahan_role_tetap_menolak_f1(): void
    {
        $pic = $this->createPicPegawai();
        $this->ajukanUlang($pic, 'pic');
        $this->verifikasi();
        // Pembuat draf berbeda dari pengaju; peran pengaju berubah setelah pengajuan.
        $this->rencanaAksi->update(['created_by' => $this->createUserWithRole('pegawai')->id]);
        DB::table('user_roles')->where('user_id', $pic->id)->update(['role_id' => Role::where('kode', 'perencanaan')->value('id')]);

        $this->sahkan($pic)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [self::F1]);
        $this->assertBelumDisahkan();
    }

    public function test_kegagalan_audit_membatalkan_pengesahan(): void
    {
        $this->verifikasi();
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new \RuntimeException('Audit gagal'));

        try {
            app(SahkanRencanaAksi::class)->handle($this->pengesah, $this->rencanaAksi->id, $this->tokens());
            $this->fail('Kegagalan audit harus diteruskan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit gagal', $e->getMessage());
        }

        $this->assertBelumDisahkan();
    }

    public function test_halaman_review_menawarkan_sahkan_hanya_bila_izin_dan_guard_terpenuhi(): void
    {
        $halaman = fn (User $aktor) => $this->actingAs($aktor)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk();
        $status = 'Status rencana aksi tidak sesuai untuk tindakan ini.';

        $halaman($this->pengesah)->assertInertia(fn (Assert $page) => $page
            ->where('rencanaAksi.can.sahkan', false)->where('rencanaAksi.businessErrors.sahkan', [$status]));

        $this->verifikasi();
        $halaman($this->pengesah)->assertInertia(fn (Assert $page) => $page
            ->where('rencanaAksi.can.sahkan', true)->where('rencanaAksi.businessErrors.sahkan', [])->where('rencanaAksi.pengesahan', null));
        $halaman($this->actor)->assertInertia(fn (Assert $page) => $page
            ->where('rencanaAksi.can.sahkan', false)->where('rencanaAksi.businessErrors.sahkan', [self::F1]));
    }
}
