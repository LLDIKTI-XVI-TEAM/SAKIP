<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
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

    private const JENDELA_TUTUP = 'Jendela penyusunan rencana aksi periode ini sudah ditutup.';

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
            ['indikator', 'unit_kerja', 'pic', 'uraian', 'alasan_deviasi_pk', 'deviasi_pk', 'target_periode', 'persyaratan_bukti', 'bukti_dukungs'],
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
        $this->buatPersyaratanWajib($kedua, ['izinkan_teks' => false]);
        Pengaturan::updateOrCreate(['kunci' => 'berkas.unggahan_aktif'], ['grup' => 'berkas', 'nilai' => 'false', 'tipe' => 'boolean']);
        $headerKedua = $this->drafManual($kedua);

        $this->ajukan($kedua['pic'], $headerKedua, 2)->assertSessionHasNoErrors();

        $pemenuhan = RencanaAksiVersi::where('rencana_aksi_id', $headerKedua->id)->sole()->snapshot['persyaratan_bukti'][0]['pemenuhan'];
        $this->assertSame(['file'], $pemenuhan['mode_dikecualikan']);
        $this->assertTrue($pemenuhan['tidak_dapat_dipenuhi']);
    }

    public function test_pic_di_luar_jendela_tanpa_pj_tanpa_grant_atau_deny_ditolak(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);

        // (a) Sehari setelah jendela 1–31 Maret berakhir.
        $this->travelTo(Carbon::parse('2026-04-01 09:00', 'Asia/Makassar'));
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasErrors(['jendela' => self::JENDELA_TUTUP]);

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
        $this->assertDatabaseCount('rencana_aksi_versi', 0);

        // (b) Hari terakhir jendela pukul 23:30 WITA masih diterima.
        $this->travelTo(Carbon::parse('2026-03-31 23:30', 'Asia/Makassar'));
        $this->ajukan($fixture['pic'], $header, 2)->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $header->fresh()->status_alur);

        // (b) 00:30 WITA 1 April ditolak walau tanggal UTC masih 31 Maret.
        $kedua = $this->buatFixtureManual();
        $headerKedua = $this->drafManual($kedua);
        $this->travelTo(Carbon::parse('2026-04-01 00:30', 'Asia/Makassar'));
        $this->assertSame('2026-03-31', now()->utc()->toDateString());
        $this->ajukan($kedua['pic'], $headerKedua, 2)->assertSessionHasErrors(['jendela' => self::JENDELA_TUTUP]);

        // (c) PJ efektif berpindah ke pengguna lain.
        $this->travelTo(Carbon::parse('2026-03-10 09:00', 'Asia/Makassar'));
        PenugasanIndikator::create([
            'indikator_id' => $kedua['indikator']->id,
            'user_id' => $this->penggunaDenganPeran('pegawai')->id,
            'tanggal_mulai_berlaku' => '2026-03-05',
            'ditetapkan_oleh' => $kedua['perencanaan']->id,
            'created_at' => now(),
        ]);
        $this->ajukan($kedua['pic'], $headerKedua, 2)->assertSessionHasErrors(['jendela' => 'Tindakan ini memerlukan penugasan PIC yang efektif.']);
        $this->assertSame('draft', $headerKedua->fresh()->status_alur);
    }

    public function test_jalur_perencanaan_global_di_luar_jendela_tercatat_sebagai_perencanaan(): void
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

        // Lingkup periode tidak diperiksa untuk pengajuan (periode dijaga saat simpan).
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
}
