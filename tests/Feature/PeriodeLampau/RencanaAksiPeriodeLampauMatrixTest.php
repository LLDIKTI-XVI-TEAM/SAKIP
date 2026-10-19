<?php

namespace Tests\Feature\PeriodeLampau;

use App\Models\AuditLog;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\RencanaAksiVersi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PeriodeLampauFixtures;
use Tests\TestCase;

/**
 * Matriks periode lampau 2026 pada jalur Rencana Aksi (ISS-03.05): satu sel
 * per gerbang × {periode lampau TW I–III / TW IV normal} ×
 * {Perencanaan / PIC}, dengan kontrol positif pada setiap penolakan.
 * Pengecualian gerbang pengajuan mengikuti Q63: hanya jalur Perencanaan yang
 * dikecualikan; PIC tidak pernah dikecualikan.
 */
class RencanaAksiPeriodeLampauMatrixTest extends TestCase
{
    use PeriodeLampauFixtures, RefreshDatabase;

    private const PESAN_BUKTI = 'Bukti wajib belum lengkap: KAK.';

    private const PESAN_KEGIATAN = 'Rencana aksi wajib memiliki minimal satu kegiatan yang diklaim pada tahap rencana aksi.';

    private const PESAN_DEVIASI = 'Skor periode terakhir berbeda dari target PK; alasan deviasi wajib diisi.';

    public function test_periode_lampau_terkunci_bagi_pic_dan_terbuka_bagi_perencanaan(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $header = $this->buatDrafLampau($fixture);
        $targets = [$this->sel($fixture['periode1'], 10), $this->sel($fixture['periode2'], 20), $this->sel($fixture['periode3'], 30), $this->sel($fixture['periode4'], 100)];

        // Q41 §41.2 butir 4, Q46 §46.1 butir 1: periode lampau terkunci bagi PIC
        // dan pesannya spesifik per periode terkunci.
        $this->simpanMatriks($fixture, $header, $targets)->assertSessionHasErrors('jendela');
        $pesan = (string) session('errors')->first('jendela');
        $this->assertStringContainsString('Triwulan I', $pesan);
        $this->assertStringContainsString('Triwulan III', $pesan);
        $this->assertStringNotContainsString('Triwulan IV', $pesan);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());

        // Perencanaan tidak terikat kunci dan mengisi seluruh periode lampau.
        $this->simpanMatriks($fixture, $header, $targets, $fixture['perencanaan'])->assertSessionHasNoErrors();
        $this->assertSame(4, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_tw_iv_normal_tetap_dapat_disimpan_pic(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $header = $this->buatDrafLampau($fixture);

        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode4'], 100)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rencana_aksi_target', [
            'rencana_aksi_id' => $header->id,
            'periode_id' => $fixture['periode4']->id,
            'nilai' => 100,
        ]);
    }

    public function test_pic_dapat_menyimpan_kembali_periode_lampau_tanpa_perubahan_nilai(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $header = $this->buatDrafLampau($fixture);
        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode1'], 10), $this->sel($fixture['periode2'], 20), $this->sel($fixture['periode3'], 30), $this->sel($fixture['periode4'], 100)], $fixture['perencanaan'])->assertSessionHasNoErrors();

        // Periode lampau yang nilainya tidak berubah tidak menahan simpan PIC (Q41 §41.2).
        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode1'], 10), $this->sel($fixture['periode2'], 20), $this->sel($fixture['periode3'], 30), $this->sel($fixture['periode4'], 90)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rencana_aksi_target', [
            'rencana_aksi_id' => $header->id,
            'periode_id' => $fixture['periode4']->id,
            'nilai' => 90,
        ]);
    }

    public function test_bukti_wajib_dikecualikan_pada_data_awal_2026_jalur_perencanaan(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $this->buatPersyaratanWajib($fixture);
        $header = $this->buatDrafLampau($fixture);
        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode1'], 25), $this->sel($fixture['periode2'], 50), $this->sel($fixture['periode3'], 75), $this->sel($fixture['periode4'], 100)], $fixture['perencanaan'])->assertSessionHasNoErrors();

        // Q63 butir 1: berkas wajib dikecualikan hanya pada jalur Perencanaan
        // (Q32.7, Q46 §46.1, Workflow §15 butir 4).
        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame('perencanaan', RencanaAksiVersi::sole()->jalur_pengajuan);
    }

    public function test_bukti_wajib_dikecualikan_bagi_perencanaan_saat_seluruh_periode_lampau(): void
    {
        [$fixture, $header] = $this->drafLampauDenganBuktiWajib();

        // Q63 butir 1: cabang seluruh-periode-lampau tetap memerlukan jalur Perencanaan.
        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame('perencanaan', RencanaAksiVersi::sole()->jalur_pengajuan);
    }

    public function test_bukti_wajib_tetap_diwajibkan_bagi_pic_saat_seluruh_periode_lampau(): void
    {
        [$fixture, $header] = $this->drafLampauDenganBuktiWajib();

        // Q63 butir 2: PIC tidak pernah dikecualikan dari bukti wajib.
        $this->ajukanDraf($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN_BUKTI]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_kegiatan_minimal_dikecualikan_bagi_perencanaan_saat_seluruh_periode_lampau(): void
    {
        [$fixture, $header] = $this->drafLampauTanpaKegiatan();

        // Q63 butir 2: pengecualian kegiatan minimal hanya pada jalur Perencanaan.
        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
        $this->assertSame('perencanaan', RencanaAksiVersi::sole()->jalur_pengajuan);
    }

    public function test_kegiatan_minimal_tetap_diwajibkan_bagi_pic_saat_seluruh_periode_lampau(): void
    {
        [$fixture, $header] = $this->drafLampauTanpaKegiatan();

        $this->ajukanDraf($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN_KEGIATAN]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_bukti_wajib_tetap_diwajibkan_pada_jalur_pic_non_lampau(): void
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);

        // Kontrol positif: di luar jalur lampau, bukti wajib tetap menahan pengajuan.
        $this->ajukanDraf($fixture['pic'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN_BUKTI]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_alasan_deviasi_pk_tetap_diwajibkan_pada_jalur_lampau(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $header = $this->buatDrafLampau($fixture);
        $targets = [$this->sel($fixture['periode1'], 25), $this->sel($fixture['periode2'], 50), $this->sel($fixture['periode3'], 75), $this->sel($fixture['periode4'], 90)];
        $this->simpanMatriks($fixture, $header, $targets, $fixture['perencanaan'])->assertSessionHasNoErrors();

        // Q63 butir 3: rekonsiliasi target PK tetap wajib di semua jalur (ADR-0008).
        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasErrors(['pengajuan' => self::PESAN_DEVIASI]);

        $this->simpanMatriks($fixture, $header, $targets, $fixture['perencanaan'], ['alasan_deviasi_pk' => 'Selisih mengikuti dokumen resmi 2026.'])->assertSessionHasNoErrors();
        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasNoErrors();
    }

    public function test_target_periode_lampau_kosong_dicatat_tidak_ditargetkan(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $header = $this->buatDrafLampau($fixture);
        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode4'], 100)], $fixture['perencanaan'])->assertSessionHasNoErrors();

        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasNoErrors();

        // Q47 §47.2 butir 4-5: target lampau kosong = `tidak_ditargetkan`,
        // berbeda dari `0`, dan TW IV tetap dinilai.
        $status = array_column(RencanaAksiVersi::sole()->snapshot['target_periode'], 'status_perhitungan', 'periode_id');
        $this->assertSame('tidak_ditargetkan', $status[$fixture['periode1']->id]);
        $this->assertSame('terhitung', $status[$fixture['periode4']->id]);
    }

    public function test_halaman_menandai_gerbang_lampau_sebagai_dikecualikan_hanya_untuk_perencanaan(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $this->buatPersyaratanWajib($fixture);
        $header = $this->buatDrafLampau($fixture);
        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode1'], 25), $this->sel($fixture['periode2'], 50), $this->sel($fixture['periode3'], 75), $this->sel($fixture['periode4'], 100)], $fixture['perencanaan'])->assertSessionHasNoErrors();

        // Q63: kartu kesiapan dan panel bukti memakai status pengecualian yang sama.
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.siap', true)
            ->where('rencanaAksi.pengajuan.dikecualikan', true)
            ->where('bukti.dikecualikan', true)
            ->where('bukti.ringkasan.lengkap', false));

        // PIC pada RA yang sama tidak memperoleh pengecualian.
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.dikecualikan', false)
            ->where('bukti.dikecualikan', false));
    }

    public function test_status_pengecualian_mengikuti_jalur_versi_beku_setelah_diajukan(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $this->buatPersyaratanWajib($fixture);
        $header = $this->buatDrafLampau($fixture);
        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode1'], 25), $this->sel($fixture['periode2'], 50), $this->sel($fixture['periode3'], 75), $this->sel($fixture['periode4'], 100)], $fixture['perencanaan'])->assertSessionHasNoErrors();
        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasNoErrors();

        // Q63: RA yang sudah diajukan memakai jalur versi beku, bukan izin pembaca.
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.dikecualikan', true)
            ->where('bukti.dikecualikan', true));

        // Setelah dibuka kembali, status pengecualian kembali mengikuti jalur aktor.
        $header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.pengajuan.dikecualikan', false)
            ->where('bukti.dikecualikan', false));
    }

    public function test_jadwal_2027_seluruh_periode_lampau_tetap_mewajibkan_gerbang_bagi_perencanaan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);
        KlaimKegiatan::where('rencana_aksi_id', $header->id)->delete();

        // Jadwal 2027 diaktivasi setelah seluruh pengisian selesai.
        $fixture['jadwal']->update(['tahun' => 2027, 'activated_at' => '2027-04-05 09:00:00',
            'rencana_aksi_mulai' => '2027-03-01', 'rencana_aksi_selesai' => '2027-03-31', 'penutupan' => '2027-12-31']);
        $fixture['jadwal']->periode()->update(['pengisian_mulai' => '2027-03-01', 'pengisian_selesai' => '2027-03-31',
            'reviu_mulai' => '2027-04-01', 'reviu_selesai' => '2027-04-30']);
        $header->update(['tahun' => 2027]);
        $this->travelTo(now()->setDate(2027, 3, 10)->setTime(9, 0));

        // Q63 §63.1: penolakan berasal dari tahun RA (bukan 2026) dan
        // `PeriodeTanpaTarget::isPeriodeLampau` yang juga mensyaratkan jadwal
        // 2026; RA 2027 tetap wajib kegiatan minimal dan bukti wajib.
        $this->ajukanDraf($fixture['perencanaan'], $header)
            ->assertSessionHasErrors(['pengajuan' => self::PESAN_KEGIATAN])
            ->assertSessionHasErrors(['pengajuan' => self::PESAN_BUKTI]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_pengajuan_lampau_membekukan_versi_dan_mengaudit_aktor(): void
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $header = $this->buatDrafLampau($fixture);
        $this->simpanMatriks($fixture, $header, [$this->sel($fixture['periode1'], 25), $this->sel($fixture['periode2'], 50), $this->sel($fixture['periode3'], 75), $this->sel($fixture['periode4'], 100)], $fixture['perencanaan'])->assertSessionHasNoErrors();

        $this->ajukanDraf($fixture['perencanaan'], $header)->assertSessionHasNoErrors();

        $versi = RencanaAksiVersi::sole();
        $this->assertSame('perencanaan', $versi->jalur_pengajuan);
        $this->assertSame($fixture['perencanaan']->id, $versi->diajukan_by);
        $this->assertSame(RencanaAksi::STATUS_DIAJUKAN, $header->fresh()->status_alur);
        $audit = AuditLog::where('tindakan', 'rencana_aksi.ajukan')->where('objek_id', $header->id)->sole();
        $this->assertSame($fixture['perencanaan']->id, $audit->actor_id);
        $this->assertSame(0, AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('objek_id', $header->id)->count());
    }

    /**
     * Draf lengkap pada jadwal yang seluruh periodenya lampau saat aktivasi,
     * dengan persyaratan bukti wajib tahap rencana aksi.
     *
     * @return array{0: array<string, mixed>, 1: RencanaAksi}
     */
    private function drafLampauDenganBuktiWajib(string $aktivasi = '2026-04-05 09:00:00'): array
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => $aktivasi]);
        $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);

        return [$fixture, $header];
    }

    /**
     * Draf lengkap pada jadwal yang seluruh periodenya lampau saat aktivasi,
     * tanpa klaim kegiatan tahap rencana aksi.
     *
     * @return array{0: array<string, mixed>, 1: RencanaAksi}
     */
    private function drafLampauTanpaKegiatan(string $aktivasi = '2026-04-05 09:00:00'): array
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => $aktivasi]);
        $header = $this->drafManual($fixture);
        KlaimKegiatan::where('rencana_aksi_id', $header->id)->delete();

        return [$fixture, $header];
    }
}
