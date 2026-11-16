<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\Pengukuran\EnsureDraftPengukuran;
use App\Actions\PerjanjianKinerja\SetujuiRevisiPerjanjianKinerja;
use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\RenstraPk;
use App\Models\RenstraPkVersi;
use App\Models\TargetKinerja;
use App\Support\TargetTahunanDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Revisi PK terhadap Rencana Aksi dan pengukuran lewat jalur produksi: pengukuran mulai periode berlaku memakai snapshot
 * versi baru, sedangkan target periode RA yang sudah sah tidak ikut berubah sampai RA dibuka kembali dan disesuaikan.
 * Fixture RA (TW I, TW II nilai akhir) dilengkapi PK yang dikunci jadwal dan target master yang sama dengan snapshot.
 */
class RevisiPkRencanaAksiTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RenstraPk $pk;

    private RencanaAksi $ra;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->fixture = $this->buatFixtureManual();
        $f = $this->fixture;
        $this->pk = RenstraPk::create(['renstra_id' => $f['renstra']->id, 'tahun' => 2026, 'nomor_pk' => 'PK-UJI-RA', 'tanggal_pk' => '2026-01-02',
            'created_by' => $f['perencanaan']->id]);
        $f['jadwal']->update(['renstra_pk_id' => $this->pk->id]);
        TargetKinerja::create(['indikator_kinerja_id' => $f['indikator']->id, 'tahun' => 2026, 'target_tahunan' => '100']);
        $this->ra = $this->sahkanRa($this->drafManual($f));
    }

    public function test_pengukuran_periode_mulai_revisi_membekukan_target_versi_pk_akhir(): void
    {
        // TW I sudah dilaporkan sehingga revisi berlaku mulai TW II.
        PengukuranKinerja::create(['indikator_id' => $this->fixture['indikator']->id, 'tahun' => 2026, 'periode_id' => $this->fixture['periode1']->id,
            'jadwal_snapshot_id' => $this->fixture['snapshot']->id, 'sumber_nilai' => 'manual', 'status_alur' => 'disahkan', 'created_by' => $this->fixture['perencanaan']->id]);

        $this->revisi(['target' => [['indikator_id' => $this->fixture['indikator']->id, 'nilai' => '120']]]);
        $baru = $this->snapshotVersi(2);
        $this->assertSame([$this->fixture['periode2']->id, '120'], [$baru->periode_mulai_id, TargetTahunanDecimal::normalize($baru->getRawOriginal('target'))]);
        $this->kegiatanKe($this->fixture['periode2']);
        $this->sesuaikanRa($baru, [$this->manual($this->fixture['periode2'], 120)]);

        $this->assertSame(['120', '120'], [$this->ajukanPengukuran($baru, $this->fixture['periode2']), $this->targetVersiAkhir()]);
    }

    public function test_koreksi_snapshot_sebelum_revisi_dokumen_tercatat_di_versi_pk_akhir_dan_pengukuran(): void
    {
        $koreksi = $this->terbitkanSnapshotKoreksi($this->fixture, ['target' => 110, 'periode_mulai_id' => $this->fixture['periode2']->id]);

        $this->revisi();
        $this->kegiatanKe($this->fixture['periode2']);
        $this->sesuaikanRa($koreksi, [$this->manual($this->fixture['periode2'], 110)]);

        $this->assertSame(['110', '110'], [$this->ajukanPengukuran($koreksi, $this->fixture['periode2']), $this->targetVersiAkhir()]);
    }

    public function test_revisi_tidak_mengubah_target_periode_ra_dan_ra_dapat_dibuka_kembali_terhadap_snapshot_baru(): void
    {
        $versiSah = $this->ra->versions()->sole();
        $sebelum = fn (): array => [$versiSah->fresh()->snapshot, $versiSah->fresh()->jadwal_snapshot_id,
            RencanaAksiTarget::where('rencana_aksi_id', $this->ra->id)->orderBy('periode_id')->pluck('nilai', 'periode_id')->all()];
        $awal = $sebelum();

        $this->revisi(['target' => [['indikator_id' => $this->fixture['indikator']->id, 'nilai' => '120']]]);

        $this->assertSame($awal, $sebelum());
        // Pembaca RA membandingkan target periode sah dengan snapshot terbaru, sehingga selisih terhadap target PK baru tampil.
        $this->actingAs($this->fixture['perencanaan'])->get("/rencana-aksi/{$this->ra->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.target_pk', '120.000000000000')->where('rencanaAksi.deviasi_pk.ada', true));
        $this->sesuaikanRa($this->snapshotVersi(2), [$this->manual($this->fixture['periode1'], 100), $this->manual($this->fixture['periode2'], 120)]);
        $this->assertSame($this->snapshotVersi(2)->id, $this->ra->versions()->reorder('nomor', 'desc')->firstOrFail()->jadwal_snapshot_id);
    }

    /** RA dari draf diajukan PIC lalu diverifikasi dan disahkan Perencanaan. */
    private function sahkanRa(RencanaAksi $header): RencanaAksi
    {
        $this->actingAs($this->fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $header->fresh()->versi])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $token = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($this->fixture['perencanaan'], $header->id, $token());
        app(SahkanRencanaAksi::class)->handle($this->fixture['perencanaan'], $header->id, $token());

        return $header->fresh();
    }

    /**
     * Buka kembali RA sah, simpan target terhadap snapshot terbaru, lalu sahkan ulang.
     *
     * @param  list<array<string, mixed>>  $targets
     */
    private function sesuaikanRa(JadwalSnapshot $snapshot, array $targets): void
    {
        $versiSah = $this->ra->versions()->reorder('nomor', 'desc')->firstOrFail();
        $this->actingAs($this->penggunaDenganPeran('perencanaan'))->postJson("/rencana-aksi/{$this->ra->id}/buka-kembali", ['expected_versi' => $this->ra->fresh()->versi,
            'versi_pengajuan_id' => $versiSah->id, 'alasan' => 'Penyesuaian target periode setelah revisi PK.'])->assertOk();
        $this->actingAs($this->fixture['pic'])->post("/rencana-aksi/{$this->ra->id}/target", ['expected_versi' => $this->ra->fresh()->versi,
            'expected_snapshot_id' => $snapshot->id, 'expected_snapshot_versi' => $snapshot->nomor_versi,
            'targets' => $targets])->assertSessionHasNoErrors();
        $this->ra = $this->sahkanRa($this->ra);
    }

    /** Draf pengukuran dibentuk jalur produksi (menolak snapshot yang bukan versi berlaku), lalu diajukan; mengembalikan target PK beku. */
    private function ajukanPengukuran(JadwalSnapshot $snapshot, Periode $periode): ?string
    {
        $header = app(EnsureDraftPengukuran::class)->handle($this->fixture['perencanaan'], $snapshot->id, $periode->id);
        $this->actingAs($this->fixture['perencanaan'])->post("/pengukuran/{$header->id}", ['versi' => $header->versi, 'action' => 'ajukan',
            'nilai' => (string) TargetTahunanDecimal::normalize($snapshot->getRawOriginal('target')), 'progres_kegiatan' => 'Progres pengujian revisi PK.',
            'kendala_masalah' => 'Tidak ada kendala.', 'strategi_tindak_lanjut' => 'Lanjutkan kegiatan.'])->assertSessionHasNoErrors();

        return TargetTahunanDecimal::normalize((string) KinerjaSnapshot::where('pengukuran_id', $header->id)->sole()->snapshot['target_pk']);
    }

    /** Snapshot baru mulai TW II: kegiatan RA dipindah ke periode berlaku, setara pengguna memperbaiki klaimnya sebelum mengajukan ulang. */
    private function kegiatanKe(Periode $periode): void
    {
        Kegiatan::whereIn('id', KlaimKegiatan::where('rencana_aksi_id', $this->ra->id)->pluck('kegiatan_id'))->update(['periode_id' => $periode->id]);
    }

    private function targetVersiAkhir(): ?string
    {
        $versi = RenstraPkVersi::where('renstra_pk_id', $this->pk->id)->orderByDesc('nomor_versi')->firstOrFail();

        return TargetTahunanDecimal::normalize($versi->target()->where('indikator_id', $this->fixture['indikator']->id)->sole()->getRawOriginal('nilai'));
    }

    private function snapshotVersi(int $nomor): JadwalSnapshot
    {
        return JadwalSnapshot::where('indikator_id', $this->fixture['indikator']->id)->where('nomor_versi', $nomor)->sole();
    }

    /** @param  array<string, mixed>  $data */
    private function revisi(array $data = []): void
    {
        app(SetujuiRevisiPerjanjianKinerja::class)->handle($this->fixture['perencanaan'], $this->pk->id, [
            'expected_nomor_versi' => 0, 'expected_dasar' => JadwalTahunan::tokenDasar($this->fixture['jadwal']->fresh()->snapshotDasar()),
            'nomor_surat' => 'B-12/2026', 'tanggal_surat' => '2026-03-09', 'alasan' => 'Revisi PK sesuai surat persetujuan pimpinan.',
            'lampiran' => [['file' => UploadedFile::fake()->create('pk-akhir.pdf', 20, 'application/pdf'), 'keterangan' => 'PK akhir']], ...$data]);
    }
}
