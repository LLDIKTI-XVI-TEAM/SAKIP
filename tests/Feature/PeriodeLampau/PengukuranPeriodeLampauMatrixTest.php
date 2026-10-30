<?php

namespace Tests\Feature\PeriodeLampau;

use App\Models\JadwalTahunan;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Services\RencanaAksi\PeriodeTanpaTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PengukuranPeriodeLampauFixtures;
use Tests\TestCase;

/**
 * Matriks periode lampau 2026 pada jalur pengukuran (ISS-07.05): satu sel per
 * periode lampau TW I–III × {Perencanaan / PIC}, ditambah kontrol TW IV normal
 * dan jendela yang diperpanjang melewati aktivasi. Pengecualian gerbang hanya
 * mengikuti jalur Perencanaan (Q32.7, Q46 §46.1, Q63); PIC tidak pernah
 * dikecualikan (Q41 §41.2).
 */
class PengukuranPeriodeLampauMatrixTest extends TestCase
{
    use PengukuranPeriodeLampauFixtures, RefreshDatabase;

    private const PESAN_JENDELA = 'Jendela pengisian PIC periode ini sudah ditutup.';

    private const NARASI_WAJIB = ['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut'];

    #[DataProvider('periodeLampau')]
    public function test_perencanaan_mengisi_setiap_periode_lampau_tanpa_gerbang(string $kunciPeriode): void
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();
        $pengukuran = $this->drafPengukuran($fixture, $fixture[$kunciPeriode]);

        // Q46 §46.1 butir 1: RA sah, target, bukti, dan narasi tidak menahan
        // pengisian periode lampau oleh Perencanaan.
        $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 80])->assertRedirect('/pengukuran');

        $versi = KinerjaSnapshot::sole();
        $this->assertSame(['diajukan', 2], [$pengukuran->fresh()->status_alur, $pengukuran->fresh()->versi]);
        $this->assertSame('perencanaan', $versi->jalur_pengajuan);
        $this->assertNull($versi->rencana_aksi_versi_id);
        $this->assertNull($versi->snapshot['target']);
        $this->assertTrue($versi->snapshot['tanpa_target_periode']);
        foreach (PengukuranKinerja::NARASI as $narasi) {
            $this->assertNull($versi->snapshot[$narasi], $narasi);
        }
        // Nilai ajuan benar-benar tersimpan dan terhitung, bukan dua null yang kebetulan sama.
        $this->assertSame('terhitung', $versi->snapshot['status_perhitungan']);
        $this->assertSame(80.0, (float) $versi->snapshot['nilai']);
    }

    public static function periodeLampau(): array
    {
        return ['Triwulan I' => ['periode1'], 'Triwulan II' => ['periode2'], 'Triwulan III' => ['periode3']];
    }

    #[DataProvider('periodeLampau')]
    public function test_pic_ditolak_pada_setiap_periode_lampau(string $kunciPeriode): void
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();
        $periode = $fixture[$kunciPeriode];

        // Gerbang jendela PIC tetap berlaku meski grant unit tersedia.
        $this->buatDrafPengukuran($fixture, $periode, $fixture['pic'])->assertSessionHasErrors('pengukuran');
        $this->assertSame([self::PESAN_JENDELA], session('errors')->get('pengukuran'));
        $this->assertDatabaseCount('pengukuran_kinerjas', 0);

        // Kontrol positif pada sel yang sama: Perencanaan membentuk draf.
        $pengukuran = $this->drafPengukuran($fixture, $periode, $fixture['perencanaan']);

        // PIC juga ditolak saat header sudah tersedia.
        $response = $this->kirimPengukuran($pengukuran, $fixture['pic'], ['nilai' => 80]);
        $response->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertContains(self::PESAN_JENDELA, $response->json('errors.versi'));
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(['draft', 1], [$pengukuran->fresh()->status_alur, $pengukuran->fresh()->versi]);
    }

    public function test_tw_empat_normal_mewajibkan_gerbang_untuk_pic_dan_perencanaan(): void
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(9, 0));
        $pengukuran = $this->drafPengukuran($fixture, $fixture['periode4'], $fixture['pic']);

        // PIC di dalam jendela TW IV tetap tertahan gerbang RA dan narasi.
        $this->kirimPengukuran($pengukuran, $fixture['pic'], ['nilai' => 90])
            ->assertUnprocessable()->assertJsonValidationErrors(['pengajuan', ...self::NARASI_WAJIB]);

        // Perencanaan tidak dibebaskan pada periode normal.
        $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 90])
            ->assertUnprocessable()->assertJsonValidationErrors(['pengajuan', ...self::NARASI_WAJIB]);

        // Kontrol positif: gerbang terpenuhi lewat RA sah dan narasi.
        $this->sahkanRencanaAksi($fixture, $fixture['periode4']);
        $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 90, ...$this->narasiLengkap()])
            ->assertRedirect('/pengukuran');

        $versi = $pengukuran->fresh()->latestVersion;
        $this->assertSame('perencanaan', $versi->jalur_pengajuan);
        $this->assertSame('terhitung', $versi->snapshot['status_perhitungan']);
        $this->assertNotNull($versi->rencana_aksi_versi_id);
    }

    public function test_jendela_diperpanjang_melewati_aktivasi_menjadikan_periode_normal(): void
    {
        $fixture = $this->siapkanPengukuranPeriodeLampau2026();
        $fixture['jadwal']->periode()->where('periode_id', $fixture['periode1']->id)
            ->update(['pengisian_selesai' => '2026-04-20']);

        // Q41 §41.2 catatan 19 Oktober 2026: batas baru melewati tanggal
        // aktivasi 5 April, sehingga TW I bukan lagi periode lampau.
        $jendela = PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->sole();
        $this->assertFalse(PeriodeTanpaTarget::isPeriodeLampau(JadwalTahunan::findOrFail($fixture['jadwal']->id), $jendela));

        // PIC kembali dapat membentuk draf pada jendela yang diperpanjang.
        $pengukuran = $this->drafPengukuran($fixture, $fixture['periode1'], $fixture['pic']);
        $this->sahkanRencanaAksi($fixture, $fixture['periode1']);

        // Gerbang narasi normal berlaku lagi; tidak ada penolakan jendela PIC.
        $ditolak = $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 70]);
        $ditolak->assertUnprocessable()->assertJsonValidationErrors(self::NARASI_WAJIB)->assertJsonMissingValidationErrors('versi');

        $this->kirimPengukuran($pengukuran, $fixture['perencanaan'], ['nilai' => 70, ...$this->narasiLengkap()])
            ->assertRedirect('/pengukuran');
        $this->assertSame(1, KinerjaSnapshot::count());
    }
}
