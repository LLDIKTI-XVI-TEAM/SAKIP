<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Models\AuditLog;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class BatasAwalPengisianTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    private Periode $tw3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-25 10:00', 'Asia/Makassar'));
    }

    /** Empat triwulan bertanggal standar; setiap jendela dibuka tepat pada batas awalnya. @return array{0: User, 1: array<string, mixed>} */
    private function draftEmpatTriwulan(): array
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $periode = [];
        foreach ([1 => ['2026-03-01', '2026-03-20'], 2 => ['2026-06-01', '2026-06-20'], 3 => ['2026-09-01', '2026-09-20'], 4 => ['2026-12-01', '2027-01-15']] as $urutan => [$mulai, $selesai]) {
            $master = $this->calendarMaster($urutan, $urutan === 4);
            $periode[] = ['periode_id' => $master->id, 'periode_revisi' => $master->revisi, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => $selesai];
        }

        return [$actor, ['renstra_id' => $renstra->id, 'tahun' => 2026, 'penutupan' => '2027-01-20', 'periode' => $periode]];
    }

    public function test_jendela_tepat_pada_batas_awal_tersimpan_termasuk_pengisian_tw4_di_januari(): void
    {
        [$actor, $payload] = $this->draftEmpatTriwulan();

        $this->actingAs($actor)->post('/jadwal', $payload)->assertSessionHasNoErrors()->assertRedirect();

        $tersimpan = DB::table('jadwal_periode')->orderBy('pengisian_mulai')->get(['periode_id', 'pengisian_mulai', 'pengisian_selesai'])
            ->map(fn (object $row): array => (array) $row)->all();
        $this->assertEquals(array_map(fn (array $window): array => array_diff_key($window, ['periode_revisi' => true]), $payload['periode']), $tersimpan);
    }

    /** @return array<string, array{int, string, string}> */
    public static function jendelaSebelumBatas(): array
    {
        return [
            'Triwulan I sebelum 1 Maret' => [0, '2026-02-28', '1 Maret 2026'],
            'Triwulan II sebelum 1 Juni' => [1, '2026-05-31', '1 Juni 2026'],
            'Triwulan III sebelum 1 September' => [2, '2026-08-31', '1 September 2026'],
            'Triwulan IV sebelum 1 Desember' => [3, '2026-11-30', '1 Desember 2026'],
        ];
    }

    #[DataProvider('jendelaSebelumBatas')]
    public function test_pengisian_mulai_sebelum_tanggal_1_bulan_terakhir_periode_ditolak(int $index, string $mulai, string $batas): void
    {
        [$actor, $payload] = $this->draftEmpatTriwulan();
        $payload['periode'][$index]['pengisian_mulai'] = $mulai;

        $this->actingAs($actor)->post('/jadwal', $payload)
            ->assertSessionHasErrors(["periode.$index.pengisian_mulai" => "Jendela pengisian paling cepat dibuka $batas, tanggal 1 bulan terakhir periode."]);

        $this->assertSame(0, DB::table('jadwal_tahunan')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tambah_ditolak')->where('nilai_baru->alasan_penolakan', 'aturan_jadwal')->count());
    }

    public function test_periode_tanpa_tanggal_di_jadwal_ditolak_dengan_pesan(): void
    {
        $actor = $this->calendarActor();
        // Data lama sebelum tanggal periode wajib: batas awal tidak dapat dihitung.
        $lama = Periode::create(['nama' => 'Periode Lama', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true])->refresh();
        $payload = $this->calendarPayload($this->calendarRenstra($actor), $lama);

        $this->actingAs($actor)->post('/jadwal', $payload)
            ->assertSessionHasErrors(['periode.0.periode_id' => 'Lengkapi tanggal mulai dan selesai periode ini di Master Periode sebelum menyusun jadwal.']);
        $this->assertSame(0, DB::table('jadwal_tahunan')->count());

        $lama->update(['bulan_mulai' => 10, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31]);
        $this->post('/jadwal', [...$payload, 'periode' => [[...$payload['periode'][0], 'periode_revisi' => $lama->fresh()->revisi]]])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('jadwal_tahunan')->count());
    }

    public function test_aktivasi_draf_lama_yang_melanggar_batas_awal_terblokir_memakai_tanggal_periode_saat_aktivasi(): void
    {
        $this->readyJadwal();
        $actor = $this->activator();
        $url = '/jadwal/'.$this->ready['jadwal']->id;
        $kode = fn (): array => array_column($this->actingAs($actor)->getJson("$url/kesiapan-aktivasi")->assertOk()->json('blockers'), 'code');
        $this->assertSame([], $kode());

        // Draf tersimpan sebelum batas awal berlaku: Triwulan IV dibuka 15 November.
        DB::table('jadwal_periode')->where('jadwal_id', $this->ready['jadwal']->id)->update(['pengisian_mulai' => '2026-11-15']);
        $this->assertContains('kalender_tidak_valid', $kode());
        $this->from($url)->post("$url/aktivasi", $this->activationPayload())->assertSessionHasErrors('aktivasi');
        $this->assertSame('draft', $this->ready['jadwal']->fresh()->status);

        // Periode yang kini berakhir November membuka batas 1 November, sehingga jendela yang sama sah.
        $this->ready['periode']->update(['bulan_selesai' => 11, 'hari_selesai' => 30]);
        $this->assertNotContains('kalender_tidak_valid', $kode());

        $this->ready['periode']->update(['bulan_mulai' => null, 'hari_mulai' => null, 'bulan_selesai' => null, 'hari_selesai' => null]);
        $this->assertContains('kalender_tidak_valid', $kode());
    }

    /** Jadwal 2026 aktif dengan Triwulan IV dibuka 22 Oktober, tersimpan sebelum batas awal berlaku. */
    private function jadwalAktif2026(): User
    {
        $this->readyJadwal();
        $this->tw3 = $this->calendarMaster(3, false);
        $base = $this->calendarPayload($this->ready['renstra'], $this->ready['periode']);
        app(SaveJadwalDraft::class)->handle($this->ready['owner'], [...$base, 'revisi' => $this->ready['jadwal']->fresh()->revisi, 'periode' => [...$base['periode'],
            ['periode_id' => $this->tw3->id, 'periode_revisi' => $this->tw3->revisi, 'pengisian_mulai' => '2026-09-01', 'pengisian_selesai' => '2026-10-10']]], $this->ready['jadwal']);
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        DB::table('jadwal_periode')->where('periode_id', $this->ready['periode']->id)->update(['pengisian_mulai' => '2026-10-22']);

        return $this->activator(['jadwal:update']);
    }

    private function revisi(User $actor, string $tw3Selesai, string $tw4Mulai, string $tw4Selesai = '2027-01-11'): TestResponse
    {
        $jadwal = $this->ready['jadwal']->fresh();

        return $this->actingAs($actor)->from("/jadwal/$jadwal->id")->post("/jadwal/$jadwal->id/revisi-jendela", [
            'expected_revisi' => $jadwal->revisi, 'alasan' => 'Penyesuaian jendela pengisian unit.', 'periode' => [
                ['periode_id' => $this->tw3->id, 'pengisian_mulai' => '2026-09-01', 'pengisian_selesai' => $tw3Selesai],
                ['periode_id' => $this->ready['periode']->id, 'pengisian_mulai' => $tw4Mulai, 'pengisian_selesai' => $tw4Selesai],
            ]]);
    }

    /** @return array<string, array{string, string}> */
    private function jendelaTersimpan(): array
    {
        return DB::table('jadwal_periode')->where('jadwal_id', $this->ready['jadwal']->id)->get()
            ->mapWithKeys(fn (object $row): array => [$row->periode_id => [$row->pengisian_mulai, $row->pengisian_selesai]])->all();
    }

    public function test_revisi_jendela_lain_tidak_memeriksa_ulang_jendela_tw4_yang_tidak_diubah(): void
    {
        $perencana = $this->jadwalAktif2026();

        $this->revisi($perencana, '2026-10-20', '2026-10-22')->assertSessionHasNoErrors();

        $this->assertSame('aktif', $this->ready['jadwal']->fresh()->status);
        $this->assertEquals([$this->tw3->id => ['2026-09-01', '2026-10-20'], $this->ready['periode']->id => ['2026-10-22', '2027-01-11']], $this->jendelaTersimpan());
    }

    public function test_revisi_yang_mengubah_jendela_tw4_memeriksa_batas_awal(): void
    {
        $perencana = $this->jadwalAktif2026();
        $sebelum = $this->jendelaTersimpan();
        $pesan = 'Jendela pengisian paling cepat dibuka 1 Desember 2026, tanggal 1 bulan terakhir periode.';

        $this->revisi($perencana, '2026-10-10', '2026-11-30')->assertSessionHasErrors(['periode.1.pengisian_mulai' => $pesan]);
        // Jendela yang tanggalnya diubah diperiksa utuh, walau hanya tanggal selesainya yang digeser.
        $this->revisi($perencana, '2026-10-10', '2026-10-22', '2027-01-15')->assertSessionHasErrors(['periode.1.pengisian_mulai' => $pesan]);
        $this->assertEquals($sebelum, $this->jendelaTersimpan());
        $this->assertSame(2, AuditLog::where('tindakan', 'jadwal.revisi_jendela_ditolak')->where('nilai_baru->alasan_penolakan', 'aturan_jadwal')->count());

        $this->revisi($perencana, '2026-10-10', '2026-12-01', '2027-01-15')->assertSessionHasNoErrors();
        $this->assertSame(['2026-12-01', '2027-01-15'], $this->jendelaTersimpan()[$this->ready['periode']->id]);
    }
}
