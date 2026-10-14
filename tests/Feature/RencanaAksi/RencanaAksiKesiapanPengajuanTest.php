<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Props kesiapan pengajuan di halaman Show: `pengajuan.siap/alasan` dari
 * evaluator tanpa kunci dan `can.submit` dari syarat gerbang pengajuan.
 */
class RencanaAksiKesiapanPengajuanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    private const ALASAN_DEVIASI = 'Skor periode terakhir berbeda dari target PK; alasan deviasi wajib diisi.';

    public function test_show_memuat_pengajuan_belum_siap_dengan_alasan_dan_can_submit(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [$this->manual($fixture['periode1'], 100)]);

        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.can.submit', true)
            ->where('rencanaAksi.pengajuan.siap', false)
            ->where('rencanaAksi.pengajuan.alasan', ['Target periode Triwulan II belum lengkap.'])
            ->where('rencanaAksi.versi_pengajuan', null));
    }

    public function test_show_siap_setelah_target_lengkap_dan_bukti_wajib_terpenuhi(): void
    {
        $fixture = $this->buatFixtureManual();
        $jenis = $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);

        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.siap', false)
            ->where('rencanaAksi.pengajuan.alasan', ['Bukti wajib belum lengkap: KAK.']));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/bukti", [
            'jenis_berkas_id' => $jenis->id,
            'mode' => 'teks',
            'isi_teks' => 'Ringkasan kerangka acuan kerja.',
        ])->assertSessionHasNoErrors();

        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.siap', true)
            ->where('rencanaAksi.pengajuan.alasan', []));
    }

    public function test_deviasi_tanpa_alasan_menjadi_alasan_tolak_dan_target_pk_null_tidak(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture, 100, 90);

        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.alasan', [self::ALASAN_DEVIASI]));

        $tanpaPk = $this->buatFixtureManual(targetPk: null);
        $headerTanpaPk = $this->drafManual($tanpaPk, 100, 90);

        $this->tampil($tanpaPk['pic'], $headerTanpaPk)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.deviasi_pk.dapat_dinilai', false)
            ->where('rencanaAksi.pengajuan.siap', true)
            ->where('rencanaAksi.pengajuan.alasan', []));
    }

    public function test_deviasi_props_memakai_half_up_dua_sisi(): void
    {
        $fixture = $this->buatFixtureManual(targetPk: '76.255');
        $header = $this->drafManual($fixture, '76.26', '76.26');

        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.deviasi_pk.ada', false)
            ->where('rencanaAksi.deviasi_pk.target_pk', '76.26')
            ->where('rencanaAksi.deviasi_pk.skor_periode_terakhir', '76.26')
            ->where('rencanaAksi.pengajuan.siap', true)
            ->where('rencanaAksi.pengajuan.alasan', []));
    }

    public function test_can_submit_false_tanpa_izin_ajukan_periode_terkunci_atau_jepit_usang(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);
        $this->assertCanSubmit(true, $fixture['pic'], $header);

        // (a) Grant ajukan dicabut: izin dasar hilang.
        $this->cabutGrant($fixture['pic'], 'rencana_aksi:ajukan');
        $this->assertCanSubmit(false, $fixture['pic'], $header);
        $this->grant($fixture['pic'], 'rencana_aksi:ajukan', $fixture['unit']->id, $fixture['perencanaan']);

        // (b) Periode terkunci bagi PIC, Perencanaan jalur global tetap boleh.
        $this->travelTo(now()->setDate(2026, 4, 2)->setTime(9, 0));
        $this->assertCanSubmit(false, $fixture['pic'], $header);
        $this->assertCanSubmit(true, $fixture['perencanaan'], $header);

        // (c) Snapshot koreksi baru terbit setelah draf dijepit ke v1.
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $this->terbitkanSnapshotKoreksi($fixture);
        $this->assertCanSubmit(false, $fixture['pic'], $header);
    }

    public function test_jepit_usang_menjadi_alasan_yang_dapat_ditindaklanjuti_sampai_target_disimpan_ulang(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $v2 = $this->terbitkanSnapshotKoreksi($fixture);

        // Isi lengkap, tetapi gerbang menolak sampai target disimpan di konteks v2.
        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.can.submit', false)
            ->where('rencanaAksi.pengajuan.siap', false)
            ->where('rencanaAksi.pengajuan.alasan', ['Konteks indikator berubah (snapshot koreksi baru terbit). Simpan target sebelum mengajukan.']));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 2,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => [$this->manual($fixture['periode1'], 100), $this->manual($fixture['periode2'], 100)],
        ])->assertSessionHasNoErrors();

        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.can.submit', true)
            ->where('rencanaAksi.pengajuan.siap', true)
            ->where('rencanaAksi.pengajuan.alasan', []));
    }

    public function test_penyebut_nol_menjadi_alasan_tolak(): void
    {
        $fixture = $this->buatFixtureRasio();
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [
            ...$this->rasio($fixture, $fixture['periode1'], 0, 0),
            ...$this->rasio($fixture, $fixture['periode2'], 1, 1),
        ]);

        $this->tampil($fixture['pic'], $header)->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.alasan', ['Target periode Triwulan I tidak dapat dihitung; perbaiki target sebelum mengajukan.']));
    }

    private function tampil(User $aktor, RencanaAksi $header): TestResponse
    {
        return $this->actingAs($aktor)->get("/rencana-aksi/{$header->id}")->assertOk();
    }

    private function assertCanSubmit(bool $diharapkan, User $aktor, RencanaAksi $header): void
    {
        $this->tampil($aktor, $header)->assertInertia(fn ($page) => $page->where('rencanaAksi.can.submit', $diharapkan));
    }
}
