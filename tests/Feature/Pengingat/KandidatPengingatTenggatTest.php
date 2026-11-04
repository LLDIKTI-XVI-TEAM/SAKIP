<?php

namespace Tests\Feature\Pengingat;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Pengingat\KandidatPengingatTenggat;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Fase A ISS-12.04: kandidat pengingat tenggat murni baca pada tanggal bisnis eksplisit.
 * Fixture dasar: jadwal aktif 2026 dengan batas pengisian 2026-03-15 dan satu header draft.
 */
class KandidatPengingatTenggatTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw1;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function evaluasi(string $tanggal, array $hMinus): array
    {
        return app(KandidatPengingatTenggat::class)->untukTanggal(
            CarbonImmutable::parse($tanggal, 'Asia/Makassar'),
            $hMinus,
        );
    }

    private function indikatorDenganPic(string $kode, User $pic, array $indikator = [], array $snapshot = []): JadwalSnapshot
    {
        $snapshot = $this->indikatorBaru($kode, $indikator, $snapshot);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $snapshot;
    }

    public function test_h_minus_7_3_1_menghasilkan_kandidat_dengan_h_benar(): void
    {
        foreach ([7 => '2026-03-08', 3 => '2026-03-12', 1 => '2026-03-14'] as $h => $tanggal) {
            $hasil = $this->evaluasi($tanggal, [7, 3, 1]);

            $this->assertSame($tanggal, $hasil['tanggal']);
            $this->assertSame(1, $hasil['diperiksa']);
            $this->assertCount(1, $hasil['kandidat']);
            $kandidat = $hasil['kandidat'][0];
            $this->assertSame($this->tw1->id, $kandidat['periode_jadwal_id']);
            $this->assertSame($this->actor->id, $kandidat['user_id']);
            $this->assertSame($h, $kandidat['h']);
            $this->assertSame('2026-03-15', $kandidat['jatuh_tempo']);
            $this->assertSame(1, $kandidat['jumlah']);
            $this->assertSame([[
                'indikator_id' => $this->context->indikator_id,
                'kode' => 'I-UJI',
                'nama' => 'Indikator Uji',
            ]], $kandidat['indikator']);
        }
    }

    public function test_header_kosong_draft_dan_dikembalikan_tetap_kandidat(): void
    {
        $this->pengukuran->delete();
        $kosong = $this->evaluasi('2026-03-14', [1]);
        $this->assertCount(1, $kosong['kandidat']);
        $this->assertSame(1, $kosong['kandidat'][0]['jumlah']);

        $draft = $this->headerUntuk($this->context);
        $this->assertCount(1, $this->evaluasi('2026-03-14', [1])['kandidat']);

        $draft->update(['status_alur' => 'dikembalikan']);
        $kembali = $this->evaluasi('2026-03-14', [1]);
        $this->assertCount(1, $kembali['kandidat']);
        $this->assertSame($this->actor->id, $kembali['kandidat'][0]['user_id']);
    }

    public function test_h_minus_6_5_4_2_tidak_menghasilkan_kandidat_tetapi_tetap_memeriksa(): void
    {
        foreach (['2026-03-09', '2026-03-10', '2026-03-11', '2026-03-13'] as $tanggal) {
            $hasil = $this->evaluasi($tanggal, [7, 3, 1]);

            $this->assertSame([], $hasil['kandidat']);
            $this->assertSame(1, $hasil['diperiksa']);
        }
    }

    public function test_h_minus_kosong_tidak_menghasilkan_kandidat_walau_periode_aktif(): void
    {
        $hasil = $this->evaluasi('2026-03-14', []);

        $this->assertSame('2026-03-14', $hasil['tanggal']);
        $this->assertSame(1, $hasil['diperiksa']);
        $this->assertSame([], $hasil['kandidat']);
    }

    public function test_header_diajukan_diverifikasi_disahkan_dikecualikan(): void
    {
        foreach (['diajukan', 'diverifikasi', 'disahkan'] as $status) {
            $this->pengukuran->update(['status_alur' => $status]);

            $hasil = $this->evaluasi('2026-03-14', [1]);

            $this->assertSame([], $hasil['kandidat'], "Status $status seharusnya tuntas.");
        }
    }

    public function test_batas_tenggat_h_nol_dan_h_plus_satu_bukan_h_nol(): void
    {
        $tenggat = $this->evaluasi('2026-03-15', [0]);
        $this->assertCount(1, $tenggat['kandidat']);
        $this->assertSame(0, $tenggat['kandidat'][0]['h']);

        $this->assertSame([], $this->evaluasi('2026-03-16', [0])['kandidat']);

        // Kontrol: selisih sebenarnya -1 sehingga H+1 tidak boleh dianggap H-0.
        $lewat = $this->evaluasi('2026-03-16', [-1]);
        $this->assertCount(1, $lewat['kandidat']);
        $this->assertSame(-1, $lewat['kandidat'][0]['h']);
    }

    public function test_beberapa_indikator_pic_sama_pada_periode_sama_digabung_satu_entri(): void
    {
        $this->indikatorDenganPic('I-UJI-2', $this->actor);
        $this->indikatorDenganPic('I-UJI-3', $this->actor);

        $hasil = $this->evaluasi('2026-03-14', [1]);

        $this->assertCount(1, $hasil['kandidat']);
        $kandidat = $hasil['kandidat'][0];
        $this->assertSame($this->tw1->id, $kandidat['periode_jadwal_id']);
        $this->assertSame(3, $kandidat['jumlah']);
        $this->assertCount(3, $kandidat['indikator']);
        $this->assertEqualsCanonicalizing(['I-UJI', 'I-UJI-2', 'I-UJI-3'],
            array_column($kandidat['indikator'], 'kode'));
    }

    public function test_dua_pic_berbeda_pada_periode_sama_belum_selesai_menghasilkan_dua_entri_terpisah(): void
    {
        $picA = $this->userWithRole('pegawai');
        $picB = $this->userWithRole('pegawai');
        $indikatorA = $this->indikatorDenganPic('I-PIC-A', $picA);
        $indikatorB = $this->indikatorDenganPic('I-PIC-B', $picB);

        $hasil = $this->evaluasi('2026-03-14', [1]);

        $kandidat = collect($hasil['kandidat']);
        $entriA = $kandidat->firstWhere('user_id', $picA->id);
        $entriB = $kandidat->firstWhere('user_id', $picB->id);

        $this->assertNotNull($entriA);
        $this->assertNotNull($entriB);
        $this->assertNotSame($entriA['user_id'], $entriB['user_id']);
        $this->assertSame($this->tw1->id, $entriA['periode_jadwal_id']);
        $this->assertSame($this->tw1->id, $entriB['periode_jadwal_id']);
        $this->assertCount(1, $kandidat->where('user_id', $picA->id));
        $this->assertCount(1, $kandidat->where('user_id', $picB->id));
        $this->assertSame(1, $entriA['jumlah']);
        $this->assertSame(1, $entriB['jumlah']);
        $this->assertSame([$indikatorA->indikator_id], array_column($entriA['indikator'], 'indikator_id'));
        $this->assertSame([$indikatorB->indikator_id], array_column($entriB['indikator'], 'indikator_id'));
    }

    public function test_pic_sama_pada_dua_periode_berbeda_menghasilkan_dua_entri_terpisah(): void
    {
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $tw3 = $this->tambahPeriode(3, '2026-06-01', '2026-06-10');

        $hasil = $this->evaluasi('2026-06-08', [7, 2]);

        $this->assertSame(3, $hasil['diperiksa']);
        $perPeriode = collect($hasil['kandidat'])->where('user_id', $this->actor->id)->keyBy('periode_jadwal_id');
        $this->assertCount(2, $perPeriode);
        $this->assertSame(7, $perPeriode[$tw2->id]['h']);
        $this->assertSame('2026-06-15', $perPeriode[$tw2->id]['jatuh_tempo']);
        $this->assertSame(2, $perPeriode[$tw3->id]['h']);
        $this->assertSame('2026-06-10', $perPeriode[$tw3->id]['jatuh_tempo']);
        $this->assertSame(1, $perPeriode[$tw2->id]['jumlah']);
        $this->assertSame(1, $perPeriode[$tw3->id]['jumlah']);
    }

    public function test_pic_dibaca_pada_tanggal_evaluasi_bukan_tanggal_hari_ini(): void
    {
        $pic = $this->userWithRole('pegawai');
        $baru = $this->indikatorBaru('I-PIC-BARU');
        PenugasanIndikator::create(['indikator_id' => $baru->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-03-10', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        // Hari ini (travelTo fixture) 2026-03-15, tetapi tanggal evaluasi 2026-03-08 belum efektif.
        $sebelum = $this->evaluasi('2026-03-08', [7]);
        $this->assertSame([], collect($sebelum['kandidat'])->where('user_id', $pic->id)->values()->all());

        $sesudah = $this->evaluasi('2026-03-12', [3]);
        $kandidat = collect($sesudah['kandidat'])->firstWhere('user_id', $pic->id);
        $this->assertNotNull($kandidat);
        $this->assertSame(3, $kandidat['h']);
        $this->assertSame([$baru->indikator_id], array_column($kandidat['indikator'], 'indikator_id'));
    }

    public function test_periode_lampau_2026_tidak_menghasilkan_kandidat(): void
    {
        $lampau = $this->tambahPeriode(2, '2026-02-01', '2026-03-10');
        $this->indikatorDenganPic('I-LAMPAU', $this->actor, [], ['periode_mulai_id' => $lampau->periode_id]);

        $hasil = $this->evaluasi('2026-03-03', [7]);

        $this->assertSame([], $hasil['kandidat']);
        $this->assertSame(1, $hasil['diperiksa']);
    }

    public function test_jadwal_ditutup_dan_tahun_ditutup_tidak_menghasilkan_kandidat(): void
    {
        $this->jadwal->update(['status' => 'ditutup']);
        $ditutup = $this->evaluasi('2026-03-14', [1]);
        $this->assertSame([], $ditutup['kandidat']);
        $this->assertSame(0, $ditutup['diperiksa']);

        $this->jadwal->update(['status' => 'aktif', 'penutupan' => '2026-03-10']);
        $tahunDitutup = $this->evaluasi('2026-03-14', [1]);
        $this->assertSame([], $tahunDitutup['kandidat']);
        $this->assertSame(0, $tahunDitutup['diperiksa']);
    }

    public function test_penutupan_tahun_dinilai_pada_tanggal_evaluasi_eksplisit_bukan_hari_ini(): void
    {
        $this->jadwal->update(['penutupan' => '2026-03-18']);

        // Hari ini (travelTo fixture) 2026-03-15 masih sebelum penutupan, sehingga
        // evaluasi 2026-03-20 hanya tertutup bila tanggal eksplisit yang dipakai.
        $sebelum = $this->evaluasi('2026-03-14', [1, -5]);
        $this->assertSame(1, $sebelum['diperiksa']);
        $this->assertCount(1, $sebelum['kandidat']);
        $this->assertSame(1, $sebelum['kandidat'][0]['h']);

        $sesudah = $this->evaluasi('2026-03-20', [1, -5]);
        $this->assertSame(0, $sesudah['diperiksa']);
        $this->assertSame([], $sesudah['kandidat']);
    }

    public function test_unit_nonaktif_indikator_arsip_snapshot_belum_berlaku_dan_tanpa_pic_dikecualikan(): void
    {
        $pic = $this->userWithRole('pegawai');

        $unitNonaktif = $this->unitBaru('Unit Nonaktif');
        $unitNonaktif->update(['status' => 'nonaktif']);
        $this->indikatorDenganPic('I-UNIT-NONAKTIF', $pic, [], ['unit_id' => $unitNonaktif->id]);

        $arsip = $this->indikatorDenganPic('I-ARSIP', $pic);
        IndikatorKinerja::whereKey($arsip->indikator_id)->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        $tw2 = $this->tambahPeriode(2, '2026-04-01', '2026-04-15');
        $this->indikatorDenganPic('I-BELUM-BERLAKU', $pic, [], ['periode_mulai_id' => $tw2->periode_id]);

        $tanpaPic = $this->indikatorBaru('I-TANPA-PIC');

        $hasil = $this->evaluasi('2026-03-14', [1]);

        $this->assertSame(1, $hasil['diperiksa']);
        $kandidat = collect($hasil['kandidat']);
        $this->assertSame([], $kandidat->where('user_id', $pic->id)->values()->all());
        $this->assertFalse($kandidat->contains(fn (array $k): bool => collect($k['indikator'])
            ->contains('indikator_id', $tanpaPic->indikator_id)));
        // Service tetap menghasilkan kandidat sah pada tanggal yang sama.
        $this->assertCount(1, $kandidat->where('user_id', $this->actor->id));
    }

    public function test_indikator_arsip_dengan_header_draft_atau_dikembalikan_dikecualikan(): void
    {
        // Regresi MAJOR: DaftarKewajibanPengukuran hanya mengecualikan arsip tanpa header,
        // sehingga indikator arsip berheader draft/dikembalikan ikut lolos sebelum fix.
        foreach (['draft', 'dikembalikan'] as $index => $status) {
            $pic = $this->userWithRole('pegawai');
            $snapshot = $this->indikatorDenganPic('I-ARSIP-HEADER-'.$index, $pic);
            $this->headerUntuk($snapshot)->update(['status_alur' => $status]);
            IndikatorKinerja::whereKey($snapshot->indikator_id)->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

            $hasil = $this->evaluasi('2026-03-14', [1]);

            $kandidat = collect($hasil['kandidat']);
            $this->assertNull($kandidat->firstWhere('user_id', $pic->id),
                "Indikator arsip berheader $status seharusnya dikecualikan.");
            $this->assertFalse($kandidat->contains(fn (array $k): bool => collect($k['indikator'])
                ->contains('indikator_id', $snapshot->indikator_id)),
                "Indikator arsip berheader $status tidak boleh muncul pada entri PIC mana pun.");
            // Kontrol: fixture sah tetap menjadi kandidat pada tanggal yang sama.
            $this->assertCount(1, $kandidat->where('user_id', $this->actor->id));
        }
    }

    public function test_sesi_koreksi_hanya_memuat_indikator_dan_periode_di_dalam_lingkup(): void
    {
        // Regresi: sebelum fix, seluruh indikator pada periode jendela PIC ikut menjadi kandidat.
        $indikatorA = $this->indikatorDenganPic('I-DALAM-LINGKUP', $this->actor);
        $indikatorB = $this->indikatorDenganPic('I-DI-LUAR-LINGKUP', $this->actor);
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-05');
        $indikatorC = $this->indikatorDenganPic('I-PERIODE-LUAR-LINGKUP', $this->actor, [], ['periode_mulai_id' => $tw2->periode_id]);

        // Jendela PIC resmi berada di dalam rentang sesi dan mengapit tanggal evaluasi.
        $this->tw1->update(['pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-05']);
        $this->jadwal->update(['status' => 'aktif', 'koreksi_mulai' => '2026-06-01 08:00', 'koreksi_sampai' => '2026-06-05 17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'],
                'indikator_ids' => [$indikatorA->indikator_id, $indikatorC->indikator_id],
                'periode_ids' => [$this->tw1->periode_id],
                'jendela_pic_ids' => [$this->tw1->periode_id, $tw2->periode_id]]]);

        $hasil = $this->evaluasi('2026-06-03', [2]);

        $this->assertSame(2, $hasil['diperiksa']);
        $entri = collect($hasil['kandidat'])->where('periode_jadwal_id', $this->tw1->id)
            ->where('user_id', $this->actor->id)->values();
        $this->assertCount(1, $entri);
        $this->assertSame(1, $entri[0]['jumlah']);
        $this->assertSame([$indikatorA->indikator_id], array_column($entri[0]['indikator'], 'indikator_id'));

        // B di luar lingkup indikator dan C di luar lingkup periode sama-sama tidak boleh muncul.
        $kandidat = collect($hasil['kandidat']);
        $indikatorMuncul = $kandidat->flatMap(fn (array $k): array => array_column($k['indikator'], 'indikator_id'))->all();
        $this->assertNotContains($indikatorB->indikator_id, $indikatorMuncul);
        $this->assertNotContains($indikatorC->indikator_id, $indikatorMuncul);
        $this->assertSame([], $kandidat->where('periode_jadwal_id', $tw2->id)->values()->all());
    }

    public function test_instan_utc_dinormalkan_ke_zona_bisnis_sebelum_menyaring_periode(): void
    {
        // Regresi: sebelum fix, 2026-03-07 16:30 UTC masih 7 Maret sehingga jendela 8 Maret tersaring.
        $tw2 = $this->tambahPeriode(2, '2026-03-08', '2026-03-15');
        $indikator = $this->indikatorDenganPic('I-UTC-WITA', $this->actor, [], ['periode_mulai_id' => $tw2->periode_id]);

        $hasil = app(KandidatPengingatTenggat::class)->untukTanggal(
            CarbonImmutable::parse('2026-03-07 16:30', 'UTC'),
            [7],
        );

        $this->assertSame('2026-03-08', $hasil['tanggal']);
        $kandidat = collect($hasil['kandidat'])->firstWhere('periode_jadwal_id', $tw2->id);
        $this->assertNotNull($kandidat);
        $this->assertSame(7, $kandidat['h']);
        $this->assertContains($indikator->indikator_id, array_column($kandidat['indikator'], 'indikator_id'));
    }

    public function test_service_tidak_menulis_baris_apa_pun(): void
    {
        $tabel = ['pengukuran_kinerjas', 'jadwal_periode', 'penanggung_jawab', 'audit_log', 'jobs', 'failed_jobs'];
        $sebelum = [];
        foreach ($tabel as $nama) {
            $sebelum[$nama] = DB::table($nama)->count();
        }

        $hasil = $this->evaluasi('2026-03-14', [1]);

        $this->assertCount(1, $hasil['kandidat']);
        foreach ($tabel as $nama) {
            $this->assertSame($sebelum[$nama], DB::table($nama)->count(), "Tabel $nama berubah.");
        }
    }
}
