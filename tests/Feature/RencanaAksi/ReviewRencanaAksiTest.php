<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\KembalikanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\RencanaAksiVersi;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class ReviewRencanaAksiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    private RencanaAksiVersi $versiPengajuan;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->reviewer = $this->createUserWithRole('perencanaan');
        $this->versiPengajuan = $this->createRencanaAksiSubmission();
    }

    private function tokens(): array
    {
        return ['expected_versi' => 1, 'versi_pengajuan_id' => $this->versiPengajuan->id];
    }

    public function test_verifikasi_memakai_alasan_server_dan_tidak_mengubah_snapshot(): void
    {
        $beku = $this->versiPengajuan->fresh()->getRawOriginal('snapshot');
        $this->actingAs($this->reviewer)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", $this->tokens())->assertSuccessful();
        $this->assertSame('diverifikasi', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(2, $this->rencanaAksi->fresh()->versi);
        $this->assertSame($beku, $this->versiPengajuan->fresh()->getRawOriginal('snapshot'));
        $audit = AuditLog::where('tindakan', 'rencana_aksi.verifikasi')->sole();
        $this->assertNotEmpty($audit->alasan);
        $this->assertSame('rencana_aksi:verifikasi', $audit->dasar_izin['permission']);
        $this->assertFalse($audit->nilai_baru['self_approval']);
    }

    public function test_f1_memakai_provenance_pic_meski_pengaju_sekarang_superadmin(): void
    {
        $this->actingAs($this->actor)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", $this->tokens())->assertUnprocessable()->assertJsonValidationErrors('businessErrors');
        $this->assertSame('diajukan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.verifikasi_ditolak')->count());
        $this->actingAs($this->actor)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/kembalikan", $this->tokens() + ['alasan' => 'Perbaiki target.'])->assertSuccessful();
        $this->assertSame('dikembalikan', $this->rencanaAksi->fresh()->status_alur);
    }

    public function test_perencanaan_boleh_self_review_dengan_audit(): void
    {
        $versi = $this->rencanaAksi->versions()->create(array_merge($this->versiPengajuan->only(['jadwal_snapshot_id', 'snapshot', 'dasar_izin_pengajuan']), [
            'nomor' => 2, 'diajukan_by' => $this->reviewer->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan',
        ]));
        app(VerifikasiRencanaAksi::class)->handle($this->reviewer, $this->rencanaAksi->id, ['expected_versi' => 1, 'versi_pengajuan_id' => $versi->id]);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.verifikasi')->sole()->nilai_baru['self_approval']);
    }

    public function test_arsip_dan_unit_nonaktif_menolak_mutasi_tetapi_histori_tetap_dapat_dibaca(): void
    {
        $this->indikator->update(['status' => 'arsip']);
        $this->unit->update(['status' => 'nonaktif']);
        foreach (['verifikasi', 'kembalikan'] as $aksi) {
            $this->actingAs($this->reviewer)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/{$aksi}", $this->tokens() + ['alasan' => 'Perbaikan.'])->assertUnprocessable();
        }
        $this->actingAs($this->reviewer)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('RencanaAksi/Review')->where('rencanaAksi.indikator.nama', 'Nama beku')
            ->where('rencanaAksi.can.verifikasi', false)->where('rencanaAksi.can.kembalikan', false)
            ->has('rencanaAksi.businessErrors.verifikasi', 2)->missing('rencanaAksi.path'));
    }

    public function test_stale_dan_versi_lama_ditolak_409_tanpa_transisi_ulang(): void
    {
        app(VerifikasiRencanaAksi::class)->handle($this->reviewer, $this->rencanaAksi->id, $this->tokens());
        $this->actingAs($this->reviewer)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/kembalikan", $this->tokens() + ['alasan' => 'Perbaikan.'])->assertConflict();
        $this->assertSame(2, $this->rencanaAksi->fresh()->versi);
    }

    public function test_deny_unit_menang_dan_penolakan_diaudit_sekali(): void
    {
        $this->denyPermission($this->reviewer, 'rencana_aksi:verifikasi', $this->unit->id);
        $this->actingAs($this->reviewer)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", $this->tokens())->assertForbidden();
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.verifikasi_ditolak')->count());
    }

    public function test_alasan_pengembalian_divalidasi_juga_pada_action_langsung(): void
    {
        foreach (['', '   ', "Rusak\0teks", str_repeat('a', 1001)] as $alasan) {
            try {
                app(KembalikanRencanaAksi::class)->handle($this->reviewer, $this->rencanaAksi->id, $this->tokens() + ['alasan' => $alasan]);
                $this->fail('Alasan tidak sah harus ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('alasan', $e->errors());
            }
        }
        $this->assertSame(1, $this->rencanaAksi->fresh()->versi);
    }

    public function test_kegagalan_audit_membatalkan_transisi(): void
    {
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new \RuntimeException('Audit gagal'));
        try {
            app(VerifikasiRencanaAksi::class)->handle($this->reviewer, $this->rencanaAksi->id, $this->tokens());
            $this->fail('Kegagalan audit harus diteruskan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit gagal', $e->getMessage());
        }
        $this->assertSame('diajukan', $this->rencanaAksi->fresh()->status_alur);
    }

    public function test_review_memakai_penutupan_tahunan_dan_lingkup_koreksi_bukan_jendela_pengukuran(): void
    {
        $this->jadwal->update(['rencana_aksi_selesai' => today()->subDay()->toDateString(), 'penutupan' => today()->subDay()->toDateString()]);
        $url = "/rencana-aksi/{$this->rencanaAksi->id}/verifikasi";
        $this->actingAs($this->reviewer)->postJson($url, $this->tokens())->assertUnprocessable();
        $this->jadwal->update(['koreksi_mulai' => now()->subHour(), 'koreksi_sampai' => now()->addHour(),
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [], 'periode_ids' => []]]);
        $this->postJson($url, $this->tokens())->assertUnprocessable();
        $this->jadwal->update(['lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$this->indikator->id], 'periode_ids' => [$this->snapshot->periode_mulai_id]]]);
        $this->postJson($url, $this->tokens())->assertSuccessful();
    }

    public function test_histori_dan_antrean_membaca_snapshot_bukan_master_atau_draf(): void
    {
        $this->indikator->update(['nama' => 'Nama master baru', 'satuan' => 'satuan master', 'desimal_tampilan' => 4]);
        $this->rencanaAksi->update(['uraian' => 'Narasi draf baru']);
        $snapshot = $this->versiPengajuan->jadwalSnapshot;
        $this->actingAs($this->reviewer)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('rencanaAksi.indikator.satuan', $snapshot->satuan)->where('rencanaAksi.indikator.desimal_tampilan', $snapshot->desimal_tampilan)
            ->where('rencanaAksi.uraian', 'Narasi beku')->where('rencanaAksi.target_periode.0.nilai', '0')
            ->where('rencanaAksi.target_periode.0.periode_nama', 'Triwulan beku')->missing('rencanaAksi.versi_pengajuan.dasar_izin_pengajuan'));
        $this->get('/rencana-aksi/review')->assertOk()->assertInertia(fn (Assert $p) => $p->component('RencanaAksi/ReviewIndex')
            ->where('daftar.0.indikator.nama', 'Nama beku')->where('pagination.total', 1));
        $baru = $this->rencanaAksi->versions()->create(array_merge($this->versiPengajuan->only(['jadwal_snapshot_id', 'snapshot', 'dasar_izin_pengajuan', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan']), ['nomor' => 2]));
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review?versi={$this->versiPengajuan->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('rencanaAksi.versi_pengajuan.nomor', 1)->where('rencanaAksi.can.verifikasi', false)->has('rencanaAksi.histori', 2));
        $this->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", $this->tokens())->assertConflict();
        $this->assertSame(2, $baru->nomor);
    }

    public function test_bukti_historis_tetap_privat_dan_terikat_versi(): void
    {
        Storage::fake('local');
        $bukti = $this->createBuktiDukung(['mode' => 'file', 'path' => 'ra/beku.pdf', 'nama_asli' => 'beku.pdf', 'tautan' => null]);
        Storage::disk('local')->put('ra/beku.pdf', 'PDF sintetis');
        $beku = $this->versiPengajuan->snapshot;
        $beku['bukti_dukungs'] = [['id' => $bukti->id, 'mode' => 'file', 'nama_asli' => 'beku.pdf', 'path' => 'rahasia/internal']];
        $versi = $this->rencanaAksi->versions()->create(array_merge($this->versiPengajuan->only(['jadwal_snapshot_id', 'dasar_izin_pengajuan', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan']), ['nomor' => 2, 'snapshot' => $beku]));
        $url = route('rencana-aksi.versi.bukti.download', [$this->rencanaAksi->id, $versi->id, $bukti->id]);
        $this->actingAs($this->reviewer)->get($url)->assertOk()->assertDownload('beku.pdf');
        $pengganti = $this->createBuktiDukung(['menggantikan_id' => $bukti->id]);
        $this->get($url)->assertNotFound();
        $pengganti->update(['dihapus_pada' => now(), 'dihapus_oleh' => $this->actor->id]);
        $this->get($url)->assertOk();
        $bukti->update(['dihapus_pada' => now(), 'dihapus_oleh' => $this->actor->id]);
        $this->get($url)->assertNotFound();
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertInertia(fn (Assert $p) => $p
            ->has('rencanaAksi.bukti_dukungs', 1)->where('rencanaAksi.bukti_dukungs.0.download_url', null)->missing('rencanaAksi.bukti_dukungs.0.path'));
        $this->get(route('rencana-aksi.versi.bukti.download', [$this->rencanaAksi->id, $this->versiPengajuan->id, $bukti->id]))->assertNotFound();
        $this->denyPermission($this->reviewer, 'berkas:read', $this->unit->id);
        $this->get($url)->assertForbidden();
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertInertia(fn (Assert $p) => $p->has('rencanaAksi.bukti_dukungs', 0));
    }

    public function test_scope_baca_dan_status_draft_tidak_membuka_review_mutasi(): void
    {
        $this->rencanaAksi->update(['status_alur' => 'draft']);
        $this->actingAs($this->reviewer)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", $this->tokens())->assertUnprocessable();
        $this->denyPermission($this->reviewer, 'rencana_aksi:read', $this->unit->id);
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertForbidden();
        $this->get('/rencana-aksi/review?status=semua')->assertInertia(fn (Assert $p) => $p->has('daftar', 0));
    }

    public function test_pengembalian_perencanaan_sendiri_bukan_self_approval(): void
    {
        $versi = $this->rencanaAksi->versions()->create(array_merge($this->versiPengajuan->only(['jadwal_snapshot_id', 'snapshot', 'dasar_izin_pengajuan']), [
            'nomor' => 2, 'diajukan_by' => $this->reviewer->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan',
        ]));
        app(KembalikanRencanaAksi::class)->handle($this->reviewer, $this->rencanaAksi->id, ['expected_versi' => 1, 'versi_pengajuan_id' => $versi->id, 'alasan' => 'Revisi.']);
        $audit = AuditLog::where('tindakan', 'rencana_aksi.kembalikan')->sole();
        $this->assertFalse($audit->nilai_lama['self_approval']);
        $this->assertFalse($audit->nilai_baru['self_approval']);
    }

    public function test_snapshot_tidak_lengkap_gagal_tertutup_dan_aman_dirender(): void
    {
        $beku = $this->versiPengajuan->snapshot;
        $beku['target_periode'] = [['nilai' => null]];
        $beku['bukti_dukungs'] = [['mode' => 'rusak']];
        $versi = $this->rencanaAksi->versions()->create(array_merge($this->versiPengajuan->only(['jadwal_snapshot_id', 'dasar_izin_pengajuan', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan']), ['nomor' => 2, 'snapshot' => $beku]));
        $this->actingAs($this->reviewer)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('rencanaAksi.can.verifikasi', false)->has('rencanaAksi.bukti_dukungs', 0)->where('rencanaAksi.target_periode.0.nilai', null));
        $this->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", ['expected_versi' => 1, 'versi_pengajuan_id' => $versi->id])->assertUnprocessable();
    }

    public function test_promosi_role_dan_pergantian_pj_tidak_mengubah_f1_pengaju_pic(): void
    {
        $pic = $this->createPicPegawai();
        $beku = $this->versiPengajuan->snapshot;
        $beku['pic'] = ['id' => $pic->id, 'nama' => 'PIC saat pengajuan'];
        $versi = $this->rencanaAksi->versions()->create(array_merge($this->versiPengajuan->only(['jadwal_snapshot_id', 'dasar_izin_pengajuan', 'diajukan_at']), [
            'nomor' => 2, 'snapshot' => $beku, 'diajukan_by' => $pic->id, 'jalur_pengajuan' => 'pic',
        ]));
        $pic->roles()->sync([Role::where('kode', 'perencanaan')->value('id') => [
            'id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now(),
        ]]);
        $this->createPicPegawai();
        $tokens = ['expected_versi' => 1, 'versi_pengajuan_id' => $versi->id];
        $this->actingAs($pic)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", $tokens)->assertUnprocessable();
        $this->assertSame($pic->id, $versi->fresh()->diajukan_by);
        // Pembuat draf berbeda dari pengaju; F1 tidak mengambil created_by.
        $this->actingAs($this->actor)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/verifikasi", $tokens)->assertSuccessful();
    }

    public function test_antrean_dan_detail_menolak_record_dengan_snapshot_unit_tidak_selaras(): void
    {
        $unitLain = Unit::create(['nama' => 'Unit Lain', 'created_by' => $this->actor->id]);
        $snapshotLain = JadwalSnapshot::create(array_merge($this->snapshot->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'nama', 'definisi', 'satuan',
            'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']), ['unit_id' => $unitLain->id, 'nomor_versi' => 2]));
        $this->rencanaAksi->versions()->create(array_merge($this->versiPengajuan->only(['snapshot', 'dasar_izin_pengajuan', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan']),
            ['nomor' => 2, 'jadwal_snapshot_id' => $snapshotLain->id]));

        // Selaras dengan RencanaAksiPolicy::view: versi terbaru yang merujuk snapshot unit lain tidak dapat dibuka.
        $this->actingAs($this->reviewer)->get('/rencana-aksi/review?status=semua')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('daftar', 0)->where('pagination.total', 0));
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertForbidden();
    }

    public function test_versi_historis_dengan_unit_snapshot_tidak_selaras_ditolak_pada_review_dan_unduhan_bukti(): void
    {
        Storage::fake('local');
        $bukti = $this->createBuktiDukung(['mode' => 'file', 'path' => 'ra/beku.pdf', 'nama_asli' => 'beku.pdf', 'tautan' => null]);
        Storage::disk('local')->put('ra/beku.pdf', 'PDF sintetis');
        $unitLain = Unit::create(['nama' => 'Unit Rahasia B', 'created_by' => $this->actor->id]);
        $snapshotLain = JadwalSnapshot::create(array_merge($this->snapshot->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'nama', 'definisi', 'satuan',
            'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']), ['unit_id' => $unitLain->id, 'nomor_versi' => 2]));
        $beku = $this->versiPengajuan->snapshot;
        $beku['bukti_dukungs'] = [['id' => $bukti->id, 'mode' => 'file', 'nama_asli' => 'beku.pdf', 'path' => 'rahasia/internal']];
        $bekuLain = array_merge($beku, ['unit_kerja' => ['id' => $unitLain->id, 'nama' => 'Unit Rahasia B']]);
        $kolom = $this->versiPengajuan->only(['dasar_izin_pengajuan', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan']);
        $selaras = $this->rencanaAksi->versions()->create($kolom + ['nomor' => 2, 'jadwal_snapshot_id' => $this->snapshot->id, 'snapshot' => $beku]);
        $tidakSelaras = $this->rencanaAksi->versions()->create($kolom + ['nomor' => 3, 'jadwal_snapshot_id' => $snapshotLain->id, 'snapshot' => $bekuLain]);
        $this->rencanaAksi->versions()->create($kolom + ['nomor' => 4, 'jadwal_snapshot_id' => $this->snapshot->id, 'snapshot' => $beku]);
        Log::spy();

        // Kontrol positif: versi historis yang unit snapshotnya selaras tetap terbaca dan buktinya tetap terunduh.
        $unduhSelaras = route('rencana-aksi.versi.bukti.download', [$this->rencanaAksi->id, $selaras->id, $bukti->id]);
        $this->actingAs($this->reviewer)->get("/rencana-aksi/{$this->rencanaAksi->id}/review?versi={$selaras->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('rencanaAksi.versi_pengajuan.nomor', 2)->where('rencanaAksi.bukti_dukungs.0.download_url', $unduhSelaras));
        $this->get($unduhSelaras)->assertOk()->assertDownload('beku.pdf');

        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review?versi={$tidakSelaras->id}")->assertForbidden()->assertDontSee('Unit Rahasia B');
        $this->get(route('rencana-aksi.versi.bukti.download', [$this->rencanaAksi->id, $tidakSelaras->id, $bukti->id]))->assertForbidden();
        Log::shouldHaveReceived('warning')->twice()->withArgs(fn (string $pesan, array $konteks): bool => $pesan === 'rencana_aksi.unit_snapshot_tidak_konsisten'
            && $konteks['rencana_aksi_versi_id'] === $tidakSelaras->id && $konteks['snapshot_unit_id'] === $unitLain->id);
    }
}
