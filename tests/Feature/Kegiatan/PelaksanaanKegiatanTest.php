<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Kegiatan\TotalAnggaranKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Aksi pelaksanaan kegiatan (ISS-06.03): data realisasi bertahap, gerbang bukti gabungan tahap `kegiatan`
 * saat terlaksana (PRD §15.6, Q22), tanggal realisasi dalam rentang periode (Q70 §70.1, Q74 §74.2),
 * biaya terpakai (Q74 §74.2), dan bukti pelaksanaan append-only setelah terlaksana (PRD §18.8).
 */
class PelaksanaanKegiatanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RencanaAksi $header;

    private Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->buatFixtureManual();
        // Draf membuat "Kegiatan pendukung" Triwulan I 2026 (1 Januari–31 Maret) yang diklaim tahap RA.
        $this->header = $this->buatDraf($this->fixture);
        $this->grant($this->fixture['pic'], 'kegiatan:update', $this->fixture['unit']->id, $this->fixture['perencanaan']);
        $this->kegiatan = Kegiatan::where('nama', 'Kegiatan pendukung')->sole();
        $this->kegiatan->update(['anggaran' => 1000000]);
    }

    public function test_gerbang_terlaksana_memakai_union_persyaratan_global_dan_indikator_yang_diklaim(): void
    {
        // Persyaratan global ditetapkan Perencanaan lewat Persyaratan Berkas (PRD §15.6 butir 3).
        $this->actingAs($this->fixture['perencanaan'])->post('/jenis-berkas', ['nama' => 'Daftar hadir', 'tahap' => 'kegiatan', 'wajib' => true,
            'izinkan_teks' => true])->assertSessionHasNoErrors();
        $global = JenisBerkas::where('nama', 'Daftar hadir')->sole();
        $khusus = $this->persyaratan(['nama' => 'Dokumentasi', 'indikator_id' => $this->fixture['indikator']->id]);
        // Persyaratan indikator yang tidak diklaim dan persyaratan tahap perencanaan tidak ikut gerbang ini.
        $lain = $this->fixture['indikator']->replicate();
        $lain->kode = 'I-UJI-LAIN';
        $lain->save();
        $this->persyaratan(['nama' => 'Surat tugas', 'indikator_id' => $lain->id]);
        $this->persyaratan(['nama' => 'RAB', 'indikator_id' => null, 'tahap' => 'kegiatan_perencanaan']);

        $this->realisasi(['tandai_terlaksana' => true])->assertStatus(422)->assertJsonValidationErrors('bukti');
        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $tolak = AuditLog::where('tindakan', 'kegiatan.terlaksana_ditolak')->sole();
        $this->assertEqualsCanonicalizing(['Daftar hadir', 'Dokumentasi'], $tolak->nilai_baru['persyaratan_kurang']);

        $this->bukti($global)->assertSessionHasNoErrors();
        $this->realisasi(['tandai_terlaksana' => true])->assertStatus(422)->assertJsonValidationErrors('bukti');
        $this->assertContains(['Dokumentasi'], AuditLog::where('tindakan', 'kegiatan.terlaksana_ditolak')->get()->pluck('nilai_baru.persyaratan_kurang')->all());

        $this->bukti($khusus)->assertSessionHasNoErrors();
        $this->realisasi(['tandai_terlaksana' => true])->assertRedirect();
        $this->assertSame('terlaksana', $this->kegiatan->fresh()->status);
    }

    public function test_terlaksana_dengan_isian_lengkap_tanpa_narasi_dan_tercatat_di_audit(): void
    {
        $this->realisasi(['tandai_terlaksana' => true])->assertRedirect();

        $kegiatan = $this->kegiatan->fresh();
        $this->assertSame(['terlaksana', '2026-03-15', '750000', 25, null, null, null],
            [$kegiatan->status, $kegiatan->tanggal_realisasi->format('Y-m-d'), $kegiatan->biaya_terpakai, $kegiatan->realisasi_peserta,
                $kegiatan->uraian_pelaksanaan, $kegiatan->kendala, $kegiatan->strategi_tindaklanjut]);
        $catat = AuditLog::where('tindakan', 'kegiatan.catat_realisasi')->sole();
        $this->assertNull($catat->nilai_lama['tanggal_realisasi']);
        $this->assertSame(['2026-03-15', '750000', 25], [$catat->nilai_baru['tanggal_realisasi'], (string) $catat->nilai_baru['biaya_terpakai'], $catat->nilai_baru['realisasi_peserta']]);
        $status = AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole();
        $this->assertSame([['status' => 'rencana', 'justifikasi' => null], ['status' => 'terlaksana']], [$status->nilai_lama, $status->nilai_baru]);
        $this->assertSame('kegiatan:update', $status->dasar_izin['permission']);
    }

    public function test_isian_wajib_terlaksana_dan_biaya_nonnegatif(): void
    {
        foreach (['tanggal_realisasi', 'biaya_terpakai', 'realisasi_peserta'] as $field) {
            $this->realisasi(['tandai_terlaksana' => true, $field => null])->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $this->realisasi(['biaya_terpakai' => -1])->assertStatus(422)->assertJsonValidationErrors('biaya_terpakai');
        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $this->assertSame(4, AuditLog::where('tindakan', 'kegiatan.catat_realisasi_ditolak')->count());

        // `0` sah dan biaya di atas anggaran rencana tidak ditolak (Q74 §74.2 butir 2 dan 5).
        $this->realisasi(['biaya_terpakai' => 0, 'tandai_terlaksana' => true])->assertRedirect();
        $this->realisasi(['biaya_terpakai' => 1500000])->assertRedirect();
        $this->assertSame('1500000', $this->kegiatan->fresh()->biaya_terpakai);
        $this->assertContains(['0', '1500000'], AuditLog::where('tindakan', 'kegiatan.catat_realisasi')->get()
            ->map(fn (AuditLog $audit): array => [(string) $audit->nilai_lama['biaya_terpakai'], (string) $audit->nilai_baru['biaya_terpakai']])->all());

        // Setelah terlaksana nilai dapat diubah, tetapi tidak dikosongkan.
        $this->realisasi(['biaya_terpakai' => null])->assertStatus(422)->assertJsonValidationErrors('biaya_terpakai');
        $this->assertSame('terlaksana', $this->kegiatan->fresh()->status);
    }

    public function test_tanggal_realisasi_wajib_di_dalam_rentang_periode_pada_tahun_kegiatan(): void
    {
        foreach (['2025-12-31', '2026-04-01', '2027-02-01'] as $tanggal) {
            $this->realisasi(['tanggal_realisasi' => $tanggal])->assertStatus(422)->assertJsonValidationErrors('tanggal_realisasi');
        }
        foreach (['2026-01-01', '2026-03-31'] as $tanggal) {
            $this->realisasi(['tanggal_realisasi' => $tanggal])->assertRedirect();
            $this->assertSame($tanggal, $this->kegiatan->fresh()->tanggal_realisasi->format('Y-m-d'));
        }

        // Rentang yang tidak diketahui tidak dapat dibuktikan, sehingga ditolak.
        $this->fixture['periode1']->forceFill(['bulan_mulai' => null, 'hari_mulai' => null, 'bulan_selesai' => null, 'hari_selesai' => null])->save();
        $this->realisasi()->assertStatus(422)->assertJsonValidationErrors(['tanggal_realisasi' => 'belum diatur di Master Periode']);
    }

    public function test_realisasi_diperbarui_berulang_pada_kegiatan_yang_sama_dan_tercatat_di_audit(): void
    {
        $this->realisasi(['tanggal_realisasi' => '2026-01-20', 'biaya_terpakai' => null, 'realisasi_peserta' => 10])->assertRedirect();
        $this->realisasi(['tanggal_realisasi' => '2026-02-20', 'biaya_terpakai' => null, 'realisasi_peserta' => 18, 'uraian_pelaksanaan' => 'Sesi kedua'])->assertRedirect();

        $this->assertSame(1, Kegiatan::count());
        $kegiatan = $this->kegiatan->fresh();
        $this->assertSame(['rencana', '2026-02-20', 18, 'Sesi kedua'], [$kegiatan->status, $kegiatan->tanggal_realisasi->format('Y-m-d'), $kegiatan->realisasi_peserta, $kegiatan->uraian_pelaksanaan]);
        $audit = AuditLog::where('tindakan', 'kegiatan.catat_realisasi')->get()
            ->map(fn (AuditLog $audit): array => [$audit->nilai_lama['realisasi_peserta'], $audit->nilai_baru['realisasi_peserta']]);
        $this->assertEqualsCanonicalizing([[null, 10], [10, 18]], $audit->all());
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.ubah_status')->exists());
    }

    public function test_tidak_terlaksana_dapat_menjadi_terlaksana_tetapi_status_final_menolak_realisasi(): void
    {
        $this->kegiatan->forceFill(['status' => 'tidak_terlaksana', 'justifikasi' => 'Hujan'])->save();
        $this->realisasi(['tandai_terlaksana' => true])->assertRedirect();
        $this->assertSame('terlaksana', $this->kegiatan->fresh()->status);
        // Justifikasi status sebelumnya ikut tercatat pada audit transisi (Q69 §69.2 butir 3).
        $this->assertSame(['status' => 'tidak_terlaksana', 'justifikasi' => 'Hujan'], AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole()->nilai_lama);

        foreach (['ditunda', 'batal'] as $status) {
            $this->kegiatan->forceFill(['status' => $status])->save();
            $this->realisasi()->assertStatus(422)->assertJsonValidationErrors('status');
        }
    }

    public function test_tanpa_izin_ubah_kegiatan_ditolak_dan_data_basi_409(): void
    {
        $this->realisasi(['expected_state' => str_repeat('0', 64)])->assertStatus(409);
        UserPermissionDeny::create(['user_id' => $this->fixture['pic']->id, 'permission_id' => Permission::where('kode', 'kegiatan:update')->value('id'),
            'unit_id' => $this->fixture['unit']->id, 'alasan' => 'Pembatasan pengujian', 'ditetapkan_oleh' => $this->fixture['perencanaan']->id]);
        $this->realisasi()->assertForbidden();
        $this->assertNull($this->kegiatan->fresh()->tanggal_realisasi);
        $this->assertSame(2, AuditLog::where('tindakan', 'kegiatan.catat_realisasi_ditolak')->count());
    }

    public function test_tahun_ditutup_unit_nonaktif_dan_akun_nonaktif_ditolak_serta_diaudit(): void
    {
        $this->fixture['jadwal']->update(['status' => 'ditutup', 'closed_at' => now()]);
        $this->realisasi()->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Tahun kegiatan sudah ditutup']);
        $this->fixture['jadwal']->update(['status' => 'aktif', 'closed_at' => null]);

        $this->fixture['unit']->update(['status' => 'nonaktif']);
        $this->realisasi()->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Unit kegiatan harus tetap aktif.']);
        $this->fixture['unit']->update(['status' => 'aktif']);

        $this->fixture['pic']->update(['status' => 'nonaktif']);
        $this->actingAs($this->fixture['pic'])->withHeader('X-Inertia', 'true')->post("/kegiatan/{$this->kegiatan->id}/realisasi", [
            'tanggal_realisasi' => '2026-03-15', 'expected_state' => $this->kegiatan->fresh()->versionToken(),
        ])->assertForbidden();

        $this->assertNull($this->kegiatan->fresh()->tanggal_realisasi);
        $alasan = AuditLog::where('tindakan', 'kegiatan.catat_realisasi_ditolak')->pluck('alasan')->all();
        $this->assertCount(3, $alasan);
        $this->assertContains('Akun tidak aktif saat mencatat realisasi kegiatan.', $alasan);
        $this->assertSame('inactive_user', AuditLog::where('alasan', 'Akun tidak aktif saat mencatat realisasi kegiatan.')->sole()->dasar_izin['alasan']);
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->exists());
    }

    public function test_periode_lampau_2026_mengecualikan_gerbang_bukti_hanya_pada_jalur_perencanaan(): void
    {
        $this->persyaratan(['nama' => 'Daftar hadir', 'indikator_id' => null]);
        // TW I lampau: jendela pengisian (s.d. 31 Maret) berakhir sebelum aktivasi jadwal 2026 (Q46 §46.1).
        $this->fixture['jadwal']->forceFill(['activated_at' => '2026-04-15 00:00:00'])->save();

        // PIC tidak pernah dikecualikan (Q63 §63.1 butir 2).
        $this->realisasi(['tandai_terlaksana' => true])->assertUnprocessable()->assertJsonValidationErrors('bukti');
        $this->assertSame('rencana', $this->kegiatan->fresh()->status);

        $this->realisasi(['tandai_terlaksana' => true], $this->fixture['perencanaan'])->assertRedirect();
        $this->assertSame('terlaksana', $this->kegiatan->fresh()->status);
        $this->assertSame('periode_lampau', AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole()->nilai_baru['pengecualian_bukti']);
    }

    public function test_perencanaan_pada_periode_bukan_lampau_tetap_melewati_gerbang_bukti(): void
    {
        $this->persyaratan(['nama' => 'Daftar hadir', 'indikator_id' => null]);
        // Aktivasi sebelum jendela TW I berakhir: periode normal.
        $this->fixture['jadwal']->forceFill(['activated_at' => '2026-03-15 00:00:00'])->save();

        $this->realisasi(['tandai_terlaksana' => true], $this->fixture['perencanaan'])->assertUnprocessable()->assertJsonValidationErrors('bukti');
        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
    }

    public function test_klaim_baru_setelah_terlaksana_menandai_kebutuhan_koreksi(): void
    {
        $this->realisasi(['tandai_terlaksana' => true])->assertRedirect();
        $this->form()->assertInertia(fn ($page) => $page->where('pelaksanaan.perlu_koreksi', false)->where('pelaksanaan.persyaratan_kurang', []));

        // Klaim RA indikator lain menambah persyaratan khusus indikator itu; klaim tetap diterima (Q69 §69.3 butir 3).
        $lain = $this->fixture['indikator']->replicate();
        $lain->kode = 'I-UJI-LAIN';
        $lain->save();
        $tambahan = $this->persyaratan(['nama' => 'Surat tugas', 'indikator_id' => $lain->id]);
        $headerLain = RencanaAksi::create(['indikator_id' => $lain->id, 'tahun' => 2026, 'unit_id' => $this->fixture['unit']->id,
            'jadwal_tahunan_id' => $this->fixture['jadwal']->id, 'penanggung_jawab_id' => $this->fixture['pic']->id,
            'status_alur' => RencanaAksi::STATUS_DRAFT, 'versi' => 1, 'created_by' => $this->fixture['pic']->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $headerLain->id, 'kegiatan_id' => $this->kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);

        $this->form()->assertInertia(fn ($page) => $page->where('pelaksanaan.perlu_koreksi', true)->where('pelaksanaan.persyaratan_kurang', ['Surat tugas']));

        // Koreksi append-only: bukti tambahan tetap dapat diunggah pada kegiatan terlaksana.
        $this->bukti($tambahan)->assertSessionHasNoErrors();
        $this->form()->assertInertia(fn ($page) => $page->where('pelaksanaan.perlu_koreksi', false));
    }

    public function test_bukti_pelaksanaan_append_only_setelah_terlaksana_dan_tidak_terkunci_ra_diajukan_sebelumnya(): void
    {
        $persyaratan = $this->persyaratan(['nama' => 'Dokumentasi', 'indikator_id' => null]);
        $this->bukti($persyaratan)->assertSessionHasNoErrors();
        $pertama = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();

        // Kunci "RA pengklaim diajukan" hanya untuk berkas tahap perencanaan (Q42 §42.4 butir 5).
        $this->header->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);
        $this->bukti($persyaratan, ['menggantikan_id' => $pertama->id, 'alasan_koreksi' => 'Foto keliru'])->assertSessionHasNoErrors();
        $pengganti = BuktiDukung::where('menggantikan_id', $pertama->id)->sole();
        $this->hapusBukti($pengganti)->assertSessionHasNoErrors();
        $this->bukti($persyaratan)->assertSessionHasNoErrors();
        $berlaku = BuktiDukung::where('berkasable_type', 'kegiatan')->whereNull('dihapus_pada')->whereNull('menggantikan_id')->whereKeyNot($pertama->id)->sole();
        $this->realisasi(['tandai_terlaksana' => true])->assertRedirect();

        $this->hapusBukti($berlaku)->assertSessionHasErrors('berkas');
        $this->bukti($persyaratan, ['menggantikan_id' => $berlaku->id, 'alasan_koreksi' => 'Revisi'])->assertSessionHasErrors('berkas');
        $this->assertNull($berlaku->fresh()->dihapus_pada);
        $this->bukti($persyaratan)->assertSessionHasNoErrors();
    }

    public function test_total_anggaran_tetap_dari_anggaran_rencana_setelah_biaya_terpakai_dicatat(): void
    {
        $this->realisasi(['biaya_terpakai' => 4000000, 'tandai_terlaksana' => true])->assertRedirect();

        $total = app(TotalAnggaranKegiatan::class);
        $this->assertSame('1000000', $total->perRencanaAksi($this->header)['total']);
        $this->assertSame('1000000', $total->perRencanaAksi($this->header)['per_periode'][$this->fixture['periode1']->id]['total']);
        $this->assertSame('1000000', $total->perIku($this->fixture['indikator']->id, 2026)['total']);
    }

    /** @param array<string, mixed> $data */
    private function realisasi(array $data = [], ?User $aktor = null): TestResponse
    {
        return $this->actingAs($aktor ?? $this->fixture['pic'])->postJson("/kegiatan/{$this->kegiatan->id}/realisasi", [
            'tanggal_realisasi' => '2026-03-15', 'biaya_terpakai' => 750000, 'realisasi_peserta' => 25,
            'expected_state' => $this->kegiatan->fresh()->versionToken(), ...$data,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function bukti(JenisBerkas $persyaratan, array $data = []): TestResponse
    {
        return $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas",
            ['mode' => 'teks', 'jenis_berkas_id' => $persyaratan->id, 'isi_teks' => 'Notulen rapat pelaksanaan', ...$data]);
    }

    private function hapusBukti(BuktiDukung $berkas): TestResponse
    {
        return $this->actingAs($this->fixture['pic'])->delete("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}", ['alasan' => 'Salah unggah']);
    }

    private function form(): TestResponse
    {
        return $this->actingAs($this->fixture['pic'])->get("/kegiatan/{$this->kegiatan->id}/edit")->assertOk();
    }

    /** @param array<string, mixed> $atribut */
    private function persyaratan(array $atribut): JenisBerkas
    {
        return $this->buatPersyaratanWajib($this->fixture, ['tahap' => 'kegiatan', 'izinkan_teks' => true, ...$atribut]);
    }
}
