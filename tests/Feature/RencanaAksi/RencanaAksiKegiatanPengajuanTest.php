<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Unit;
use App\Models\User;
use App\Services\Kegiatan\AnggaranKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Gerbang minimal satu kegiatan tahap RA saat pengajuan (Q42 §42.1 butir 3),
 * pengecualian Q46 §46.1, dan pembekuan klaim ke snapshot versi.
 */
class RencanaAksiKegiatanPengajuanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    private const PESAN = 'Rencana aksi wajib memiliki minimal satu kegiatan yang diklaim pada tahap rencana aksi.';

    public function test_pic_tanpa_kegiatan_ditolak_dan_halaman_menampilkan_alasan(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');

        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.pengajuan.siap', false)
                ->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => collect($alasan)->contains(self::PESAN)));
    }

    public function test_klaim_dibekukan_ke_snapshot_versi_dan_tidak_ikut_berubah(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $fixture['periode2']->id,
            'nama' => 'Bimbingan teknis', 'tujuan' => 'Tujuan beku', 'anggaran' => 0, 'created_by' => $fixture['pic']->id]);
        $klaim = KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'mengurangi',
            'catatan' => 'Catatan klaim', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $fixture['pic']->id, 'created_at' => now()]);

        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $kegiatan->update(['nama' => 'Nama berubah setelah diajukan']);

        $this->assertEquals([[
            'id' => $klaim->id, 'kegiatan_id' => $kegiatan->id, 'komponen_id' => null, 'arah_dampak' => 'mengurangi', 'catatan' => 'Catatan klaim',
            'periode_id' => $fixture['periode2']->id,
            'kegiatan' => ['nama' => 'Bimbingan teknis', 'tujuan' => 'Tujuan beku', 'anggaran' => '0', 'status' => 'rencana'],
        ]], RencanaAksiVersi::sole()->snapshot['klaim']);
    }

    public function test_data_awal_2026_lewat_jalur_perencanaan_dikecualikan(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');

        $this->ajukan($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $versi = RencanaAksiVersi::sole();
        $this->assertSame('perencanaan', $versi->jalur_pengajuan);
        $this->assertSame([], $versi->snapshot['klaim']);
    }

    public function test_ra_seluruh_periode_efektif_lampau_dikecualikan_hanya_bagi_perencanaan(): void
    {
        // Q63: PIC tidak pernah dikecualikan; Perencanaan dikecualikan pada RA seluruh-periode-lampau.
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-04-05 09:00:00');

        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);

        $this->ajukan($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame('perencanaan', RencanaAksiVersi::sole()->jalur_pengajuan);
    }

    public function test_ra_2027_dengan_seluruh_periode_efektif_lewat_tetap_memerlukan_kegiatan(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-04-05 09:00:00');
        $fixture['jadwal']->update(['tahun' => 2027, 'activated_at' => '2027-04-05 09:00:00', 'penutupan' => '2027-12-31']);
        $fixture['jadwal']->periode()->update(['pengisian_mulai' => '2027-03-01', 'pengisian_selesai' => '2027-03-31',
        ]);
        $header->update(['tahun' => 2027]);
        $this->travelTo(now()->setDate(2027, 3, 10)->setTime(9, 0));

        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.pengajuan.siap', false)
                ->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => collect($alasan)->contains(self::PESAN)));
    }

    public function test_klaim_yang_tidak_cocok_dengan_konteks_beku_terbaru_menolak_pengajuan(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        // Periode di luar jadwal RA: klaim tidak lagi sah terhadap periode efektif beku.
        $asing = Periode::create(['nama' => 'Periode Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $asing->id,
            'nama' => 'Kegiatan basi', 'created_by' => $fixture['pic']->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $fixture['pic']->id, 'created_at' => now()]);

        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors('pengajuan');
        $this->assertStringContainsString('Kegiatan basi', session('errors')->first('pengajuan'));
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_kesiapan_halaman_menampilkan_klaim_yang_tidak_cocok_dan_review_menampilkan_klaim_beku(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $asing = Periode::create(['nama' => 'Periode Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);
        $basi = $this->klaimKegiatan($fixture, $header, $asing->id, 'Kegiatan basi');
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.siap', false)
            ->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => collect($alasan)->contains(fn ($teks) => str_contains($teks, 'Kegiatan basi'))));

        KlaimKegiatan::whereKey($basi->id)->delete();
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Bimtek terklaim');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}/review")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.klaim.0.nama', 'Bimtek terklaim')
            ->where('rencanaAksi.klaim.0.periode_nama', 'Triwulan I'));
    }

    public function test_pic_tidak_dapat_mengajukan_perubahan_daftar_kegiatan_periode_terkunci_dibanding_versi_sah(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        DB::table('rencana_aksi_versi')->where('rencana_aksi_id', $header->id)->update(['disahkan_by' => $fixture['perencanaan']->id, 'disahkan_at' => now()]);
        $header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);

        // Periode I terkunci bagi PIC; tanpa perubahan daftar kegiatan pengajuan PIC tetap lolos gerbang kunci.
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Ditambah Perencanaan');
        // Halaman tidak menawarkan pengajuan yang pasti ditolak gerbang delta klaim.
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.submit', false));
        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors('jendela');

        $this->ajukan($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame(2, RencanaAksiVersi::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_versi_sah_lama_tanpa_key_klaim_tetap_menjaga_daftar_kegiatan_periode_terkunci(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        // Versi sah dibentuk sebelum klaim dibekukan: snapshot tanpa key `klaim`. Trigger
        // versi beku dimatikan sementara (ikut rollback transaksi test) untuk meniru data lama.
        $versi = RencanaAksiVersi::sole();
        $snapshot = $versi->snapshot;
        unset($snapshot['klaim']);
        DB::statement('ALTER TABLE rencana_aksi_versi DISABLE TRIGGER USER');
        DB::table('rencana_aksi_versi')->where('id', $versi->id)->update(['snapshot' => json_encode($snapshot),
            'disahkan_by' => $fixture['perencanaan']->id, 'disahkan_at' => now()]);
        DB::statement('ALTER TABLE rencana_aksi_versi ENABLE TRIGGER USER');
        $header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);

        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Ditambah Perencanaan');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors('jendela');

        $this->ajukan($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame(2, RencanaAksiVersi::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_deny_baca_kegiatan_menyembunyikan_nama_pada_alasan_klaim_tidak_cocok(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $asing = Periode::create(['nama' => 'Periode Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->klaimKegiatan($fixture, $header, $asing->id, 'Kegiatan rahasia');
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $fixture['pic']->id,
            'permission_id' => Permission::where('kode', 'kegiatan:read')->value('id'), 'unit_id' => $fixture['unit']->id,
            'alasan' => 'Pengujian deny baca kegiatan', 'ditetapkan_oleh' => $fixture['perencanaan']->id, 'created_at' => now()]);
        $pesan = '1 klaim kegiatan tidak cocok dengan konteks beku terbaru rencana aksi; hapus atau perbaiki klaim tersebut.';

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertDontSee('Kegiatan rahasia')
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => collect($alasan)->contains($pesan)));
        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => $pesan]);
        $this->assertStringNotContainsString('Kegiatan rahasia', implode(' ', session('errors')->get('pengajuan')));
    }

    public function test_deny_baca_kegiatan_pada_unit_menyembunyikan_klaim_beku_di_review(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan rahasia');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $fixture['perencanaan']->id,
            'permission_id' => Permission::where('kode', 'kegiatan:read')->value('id'), 'unit_id' => $fixture['unit']->id,
            'alasan' => 'Pengujian deny baca kegiatan', 'ditetapkan_oleh' => $fixture['perencanaan']->id, 'created_at' => now()]);

        // Mengikuti pola bukti dukung: detail disembunyikan, keputusan review tidak bergantung padanya.
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}/review")->assertOk()
            ->assertDontSee('Kegiatan rahasia')
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.klaim', null)->where('rencanaAksi.can.kegiatan', false));
    }

    public function test_klaim_ditolak_bila_unit_snapshot_terbaru_tidak_selaras_dengan_header(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $unitBaru = Unit::create(['nama' => 'Unit Pindahan', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $this->terbitkanSnapshotKoreksi($fixture, ['unit_id' => $unitBaru->id]);
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $fixture['periode1']->id,
            'nama' => 'Kegiatan unit lama', 'created_by' => $fixture['perencanaan']->id]);

        $this->actingAs($fixture['perencanaan'])->post("/kegiatan/{$kegiatan->id}/klaim", ['rencana_aksi_id' => $header->id, 'arah_dampak' => 'menambah'])
            ->assertSessionHasErrors('rencana_aksi_id');
        $this->assertSame(0, KlaimKegiatan::where('rencana_aksi_id', $header->id)->count());
    }

    /** @return array<string, array{string, string}> */
    public static function atributRencanaKegiatan(): array
    {
        return ['nama' => ['nama', 'Nama rencana diperbarui'], 'tujuan' => ['tujuan', 'Tujuan rencana diperbarui'], 'anggaran' => ['anggaran', '2500000']];
    }

    #[DataProvider('atributRencanaKegiatan')]
    public function test_perubahan_atribut_rencana_kegiatan_menjaga_periode_terkunci(string $field, string $nilai): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $klaim = $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        DB::table('rencana_aksi_versi')->where('rencana_aksi_id', $header->id)->update(['disahkan_by' => $fixture['perencanaan']->id, 'disahkan_at' => now()]);
        $header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));
        $this->grant($fixture['pic'], 'kegiatan:update', $fixture['unit']->id, $fixture['perencanaan']);
        $kegiatan = Kegiatan::findOrFail($klaim->kegiatan_id);

        $this->actingAs($fixture['pic'])->post("/kegiatan/{$kegiatan->id}", [
            ...$kegiatan->rencanaAttributes(), 'expected_state' => $kegiatan->versionToken(), $field => $nilai,
        ])->assertSessionHasNoErrors();
        $this->assertSame($nilai, $kegiatan->fresh()->getAttribute($field));
        $this->ajukan($fixture['pic'], $header)->assertSessionHasErrors('jendela');
        $this->assertDatabaseCount('rencana_aksi_versi', 1);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.submit', false));
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('objek_id', $header->id)->count());

        $this->ajukan($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame($nilai, RencanaAksiVersi::orderByDesc('nomor')->firstOrFail()->snapshot['klaim'][0]['kegiatan'][$field]);
    }

    public function test_status_pelaksanaan_saja_tidak_mengubah_delta_rencana_periode_terkunci(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $klaim = $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal');
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        DB::table('rencana_aksi_versi')->where('rencana_aksi_id', $header->id)->update(['disahkan_by' => $fixture['perencanaan']->id, 'disahkan_at' => now()]);
        $header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));
        Kegiatan::findOrFail($klaim->kegiatan_id)->update(['status' => 'batal', 'justifikasi' => 'Kendala pelaksanaan']);

        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $this->assertSame('batal', RencanaAksiVersi::orderByDesc('nomor')->firstOrFail()->snapshot['klaim'][0]['kegiatan']['status']);
        $this->assertSame('rencana', RencanaAksiVersi::orderBy('nomor')->firstOrFail()->snapshot['klaim'][0]['kegiatan']['status']);
    }

    /** @return array<string, array{string}> */
    public static function mutasiDaftarKlaim(): array
    {
        return ['tambah klaim' => ['tambah'], 'hapus klaim' => ['hapus'], 'tambah dan salin kegiatan dari RA' => ['kegiatan']];
    }

    #[DataProvider('mutasiDaftarKlaim')]
    public function test_mutasi_daftar_klaim_menolak_pengajuan_dengan_versi_lama(string $mutasi): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $klaim = $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal');
        $this->klaimKegiatan($fixture, $header, $fixture['periode2']->id, 'Kegiatan lain');
        $versiLama = $header->fresh()->versi;
        $aktorLain = $this->actingAs($fixture['perencanaan']);
        if ($mutasi === 'tambah') {
            $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $fixture['periode1']->id,
                'nama' => 'Kegiatan baru', 'anggaran' => 0, 'created_by' => $fixture['perencanaan']->id]);
            $aktorLain->post("/kegiatan/{$kegiatan->id}/klaim", ['rencana_aksi_id' => $header->id, 'arah_dampak' => 'menambah'])->assertSessionHasNoErrors();
        } elseif ($mutasi === 'hapus') {
            $aktorLain->delete("/kegiatan/{$klaim->kegiatan_id}/klaim/{$klaim->id}", ['alasan' => 'Klaim keliru'])->assertSessionHasNoErrors();
        } else {
            $aktorLain->post("/rencana-aksi/{$header->id}/kegiatan", ['periode_id' => $fixture['periode1']->id,
                'salin_ke_periode_ids' => [$fixture['periode2']->id], 'nama' => 'Kegiatan dari RA', 'anggaran' => 0, 'arah_dampak' => 'menambah'])->assertSessionHasNoErrors();
        }

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $versiLama])
            ->assertConflict()->assertJsonValidationErrors('expected_versi');
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->assertSame($versiLama + 1, $header->fresh()->versi);
        $this->assertSame(RencanaAksi::STATUS_DRAFT, $header->fresh()->status_alur);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('objek_id', $header->id)->count());
        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rencana_aksi_versi', 1);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function atributKegiatanTerklaim(): array
    {
        return ['nama' => ['nama', 'Nilai yang diubah pengguna lain', true], 'tujuan' => ['tujuan', 'Nilai yang diubah pengguna lain', true],
            'anggaran' => ['anggaran', '750000', true], 'lokasi di luar snapshot' => ['lokasi', 'Nilai yang diubah pengguna lain', false]];
    }

    #[DataProvider('atributKegiatanTerklaim')]
    public function test_perubahan_atribut_beku_kegiatan_menolak_pengajuan_dengan_versi_lama(string $field, string $nilai, bool $beku): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $kegiatan = Kegiatan::findOrFail($this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal')->kegiatan_id);
        $versiLama = $header->fresh()->versi;

        $this->actingAs($fixture['perencanaan'])->post("/kegiatan/{$kegiatan->id}", [
            ...$kegiatan->rencanaAttributes(), 'expected_state' => $kegiatan->versionToken(), $field => $nilai,
        ])->assertSessionHasNoErrors();

        $this->assertSame($versiLama + ($beku ? 1 : 0), $header->fresh()->versi);
        if ($beku) {
            $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $versiLama])
                ->assertConflict()->assertJsonValidationErrors('expected_versi');
            $this->assertDatabaseCount('rencana_aksi_versi', 0);
        }
    }

    /**
     * Endpoint tambah kegiatan global sudah dihapus (Q74 §74.1), sehingga aturan
     * `AnggaranKegiatan::bolehKosong` diuji lewat ubah — jalur yang tidak melewati
     * gerbang periode Rencana Aksi: kosong sah untuk periode lampau (Q46 §46.1 butir 3)
     * dan wajib diisi pada periode berjalan.
     */
    public function test_kegiatan_periode_lampau_boleh_tanpa_anggaran_sedangkan_periode_berjalan_wajib(): void
    {
        $fixture = $this->buatFixtureManual();
        // Aktivasi 15 April 2026: Triwulan I (batas 31 Maret) lampau, Triwulan II (batas 30 Juni) berjalan.
        $fixture['jadwal']->update(['activated_at' => '2026-04-15 09:00:00']);
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode2']->id)->update(['pengisian_selesai' => '2026-06-30']);
        $this->grant($fixture['pic'], 'kegiatan:update', $fixture['unit']->id, $fixture['perencanaan']);

        $buat = fn (Periode $periode): Kegiatan => Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026,
            'periode_id' => $periode->id, 'nama' => 'Kegiatan tanpa anggaran', 'anggaran' => 0, 'created_by' => $fixture['pic']->id]);
        $lampau = $buat($fixture['periode1']);
        $berjalan = $buat($fixture['periode2']);
        $ubah = fn (Kegiatan $kegiatan): TestResponse => $this->actingAs($fixture['pic'])->post("/kegiatan/{$kegiatan->id}", [
            ...$kegiatan->rencanaAttributes(), 'anggaran' => null, 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]);

        $ubah($lampau)->assertSessionHasNoErrors();
        $this->assertNull($lampau->fresh()->anggaran);

        $ubah($berjalan)->assertSessionHasErrors(['anggaran' => AnggaranKegiatan::PESAN_WAJIB]);
        $this->assertSame('0', $berjalan->fresh()->anggaran);
    }

    public function test_pic_menambah_kegiatan_dari_ra_tanpa_anggaran_ditolak_tanpa_mutasi(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $this->grant($fixture['pic'], 'kegiatan:create', $fixture['unit']->id, $fixture['perencanaan']);
        $versi = $header->fresh()->versi;

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/kegiatan", ['periode_id' => $fixture['periode1']->id,
            'nama' => 'Kegiatan tanpa anggaran', 'arah_dampak' => 'menambah'])->assertSessionHasErrors(['anggaran' => AnggaranKegiatan::PESAN_WAJIB]);
        $this->assertFalse(Kegiatan::where('nama', 'Kegiatan tanpa anggaran')->exists());
        $this->assertSame($versi, $header->fresh()->versi);
    }

    /**
     * Draf manual lengkap tanpa klaim tahap RA, dengan waktu aktivasi jadwal
     * ditetapkan eksplisit agar status periode lampau tidak bergantung tanggal run.
     *
     * @return array{0: array<string, mixed>, 1: RencanaAksi}
     */
    private function drafTanpaKegiatan(string $aktivasi): array
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => $aktivasi]);
        $header = $this->drafManual($fixture);
        KlaimKegiatan::where('rencana_aksi_id', $header->id)->delete();

        return [$fixture, $header];
    }

    /** @param array<string, mixed> $fixture */
    private function klaimKegiatan(array $fixture, RencanaAksi $header, string $periodeId, string $nama): KlaimKegiatan
    {
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $periodeId, 'nama' => $nama, 'anggaran' => 0, 'created_by' => $fixture['pic']->id]);

        return KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $fixture['pic']->id, 'created_at' => now()]);
    }

    private function ajukan(User $aktor, RencanaAksi $header): TestResponse
    {
        return $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $header->fresh()->versi]);
    }
}
