<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\AjukanRencanaAksi;
use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\Pengaturan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Pengajuan rencana aksi: gerbang server di bawah kunci, pembekuan satu
 * baris `rencana_aksi_versi`, provenance pengaju, dan audit sukses/tolak.
 */
class RencanaAksiAjukanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    private const ALASAN_TOLAK_OTORISASI = 'Percobaan pengajuan rencana aksi ditolak oleh sistem otorisasi.';

    public function test_pic_mengajukan_draf_lengkap_membentuk_versi_beku(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);

        $this->ajukan($fixture['pic'], $header, 2)
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Rencana aksi berhasil diajukan untuk verifikasi.');

        $header->refresh();
        $this->assertSame('diajukan', $header->status_alur);
        $this->assertSame(3, $header->versi);

        $versi = RencanaAksiVersi::sole();
        $this->assertSame(1, $versi->nomor);
        $this->assertSame($header->id, $versi->rencana_aksi_id);
        $this->assertSame($fixture['snapshot']->id, $versi->jadwal_snapshot_id);
        $this->assertSame($fixture['pic']->id, $versi->diajukan_by);
        $this->assertSame('pic', $versi->jalur_pengajuan);
        $this->assertSame('rencana_aksi:ajukan', $versi->dasar_izin_pengajuan['permission']);
        $this->assertSame($fixture['unit']->id, $versi->dasar_izin_pengajuan['unit_id']);
        $this->assertSame($fixture['pic']->id, $versi->dasar_izin_pengajuan['pic_id']);
        $this->assertIsString($versi->dasar_izin_pengajuan['penugasan_id']);

        // JSONB tidak mempertahankan urutan key objek, maka objek dibandingkan tanpa urutan.
        $snapshot = $versi->snapshot;
        $this->assertEqualsCanonicalizing(
            ['indikator', 'unit_kerja', 'pic', 'uraian', 'alasan_deviasi_pk', 'deviasi_pk', 'target_periode', 'klaim', 'persyaratan_bukti', 'bukti_dukungs'],
            array_keys($snapshot),
        );
        $this->assertEquals(['kode' => $fixture['indikator']->kode, 'nama' => 'Indikator Manual Uji'], $snapshot['indikator']);
        $this->assertEquals(['id' => $fixture['unit']->id, 'nama' => $fixture['unit']->nama], $snapshot['unit_kerja']);
        $this->assertEquals(['id' => $fixture['pic']->id, 'nama' => $fixture['pic']->nama], $snapshot['pic']);
        $this->assertNull($snapshot['uraian']);
        $this->assertNull($snapshot['alasan_deviasi_pk']);
        $this->assertEquals(['status' => 'setara', 'skor' => '100.00', 'target_pk' => '100.00', 'presisi' => 2], $snapshot['deviasi_pk']);
        $this->assertEquals([
            ['periode_id' => $fixture['periode1']->id, 'periode_nama' => 'Triwulan I', 'periode_urutan' => 1, 'nilai' => '100.00', 'status_perhitungan' => 'terhitung', 'keterangan' => null, 'komponen' => []],
            ['periode_id' => $fixture['periode2']->id, 'periode_nama' => 'Triwulan II', 'periode_urutan' => 2, 'nilai' => '100.00', 'status_perhitungan' => 'terhitung', 'keterangan' => null, 'komponen' => []],
        ], $snapshot['target_periode']);
        $this->assertSame([], $snapshot['persyaratan_bukti']);
        $this->assertSame([], $snapshot['bukti_dukungs']);

        $audit = AuditLog::where('tindakan', 'rencana_aksi.ajukan')->sole();
        $this->assertSame($fixture['pic']->id, $audit->actor_id);
        $this->assertSame($header->id, $audit->objek_id);
        $this->assertEquals(['status_alur' => 'draft', 'versi' => 2, 'snapshot_draf_id' => $fixture['snapshot']->id], $audit->nilai_lama);
        $this->assertSame('diajukan', $audit->nilai_baru['status_alur']);
        $this->assertSame(3, $audit->nilai_baru['versi']);
        $this->assertEquals([
            'id' => $versi->id,
            'nomor' => 1,
            'jalur_pengajuan' => 'pic',
            'diajukan_by' => $fixture['pic']->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
        ], $audit->nilai_baru['versi_pengajuan']);
        $this->assertSame('Mengajukan rencana aksi untuk verifikasi.', $audit->alasan);
        $this->assertSame('diizinkan', $audit->dasar_izin['keputusan']);
    }

    public function test_target_belum_lengkap_ditolak_tanpa_versi(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [$this->manual($fixture['periode1'], 100)]);

        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasErrors(['pengajuan' => 'Target periode Triwulan II belum lengkap.']);

        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->assertSame('draft', $header->fresh()->status_alur);
        $this->assertSame(2, $header->fresh()->versi);
        $this->assertSame('Target periode Triwulan II belum lengkap.', AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->sole()->alasan);
    }

    public function test_penyebut_nol_ditolak(): void
    {
        $fixture = $this->buatFixtureRasio();
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [
            ...$this->rasio($fixture, $fixture['periode1'], 0, 0),
            ...$this->rasio($fixture, $fixture['periode2'], 1, 1),
        ]);

        $this->ajukan($fixture['pic'], $header, 2)
            ->assertSessionHasErrors(['pengajuan' => 'Target periode Triwulan I tidak dapat dihitung; perbaiki target sebelum mengajukan.']);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_nilai_nol_pada_pembilang_sah(): void
    {
        $fixture = $this->buatFixtureRasio(targetPk: '0');
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [
            ...$this->rasio($fixture, $fixture['periode1'], 0, 5),
            ...$this->rasio($fixture, $fixture['periode2'], 0, 5),
        ]);

        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();

        $target = RencanaAksiVersi::sole()->snapshot['target_periode'];
        $this->assertSame(['0.00', '0.00'], array_column($target, 'nilai'));
        $this->assertSame(['terhitung', 'terhitung'], array_column($target, 'status_perhitungan'));
        $this->assertNull($target[0]['keterangan']);
        $this->assertEqualsCanonicalizing(
            [$fixture['pembilang']->id, $fixture['penyebut']->id],
            array_column($target[0]['komponen'], 'komponen_id'),
        );
    }

    public function test_deviasi_tanpa_alasan_ditolak_dan_dengan_alasan_diterima(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture, 100, 90);

        $this->ajukan($fixture['pic'], $header, 2)
            ->assertSessionHasErrors(['pengajuan' => 'Skor periode terakhir berbeda dari target PK; alasan deviasi wajib diisi.']);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);

        $this->simpan($fixture, $header, 2, [$this->manual($fixture['periode1'], 100), $this->manual($fixture['periode2'], 90)],
            tambahan: ['alasan_deviasi_pk' => 'Realisasi anggaran triwulan II terlambat.']);

        $this->ajukan($fixture['pic'], $header, 3)->assertSessionHasNoErrors();

        $snapshot = RencanaAksiVersi::sole()->snapshot;
        $this->assertSame('deviasi', $snapshot['deviasi_pk']['status']);
        $this->assertSame('90.00', $snapshot['deviasi_pk']['skor']);
        $this->assertSame('Realisasi anggaran triwulan II terlambat.', $snapshot['alasan_deviasi_pk']);
    }

    public function test_target_pk_null_boleh_diajukan(): void
    {
        $fixture = $this->buatFixtureManual(targetPk: null);
        $header = $this->drafManual($fixture, 100, 90);

        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();

        $this->assertEquals(
            ['status' => 'tidak_dapat_dinilai', 'skor' => '90.00', 'target_pk' => null, 'presisi' => 2],
            RencanaAksiVersi::sole()->snapshot['deviasi_pk'],
        );
    }

    public function test_bukti_wajib_belum_terpenuhi_ditolak_dan_waiver_file_meloloskan(): void
    {
        $fixture = $this->buatFixtureManual();
        $jenis = $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);

        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasErrors(['pengajuan' => 'Bukti wajib belum lengkap: KAK.']);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/bukti", [
            'jenis_berkas_id' => $jenis->id,
            'mode' => 'teks',
            'isi_teks' => 'Ringkasan kerangka acuan kerja.',
        ])->assertSessionHasNoErrors();

        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();

        $snapshot = RencanaAksiVersi::sole()->snapshot;
        $this->assertTrue($snapshot['persyaratan_bukti'][0]['pemenuhan']['terpenuhi']);
        $this->assertSame('teks', $snapshot['bukti_dukungs'][0]['mode']);
        $this->assertSame('Ringkasan kerangka acuan kerja.', $snapshot['bukti_dukungs'][0]['isi_teks']);
        $this->assertArrayHasKey('path', $snapshot['bukti_dukungs'][0]);
        $this->assertNull($snapshot['bukti_dukungs'][0]['path']);

        // Unggahan file dinonaktifkan: persyaratan file-only dikecualikan (anti-macet).
        $kedua = $this->buatFixtureManual();
        $jenisKedua = $this->buatPersyaratanWajib($kedua, ['izinkan_teks' => false]);
        Pengaturan::updateOrCreate(['kunci' => 'berkas.unggahan_aktif'], ['grup' => 'berkas', 'nilai' => 'false', 'tipe' => 'boolean']);
        $headerKedua = $this->drafManual($kedua);

        $this->ajukan($kedua['pic'], $headerKedua, 2)->assertSessionHasNoErrors();

        $versiKedua = RencanaAksiVersi::where('rencana_aksi_id', $headerKedua->id)->sole();
        $pemenuhan = $versiKedua->snapshot['persyaratan_bukti'][0]['pemenuhan'];
        $this->assertSame(['file'], $pemenuhan['mode_dikecualikan']);
        $this->assertTrue($pemenuhan['tidak_dapat_dipenuhi']);

        // PRD §18.7: penanda waiver tercatat di audit_log bersama versi, seperti pengajuan Pengukuran.
        $waiver = AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')->where('objek_tipe', 'rencana_aksi')->sole();
        $this->assertSame($headerKedua->id, $waiver->objek_id);
        $this->assertEquals([
            'versi_pengajuan_id' => $versiKedua->id,
            'nomor_pengajuan' => 1,
            'pengecualian' => [['jenis_berkas_id' => $jenisKedua->id, 'nama' => 'KAK', 'mode' => ['file'], 'alasan' => 'Unggahan file dinonaktifkan pada setelan aplikasi.']],
        ], $waiver->nilai_baru);
    }

    /**
     * Gerbang jendela dan provenance memakai satu pembacaan PJ efektif.
     * Tunggu kunci yang melewati tengah malam WITA tidak boleh membekukan PJ
     * hari berikutnya sementara izin dan jalur berasal dari PJ yang lolos gerbang.
     */
    public function test_provenance_pic_sama_dengan_pic_yang_lolos_gerbang_walau_melewati_tengah_malam(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->travelTo(Carbon::parse('2026-03-10 23:59:50', 'Asia/Makassar'));
        PenugasanIndikator::create([
            'indikator_id' => $fixture['indikator']->id,
            'user_id' => $this->penggunaDenganPeran('pegawai')->id,
            'tanggal_mulai_berlaku' => '2026-03-11',
            'ditetapkan_oleh' => $fixture['perencanaan']->id,
            'created_at' => now(),
        ]);
        DB::listen(function ($query): void {
            if (str_contains($query->sql, '"rencana_aksi_target"') && str_contains($query->sql, 'for update')) {
                $this->travelTo(Carbon::parse('2026-03-11 00:00:10', 'Asia/Makassar'));
            }
        });

        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();

        $versi = RencanaAksiVersi::sole();
        $this->assertSame($fixture['pic']->id, $versi->dasar_izin_pengajuan['pic_id']);
        $this->assertSame($fixture['pic']->id, $versi->snapshot['pic']['id']);
        // Data Model §2.34: `pic_id` = PJ efektif pada tanggal submit, jadi `diajukan_at` berada di tanggal WITA yang sama.
        $this->assertSame('2026-03-10', $versi->diajukan_at->setTimezone('Asia/Makassar')->toDateString());
    }

    /**
     * Gerbang jendela menilai waktu keputusan yang sama dengan PJ dan
     * `diajukan_at`, bukan jam sendiri: tengah malam yang jatuh selama
     * pembacaan PJ tidak boleh membuat gerbang menilai hari berikutnya.
     */
    public function test_gerbang_jendela_memakai_waktu_keputusan_yang_sama(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->travelTo(Carbon::parse('2026-03-31 23:59:59', 'Asia/Makassar'));
        $siaga = false;
        DB::listen(function ($query) use (&$siaga): void {
            if (str_contains($query->sql, '"jadwal_snapshot"') && str_contains($query->sql, 'for update')) {
                $siaga = true;
            } elseif ($siaga && str_contains($query->sql, '"penanggung_jawab"')) {
                $siaga = false;
                $this->travelTo(Carbon::parse('2026-04-01 00:00:01', 'Asia/Makassar'));
            }
        });

        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();

        $this->assertSame('2026-03-31', RencanaAksiVersi::sole()->diajukan_at->setTimezone('Asia/Makassar')->toDateString());
    }

    /**
     * Pengajuan PIC berbasis perubahan nilai. Tanpa versi sah sebelumnya
     * tidak ada pembanding sehingga pengajuan setelah batas tetap lolos bila
     * nilai sama (guard simpan sudah mencegah perubahan sejak awal);
     * penolakan karena nilai periode terkunci berubah dibuktikan pada
     * BukaKembaliRencanaAksiTest dengan versi sah sebagai pembanding.
     */
    public function test_pic_setelah_batas_tanpa_versi_sah_lolos_sedangkan_dalam_batas_diterima(): void
    {
        // (a) Sehari setelah batas 31 Maret, kedua periode terkunci bagi PIC,
        // tetapi tanpa versi sah tidak ada pembanding → lolos.
        $fixtureAwal = $this->buatFixtureManual();
        $headerAwal = $this->drafManual($fixtureAwal);
        $this->travelTo(Carbon::parse('2026-04-01 09:00', 'Asia/Makassar'));
        $this->ajukan($fixtureAwal['pic'], $headerAwal, 2)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $headerAwal->fresh()->status_alur);

        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);

        // (d) Grant ajukan dicabut: 403 dari FormRequest dengan satu audit penolakan.
        $this->travelTo(Carbon::parse('2026-03-10 09:00', 'Asia/Makassar'));
        $this->cabutGrant($fixture['pic'], 'rencana_aksi:ajukan');
        $this->ajukan($fixture['pic'], $header, 2)->assertForbidden();
        $audit = AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('alasan', self::ALASAN_TOLAK_OTORISASI)->sole();
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->grant($fixture['pic'], 'rencana_aksi:ajukan', $fixture['unit']->id, $fixture['perencanaan']);

        // (e) Deny eksplisit menang atas peran superadmin.
        $superadmin = $this->penggunaDenganPeran('superadmin');
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $superadmin->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:ajukan')->value('id'),
            'unit_id' => null,
            'alasan' => 'Fixture deny pengajuan',
            'ditetapkan_oleh' => $fixture['perencanaan']->id,
            'created_at' => now(),
        ]);
        $this->ajukan($superadmin, $header, 2)->assertForbidden();
        $this->assertSame(0, $header->versions()->count());

        // (b) Hari terakhir batas pengisian pukul 23:30 WITA masih diterima.
        $this->travelTo(Carbon::parse('2026-03-31 23:30', 'Asia/Makassar'));
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $header->fresh()->status_alur);

        // (b2) 00:30 WITA 1 April tanpa versi sah tetap lolos (bukan blanket-reject);
        // tanggal bisnis WITA tetap dipakai (UTC masih 31 Maret) untuk menentukan
        // status terkunci, tetapi tanpa pembanding versi sah dinilai lolos.
        $kedua = $this->buatFixtureManual();
        $headerKedua = $this->drafManual($kedua);
        $this->travelTo(Carbon::parse('2026-04-01 00:30', 'Asia/Makassar'));
        $this->assertSame('2026-03-31', now()->utc()->toDateString());
        $this->ajukan($kedua['pic'], $headerKedua, 2)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $headerKedua->fresh()->status_alur);

        // (c) PJ efektif berpindah ke pengguna lain.
        $ketiga = $this->buatFixtureManual();
        $headerKetiga = $this->drafManual($ketiga);
        $this->travelTo(Carbon::parse('2026-03-10 09:00', 'Asia/Makassar'));
        PenugasanIndikator::create([
            'indikator_id' => $ketiga['indikator']->id,
            'user_id' => $this->penggunaDenganPeran('pegawai')->id,
            'tanggal_mulai_berlaku' => '2026-03-05',
            'ditetapkan_oleh' => $ketiga['perencanaan']->id,
            'created_at' => now(),
        ]);
        $this->ajukan($ketiga['pic'], $headerKetiga, 2)->assertSessionHasErrors(['jendela' => 'Tindakan ini memerlukan penugasan PIC yang efektif.']);
        $this->assertSame('draft', $headerKetiga->fresh()->status_alur);
    }

    public function test_jalur_perencanaan_global_setelah_periode_terkunci_tercatat_sebagai_perencanaan(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);

        $this->travelTo(Carbon::parse('2026-05-01 09:00', 'Asia/Makassar'));
        $this->ajukan($fixture['perencanaan'], $header, 2)->assertSessionHasNoErrors();

        $versi = RencanaAksiVersi::sole();
        $this->assertSame('perencanaan', $versi->jalur_pengajuan);
        $this->assertSame($fixture['perencanaan']->id, $versi->diajukan_by);
        $this->assertEquals(['id' => $fixture['pic']->id, 'nama' => $fixture['pic']->nama], $versi->snapshot['pic']);
        $this->assertNotSame([], $versi->dasar_izin_pengajuan['sumber_allow']['roles']);
        $this->assertSame($fixture['pic']->id, $versi->dasar_izin_pengajuan['pic_id']);
    }

    public function test_pengaju_berbeda_dari_pembuat_dan_provenance_tidak_berubah_saat_pj_berganti(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(Carbon::parse('2026-03-10 09:00', 'Asia/Makassar'));
        $this->actingAs($fixture['perencanaan'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['perencanaan']->id, $header->created_by);

        $this->simpan($fixture, $header, 1, [$this->manual($fixture['periode1'], 100), $this->manual($fixture['periode2'], 100)]);
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();
        $versi = RencanaAksiVersi::sole();
        $this->assertSame($fixture['pic']->id, $versi->diajukan_by);

        PenugasanIndikator::create([
            'indikator_id' => $fixture['indikator']->id,
            'user_id' => $this->penggunaDenganPeran('pegawai')->id,
            'tanggal_mulai_berlaku' => '2026-03-10',
            'ditetapkan_oleh' => $fixture['perencanaan']->id,
            'created_at' => now(),
        ]);

        $versi = $versi->fresh();
        $this->assertSame($fixture['pic']->id, $versi->diajukan_by);
        $this->assertSame($fixture['pic']->id, $versi->dasar_izin_pengajuan['pic_id']);
        $this->assertSame('pic', $versi->jalur_pengajuan);
    }

    /**
     * Gerbang yang ditulis langsung di Action: akun nonaktif setelah request
     * lolos middleware, unit pemilik nonaktif, indikator diarsipkan, dan unit
     * snapshot terbaru yang tidak selaras dengan header. Aktor Perencanaan
     * dipakai agar resolver mengizinkan, sehingga yang menolak adalah gerbang
     * Action. Penolakan tidak membekukan versi (baris versi tidak dapat
     * dihapus) dan tetap diaudit; `can.submit` tidak menawarkan aksi itu.
     */
    public function test_gerbang_aktor_unit_indikator_dan_unit_snapshot_menolak_tanpa_versi(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $perencanaan = $fixture['perencanaan'];
        $this->assertCanSubmit(true, $perencanaan, $header);

        DB::table('users')->where('id', $perencanaan->id)->update(['status' => 'nonaktif']);
        $this->assertThrows(
            fn () => app(AjukanRencanaAksi::class)->handle($perencanaan, $header->id, ['expected_versi' => 2]),
            AuthorizationException::class,
            'Akun pengguna tidak aktif.',
        );
        DB::table('users')->where('id', $perencanaan->id)->update(['status' => 'aktif']);

        $unitNonaktif = 'Unit pemilik rencana aksi berstatus nonaktif.';
        Unit::whereKey($fixture['unit']->id)->update(['status' => 'nonaktif']);
        $this->assertCanSubmit(false, $perencanaan, $header);
        $this->ajukan($perencanaan, $header, 2)->assertSessionHasErrors(['unit_id' => $unitNonaktif]);
        Unit::whereKey($fixture['unit']->id)->update(['status' => 'aktif']);

        $arsip = "Indikator '{$fixture['indikator']->kode}' telah diarsipkan sehingga tidak dapat dibuatkan rencana aksi baru.";
        $fixture['indikator']->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->assertCanSubmit(false, $perencanaan, $header);
        $this->ajukan($perencanaan, $header, 2)->assertSessionHasErrors(['indikator_id' => $arsip]);
        $fixture['indikator']->update(['status' => IndikatorKinerja::STATUS_AKTIF]);

        // Unit snapshot diperiksa sebelum jepit, sehingga pesannya spesifik walau jepit juga usang.
        $tidakSelaras = 'Unit pemilik rencana aksi tidak selaras dengan konteks beku terbaru; pengajuan ditolak sampai snapshot koreksi tersedia.';
        $unitLain = Unit::create(['nama' => 'Unit Lain Pengajuan RA', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->terbitkanSnapshotKoreksi($fixture, ['unit_id' => $unitLain->id]);
        $this->ajukan($perencanaan, $header, 2)->assertSessionHasErrors(['snapshot' => $tidakSelaras]);

        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->assertSame('draft', $header->fresh()->status_alur);
        $this->assertEqualsCanonicalizing(
            ['Akun pengguna tidak aktif.', $unitNonaktif, $arsip, $tidakSelaras],
            AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->pluck('alasan')->all(),
        );
    }

    public function test_double_submit_dan_stale_versi_ditolak(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->simpan($fixture, $header, 2, [$this->manual($fixture['periode1'], 100), $this->manual($fixture['periode2'], 100)],
            tambahan: ['uraian' => 'Uraian diperbarui dari tab lain.']);

        // Skenario A: header masih draf tetapi versi sudah naik → 409.
        $this->ajukan($fixture['pic'], $header, 2)->assertRedirect()->assertSessionHasErrors('expected_versi');
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])
            ->assertConflict()
            ->assertJsonValidationErrors('expected_versi');
        $this->assertDatabaseCount('rencana_aksi_versi', 0);

        // Skenario B: klik ganda; status diperiksa sebelum versi.
        $this->ajukan($fixture['pic'], $header, 3)->assertSessionHasNoErrors();
        $this->assertSame(4, $header->fresh()->versi);
        $this->ajukan($fixture['pic'], $header, 3)->assertSessionHasErrors('status_alur');
        $this->ajukan($fixture['pic'], $header, 4)->assertSessionHasErrors('status_alur');

        $this->assertDatabaseCount('rencana_aksi_versi', 1);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.ajukan')->count());
        $this->assertSame(4, AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->count());
    }

    public function test_snapshot_koreksi_baru_setelah_simpan_ditolak_409(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->terbitkanSnapshotKoreksi($fixture);

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])
            ->assertConflict()
            ->assertJsonValidationErrors(['expected_snapshot_id' => 'Konteks indikator berubah (snapshot koreksi baru terbit). Muat ulang dan simpan target sebelum mengajukan.']);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_ajukan_ulang_dari_dikembalikan_membuat_nomor_2(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();
        $versiSatu = RencanaAksiVersi::sole();
        $snapshotSatu = $versiSatu->snapshot;

        DB::table('rencana_aksi')->where('id', $header->id)->update(['status_alur' => 'dikembalikan']);
        $this->simpan($fixture, $header, 3, [$this->manual($fixture['periode2'], 100)]);
        $this->ajukan($fixture['pic'], $header, 4)->assertSessionHasNoErrors();

        $this->assertSame([1, 2], RencanaAksiVersi::orderBy('nomor')->pluck('nomor')->all());
        $this->assertSame($snapshotSatu, $versiSatu->fresh()->snapshot);
        $this->assertSame('diajukan', $header->fresh()->status_alur);
    }

    public function test_sesi_koreksi_setelah_penutupan_mengizinkan_pengajuan(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $fixture['jadwal']->update(['penutupan' => '2026-03-15']);
        $this->travelTo(Carbon::parse('2026-03-20 09:00', 'Asia/Makassar'));

        $this->ajukan($fixture['pic'], $header, 2)
            ->assertSessionHasErrors(['jendela' => 'Tahun jadwal telah ditutup; pengajuan memerlukan sesi koreksi resmi.']);

        // Tanpa versi sah tidak ada pembanding sehingga dinilai sebagai daftar
        // kosong: lingkup parsial pun menerima pengajuan awal PIC maupun
        // Perencanaan selama sesi koreksinya sah.
        $fixture['jadwal']->update([
            'koreksi_mulai' => '2026-03-16 00:00:00',
            'koreksi_sampai' => '2026-03-31 23:59:59',
            'lingkup_koreksi' => [
                'jenis_objek' => ['rencana_aksi'],
                'indikator_ids' => [$fixture['indikator']->id],
                'periode_ids' => [$fixture['periode2']->id],
            ],
        ]);
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $header->fresh()->status_alur);

        $kedua = $this->buatFixtureManual();
        $headerKedua = $this->drafManual($kedua);
        $kedua['jadwal']->update([
            'penutupan' => '2026-03-15',
            'koreksi_mulai' => '2026-03-16 00:00:00',
            'koreksi_sampai' => '2026-03-31 23:59:59',
            'lingkup_koreksi' => [
                'jenis_objek' => ['rencana_aksi'],
                'indikator_ids' => [$kedua['indikator']->id],
                'periode_ids' => [$kedua['periode2']->id],
            ],
        ]);
        $this->travelTo(Carbon::parse('2026-03-20 09:00', 'Asia/Makassar'));
        $this->ajukan($kedua['perencanaan'], $headerKedua, 2)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $headerKedua->fresh()->status_alur);
    }

    /**
     * Dalam sesi koreksi (Q41 §41.2 butir 6), pengajuan dinilai terhadap
     * periode yang berubah dibanding versi sah terakhir — berlaku untuk PIC
     * maupun Perencanaan. Tanpa pembanding dinilai kosong (dibuktikan pada
     * test sebelumnya); dengan pembanding, perubahan di luar lingkup menolak,
     * perubahan di dalam lingkup menerima.
     */
    public function test_sesi_koreksi_parsial_dinilai_terhadap_periode_berubah(): void
    {
        // Perubahan TW I di luar lingkup [TW II]: PIC maupun Perencanaan ditolak.
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture, 100, 100);
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->penggunaDenganPeran('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi resmi pascapenutupan.'])->assertOk();
        $dibuka = $header->fresh()->versi;

        $this->simpan($fixture, $header, $dibuka, [$this->manual($fixture['periode1'], 50)]);
        $diubah = $header->fresh()->versi;

        $fixture['jadwal']->update([
            'penutupan' => '2026-04-05',
            'koreksi_mulai' => '2026-04-06 00:00:00',
            'koreksi_sampai' => '2026-04-30 23:59:59',
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$fixture['indikator']->id], 'periode_ids' => [$fixture['periode2']->id]],
        ]);
        $this->travelTo(Carbon::parse('2026-04-10 09:00', 'Asia/Makassar'));

        $pesanTutup = 'Tahun jadwal telah ditutup; pengajuan memerlukan sesi koreksi resmi.';
        $this->ajukan($fixture['pic'], $header, $diubah)->assertSessionHasErrors(['jendela' => $pesanTutup]);
        $this->ajukan($fixture['perencanaan'], $header, $diubah)->assertSessionHasErrors(['jendela' => $pesanTutup]);
        $this->assertSame('dikembalikan', $header->fresh()->status_alur);

        // Perubahan TW II di dalam lingkup [TW II]: PIC maupun Perencanaan diterima.
        $kedua = $this->buatFixtureManual();
        $headerKedua = $this->drafManual($kedua, 100, 100);
        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versiKedua = $headerKedua->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokensKedua = fn (): array => ['expected_versi' => $headerKedua->fresh()->versi, 'versi_pengajuan_id' => $versiKedua->id];
        app(VerifikasiRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        app(SahkanRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        $pembukaKedua = $this->penggunaDenganPeran('perencanaan');
        $this->actingAs($pembukaKedua)->postJson("/rencana-aksi/{$headerKedua->id}/buka-kembali", $tokensKedua() + ['alasan' => 'Koreksi resmi pascapenutupan.'])->assertOk();
        $dibukaKedua = $headerKedua->fresh()->versi;

        $this->simpan($kedua, $headerKedua, $dibukaKedua, [$this->manual($kedua['periode2'], 90)], tambahan: ['alasan_deviasi_pk' => 'Koreksi TW II dalam lingkup.']);
        $diubahKedua = $headerKedua->fresh()->versi;

        $kedua['jadwal']->update([
            'penutupan' => '2026-04-05',
            'koreksi_mulai' => '2026-04-06 00:00:00',
            'koreksi_sampai' => '2026-04-30 23:59:59',
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$kedua['indikator']->id], 'periode_ids' => [$kedua['periode2']->id]],
        ]);
        $this->travelTo(Carbon::parse('2026-04-10 09:00', 'Asia/Makassar'));

        $this->ajukan($kedua['pic'], $headerKedua, $diubahKedua)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $headerKedua->fresh()->status_alur);
    }

    public function test_uuid_asing_404_dan_tanpa_izin_403_sebelum_validasi(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/'.Str::uuid().'/ajukan')->assertNotFound();

        $this->actingAs($this->penggunaDenganPeran('pegawai'))->post("/rencana-aksi/{$header->id}/ajukan")->assertForbidden();
        $this->assertSame(self::ALASAN_TOLAK_OTORISASI, AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('objek_id', $header->id)->sole()->alasan);

        $this->ajukan($fixture['pic'], $header, 1, ['status_alur' => 'diajukan'])->assertSessionHasErrors('status_alur');
        $this->assertSame('draft', $header->fresh()->status_alur);
    }

    public function test_show_menampilkan_versi_pengajuan_dan_can_update_false_setelah_diajukan(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.status_alur', 'diajukan')
            ->where('rencanaAksi.versi_pengajuan.nomor', 1)
            ->where('rencanaAksi.versi_pengajuan.diajukan_oleh', $fixture['pic']->nama)
            ->where('rencanaAksi.can.update', false)
            ->where('rencanaAksi.can.submit', false)
            ->where('bukti.can.upload', false));
    }

    /** @param  array<string, mixed>  $tambahan */
    private function ajukan(User $aktor, RencanaAksi $header, int $versi, array $tambahan = []): TestResponse
    {
        return $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $versi, ...$tambahan]);
    }

    private function assertCanSubmit(bool $diharapkan, User $aktor, RencanaAksi $header): void
    {
        $this->actingAs($aktor)->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.submit', $diharapkan));
    }
}
