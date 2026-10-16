<?php

namespace Tests\Feature\Rekomendasi;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RekomendasiPimpinan;
use App\Models\RenstraPk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class TetapkanRekomendasiTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_perencanaan_menyimpan_draf_tanpa_menunggu_pengesahan_pengukuran(): void
    {
        $this->assertSame('draft', $this->pengukuran->fresh()->status_alur);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->tetapkan($perencanaan, ['isi' => 'Perkuat monitoring bulanan.'])->assertSessionHasNoErrors();

        $draf = RekomendasiPimpinan::sole();
        $this->assertSame(['draf', $perencanaan->id, 1, 'Perkuat monitoring bulanan.'], [$draf->status, $draf->ditetapkan_oleh, $draf->versi, $draf->isi]);
        $this->assertNotNull($draf->created_at);
        $this->assertNull($draf->disetujui_oleh);
        $audit = AuditLog::where('tindakan', 'rekomendasi_pimpinan.tetapkan')->sole();
        $this->assertSame([$perencanaan->id, 'rekomendasi_pimpinan', $draf->id], [$audit->actor_id, $audit->objek_tipe, $audit->objek_id]);
        $this->assertNull($audit->nilai_lama);
        $this->assertSame('draf', $audit->nilai_baru['status']);
        $this->assertSame(['rekomendasi:tetapkan', 'diizinkan'], [$audit->dasar_izin['permission'], $audit->dasar_izin['keputusan']]);
    }

    public function test_pimpinan_menyetujui_draf_setelah_mengubah_isinya(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');
        $this->tetapkan($perencanaan, ['isi' => 'Draf awal.']);
        $pimpinan = $this->userWithRole('pimpinan');

        $this->tetapkan($pimpinan, ['status' => 'disetujui', 'isi' => 'Isi final Pimpinan.', 'versi_draf' => 1])->assertSessionHasNoErrors();

        $row = RekomendasiPimpinan::sole();
        $this->assertSame(['disetujui', 'Isi final Pimpinan.', $perencanaan->id, $pimpinan->id, 'pimpinan'],
            [$row->status, $row->isi, $row->ditetapkan_oleh, $row->disetujui_oleh, $row->disetujui_oleh_role]);
        $this->assertNotNull($row->disetujui_pada);
        $this->assertFalse($row->isCadangan());
        $audit = AuditLog::where('tindakan', 'rekomendasi_pimpinan.setujui')->sole();
        $this->assertSame(['Draf awal.', 'draf'], [$audit->nilai_lama['isi'], $audit->nilai_lama['status']]);
        $this->assertSame(['Isi final Pimpinan.', 'disetujui', 'pimpinan', false], [$audit->nilai_baru['isi'], $audit->nilai_baru['status'], $audit->nilai_baru['disetujui_oleh_role'], $audit->nilai_baru['persetujuan_cadangan']]);
        $this->assertSame('diizinkan', $audit->dasar_izin['keputusan']);
    }

    public function test_pimpinan_menulis_rekomendasi_langsung_disetujui_atau_sebagai_draf(): void
    {
        $pimpinan = $this->userWithRole('pimpinan');

        $this->tetapkan($pimpinan, ['status' => 'disetujui', 'isi' => 'Arahan langsung.'])->assertSessionHasNoErrors();
        $this->tetapkan($pimpinan, ['isi' => 'Draf Pimpinan.', 'berlaku_id' => RekomendasiPimpinan::sole()->id])->assertSessionHasNoErrors();

        $langsung = RekomendasiPimpinan::where('status', 'disetujui')->sole();
        $this->assertSame([$pimpinan->id, $pimpinan->id, 'pimpinan'], [$langsung->ditetapkan_oleh, $langsung->disetujui_oleh, $langsung->disetujui_oleh_role]);
        $this->assertNull(AuditLog::where('tindakan', 'rekomendasi_pimpinan.setujui')->sole()->nilai_lama);
        $this->assertSame($pimpinan->id, RekomendasiPimpinan::where('status', 'draf')->sole()->ditetapkan_oleh);
    }

    public function test_persetujuan_selain_pimpinan_diberi_penanda_cadangan(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');
        $this->tetapkan($perencanaan, ['isi' => 'Draf awal.']);

        $this->tetapkan($perencanaan, ['status' => 'disetujui', 'isi' => 'Draf awal.', 'versi_draf' => 1])->assertSessionHasNoErrors();
        $this->tetapkan($this->actor, ['status' => 'disetujui', 'isi' => 'Disetujui Superadmin.', 'berlaku_id' => RekomendasiPimpinan::sole()->id])->assertSessionHasNoErrors();

        $rows = RekomendasiPimpinan::orderBy('disetujui_pada')->orderBy('id')->get()->keyBy('disetujui_oleh_role');
        $this->assertEqualsCanonicalizing(['perencanaan', 'superadmin'], $rows->keys()->all());
        $this->assertTrue($rows['perencanaan']->isCadangan());
        $this->assertTrue($rows['superadmin']->isCadangan());
        $audits = AuditLog::where('tindakan', 'rekomendasi_pimpinan.setujui')->get();
        $this->assertCount(2, $audits);
        foreach ($audits as $audit) {
            $this->assertTrue($audit->nilai_baru['persetujuan_cadangan']);
            $this->assertSame('diizinkan', $audit->dasar_izin['keputusan']);
        }
    }

    public function test_data_awal_dicatat_perencanaan_langsung_disetujui_dengan_rujukan_sumber(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');

        $this->tetapkan($perencanaan, ['status' => 'disetujui', 'isi' => 'Rekomendasi TW I.', 'rujukan_sumber' => 'Laporan Kinerja Triwulan I 2026'])->assertSessionHasNoErrors();

        $row = RekomendasiPimpinan::sole();
        $this->assertSame(['disetujui', 'perencanaan', 'Laporan Kinerja Triwulan I 2026'], [$row->status, $row->disetujui_oleh_role, $row->rujukan_sumber]);
        $this->assertTrue($row->isCadangan());
        $this->assertSame('Laporan Kinerja Triwulan I 2026', AuditLog::where('tindakan', 'rekomendasi_pimpinan.setujui')->sole()->nilai_baru['rujukan_sumber']);
    }

    public function test_perubahan_rekomendasi_disetujui_membentuk_baris_baru_dan_riwayat_tetap(): void
    {
        $pimpinan = $this->userWithRole('pimpinan');
        $perencanaan = $this->userWithRole('perencanaan');
        $this->tetapkan($pimpinan, ['status' => 'disetujui', 'isi' => 'Versi pertama.']);
        $lama = RekomendasiPimpinan::sole();

        $this->tetapkan($perencanaan, ['isi' => 'Usulan perubahan.', 'berlaku_id' => $lama->id])->assertSessionHasNoErrors();
        $this->tetapkan($perencanaan, ['isi' => 'Usulan perubahan revisi.', 'versi_draf' => 1, 'berlaku_id' => $lama->id])->assertSessionHasNoErrors();
        $this->assertSame(2, RekomendasiPimpinan::where('status', 'draf')->sole()->versi);
        $this->assertSame(['Usulan perubahan.', 1], [AuditLog::where('tindakan', 'rekomendasi_pimpinan.ubah_draf')->sole()->nilai_lama['isi'], AuditLog::where('tindakan', 'rekomendasi_pimpinan.ubah_draf')->sole()->nilai_lama['versi']]);
        $this->tetapkan($pimpinan, ['status' => 'disetujui', 'isi' => 'Versi kedua.', 'versi_draf' => 2, 'berlaku_id' => $lama->id])->assertSessionHasNoErrors();

        $this->assertSame(2, RekomendasiPimpinan::where('status', 'disetujui')->count());
        $this->assertSame(0, RekomendasiPimpinan::where('status', 'draf')->count());
        $this->assertSame($lama->getRawOriginal(), $lama->fresh()->getRawOriginal());
    }

    public function test_perubahan_dari_tampilan_rekomendasi_berlaku_yang_basi_ditolak_409(): void
    {
        $pimpinan = $this->userWithRole('pimpinan');
        $this->tetapkan($pimpinan, ['status' => 'disetujui', 'isi' => 'R1.'])->assertSessionHasNoErrors();
        $r1 = RekomendasiPimpinan::sole()->id;
        $this->tetapkan($this->userWithRole('perencanaan'), ['status' => 'disetujui', 'isi' => 'R2.', 'berlaku_id' => $r1])->assertSessionHasNoErrors();
        $r2 = RekomendasiPimpinan::where('isi', 'R2.')->value('id');

        foreach ([$r1, null] as $dilihat) {
            foreach (['disetujui', 'draf'] as $status) {
                $this->actingAs($pimpinan)->postJson('/rekomendasi', $this->payload(['status' => $status, 'isi' => 'R1 disunting.', 'berlaku_id' => $dilihat]))
                    ->assertStatus(409)->assertJsonValidationErrors('konflik');
            }
        }
        $this->assertSame(2, RekomendasiPimpinan::count());
        $this->assertSame(4, AuditLog::where('tindakan', 'rekomendasi_pimpinan.tetapkan_ditolak')->get()->where('nilai_baru.alasan_penolakan', 'konflik_versi')->count());

        $this->tetapkan($pimpinan, ['status' => 'disetujui', 'isi' => 'R3.', 'berlaku_id' => strtoupper($r2)])->assertSessionHasNoErrors();
        $this->assertSame('R3.', RekomendasiPimpinan::where('status', 'disetujui')->orderByDesc('disetujui_pada')->orderByDesc('id')->value('isi'));
    }

    public function test_hanya_pemegang_izin_yang_dapat_menetapkan_dan_penolakan_diaudit(): void
    {
        foreach (['pegawai', 'admin'] as $kode) {
            $this->tetapkan($this->userWithRole($kode), ['status' => 'disetujui'])->assertForbidden();
        }
        $this->assertDatabaseCount('rekomendasi_pimpinan', 0);
        $ditolak = AuditLog::where('tindakan', 'rekomendasi_pimpinan.tetapkan_ditolak')->get();
        $this->assertCount(2, $ditolak);
        $this->assertSame(['izin_ditolak', 'ditolak'], [$ditolak[0]->nilai_baru['alasan_penolakan'], $ditolak[0]->dasar_izin['keputusan']]);

        $this->tetapkan($this->userWithRole('pimpinan'), ['status' => 'disetujui'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rekomendasi_pimpinan', 1);
    }

    public function test_peran_penyetuju_tidak_dapat_dikirim_klien(): void
    {
        $this->tetapkan($this->userWithRole('perencanaan'), ['status' => 'disetujui', 'disetujui_oleh_role' => 'pimpinan'])->assertSessionHasErrors('rekomendasi_pimpinan');

        $this->assertDatabaseCount('rekomendasi_pimpinan', 0);
        $this->assertSame('input_tidak_valid', AuditLog::where('tindakan', 'rekomendasi_pimpinan.tetapkan_ditolak')->sole()->nilai_baru['alasan_penolakan']);
    }

    public function test_token_draf_basi_ditolak_409_tanpa_mengubah_data(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');
        $pimpinan = $this->userWithRole('pimpinan');
        $this->tetapkan($perencanaan, ['isi' => 'Draf awal.']);
        $this->tetapkan($perencanaan, ['isi' => 'Draf diperbarui.', 'versi_draf' => 1]);

        foreach ([['versi_draf' => 1], ['versi_draf' => null]] as $token) {
            $this->actingAs($pimpinan)->postJson('/rekomendasi', $this->payload(['status' => 'disetujui', 'isi' => 'Isi yang dilihat.', ...$token]))
                ->assertStatus(409)->assertJsonValidationErrors('konflik');
        }
        $this->tetapkan($pimpinan, ['status' => 'disetujui', 'isi' => 'Draf diperbarui.', 'versi_draf' => 2])->assertSessionHasNoErrors();
        $this->actingAs($perencanaan)->postJson('/rekomendasi', $this->payload(['isi' => 'Ubah draf yang sudah disetujui.', 'versi_draf' => 2]))->assertStatus(409);

        $this->assertSame(['Draf diperbarui.'], RekomendasiPimpinan::pluck('isi')->all());
        $ditolak = AuditLog::where('tindakan', 'rekomendasi_pimpinan.tetapkan_ditolak')->get();
        $this->assertCount(3, $ditolak);
        $this->assertSame('konflik_versi', $ditolak[0]->nilai_baru['alasan_penolakan']);
    }

    public function test_indikator_yang_tidak_diukur_pada_tahun_atau_periode_ditolak(): void
    {
        $perencanaan = $this->userWithRole('perencanaan');
        $diluarJadwal = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);

        $this->tetapkan($perencanaan, ['periode_id' => $diluarJadwal->id])->assertSessionHasErrors('periode_id');
        $this->tetapkan($perencanaan, ['tahun' => 2027])->assertSessionHasErrors('periode_id');

        // Jadwal draft belum mengukur apa pun meski periode dan snapshotnya sudah ada.
        $pk = RenstraPk::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2028, 'nomor_pk' => 'PK-2028', 'tanggal_pk' => '2028-01-01', 'created_by' => $this->actor->id]);
        $draft = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2028, 'renstra_pk_id' => $pk->id, 'penutupan' => '2028-12-31', 'status' => 'draft']);
        PeriodeJadwal::create(['jadwal_id' => $draft->id, 'periode_id' => $this->pengukuran->periode_id, 'pengisian_mulai' => '2028-03-01', 'pengisian_selesai' => '2028-03-15', 'reviu_mulai' => '2028-03-15', 'reviu_selesai' => '2028-04-15']);
        JadwalSnapshot::create([...$this->snapshotAttributes(), 'jadwal_id' => $draft->id, 'periode_mulai_id' => $this->pengukuran->periode_id]);
        $this->tetapkan($perencanaan, ['tahun' => 2028])->assertSessionHasErrors('periode_id');

        // Indikator yang mulai diukur TW II belum dapat diberi rekomendasi TW I.
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $diluarJadwal->id, 'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15', 'reviu_mulai' => '2026-06-15', 'reviu_selesai' => '2026-07-15']);
        $mulaiTw2 = IndikatorKinerja::create([...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'tipe_perhitungan', 'status', 'tahun_mulai_berlaku', 'created_by', 'created_by_role']), 'kode' => 'I-UJI-TW2']);
        JadwalSnapshot::create([...$this->snapshotAttributes(), 'indikator_id' => $mulaiTw2->id, 'jadwal_id' => $this->jadwal->id, 'periode_mulai_id' => $diluarJadwal->id]);
        $this->tetapkan($perencanaan, ['indikator_id' => $mulaiTw2->id])->assertSessionHasErrors('periode_id');

        $this->assertDatabaseCount('rekomendasi_pimpinan', 0);
        $this->assertSame(array_fill(0, 4, 'aturan_rekomendasi'), AuditLog::where('tindakan', 'rekomendasi_pimpinan.tetapkan_ditolak')->get()->map(fn ($a) => $a->nilai_baru['alasan_penolakan'])->all());
        $this->tetapkan($perencanaan, ['indikator_id' => $mulaiTw2->id, 'periode_id' => $diluarJadwal->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rekomendasi_pimpinan', 1);
    }

    /** @return array<string, mixed> */
    private function snapshotAttributes(): array
    {
        return $this->context->only(['indikator_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']);
    }

    /** @param  array<string, mixed>  $override */
    private function tetapkan(User $actor, array $override = []): TestResponse
    {
        return $this->actingAs($actor)->from('/rekomendasi')->post('/rekomendasi', $this->payload($override));
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_replace(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'status' => 'draf', 'isi' => 'Perkuat monitoring bulanan.'], $override);
    }
}
