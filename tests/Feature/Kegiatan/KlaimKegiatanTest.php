<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshotKomponen;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class KlaimKegiatanTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    private Kegiatan $kegiatan;

    private IndikatorKomponen $komponen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->kegiatan = $this->buatKegiatan();
        $this->komponen = $this->buatKomponen('pembilang', true);
    }

    public function test_klaim_terbentuk_tahap_ra_dan_tidak_mengubah_nilai(): void
    {
        $sebelum = [DB::table('pengukuran_komponen')->count(), DB::table('rencana_aksi_target')->count(), DB::table('pengukuran_kinerjas')->count()];

        $this->actingAs($this->actor)->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id, 'arah_dampak' => 'mengurangi', 'catatan' => 'Mendukung komponen']))
            ->assertSessionHasNoErrors()->assertRedirect();

        $klaim = KlaimKegiatan::sole();
        $this->assertSame('rencana_aksi', $klaim->sumber_klaim);
        $this->assertNull($klaim->pengukuran_id);
        $this->assertSame($this->komponen->id, $klaim->komponen_id);
        $this->assertSame('mengurangi', $klaim->arah_dampak);
        $this->assertSame($this->actor->id, $klaim->created_by);
        $audit = AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->sole();
        $this->assertSame($klaim->id, $audit->objek_id);
        $this->assertSame('rencana_aksi:update', $audit->dasar_izin['rencana_aksi']['permission']);
        $this->assertSame('kegiatan:update', $audit->dasar_izin['kegiatan']['permission']);
        $this->assertSame($sebelum, [DB::table('pengukuran_komponen')->count(), DB::table('rencana_aksi_target')->count(), DB::table('pengukuran_kinerjas')->count()]);
    }

    public function test_konteks_tidak_cocok_ditolak_422_dan_diaudit(): void
    {
        $unitLain = Unit::create(['nama' => 'Unit Lain', 'created_by' => $this->actor->id]);
        $kasus = [
            'unit' => [$this->buatKegiatan(['unit_id' => $unitLain->id]), [], 'rencana_aksi_id'],
            'tahun' => [$this->buatKegiatan(['tahun' => 2027]), [], 'rencana_aksi_id'],
            'komponen' => [$this->kegiatan, ['komponen_id' => $this->buatKomponen('penyebut', false)->id], 'komponen_id'],
        ];
        foreach ($kasus as [$kegiatan, $tambahan, $field]) {
            $this->actingAs($this->actor)->post($this->url($kegiatan), $this->payload($tambahan))->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('klaim_kegiatan', 0);
        $this->assertSame(3, AuditLog::where('tindakan', 'klaim_kegiatan.tambah_ditolak')->count());
    }

    public function test_klaim_identik_ditolak_termasuk_tanpa_komponen(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $this->post($this->url(), $this->payload(['arah_dampak' => 'mengurangi']))->assertSessionHasErrors('komponen_id');
        $this->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('klaim_kegiatan', 2);
    }

    public function test_kegiatan_batal_atau_tidak_terlaksana_tetap_dapat_diklaim(): void
    {
        foreach (['batal', 'tidak_terlaksana'] as $status) {
            $kegiatan = $this->buatKegiatan();
            $kegiatan->forceFill(['status' => $status, 'justifikasi' => 'Kendala pelaksanaan'])->save();
            $this->actingAs($this->actor)->post($this->url($kegiatan), $this->payload())->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('klaim_kegiatan', 2);
    }

    public function test_ra_yang_sudah_diajukan_menolak_tambah_dan_hapus_dengan_422(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $klaim = KlaimKegiatan::sole();
        foreach ([RencanaAksi::STATUS_DIAJUKAN, RencanaAksi::STATUS_DIVERIFIKASI, RencanaAksi::STATUS_DISAHKAN] as $status) {
            $this->rencanaAksi->update(['status_alur' => $status]);
            $this->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id]))->assertSessionHasErrors('status_alur');
            $this->delete($this->url().'/'.$klaim->id, ['alasan' => 'Salah klaim'])->assertSessionHasErrors('status_alur');
        }

        $this->assertDatabaseCount('klaim_kegiatan', 1);
        $this->assertSame(3, AuditLog::where('tindakan', 'klaim_kegiatan.tambah_ditolak')->count());
        $this->assertSame(3, AuditLog::where('tindakan', 'klaim_kegiatan.hapus_ditolak')->count());
        // Audit menyimpan pesan domain dari ValidationException, bukan pesan generik.
        $this->assertSame('Daftar kegiatan hanya dapat diubah pada rencana aksi berstatus draf atau dikembalikan.',
            AuditLog::where('tindakan', 'klaim_kegiatan.tambah_ditolak')->first()->alasan);
    }

    public function test_tanpa_izin_kegiatan_atau_terkena_deny_ditolak_403_dan_diaudit(): void
    {
        $pic = $this->createPicPegawai();
        $this->actingAs($pic)->post($this->url(), $this->payload())->assertForbidden();

        $this->grantUnitPermission($pic, 'kegiatan:update');
        $this->denyPermission($pic, 'kegiatan:update', $this->unit->id);
        $this->post($this->url(), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('klaim_kegiatan', 0);
        $audits = AuditLog::where('tindakan', 'klaim_kegiatan.tambah_ditolak')->get();
        $this->assertCount(2, $audits);
        $this->assertSame('explicit_deny', $audits->last()->dasar_izin['alasan']);
    }

    public function test_grant_kegiatan_tanpa_kewenangan_ra_atau_pic_efektif_ditolak(): void
    {
        $pegawai = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($pegawai, 'kegiatan:update');
        $this->actingAs($pegawai)->post($this->url(), $this->payload())->assertForbidden();

        // Grant RA ada tetapi bukan PIC efektif indikator: gerbang tulis RA menolak sebagai validasi bisnis.
        $this->grantUnitPermission($pegawai, 'rencana_aksi:update');
        $this->post($this->url(), $this->payload())->assertSessionHasErrors('jendela');

        $this->assertDatabaseCount('klaim_kegiatan', 0);
    }

    public function test_pic_ditolak_pada_periode_terkunci_tetapi_perencanaan_tidak(): void
    {
        $pic = $this->picKegiatan();
        $this->actingAs($pic)->post($this->url(), $this->payload())->assertSessionHasNoErrors();

        $this->travelTo(now()->setDate(2026, 4, 1));
        $this->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id]))->assertSessionHasErrors('jendela');
        $this->delete($this->url().'/'.KlaimKegiatan::sole()->id, ['alasan' => 'Salah klaim'])->assertSessionHasErrors('jendela');

        $this->actingAs($this->createUserWithRole('perencanaan'))->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('klaim_kegiatan', 2);
    }

    public function test_hapus_hanya_oleh_pembuat_atau_perencanaan_dan_wajib_beralasan(): void
    {
        $pembuat = $this->picKegiatan();
        $this->actingAs($pembuat)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $klaim = KlaimKegiatan::sole();

        $picLain = $this->picKegiatan();
        $this->actingAs($picLain)->delete($this->url().'/'.$klaim->id, ['alasan' => 'Bukan milik saya'])->assertForbidden();
        $this->actingAs($pembuat)->delete($this->url().'/'.$klaim->id, ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->assertDatabaseCount('klaim_kegiatan', 1);

        $this->actingAs($this->createUserWithRole('perencanaan'))->delete($this->url().'/'.$klaim->id, ['alasan' => 'Klaim keliru periode'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('klaim_kegiatan', 0);
        $audit = AuditLog::where('tindakan', 'klaim_kegiatan.hapus')->sole();
        $this->assertSame('Klaim keliru periode', $audit->alasan);
        $this->assertSame($this->kegiatan->id, $audit->nilai_lama['kegiatan_id']);
        $this->assertSame(1, AuditLog::where('tindakan', 'klaim_kegiatan.hapus_ditolak')->count());
    }

    public function test_klaim_kegiatan_lain_dijawab_404_tanpa_mutasi(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $lain = $this->buatKegiatan();

        $this->delete($this->url($lain).'/'.KlaimKegiatan::sole()->id, ['alasan' => 'Salah klaim'])->assertNotFound();
        $this->assertDatabaseCount('klaim_kegiatan', 1);
    }

    public function test_halaman_kegiatan_memuat_panel_klaim_sesuai_gerbang(): void
    {
        $edit = '/kegiatan/'.$this->kegiatan->id.'/edit';
        $this->actingAs($this->actor)->get($edit)->assertOk()->assertInertia(fn ($page) => $page
            ->has('klaim.daftar', 0)
            ->where('klaim.can.tambah', true)
            ->where('klaim.opsiRencanaAksi.0.id', $this->rencanaAksi->id)
            ->where('klaim.opsiRencanaAksi.0.komponen.0.id', $this->komponen->id));

        $this->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id]))->assertSessionHasNoErrors();
        $this->get($edit)->assertInertia(fn ($page) => $page
            ->where('klaim.daftar.0.komponen', 'PEMBILANG — pembilang')
            ->where('klaim.daftar.0.can.hapus', true));

        // RA yang sudah diajukan tidak lagi ditawarkan dan klaimnya tidak dapat dihapus.
        $this->rencanaAksi->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);
        $this->get($edit)->assertInertia(fn ($page) => $page
            ->has('klaim.opsiRencanaAksi', 0)
            ->where('klaim.can.tambah', false)
            ->where('klaim.daftar.0.can.hapus', false));
    }

    public function test_daftar_kegiatan_menawarkan_klaim_hanya_dengan_kewenangan_ra(): void
    {
        $this->actingAs($this->actor)->get('/kegiatan?tahun=2026')->assertInertia(fn ($page) => $page->where('kegiatan.data.0.can.klaim', true));

        $pegawai = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($pegawai, 'kegiatan:update');
        $this->actingAs($pegawai)->get('/kegiatan?tahun=2026')->assertInertia(fn ($page) => $page
            ->where('kegiatan.data.0.can.update', true)
            ->where('kegiatan.data.0.can.klaim', false));
    }

    public function test_deny_baca_ra_pada_unit_menyembunyikan_panel_klaim(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $this->denyPermission($this->actor, 'rencana_aksi:read', $this->unit->id);

        $this->get('/kegiatan/'.$this->kegiatan->id.'/edit')->assertOk()->assertInertia(fn ($page) => $page->where('klaim', null));
    }

    public function test_akun_nonaktif_ditolak_tanpa_mutasi_dan_percobaannya_diaudit(): void
    {
        $pic = $this->picKegiatan();
        $this->grantUnitPermission($pic, 'kegiatan:create');
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        $klaim = KlaimKegiatan::sole();
        $pic->update(['status' => 'nonaktif']);

        $this->actingAs($pic)->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id]))->assertRedirect();
        $this->delete($this->url().'/'.$klaim->id, ['alasan' => 'Salah klaim'])->assertRedirect();
        $this->post('/rencana-aksi/'.$this->rencanaAksi->id.'/kegiatan', ['periode_id' => $this->snapshot->periode_mulai_id, 'nama' => 'Rapat', 'arah_dampak' => 'menambah'])
            ->assertRedirect();

        $this->assertDatabaseCount('klaim_kegiatan', 1);
        $this->assertDatabaseCount('kegiatan', 1);
        foreach (['klaim_kegiatan.tambah_ditolak' => $this->kegiatan->id, 'klaim_kegiatan.hapus_ditolak' => $klaim->id, 'kegiatan.buat_ditolak' => $this->rencanaAksi->id] as $tindakan => $objekId) {
            $audit = AuditLog::where('tindakan', $tindakan)->sole();
            $this->assertSame($objekId, $audit->objek_id);
            $this->assertSame($pic->id, $audit->actor_id);
            $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
        }
    }

    public function test_opsi_klaim_hanya_kombinasi_yang_tersedia_dan_label_dari_snapshot(): void
    {
        $edit = '/kegiatan/'.$this->kegiatan->id.'/edit';
        $this->actingAs($this->actor)->post($this->url(), $this->payload())->assertSessionHasNoErrors();
        // Master berubah setelah aktivasi; label klaim tetap dari snapshot beku RA.
        $this->komponen->update(['label' => 'Label master baru']);
        $this->get($edit)->assertInertia(fn ($page) => $page
            ->where('klaim.opsiRencanaAksi.0.tanpa_komponen', false)
            ->where('klaim.opsiRencanaAksi.0.komponen.0.nama', 'PEMBILANG — pembilang'));

        $this->post($this->url(), $this->payload(['komponen_id' => $this->komponen->id]))->assertSessionHasNoErrors();
        $this->get($edit)->assertInertia(fn ($page) => $page
            ->has('klaim.opsiRencanaAksi', 0)
            ->where('klaim.can.tambah', false)
            ->where('klaim.daftar.1.komponen', 'PEMBILANG — pembilang'));
    }

    private function url(?Kegiatan $kegiatan = null): string
    {
        return '/kegiatan/'.($kegiatan ?? $this->kegiatan)->id.'/klaim';
    }

    /** @param array<string, mixed> $tambahan */
    private function payload(array $tambahan = []): array
    {
        return array_replace(['rencana_aksi_id' => $this->rencanaAksi->id, 'komponen_id' => null, 'arah_dampak' => 'menambah', 'catatan' => null], $tambahan);
    }

    /** @param array<string, mixed> $atribut */
    private function buatKegiatan(array $atribut = []): Kegiatan
    {
        return Kegiatan::create(array_replace([
            'unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id,
            'nama' => 'Fasilitasi unit', 'tujuan' => null, 'created_by' => $this->actor->id,
        ], $atribut));
    }

    private function buatKomponen(string $peran, bool $diSnapshot): IndikatorKomponen
    {
        $komponen = IndikatorKomponen::create(['indikator_id' => $this->indikator->id, 'kode' => strtoupper($peran), 'label' => $peran, 'peran' => $peran,
            'bobot' => 1, 'urutan' => 1, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        if ($diSnapshot) {
            JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $this->snapshot->id, 'komponen_id' => $komponen->id, 'kode' => $komponen->kode,
                'label' => $peran, 'peran' => $peran, 'bobot' => 1, 'urutan' => 1]);
        }

        return $komponen;
    }

    /** PIC operasional: grant RA + kegiatan pada unit dan penugasan PJ efektif hari ini. */
    private function picKegiatan(): User
    {
        $pic = $this->createPicPegawai();
        $this->grantUnitPermission($pic, 'kegiatan:update');

        return $pic;
    }
}
