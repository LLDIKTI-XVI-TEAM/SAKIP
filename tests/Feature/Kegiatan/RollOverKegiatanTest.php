<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Services\Kegiatan\TotalAnggaranKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Kegiatan lanjutan hasil transisi `ditunda` (Q41 §41.4, Q69 §69.5): kunci periode tujuan bagi PIC dan
 * klaim tahap pengukuran kegiatan lanjutan.
 */
class RollOverKegiatanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RencanaAksi $header;

    private Kegiatan $asal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->buatFixtureManual();
        // Draf memuat "Kegiatan pendukung" beranggaran 0 di Triwulan I yang diklaim tahap RA.
        $this->header = $this->drafManual($this->fixture);
        $this->grant($this->fixture['pic'], 'kegiatan:update', $this->fixture['unit']->id, $this->fixture['perencanaan']);
        $this->asal = Kegiatan::where('nama', 'Kegiatan pendukung')->sole();
    }

    public function test_periode_tujuan_terkunci_ditolak_bagi_pic_dan_tidak_ditawarkan(): void
    {
        // Lewat pengisian_selesai Triwulan II (31 Maret) pada jadwal tahun kegiatan (Q69 §69.5 butir 2).
        $this->travelTo(now()->setDate(2026, 4, 15)->setTime(9, 0));
        $jumlah = Kegiatan::count();
        // Form tidak menawarkan Tunda bila satu-satunya periode tujuan terkunci bagi PIC.
        $this->assertSame([false, []], $this->pilihanTunda('pic'));

        $this->tunda(json: true)->assertUnprocessable()->assertJsonValidationErrors('periode_tujuan_id');

        $this->assertSame([$jumlah, 'rencana'], [Kegiatan::count(), $this->asal->fresh()->status]);
        $this->assertSame('Periode tujuan terkunci bagi PIC setelah batas pengisian lewat: Triwulan II (batas 2026-03-31).',
            AuditLog::where('tindakan', 'kegiatan.ubah_status_ditolak')->sole()->alasan);
    }

    public function test_kunci_berlaku_walau_kegiatan_belum_diklaim_dan_perencanaan_dikecualikan(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 15)->setTime(9, 0));
        KlaimKegiatan::where('kegiatan_id', $this->asal->id)->delete();
        $this->tunda(json: true)->assertUnprocessable()->assertJsonValidationErrors('periode_tujuan_id');

        // Perencanaan dikecualikan dari kunci periode sampai penutupan (Q41 §41.2).
        $this->tunda(aktor: 'perencanaan')->assertRedirect();
        $this->assertSame('ditunda', $this->asal->fresh()->status);
    }

    public function test_perencanaan_tetap_ditawari_periode_tujuan_terkunci(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 15)->setTime(9, 0));
        $this->assertSame([true, [['id' => $this->fixture['periode2']->id, 'nama' => 'Triwulan II',
            'tanggal' => ['bulan_mulai' => 4, 'hari_mulai' => 1, 'bulan_selesai' => 6, 'hari_selesai' => 30]]]], $this->pilihanTunda('perencanaan'));
    }

    public function test_periode_tujuan_terbuka_tetap_ditawarkan_dan_diterima_bagi_pic(): void
    {
        $this->assertSame([true, [['id' => $this->fixture['periode2']->id, 'nama' => 'Triwulan II',
            'tanggal' => ['bulan_mulai' => 4, 'hari_mulai' => 1, 'bulan_selesai' => 6, 'hari_selesai' => 30]]]], $this->pilihanTunda('pic'));
        $this->tunda()->assertRedirect();
        $this->assertSame('ditunda', $this->asal->fresh()->status);
    }

    public function test_tunda_membentuk_lanjutan_tahun_sama_tanpa_berkas_dan_asal_tetap_ditunda(): void
    {
        $rab = BuktiDukung::create(['berkasable_type' => 'kegiatan', 'berkasable_id' => $this->asal->id,
            'jenis_berkas_id' => $this->persyaratanPerencanaan()->id, 'mode' => 'file', 'path' => 'berkas/kegiatan/rab.xlsx',
            'nama_asli' => 'rab.xlsx', 'uploaded_by' => $this->fixture['pic']->id, 'created_at' => now()]);

        $this->tunda()->assertRedirect();

        $asal = $this->asal->fresh();
        $lanjutan = $this->lanjutan();
        $this->assertSame(['ditunda', 2026, 2026], [$asal->status, (int) $asal->tahun, (int) $lanjutan->tahun]);
        $this->assertSame($this->fixture['periode2']->id, $lanjutan->periode_id);
        // Berkas asal, termasuk RAB, tetap milik asal dan tidak disalin ke lanjutan (Q41 §41.4 butir 5).
        $this->assertSame($asal->id, $rab->fresh()->berkasable_id);
        $this->assertFalse(BuktiDukung::where('berkasable_type', 'kegiatan')->where('berkasable_id', $lanjutan->id)->exists());
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.roll_over')->exists());
    }

    public function test_klaim_pengukuran_langsung_dibuat_bila_baris_pengukuran_periode_tujuan_sudah_ada(): void
    {
        KlaimKegiatan::where('kegiatan_id', $this->asal->id)->update(['arah_dampak' => 'mengurangi', 'catatan' => 'Catatan klaim asal']);
        $this->header->forceFill(['status_alur' => RencanaAksi::STATUS_DISAHKAN])->save();
        $versi = $this->header->fresh()->versi;
        // Pengukuran tujuan yang sudah disahkan tetap menerima klaim data kerja; versinya yang beku tidak disentuh.
        $pengukuran = $this->pengukuran($this->fixture['periode2'], 'disahkan');

        $this->tunda()->assertRedirect();

        $klaim = KlaimKegiatan::where('kegiatan_id', $this->lanjutan()->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran', $pengukuran->id, null, 'mengurangi', 'Catatan klaim asal'],
            [$klaim->rencana_aksi_id, $klaim->sumber_klaim, $klaim->pengukuran_id, $klaim->komponen_id, $klaim->arah_dampak, $klaim->catatan]);
        // RA yang sudah disahkan tidak dibuka kembali untuk memindahkan kegiatan (Q41 §41.4 butir 3).
        $this->assertSame([RencanaAksi::STATUS_DISAHKAN, $versi], [$this->header->fresh()->status_alur, $this->header->fresh()->versi]);
        $geser = AuditLog::where('tindakan', 'kegiatan.geser_periode')->sole();
        $this->assertSame([$klaim->id], array_column($geser->nilai_baru['klaim'], 'id'));
        $this->assertSame(['pengukuran', $pengukuran->id], [$geser->nilai_baru['klaim'][0]['sumber_klaim'], $geser->nilai_baru['klaim'][0]['pengukuran_id']]);
        $this->assertFalse(AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->exists());
    }

    public function test_klaim_pengukuran_dibuat_saat_baris_pengukuran_periode_tujuan_terbentuk(): void
    {
        $this->tunda()->assertRedirect();
        $lanjutan = $this->lanjutan();
        $this->assertFalse(KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->exists());
        $this->assertSame([], AuditLog::where('tindakan', 'kegiatan.geser_periode')->sole()->nilai_baru['klaim']);

        $this->bentukDraf()->assertSessionHasNoErrors();
        $pengukuran = PengukuranKinerja::where('periode_id', $this->fixture['periode2']->id)->sole();

        $klaim = KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran', $pengukuran->id], [$klaim->rencana_aksi_id, $klaim->sumber_klaim, $klaim->pengukuran_id]);
        $audit = AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->sole();
        $this->assertSame([$this->fixture['perencanaan']->id, $klaim->id, $this->asal->id, $pengukuran->id],
            [$audit->actor_id, $audit->objek_id, $audit->nilai_baru['kegiatan_asal_id'], $audit->nilai_baru['pengukuran_id']]);
        $this->assertNotEmpty($audit->dasar_izin);

        // Pembukaan ulang draf yang sudah ada tidak menggandakan klaim.
        $this->bentukDraf()->assertSessionHasNoErrors();
        $this->assertSame(1, KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->count());
    }

    public function test_rantai_dengan_perantara_belum_diklaim_tetap_meneruskan_klaim_rantai_asal(): void
    {
        // A ditunda ke B dan B ke C sebelum baris pengukuran TW II maupun TW III ada: B dan C belum berklaim.
        $tw3 = $this->triwulanTiga();
        $this->tunda()->assertRedirect();
        $b = $this->lanjutan();
        $this->tunda(['periode_tujuan_id' => $tw3->id], $b)->assertRedirect();
        $c = Kegiatan::where('kegiatan_asal_id', $b->id)->sole();
        $this->assertSame(0, KlaimKegiatan::whereIn('kegiatan_id', [$b->id, $c->id])->count());

        // Baris TW III terbentuk: C diklaim dari klaim A walau perantara B belum diklaim.
        $this->bentukDraf($tw3)->assertSessionHasNoErrors();
        $klaimC = KlaimKegiatan::where('kegiatan_id', $c->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran'], [$klaimC->rencana_aksi_id, $klaimC->sumber_klaim]);
        $this->assertSame($b->id, AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->sole()->nilai_baru['kegiatan_asal_id']);

        // Baris TW II terbentuk kemudian: B ikut diklaim tanpa menggandakan klaim C.
        $this->bentukDraf()->assertSessionHasNoErrors();
        $this->assertSame([1, 1], [KlaimKegiatan::where('kegiatan_id', $b->id)->count(), KlaimKegiatan::where('kegiatan_id', $c->id)->count()]);
    }

    public function test_klaim_tahap_ra_untuk_kegiatan_lanjutan_ditolak_di_server(): void
    {
        $this->tunda()->assertRedirect();
        $lanjutan = $this->lanjutan();

        $this->actingAs($this->fixture['pic'])->postJson("/kegiatan/{$lanjutan->id}/klaim", ['rencana_aksi_id' => $this->header->id, 'arah_dampak' => 'menambah'])
            ->assertUnprocessable()->assertJsonValidationErrors('kegiatan_id');

        $this->assertFalse(KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->exists());
        $this->assertSame($lanjutan->id, AuditLog::where('tindakan', 'klaim_kegiatan.tambah_ditolak')->sole()->objek_id);

        // Setelah diklaim otomatis, halaman tidak menawarkan operasi yang pasti ditolak walau RA unit/tahun yang sama masih draf.
        $this->bentukDraf()->assertSessionHasNoErrors();
        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}/kegiatan/{$lanjutan->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('klaim.can.tambah', false)->where('klaim.opsiRencanaAksi', []));
    }

    public function test_persyaratan_perencanaan_tidak_diperiksa_untuk_lanjutan_tahap_pengukuran(): void
    {
        // Asal memenuhi persyaratan wajib; lanjutan tanpa berkas tidak menghalangi pengajuan RA (Q41 §41.4 butir 5).
        BuktiDukung::create(['berkasable_type' => 'kegiatan', 'berkasable_id' => $this->asal->id, 'jenis_berkas_id' => $this->persyaratanPerencanaan()->id,
            'mode' => 'file', 'path' => 'berkas/kegiatan/rab.xlsx', 'nama_asli' => 'rab.xlsx', 'uploaded_by' => $this->fixture['pic']->id, 'created_at' => now()]);
        $this->pengukuran($this->fixture['periode2']);
        $this->tunda()->assertRedirect();
        $this->assertSame('pengukuran', KlaimKegiatan::where('kegiatan_id', $this->lanjutan()->id)->sole()->sumber_klaim);

        $this->actingAs($this->fixture['pic'])->post("/rencana-aksi/{$this->header->id}/ajukan", ['expected_versi' => $this->header->fresh()->versi])
            ->assertSessionHasNoErrors();
        $this->assertSame(RencanaAksi::STATUS_DIAJUKAN, $this->header->fresh()->status_alur);
    }

    public function test_anggaran_lanjutan_disalin_dapat_diubah_dan_total_beku_tidak_berubah(): void
    {
        $this->asal->update(['anggaran' => 2500000]);
        $this->actingAs($this->fixture['pic'])->post("/rencana-aksi/{$this->header->id}/ajukan", ['expected_versi' => $this->header->fresh()->versi])
            ->assertSessionHasNoErrors();
        $beku = $this->totalBeku();
        $total = app(TotalAnggaranKegiatan::class);

        $this->tunda()->assertRedirect();
        $lanjutan = $this->lanjutan();
        $this->assertSame('2500000', $lanjutan->anggaran);
        // Lanjutan belum diklaim: asal tetap terhitung agar anggarannya tidak hilang (Q69 §69.6 butir 1).
        $this->assertSame('2500000', $total->perRencanaAksi($this->header->fresh())['total']);

        $this->bentukDraf()->assertSessionHasNoErrors();
        $this->assertSame(['total' => '2500000', 'belum_diisi' => 0], $total->perRencanaAksi($this->header->fresh())['per_periode'][$this->fixture['periode2']->id]);

        $token = $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}/kegiatan/{$lanjutan->id}")->assertOk()
            ->viewData('page')['props']['kegiatan']['version_token'];
        $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$lanjutan->id}", [
            ...$lanjutan->only(['unit_id', 'tahun', 'periode_id', 'nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi', 'tanggal_rencana']),
            'anggaran' => 3000000, 'expected_state' => $token,
        ])->assertSessionHasNoErrors();
        $this->assertSame('3000000', $lanjutan->fresh()->anggaran);
        // Total beku pada versi RA tidak berubah (Q42 §42.2 butir 6).
        $this->assertSame($beku, $this->totalBeku());
    }

    public function test_pembentukan_draf_mengunci_advisory_dan_header_ra_sebelum_indikator(): void
    {
        // Urutan sama dengan transisi ditunda (advisory → header RA) dan writer RA (header RA → indikator) agar klaim tertunda tidak deadlock.
        DB::enableQueryLog();
        $this->bentukDraf()->assertSessionHasNoErrors();

        $advisory = $this->posisiAdvisoryLanjutan();
        $headerRa = $this->posisiQuery('/from "rencana_aksi".*for key share/');
        $indikator = $this->posisiQuery('/from "indikator_kinerjas".*for update/');
        $this->assertNotContains(null, [$advisory, $headerRa, $indikator]);
        $this->assertTrue($advisory < $headerRa && $headerRa < $indikator, "advisory {$advisory}, header RA {$headerRa}, indikator {$indikator}");
    }

    public function test_transisi_ditunda_mengunci_advisory_sebelum_header_ra_dan_kegiatan(): void
    {
        // Advisory tahun × periode tujuan menyerialkan ditunda dengan pembentukan draf; tanpanya lanjutan dapat tertinggal tanpa klaim.
        DB::enableQueryLog();
        $this->tunda()->assertSessionHasNoErrors();

        $advisory = $this->posisiAdvisoryLanjutan();
        $headerRa = $this->posisiQuery('/from "rencana_aksi".*for update/');
        $kegiatan = $this->posisiQuery('/from "kegiatan".*for update/');
        $this->assertNotContains(null, [$advisory, $headerRa, $kegiatan]);
        $this->assertTrue($advisory < $headerRa && $headerRa < $kegiatan, "advisory {$advisory}, header RA {$headerRa}, kegiatan {$kegiatan}");
    }

    public function test_halaman_ra_dan_form_menautkan_asal_dan_lanjutan_dua_arah(): void
    {
        $this->pengukuran($this->fixture['periode2']);
        $this->tunda()->assertRedirect();
        $lanjutan = $this->lanjutan();

        $daftar = collect($this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}/kegiatan")->assertOk()
            ->viewData('page')['props']['kegiatan']['daftar'])->keyBy('kegiatan_id');
        $this->assertSame(['id' => $lanjutan->id, 'periode_id' => $this->fixture['periode2']->id], $daftar[$this->asal->id]['lanjutan']);
        $this->assertNull($daftar[$this->asal->id]['asal']);
        $this->assertSame(['id' => $this->asal->id, 'periode_id' => $this->asal->periode_id], $daftar[$lanjutan->id]['asal']);
        $this->assertNull($daftar[$lanjutan->id]['lanjutan']);

        $form = fn (Kegiatan $kegiatan): array => $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}/kegiatan/{$kegiatan->id}")
            ->assertOk()->viewData('page')['props']['kegiatan'];
        $this->assertSame([$this->fixture['periode2']->nama, null], [$form($this->asal)['periode_tujuan'], $form($this->asal)['periode_asal']]);
        $this->assertSame([null, $this->asal->periode->nama], [$form($lanjutan)['periode_tujuan'], $form($lanjutan)['periode_asal']]);
    }

    private function posisiQuery(string $pola): ?int
    {
        return array_key_first(array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $query): bool => preg_match($pola, $query) === 1));
    }

    /** Advisory kunci kegiatan lanjutan; dibedakan lewat binding karena `Periode::lockConfiguration` juga memakai advisory lock. */
    private function posisiAdvisoryLanjutan(): ?int
    {
        $tahunPeriode = 'sakip:kegiatan-lanjutan:2026:'.strtolower($this->fixture['periode2']->id);

        return array_key_first(array_filter(DB::getQueryLog(), fn (array $log): bool => str_contains($log['query'], 'pg_advisory_xact_lock(')
            && in_array($tahunPeriode, $log['bindings'], true)));
    }

    private function lanjutan(): Kegiatan
    {
        return Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();
    }

    /** @return array<string, mixed> */
    private function totalBeku(): array
    {
        return RencanaAksiVersi::where('rencana_aksi_id', $this->header->id)->orderByDesc('nomor')->firstOrFail()->snapshot['anggaran_kegiatan'];
    }

    private function pengukuran(Periode $periode, string $status = 'draft'): PengukuranKinerja
    {
        return PengukuranKinerja::create(['indikator_id' => $this->fixture['indikator']->id, 'tahun' => 2026, 'periode_id' => $periode->id,
            'jadwal_snapshot_id' => $this->fixture['snapshot']->id, 'sumber_nilai' => 'manual', 'status_alur' => $status, 'created_by' => $this->fixture['pic']->id]);
    }

    private function bentukDraf(?Periode $periode = null): TestResponse
    {
        return $this->actingAs($this->fixture['perencanaan'])->post('/pengukuran/ensure-draft',
            ['jadwal_snapshot_id' => $this->fixture['snapshot']->id, 'periode_id' => ($periode ?? $this->fixture['periode2'])->id]);
    }

    private function triwulanTiga(): Periode
    {
        $tw3 = Periode::firstOrCreate(['nama' => 'Triwulan III', 'urutan' => 3], ['aktif' => true, 'is_nilai_akhir' => false, 'bulan_mulai' => 7, 'hari_mulai' => 1,
            'bulan_selesai' => 9, 'hari_selesai' => 30]);
        PeriodeJadwal::create(['jadwal_id' => $this->fixture['jadwal']->id, 'periode_id' => $tw3->id, 'pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => '2026-09-30']);

        return $tw3;
    }

    private function persyaratanPerencanaan(): JenisBerkas
    {
        return JenisBerkas::create(['nama' => 'RAB', 'tahap' => 'kegiatan_perencanaan', 'mode' => 'file', 'wajib' => true,
            'format_diizinkan' => 'xlsx,pdf', 'ukuran_maks_kb' => 10240, 'aktif' => true, 'created_by' => $this->fixture['perencanaan']->id]);
    }

    /** @return array{bool, list<array{id: string, nama: string}>} Tunda ditawarkan dan periode tujuan di form kegiatan RA. */
    private function pilihanTunda(string $aktor): array
    {
        $props = $this->actingAs($this->fixture[$aktor])->get("/rencana-aksi/{$this->header->id}/kegiatan/{$this->asal->id}")
            ->assertOk()->viewData('page')['props'];

        return [in_array('ditunda', $props['can']['ubah_status'], true), $props['opsiPeriodeTujuan']];
    }

    /** @param array<string, mixed> $data */
    private function tunda(array $data = [], ?Kegiatan $kegiatan = null, string $aktor = 'pic', bool $json = false): TestResponse
    {
        $kegiatan ??= $this->asal;
        $payload = ['status' => 'ditunda', 'justifikasi' => 'Narasumber berhalangan.', 'periode_tujuan_id' => $this->fixture['periode2']->id,
            'expected_state' => $kegiatan->fresh()->versionToken(), ...$data];
        $this->actingAs($this->fixture[$aktor]);

        return $json ? $this->postJson("/kegiatan/{$kegiatan->id}/status", $payload) : $this->post("/kegiatan/{$kegiatan->id}/status", $payload);
    }
}
