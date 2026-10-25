<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class KegiatanRencanaAksiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    private Periode $tw1;

    private Periode $tw2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->tw1 = Periode::findOrFail($this->snapshot->periode_mulai_id);
        $this->tw2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->tw2->id, 'pengisian_mulai' => '2026-04-01',
            'pengisian_selesai' => '2026-06-30']);
    }

    public function test_satu_aksi_membuat_kegiatan_dan_klaim_tahap_ra(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload(['tanggal_rencana' => '2026-03-20']))
            ->assertSessionHasNoErrors()->assertRedirect();

        $kegiatan = Kegiatan::sole();
        $this->assertSame([$this->unit->id, 2026, $this->tw1->id, 'rencana', '2026-03-20'],
            [$kegiatan->unit_id, $kegiatan->tahun, $kegiatan->periode_id, $kegiatan->status, $kegiatan->tanggal_rencana->format('Y-m-d')]);
        $klaim = KlaimKegiatan::sole();
        $this->assertSame([$this->rencanaAksi->id, $kegiatan->id, 'rencana_aksi', $this->actor->id],
            [$klaim->rencana_aksi_id, $klaim->kegiatan_id, $klaim->sumber_klaim, $klaim->created_by]);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.buat')->where('objek_id', $kegiatan->id)->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->where('objek_id', $klaim->id)->count());
    }

    public function test_data_awal_2026_lewat_jalur_perencanaan_boleh_tanpa_anggaran(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload(['anggaran' => null, 'salin_ke_periode_ids' => [$this->tw2->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([null, null], Kegiatan::pluck('anggaran')->all());
    }

    public function test_salin_ke_periode_lain_membuat_pasangan_tersendiri_tanpa_berkas(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload([
            'tanggal_rencana' => '2026-03-20', 'tujuan' => 'Tujuan bersama', 'anggaran' => 2500000, 'salin_ke_periode_ids' => [$this->tw2->id],
        ]))->assertSessionHasNoErrors();

        $kegiatan = Kegiatan::orderBy('periode_id')->get()->keyBy('periode_id');
        $this->assertCount(2, $kegiatan);
        $salinan = $kegiatan[$this->tw2->id];
        $this->assertSame(['Rapat koordinasi', 'Tujuan bersama', '2500000'], [$salinan->nama, $salinan->tujuan, $salinan->anggaran]);
        $this->assertNull($salinan->tanggal_rencana);
        $this->assertNotSame($kegiatan[$this->tw1->id]->id, $salinan->id);
        $this->assertSame(2, KlaimKegiatan::where('sumber_klaim', 'rencana_aksi')->distinct('kegiatan_id')->count('kegiatan_id'));
        $this->assertDatabaseCount('berkas', 0);
    }

    public function test_pic_ditolak_pada_periode_terkunci_secara_atomik_tetapi_perencanaan_tidak(): void
    {
        $pic = $this->createPicPegawai();
        $this->grantUnitPermission($pic, 'kegiatan:create');
        $this->travelTo(now()->setDate(2026, 4, 1));

        $this->actingAs($pic)->post($this->url(), $this->payload())->assertSessionHasErrors('jendela');
        $this->post($this->url(), $this->payload(['periode_id' => $this->tw2->id, 'salin_ke_periode_ids' => [$this->tw1->id]]))->assertSessionHasErrors('jendela');
        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertDatabaseCount('klaim_kegiatan', 0);
        $this->assertSame(2, AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->count());

        $this->post($this->url(), $this->payload(['periode_id' => $this->tw2->id]))->assertSessionHasNoErrors();
        $this->actingAs($this->createUserWithRole('perencanaan'))->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('klaim_kegiatan', 2);
    }

    public function test_ra_yang_tidak_dapat_disunting_menolak_dengan_422(): void
    {
        $this->rencanaAksi->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);

        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasErrors('status_alur');
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_deny_baca_kegiatan_menolak_tambah_dari_ra_tanpa_mutasi_dan_diaudit(): void
    {
        $pic = $this->createPicPegawai();
        $this->grantUnitPermission($pic, 'kegiatan:create');
        $this->denyPermission($pic, 'kegiatan:read', $this->unit->id);

        $this->actingAs($pic)->post($this->url(), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertDatabaseCount('klaim_kegiatan', 0);
        $audit = AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->sole();
        $this->assertSame('kegiatan:read', $audit->dasar_izin['permission']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['alasan']);
    }

    public function test_tanpa_izin_buat_kegiatan_ditolak_403_dan_diaudit(): void
    {
        $pic = $this->createPicPegawai();
        $this->actingAs($pic)->post($this->url(), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertSame('kegiatan:create', AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->sole()->dasar_izin['permission']);
    }

    public function test_periode_di_luar_jadwal_atau_nonaktif_ditolak_tanpa_mutasi(): void
    {
        $asing = Periode::create(['nama' => 'Semester Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->actingAs($this->actor)->post($this->url(), $this->payload(['periode_id' => $asing->id]))->assertSessionHasErrors('periode_id');

        $this->tw2->update(['aktif' => false]);
        $this->post($this->url(), $this->payload(['salin_ke_periode_ids' => [$this->tw2->id]]))->assertSessionHasErrors('periode_id');

        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertDatabaseCount('klaim_kegiatan', 0);
    }

    public function test_halaman_ra_memuat_kegiatan_per_periode_dan_capability(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::sole();

        $this->get('/rencana-aksi/'.$this->rencanaAksi->id)->assertOk()->assertInertia(fn ($page) => $page
            ->has('kegiatan.daftar', 1)
            ->where('kegiatan.daftar.0.kegiatan_id', $kegiatan->id)
            ->where('kegiatan.daftar.0.periode_id', $this->tw1->id)
            ->where('kegiatan.daftar.0.nama', 'Rapat koordinasi')
            ->where('kegiatan.can', ['tambah' => true, 'kelola' => true]));

        // PIC tanpa izin buat kegiatan tetap dapat membaca daftar, tetapi tidak ditawari tambah.
        $this->actingAs($this->createPicPegawai())->get('/rencana-aksi/'.$this->rencanaAksi->id)->assertOk()
            ->assertInertia(fn ($page) => $page->has('kegiatan.daftar', 1)->where('kegiatan.can.tambah', false));
    }

    public function test_deny_baca_kegiatan_pada_unit_menyembunyikan_panel_kegiatan_ra(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $this->denyPermission($this->actor, 'kegiatan:read', $this->unit->id);

        $this->get('/rencana-aksi/'.$this->rencanaAksi->id)->assertOk()->assertInertia(fn ($page) => $page->where('kegiatan', null));
    }

    private function url(): string
    {
        return '/rencana-aksi/'.$this->rencanaAksi->id.'/kegiatan';
    }

    /** @param array<string, mixed> $tambahan */
    private function payload(array $tambahan = []): array
    {
        return array_replace(['periode_id' => $this->tw1->id, 'nama' => 'Rapat koordinasi', 'anggaran' => 0, 'tujuan' => null, 'sasaran_peserta' => null,
            'keterangan_peserta' => null, 'lokasi' => null, 'tanggal_rencana' => null, 'komponen_id' => null, 'arah_dampak' => 'menambah', 'catatan' => null], $tambahan);
    }
}
