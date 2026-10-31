<?php

namespace Tests\Feature\Pengukuran;

use App\Actions\StatusCapaian\TetapkanStatusCapaian;
use App\Models\AuditLog;
use App\Models\KinerjaSnapshot;
use App\Models\Permission;
use App\Models\StatusCapaian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Transisi `disahkan → dikembalikan` pada pengukuran: alasan wajib, deny menang,
 * tahun tertutup hanya boleh dibuka di dalam sesi koreksi yang mencakup pengukuran,
 * dan versi resmi beserta status capaiannya tidak ikut dihapus.
 */
class BukaKembaliPengukuranTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    private const ALASAN = 'Koreksi resmi nilai TW I.';

    private const PESAN_TUTUP = 'Tahun sudah ditutup; buka kembali jadwal tahunan (jadwal:buka_kembali) lalu ulangi di dalam sesi koreksi yang mencakup pengukuran ini.';

    private const PESAN_TOKEN = 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.';

    /** Versi resmi pertama dengan payload manual lengkap; header ikut berstatus disahkan. */
    private function versiResmi(): KinerjaSnapshot
    {
        $versi = $this->sahkan($this->pengukuran, [
            'nilai' => '80', 'status_perhitungan' => 'terhitung', 'sumber_nilai' => 'manual', 'catatan' => null,
            'alasan_tidak_dapat_dihitung' => null,
            'progres_kegiatan' => 'Progres resmi.', 'kendala_masalah' => 'Kendala resmi.',
            'strategi_tindak_lanjut' => 'Strategi resmi.', 'tindak_lanjut_rekomendasi' => null,
            'indikator' => [...$this->context->only(['nama', 'definisi', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']),
                'id' => $this->pengukuran->indikator_id, 'kode' => 'I-UJI'],
            'unit_kerja' => $this->unit->only(['id', 'nama']),
            'pic' => $this->actor->only(['id', 'nama']),
            'periode' => $this->pengukuran->periode->only(['id', 'nama', 'urutan', 'is_nilai_akhir']),
            'target' => 70, 'target_pk' => 70, 'tanpa_target_periode' => false, 'rekomendasi_sebelumnya' => null,
            'komponen' => [], 'klaim' => [], 'persyaratan_bukti' => [], 'bukti_dukungs' => [],
        ]);
        $this->pengukuran->forceFill(['nilai' => '80', 'status_perhitungan' => 'terhitung'])->save();

        return $versi;
    }

    /** Token konkurensi dibaca dari basis data, bukan instance fixture yang belum memuat kolom default. */
    private function token(): int
    {
        return (int) DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->value('versi');
    }

    private function tetapkanLangsung(KinerjaSnapshot $versi, string $status = StatusCapaian::STATUS_TERCAPAI): StatusCapaian
    {
        return StatusCapaian::create([
            'pengukuran_id' => $this->pengukuran->id, 'pengukuran_versi_id' => $versi->id,
            'status' => $status, 'sumber' => StatusCapaian::SUMBER_MANUAL,
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
    }

    private function tetapkanLewatAksi(KinerjaSnapshot $versi, string $status = StatusCapaian::STATUS_TERCAPAI): StatusCapaian
    {
        return app(TetapkanStatusCapaian::class)->handle($this->actor, $this->pengukuran->id, [
            'pengukuran_versi_id' => $versi->id, 'status' => $status, 'versi' => $this->token(),
        ]);
    }

    private function kirim(User $aktor, array $data): TestResponse
    {
        return $this->actingAs($aktor)->post('/verifikasi/'.$this->pengukuran->id.'/buka-kembali', $data);
    }

    private function kirimJson(User $aktor, array $data): TestResponse
    {
        return $this->actingAs($aktor)->postJson('/verifikasi/'.$this->pengukuran->id.'/buka-kembali', $data);
    }

    public function test_buka_kembali_alasan_sah_memindahkan_header_dan_mencatat_audit_histori(): void
    {
        $versi = $this->versiResmi();
        $status = $this->tetapkanLangsung($versi);
        $token = $this->token();

        $this->kirim($this->actor, ['versi' => $token, 'alasan' => '  '.self::ALASAN.'  '])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $header = $this->pengukuran->fresh();
        $this->assertSame('dikembalikan', $header->status_alur);
        $this->assertSame($token + 1, $header->versi);

        $riwayat = DB::table('riwayat_pengukurans')->where('pengukuran_kinerja_id', $this->pengukuran->id)->sole();
        $this->assertSame('disahkan', $riwayat->status_dari);
        $this->assertSame('dikembalikan', $riwayat->status_ke);
        $this->assertSame(self::ALASAN, $riwayat->catatan);
        $this->assertSame($this->actor->id, $riwayat->user_id);

        $audit = AuditLog::where('tindakan', 'pengukuran.buka_kembali')->sole();
        $this->assertSame(self::ALASAN, $audit->alasan);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame('pengukuran', $audit->objek_tipe);
        $this->assertSame($this->pengukuran->id, $audit->objek_id);
        $this->assertSame('pengukuran:buka_kembali', $audit->dasar_izin['permission']);
        $this->assertSame('allow', $audit->dasar_izin['reason']);
        $this->assertSame(['id' => $versi->id, 'nomor' => 1], $audit->nilai_baru['versi_resmi_dibuka']);
        $this->assertSame($status->id, $audit->nilai_baru['status_capaian_saat_dibuka']['id']);
        $this->assertSame(StatusCapaian::STATUS_TERCAPAI, $audit->nilai_baru['status_capaian_saat_dibuka']['status']);
        $this->assertSame(StatusCapaian::SUMBER_MANUAL, $audit->nilai_baru['status_capaian_saat_dibuka']['sumber']);
        $this->assertSame($this->actor->id, $audit->nilai_baru['status_capaian_saat_dibuka']['penetap']);
        $this->assertFalse($audit->nilai_baru['self_approval']);
        $this->assertSame('disahkan', $audit->nilai_lama['status_alur']);
        $this->assertSame($token, $audit->nilai_lama['versi']);
        $this->assertSame(['id' => $versi->id, 'nomor' => 1], $audit->nilai_lama['versi_resmi']);
        $this->assertSame($status->id, $audit->nilai_lama['status_capaian_berlaku']['id']);
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_alasan_wajib_ditolak_422_tanpa_mutasi_dan_setiap_penolakan_diaudit(): void
    {
        $this->versiResmi();
        $token = $this->token();

        foreach ([
            'tanpa alasan' => ['versi' => $token],
            'string kosong' => ['versi' => $token, 'alasan' => ''],
            'spasi saja' => ['versi' => $token, 'alasan' => '   '],
            'karakter kontrol' => ['versi' => $token, 'alasan' => "Rusak\x01teks"],
        ] as $kasus => $payload) {
            $this->kirimJson($this->actor, $payload)->assertUnprocessable()->assertJsonValidationErrors('alasan');
            $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur, $kasus);
        }

        $this->assertSame($token, $this->token());
        $this->assertSame(0, DB::table('riwayat_pengukurans')->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.buka_kembali')->count());
        $penolakan = AuditLog::where('tindakan', 'pengukuran.ditolak')->get();
        $this->assertCount(4, $penolakan);
        $this->assertSame(['buka_kembali'], $penolakan->pluck('nilai_baru.tindakan_diminta')->unique()->values()->all());
        $this->assertSame(['validasi_bisnis'], $penolakan->pluck('nilai_baru.jenis_penolakan')->unique()->values()->all());
        $this->assertTrue($penolakan->every(fn (AuditLog $log): bool => $log->dasar_izin !== null
            && $log->dasar_izin['permission'] === 'pengukuran:buka_kembali'));
        $this->assertNotEmpty($penolakan->first()->nilai_baru['alasan_penolakan']);
    }

    public function test_pegawai_tanpa_izin_ditolak_403_dengan_tepat_satu_audit_penolakan(): void
    {
        $this->versiResmi();
        $pegawai = $this->userWithRole('pegawai');
        $token = $this->token();

        $this->kirimJson($pegawai, ['versi' => $token, 'alasan' => self::ALASAN])->assertForbidden();

        $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($token, $this->token());
        $this->assertSame(0, DB::table('riwayat_pengukurans')->count());
        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame($pegawai->id, $audit->actor_id);
        $this->assertSame('otorisasi', $audit->nilai_baru['jenis_penolakan']);
        $this->assertSame('buka_kembali', $audit->nilai_baru['tindakan_diminta']);
        $this->assertSame('pengukuran:buka_kembali', $audit->dasar_izin['permission']);
        $this->assertSame('no_allow', $audit->dasar_izin['reason']);
        $this->assertSame([], $audit->dasar_izin['denies']);
    }

    public function test_deny_unit_menolak_perencanaan_dengan_tepat_satu_audit_penolakan(): void
    {
        $this->versiResmi();
        $perencanaan = $this->userWithRole('perencanaan');
        $token = $this->token();
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $perencanaan->id,
            'permission_id' => Permission::where('kode', 'pengukuran:buka_kembali')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Penolakan sintetis', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $this->kirimJson($perencanaan, ['versi' => $token, 'alasan' => self::ALASAN])->assertForbidden();

        $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($token, $this->token());
        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame($perencanaan->id, $audit->actor_id);
        $this->assertSame('otorisasi', $audit->nilai_baru['jenis_penolakan']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['reason']);
        $this->assertNotEmpty($audit->dasar_izin['denies']);
    }

    public function test_token_versi_basi_ditolak_422_tanpa_mutasi(): void
    {
        $versi = $this->versiResmi();
        $this->tetapkanLewatAksi($versi);
        $token = $this->token();
        $this->assertGreaterThan(1, $token);

        $this->kirimJson($this->actor, ['versi' => $token - 1, 'alasan' => self::ALASAN])
            ->assertUnprocessable()->assertJsonPath('errors.versi', [self::PESAN_TOKEN]);

        $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($token, $this->token());
        $this->assertSame(0, DB::table('riwayat_pengukurans')->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.buka_kembali')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_jadwal_ditutup_tanpa_sesi_koreksi_ditolak_422(): void
    {
        $this->versiResmi();
        $this->jadwal->update(['status' => 'ditutup']);
        $token = $this->token();

        $this->kirimJson($this->actor, ['versi' => $token, 'alasan' => self::ALASAN])
            ->assertUnprocessable()->assertJsonPath('errors.versi', ['Jadwal tahunan harus aktif.']);

        $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($token, $this->token());
        $this->assertSame(0, DB::table('riwayat_pengukurans')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_tanggal_melewati_penutupan_tanpa_sesi_koreksi_ditolak_422_dengan_pesan_sesi_koreksi(): void
    {
        $this->versiResmi();
        $token = $this->token();
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        $this->kirimJson($this->actor, ['versi' => $token, 'alasan' => self::ALASAN])
            ->assertUnprocessable()->assertJsonPath('errors.versi', [self::PESAN_TUTUP]);

        $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($token, $this->token());
        $this->assertSame(0, DB::table('riwayat_pengukurans')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_sesi_koreksi_sah_menerima_buka_kembali_pascapenutupan(): void
    {
        $versi = $this->versiResmi();
        $perencanaan = $this->userWithRole('perencanaan');
        $token = $this->token();
        $this->jadwal->update(['koreksi_mulai' => '2027-01-01 00:00:00', 'koreksi_sampai' => '2027-01-31 23:59:59',
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->pengukuran->indikator_id],
                'periode_ids' => [$this->pengukuran->periode_id]]]);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        $this->kirim($perencanaan, ['versi' => $token, 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('dikembalikan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($token + 1, $this->token());
        $audit = AuditLog::where('tindakan', 'pengukuran.buka_kembali')->sole();
        $this->assertSame($perencanaan->id, $audit->actor_id);
        $this->assertSame(['id' => $versi->id, 'nomor' => 1], $audit->nilai_baru['versi_resmi_dibuka']);
        $this->assertNull($audit->nilai_baru['status_capaian_saat_dibuka']);
    }

    public function test_sesi_koreksi_tanpa_periode_atau_tanpa_pengukuran_ditolak_422(): void
    {
        $this->versiResmi();
        $token = $this->token();
        $dasar = ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->pengukuran->indikator_id],
            'periode_ids' => [$this->pengukuran->periode_id]];
        $this->jadwal->update(['koreksi_mulai' => '2027-01-01 00:00:00', 'koreksi_sampai' => '2027-01-31 23:59:59']);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        foreach ([
            'tanpa periode_ids' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->pengukuran->indikator_id]],
            'periode lain' => [...$dasar, 'periode_ids' => [(string) Str::uuid()]],
            'jenis objek rencana aksi' => [...$dasar, 'jenis_objek' => ['rencana_aksi']],
            'lingkup kosong' => null,
        ] as $kasus => $lingkup) {
            $this->jadwal->update(['lingkup_koreksi' => $lingkup]);
            $this->kirimJson($this->actor, ['versi' => $token, 'alasan' => self::ALASAN])
                ->assertUnprocessable()->assertJsonPath('errors.versi', [self::PESAN_TUTUP]);
            $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur, $kasus);
        }

        $this->assertSame($token, $this->token());
        $this->assertSame(0, DB::table('riwayat_pengukurans')->count());
        $this->assertSame(4, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_versi_resmi_dan_status_capaian_lama_tetap_utuh_setelah_buka_kembali(): void
    {
        $versi = $this->versiResmi();
        $status = $this->tetapkanLangsung($versi);
        $snapshotBeku = DB::table('pengukuran_versi')->where('id', $versi->id)->value('snapshot');

        $this->kirim($this->actor, ['versi' => $this->token(), 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('pengukuran_versi')->where('pengukuran_id', $this->pengukuran->id)->count());
        $this->assertSame($snapshotBeku, DB::table('pengukuran_versi')->where('id', $versi->id)->value('snapshot'));
        $baris = DB::table('status_capaian')->where('pengukuran_id', $this->pengukuran->id)->sole();
        $this->assertSame($status->id, $baris->id);
        $this->assertSame($versi->id, $baris->pengukuran_versi_id);
        $this->assertSame(StatusCapaian::STATUS_TERCAPAI, $baris->status);
        $this->assertSame(1, DB::table('status_capaian')->where('pengukuran_id', $this->pengukuran->id)->count());
    }

    public function test_pengajuan_ulang_membuat_versi_baru_tanpa_status_capaian(): void
    {
        $lama = $this->versiResmi();
        $this->tetapkanLangsung($lama);
        $this->kirim($this->actor, ['versi' => $this->token(), 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();
        $setelahBuka = $this->token();

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => $setelahBuka, 'action' => 'ajukan', 'nilai' => 75])
            ->assertRedirect('/pengukuran')->assertSessionHasNoErrors();

        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
        $baru = DB::table('pengukuran_versi')->where('pengukuran_id', $this->pengukuran->id)->where('nomor', 2)->sole();
        $this->assertNull($baru->disahkan_at);
        $this->assertNull($baru->disahkan_by);
        $this->assertSame(2, DB::table('pengukuran_versi')->where('pengukuran_id', $this->pengukuran->id)->count());
        $this->assertSame(0, DB::table('status_capaian')->where('pengukuran_versi_id', $baru->id)->count());
        $this->assertSame(1, DB::table('status_capaian')->where('pengukuran_versi_id', $lama->id)->count());
    }

    public function test_laporan_resmi_tetap_memakai_versi_disahkan_sebelumnya(): void
    {
        $lama = $this->versiResmi();
        $this->tetapkanLangsung($lama);
        $this->kirim($this->actor, ['versi' => $this->token(), 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => $this->token(), 'action' => 'ajukan', 'nilai' => 75])->assertRedirect('/pengukuran')->assertSessionHasNoErrors();
        $this->assertNull(DB::table('pengukuran_versi')->where('pengukuran_id', $this->pengukuran->id)->where('nomor', 2)->value('disahkan_at'));

        $this->actingAs($this->actor)->get('/laporan/rekap?'.http_build_query(['jadwal_id' => $this->jadwal->id]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->has('baris', 1)
            ->where('baris.0.versi.id', $lama->id)
            ->where('baris.0.versi.nomor', 1)
            ->where('baris.0.nilai', '80')
            ->where('baris.0.status_laporan', 'disahkan')
            ->where('baris.0.status_capaian', 'Tercapai'));
    }

    public function test_laporan_resmi_tetap_memakai_versi_1_tepat_setelah_buka_kembali(): void
    {
        $versi = $this->versiResmi();
        $this->tetapkanLangsung($versi);
        $snapshotBeku = DB::table('pengukuran_versi')->where('id', $versi->id)->value('snapshot');

        $this->kirim($this->actor, ['versi' => $this->token(), 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('dikembalikan', $this->pengukuran->fresh()->status_alur);
        $this->assertDatabaseCount('pengukuran_versi', 1);

        $this->actingAs($this->actor)->get('/laporan/rekap?'.http_build_query(['jadwal_id' => $this->jadwal->id]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->has('baris', 1)
            ->where('baris.0.versi.id', $versi->id)
            ->where('baris.0.versi.nomor', 1)
            ->where('baris.0.nilai', '80')
            ->where('baris.0.status_laporan', 'disahkan')
            ->where('baris.0.status_capaian', 'Tercapai')
            ->whereNot('baris.0.ditetapkan_pada', null));

        $this->assertDatabaseCount('pengukuran_versi', 1);
        $this->assertSame($snapshotBeku, DB::table('pengukuran_versi')->where('id', $versi->id)->value('snapshot'));
    }

    public function test_status_capaian_dulu_lalu_buka_kembali_tetap_menempel_di_versi_lama(): void
    {
        $versi = $this->versiResmi();
        $status = $this->tetapkanLewatAksi($versi);

        $this->kirim($this->actor, ['versi' => $this->token(), 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('dikembalikan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($versi->id, $this->pengukuran->fresh()->ratifiedVersion?->id);
        $this->assertSame(StatusCapaian::STATUS_TERCAPAI, $this->pengukuran->fresh()->statusCapaianBerlaku()?->status);
        $baris = DB::table('status_capaian')->where('pengukuran_id', $this->pengukuran->id)->sole();
        $this->assertSame($status->id, $baris->id);
        $this->assertSame($versi->id, $baris->pengukuran_versi_id);
        $audit = AuditLog::where('tindakan', 'pengukuran.buka_kembali')->sole();
        $this->assertSame($status->id, $audit->nilai_baru['status_capaian_saat_dibuka']['id']);
    }

    public function test_buka_kembali_dulu_lalu_status_capaian_ditolak(): void
    {
        $versi = $this->versiResmi();
        $this->kirim($this->actor, ['versi' => $this->token(), 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('dikembalikan', $this->pengukuran->fresh()->status_alur);

        $penolakan = null;
        try {
            $this->tetapkanLewatAksi($versi);
        } catch (ValidationException $exception) {
            $penolakan = $exception;
        }

        $this->assertInstanceOf(ValidationException::class, $penolakan);
        $this->assertSame(['Status capaian hanya dapat ditetapkan pada pengukuran berstatus Disahkan.'], $penolakan->errors()['status_capaian'] ?? []);
        $this->assertSame(0, DB::table('status_capaian')->where('pengukuran_id', $this->pengukuran->id)->count());
    }

    public function test_props_halaman_verifikasi_menyediakan_capaian_dan_histori_versi(): void
    {
        $versi = $this->versiResmi();
        $this->tetapkanLangsung($versi);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Verifikasi/Show')
            ->where('pengukuran.can.reopen', true)
            ->where('pengukuran.businessErrors.buka_kembali', [])
            ->has('pengukuran.histori_versi', 1)
            ->where('pengukuran.histori_versi.0.id', $versi->id)
            ->where('pengukuran.histori_versi.0.nomor', 1)
            ->where('pengukuran.histori_versi.0.jalur_pengajuan', 'perencanaan')
            ->where('pengukuran.histori_versi.0.resmi', true)
            ->whereNot('pengukuran.histori_versi.0.disahkan_at', null)
            ->where('pengukuran.histori_versi.0.status_capaian.status', StatusCapaian::STATUS_TERCAPAI)
            ->where('pengukuran.histori_versi.0.status_capaian.sumber', StatusCapaian::SUMBER_MANUAL)
            ->where('pengukuran.histori_versi.0.status_capaian.penetap.id', $this->actor->id));

        $this->kirim($perencanaan, ['versi' => $this->token(), 'alasan' => self::ALASAN])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($perencanaan)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.can.reopen', false)
            ->where('pengukuran.status', 'dikembalikan')
            ->where('pengukuran.businessErrors.buka_kembali', [])
            ->has('pengukuran.histori_versi', 1)
            ->where('pengukuran.histori_versi.0.resmi', true)
            ->where('pengukuran.histori_versi.0.status_capaian.status', StatusCapaian::STATUS_TERCAPAI));
    }
}
