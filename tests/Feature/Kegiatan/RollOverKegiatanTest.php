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
use App\Models\Unit;
use App\Services\Kegiatan\TotalAnggaranKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Kegiatan lanjutan hasil transisi `ditunda` (Q41 §41.4, Q69 §69.5–§69.6): kunci periode tujuan bagi PIC,
 * klaim tahap pengukuran pada RA pengklaim kegiatan asal, berkas tidak disalin, dan total anggaran.
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
        $this->fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        // Draf memuat "Kegiatan pendukung" beranggaran 0 di Triwulan I yang diklaim tahap RA.
        $this->header = $this->drafManual($this->fixture);
        $this->grant($this->fixture['pic'], 'kegiatan:update', $this->fixture['unit']->id, $this->fixture['perencanaan']);
        $this->asal = Kegiatan::where('nama', 'Kegiatan pendukung')->sole();
        KlaimKegiatan::where('kegiatan_id', $this->asal->id)->update(['arah_dampak' => 'mengurangi', 'catatan' => 'Catatan klaim asal']);
    }

    public function test_tunda_membentuk_lanjutan_tahun_sama_tanpa_berkas_dan_asal_tetap_ditunda(): void
    {
        $rab = BuktiDukung::create(['berkasable_type' => 'kegiatan', 'berkasable_id' => $this->asal->id,
            'jenis_berkas_id' => $this->persyaratanPerencanaan()->id, 'mode' => 'file', 'path' => 'berkas/kegiatan/rab.xlsx',
            'nama_asli' => 'rab.xlsx', 'uploaded_by' => $this->fixture['pic']->id, 'created_at' => now()]);

        $this->tunda()->assertRedirect();

        $asal = $this->asal->fresh();
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $asal->id)->sole();
        $this->assertSame(['ditunda', 2026, 2026], [$asal->status, (int) $asal->tahun, (int) $lanjutan->tahun]);
        $this->assertSame($this->fixture['periode2']->id, $lanjutan->periode_id);
        // Berkas asal, termasuk RAB, tetap milik asal dan tidak disalin ke lanjutan (Q41 §41.4 butir 5).
        $this->assertSame($asal->id, $rab->fresh()->berkasable_id);
        $this->assertFalse(BuktiDukung::where('berkasable_type', 'kegiatan')->where('berkasable_id', $lanjutan->id)->exists());
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.roll_over')->exists());
    }

    public function test_klaim_pengukuran_langsung_dibuat_bila_baris_pengukuran_periode_tujuan_sudah_ada(): void
    {
        $this->header->forceFill(['status_alur' => RencanaAksi::STATUS_DISAHKAN])->save();
        $versi = $this->header->fresh()->versi;
        // Pengukuran tujuan yang sudah disahkan tetap menerima klaim data kerja; versinya yang beku tidak disentuh.
        $pengukuran = $this->pengukuran($this->fixture['periode2'], 'disahkan');

        $this->tunda()->assertRedirect();

        $lanjutan = Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();
        $klaim = KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran', $pengukuran->id, null, 'mengurangi', 'Catatan klaim asal'],
            [$klaim->rencana_aksi_id, $klaim->sumber_klaim, $klaim->pengukuran_id, $klaim->komponen_id, $klaim->arah_dampak, $klaim->catatan]);
        // RA yang sudah disahkan tidak dibuka kembali untuk memindahkan kegiatan (Q41 §41.4 butir 3).
        $this->assertSame([RencanaAksi::STATUS_DISAHKAN, $versi], [$this->header->fresh()->status_alur, $this->header->fresh()->versi]);
        $geser = AuditLog::where('tindakan', 'kegiatan.geser_periode')->sole();
        $this->assertSame([$klaim->id], array_column($geser->nilai_baru['klaim'], 'id'));
        $this->assertSame($pengukuran->id, $geser->nilai_baru['klaim'][0]['pengukuran_id']);
        $this->assertFalse(AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->exists());
    }

    public function test_klaim_pengukuran_dibuat_saat_baris_pengukuran_periode_tujuan_terbentuk(): void
    {
        $this->tunda()->assertRedirect();
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();
        $this->assertFalse(KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->exists());
        $this->assertSame([], AuditLog::where('tindakan', 'kegiatan.geser_periode')->sole()->nilai_baru['klaim']);

        $this->bentukDraf()->assertSessionHasNoErrors();
        $pengukuran = PengukuranKinerja::where('periode_id', $this->fixture['periode2']->id)->sole();

        $klaim = KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran', $pengukuran->id, 'mengurangi', 'Catatan klaim asal'],
            [$klaim->rencana_aksi_id, $klaim->sumber_klaim, $klaim->pengukuran_id, $klaim->arah_dampak, $klaim->catatan]);
        $audit = AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->sole();
        $this->assertSame([$this->fixture['perencanaan']->id, $klaim->id, $this->asal->id, $pengukuran->id],
            [$audit->actor_id, $audit->objek_id, $audit->nilai_baru['kegiatan_asal_id'], $audit->nilai_baru['pengukuran_id']]);
        $this->assertNotEmpty($audit->dasar_izin);
        // Panel kegiatan RA menautkan asal dan lanjutan dua arah.
        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('kegiatan.daftar', fn ($daftar) => collect($daftar)->mapWithKeys(fn ($row) => [$row['kegiatan_id'] => [$row['asal'], $row['lanjutan']]])->all() === [
                $this->asal->id => [null, ['id' => $lanjutan->id, 'periode_id' => $this->fixture['periode2']->id]],
                $lanjutan->id => [['id' => $this->asal->id, 'periode_id' => $this->fixture['periode1']->id], null],
            ]));

        // Pembukaan ulang draf yang sudah ada tidak menggandakan klaim.
        $this->bentukDraf()->assertSessionHasNoErrors();
        $this->assertSame(1, KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->count());
    }

    public function test_rantai_lanjutan_meneruskan_klaim_pengukuran_ke_ra_yang_sama(): void
    {
        $tw3 = $this->triwulanTiga();
        $this->pengukuran($this->fixture['periode2']);
        $tw3Pengukuran = $this->pengukuran($tw3);
        $this->tunda()->assertRedirect();
        $b = Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();

        // B hanya memiliki klaim tahap pengukuran; klaim itu tetap diteruskan ke C (Q69 §69.6 butir 3).
        $this->tunda(['periode_tujuan_id' => $tw3->id], $b)->assertRedirect();

        $c = Kegiatan::where('kegiatan_asal_id', $b->id)->sole();
        $klaim = KlaimKegiatan::where('kegiatan_id', $c->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran', $tw3Pengukuran->id], [$klaim->rencana_aksi_id, $klaim->sumber_klaim, $klaim->pengukuran_id]);
    }

    public function test_rantai_dengan_perantara_belum_diklaim_tetap_meneruskan_klaim_rantai_asal(): void
    {
        // A ditunda ke B dan B ke C sebelum baris pengukuran TW II maupun TW III ada: B dan C belum berklaim.
        $tw3 = $this->triwulanTiga();
        $this->tunda()->assertRedirect();
        $b = Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();
        $this->tunda(['periode_tujuan_id' => $tw3->id], $b, 'perencanaan')->assertRedirect();
        $c = Kegiatan::where('kegiatan_asal_id', $b->id)->sole();
        $this->assertSame(0, KlaimKegiatan::whereIn('kegiatan_id', [$b->id, $c->id])->count());

        // Baris TW III terbentuk: C diklaim dari klaim A walau perantara B belum diklaim.
        $this->bentukDraf($tw3)->assertSessionHasNoErrors();
        $klaimC = KlaimKegiatan::where('kegiatan_id', $c->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran', PengukuranKinerja::where('periode_id', $tw3->id)->value('id')],
            [$klaimC->rencana_aksi_id, $klaimC->sumber_klaim, $klaimC->pengukuran_id]);
        $this->assertSame($b->id, AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->sole()->nilai_baru['kegiatan_asal_id']);

        // Baris TW II terbentuk kemudian: B ikut diklaim tanpa menggandakan klaim C.
        $this->bentukDraf()->assertSessionHasNoErrors();
        $this->assertSame([1, 1], [KlaimKegiatan::where('kegiatan_id', $b->id)->count(), KlaimKegiatan::where('kegiatan_id', $c->id)->count()]);
    }

    public function test_lanjutan_yang_dipindah_unit_tidak_diklaim_ke_ra_unit_asal(): void
    {
        $this->tunda()->assertRedirect();
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();
        // Lanjutan tanpa klaim masih boleh dipindah unit oleh SimpanKegiatan; klaim lintas unit tetap ditolak (Data Model §2.26).
        $unitLain = Unit::create(['nama' => 'Unit lain', 'status' => 'aktif', 'created_by' => $this->fixture['perencanaan']->id]);
        $lanjutan->forceFill(['unit_id' => $unitLain->id])->save();

        $this->bentukDraf()->assertSessionHasNoErrors();

        $this->assertFalse(KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->exists());
        $this->assertFalse(AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->exists());
    }

    public function test_klaim_asal_yang_ditambahkan_belakangan_diteruskan_ke_lanjutan(): void
    {
        // Klaim tetap boleh pada setiap status (Q69 §69.3 butir 3): klaim asal dihapus sebelum ditunda lalu dibuat ulang.
        $pengukuran = $this->pengukuran($this->fixture['periode2']);
        KlaimKegiatan::where('kegiatan_id', $this->asal->id)->delete();
        $this->tunda()->assertRedirect();
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();
        $this->assertFalse(KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->exists());

        $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->asal->id}/klaim",
            ['rencana_aksi_id' => $this->header->id, 'arah_dampak' => 'menambah'])->assertSessionHasNoErrors();

        $klaim = KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->sole();
        $this->assertSame([$this->header->id, 'pengukuran', $pengukuran->id], [$klaim->rencana_aksi_id, $klaim->sumber_klaim, $klaim->pengukuran_id]);
        $audit = AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->where('objek_id', $klaim->id)->sole();
        $this->assertSame([$this->fixture['pic']->id, $this->asal->id], [$audit->actor_id, $audit->nilai_baru['kegiatan_asal_id']]);
    }

    public function test_daftar_kegiatan_menilai_kunci_periode_tujuan_tanpa_query_per_baris(): void
    {
        $hitung = function (): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->fixture['pic'])->get('/kegiatan?tahun=2026')->assertOk();

            return array_column(DB::getQueryLog(), 'query');
        };
        $tambah = function (string $nama): void {
            $kegiatan = Kegiatan::create(['unit_id' => $this->fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $this->fixture['periode1']->id,
                'nama' => $nama, 'anggaran' => 0, 'created_by' => $this->fixture['pic']->id]);
            KlaimKegiatan::create(['rencana_aksi_id' => $this->header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
                'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);
        };
        // Pembanding sudah memuat satu baris berklaim agar query sekali per halaman ikut terhitung di kedua sisi;
        // permintaan pertama hanya memanaskan cache izin.
        $tambah('Bimtek');
        $hitung();
        $awal = $hitung();
        array_map($tambah, ['Monev', 'Rapat', 'Rakor']);

        $this->assertCount(count($awal), $hitung());
    }

    public function test_pembentukan_draf_mengunci_advisory_dan_header_ra_sebelum_indikator(): void
    {
        // Urutan sama dengan transisi ditunda (advisory → header RA) dan writer RA (header RA → indikator) agar klaim tertunda tidak deadlock.
        DB::enableQueryLog();
        $this->bentukDraf()->assertSessionHasNoErrors();
        $sql = array_column(DB::getQueryLog(), 'query');
        $posisi = fn (string $pola): ?int => array_key_first(array_filter($sql, fn (string $query): bool => preg_match($pola, $query) === 1));

        $advisory = $posisi('/pg_advisory_xact_lock/');
        $headerRa = $posisi('/from "rencana_aksi".*for key share/');
        $indikator = $posisi('/from "indikator_kinerjas".*for update/');
        $this->assertNotContains(null, [$advisory, $headerRa, $indikator]);
        $this->assertTrue($advisory < $headerRa && $headerRa < $indikator, "advisory {$advisory}, header RA {$headerRa}, indikator {$indikator}");
    }

    public function test_periode_tujuan_terkunci_ditolak_bagi_pic_tetapi_tidak_bagi_perencanaan(): void
    {
        // Lewat pengisian_selesai Triwulan II (31 Maret) pada jadwal RA pengklaim (Q69 §69.5 butir 2).
        $this->travelTo(now()->setDate(2026, 4, 15)->setTime(9, 0));
        $jumlah = Kegiatan::count();
        // Daftar dan form tidak menawarkan Tunda bila satu-satunya periode tujuan terkunci bagi PIC; Perencanaan tetap ditawari.
        $this->assertSame([false, [], false, []], $this->pilihanTunda('pic', $this->asal));
        $tujuan = [['id' => $this->fixture['periode2']->id, 'nama' => 'Triwulan II']];
        $this->assertSame([true, $tujuan, true, $tujuan], $this->pilihanTunda('perencanaan', $this->asal));

        $this->tunda(json: true)->assertUnprocessable()->assertJsonValidationErrors('periode_tujuan_id');

        $this->assertSame([$jumlah, 'rencana'], [Kegiatan::count(), $this->asal->fresh()->status]);
        $ditolak = AuditLog::where('tindakan', 'kegiatan.ubah_status_ditolak')->sole();
        $this->assertSame('Periode tujuan terkunci bagi PIC setelah batas pengisian lewat: Triwulan II (batas 2026-03-31).', $ditolak->alasan);

        // Kontrol: kegiatan tanpa RA pengklaim tidak memiliki kunci periode Rencana Aksi.
        $bebas = Kegiatan::create(['unit_id' => $this->fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $this->fixture['periode1']->id,
            'nama' => 'Kegiatan tanpa klaim', 'anggaran' => 0, 'created_by' => $this->fixture['pic']->id]);
        $this->assertSame([true, $tujuan, true, $tujuan], $this->pilihanTunda('pic', $bebas));
        $this->tunda(kegiatan: $bebas)->assertRedirect();

        // Perencanaan dikecualikan dari kunci periode sampai penutupan (Q41 §41.2).
        $this->tunda(aktor: 'perencanaan')->assertRedirect();
        $this->assertSame('ditunda', $this->asal->fresh()->status);
    }

    public function test_anggaran_lanjutan_disalin_dapat_diubah_dan_total_mengikuti_klaim(): void
    {
        $this->asal->update(['anggaran' => 2500000]);
        $this->actingAs($this->fixture['pic'])->post("/rencana-aksi/{$this->header->id}/ajukan", ['expected_versi' => $this->header->fresh()->versi])
            ->assertSessionHasNoErrors();
        $beku = $this->totalBeku();
        $total = app(TotalAnggaranKegiatan::class);

        $this->tunda()->assertRedirect();
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $this->asal->id)->sole();
        $this->assertSame('2500000', $lanjutan->anggaran);
        // Lanjutan belum diklaim: asal tetap terhitung agar anggarannya tidak hilang (Q69 §69.6 butir 1).
        $this->assertSame('2500000', $total->perRencanaAksi($this->header->fresh())['total']);

        $this->bentukDraf()->assertSessionHasNoErrors();
        // Setelah lanjutan diklaim, hanya lanjutan yang terhitung.
        $this->assertSame(['2500000', ['total' => '2500000', 'belum_diisi' => 0]],
            [$total->perRencanaAksi($this->header->fresh())['total'], $total->perRencanaAksi($this->header->fresh())['per_periode'][$this->fixture['periode2']->id]]);

        $token = $this->actingAs($this->fixture['pic'])->get("/kegiatan/{$lanjutan->id}/edit")->assertOk()->viewData('page')['props']['kegiatan']['version_token'];
        $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$lanjutan->id}", [
            ...$lanjutan->only(['unit_id', 'tahun', 'periode_id', 'nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi', 'tanggal_rencana']),
            'anggaran' => 3000000, 'expected_state' => $token,
        ])->assertSessionHasNoErrors();
        $this->assertSame('3000000', $lanjutan->fresh()->anggaran);
        // Total beku pada versi RA tidak berubah (Q42 §42.2 butir 6).
        $this->assertSame($beku, $this->totalBeku());
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
        $tw3 = Periode::create(['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->fixture['jadwal']->id, 'periode_id' => $tw3->id, 'pengisian_mulai' => '2026-03-01',
            'pengisian_selesai' => '2026-09-30']);

        return $tw3;
    }

    private function persyaratanPerencanaan(): JenisBerkas
    {
        return JenisBerkas::create(['nama' => 'RAB', 'tahap' => 'kegiatan_perencanaan', 'mode' => 'file', 'wajib' => true,
            'format_diizinkan' => 'xlsx,pdf', 'ukuran_maks_kb' => 10240, 'aktif' => true, 'created_by' => $this->fixture['perencanaan']->id]);
    }

    /** @return array{bool, list<array{id: string, nama: string}>, bool, list<array{id: string, nama: string}>} Tunda dan periode tujuan di daftar lalu form. */
    private function pilihanTunda(string $aktor, Kegiatan $kegiatan): array
    {
        $this->actingAs($this->fixture[$aktor]);
        $baris = collect($this->get('/kegiatan?tahun=2026')->assertOk()->viewData('page')['props']['kegiatan']['data'])->firstWhere('id', $kegiatan->id);
        $form = $this->get("/kegiatan/{$kegiatan->id}/edit")->assertOk()->viewData('page')['props'];

        return [in_array('ditunda', $baris['can']['ubah_status'], true), $baris['opsi_periode_tujuan'],
            in_array('ditunda', $form['can']['ubah_status'], true), $form['opsiPeriodeTujuan']];
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
