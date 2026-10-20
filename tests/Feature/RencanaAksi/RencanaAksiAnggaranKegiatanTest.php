<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Anggaran dan total kegiatan pada halaman Rencana Aksi (Q42 §42.2 butir 2
 * dan 4, §42.5): anggaran per kegiatan dan total hanya untuk pemegang akses
 * detail kegiatan unit, Perencanaan dan Pimpinan melihat seluruhnya.
 */
class RencanaAksiAnggaranKegiatanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RencanaAksi $header;

    private Kegiatan $bimtek;

    private Kegiatan $rapat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->buatFixtureManual();
        $this->fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        // Draf memuat "Kegiatan pendukung" beranggaran 0 pada Triwulan I.
        $this->header = $this->drafManual($this->fixture);
        $this->bimtek = $this->kegiatan('Bimtek penjaminan mutu', 1500000, $this->fixture['periode1']);
        $this->rapat = $this->kegiatan('Rapat koordinasi', null, $this->fixture['periode2']);
    }

    public function test_halaman_ra_memuat_anggaran_sumber_klaim_kegiatan_bersama_dan_total(): void
    {
        $this->klaimPengukuran('Survei dari pengukuran', 500000);
        $this->klaimIkuLain($this->bimtek);

        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('kegiatan.daftar', fn ($daftar) => collect($daftar)->mapWithKeys(fn ($row) => [$row['nama'] => [$row['anggaran'], $row['sumber_klaim'], $row['kegiatan_bersama']]])->all() === [
                'Bimtek penjaminan mutu' => ['1500000', 'rencana_aksi', true],
                'Kegiatan pendukung' => ['0', 'rencana_aksi', false],
                'Rapat koordinasi' => [null, 'rencana_aksi', false],
                'Survei dari pengukuran' => ['500000', 'pengukuran', false],
            ])
            ->where('kegiatan.total.total', '2000000')
            ->where('kegiatan.total.belum_diisi', 1)
            ->where('kegiatan.total.per_periode.'.$this->fixture['periode1']->id, ['total' => '1500000', 'belum_diisi' => 0])
            ->where('kegiatan.total.per_periode.'.$this->fixture['periode2']->id, ['total' => '500000', 'belum_diisi' => 1]));
    }

    public function test_anggaran_dan_total_hanya_untuk_pemegang_akses_detail_kegiatan_unit(): void
    {
        foreach ([$this->fixture['perencanaan'], $this->penggunaDenganPeran('pimpinan')] as $semuaUnit) {
            $this->actingAs($semuaUnit)->get("/rencana-aksi/{$this->header->id}")->assertOk()
                ->assertInertia(fn ($page) => $page->where('kegiatan.total.total', '1500000')->has('kegiatan.daftar.0.anggaran'));
        }

        // PIC unit lain tidak dapat membuka RA, sehingga anggaran tidak terkirim.
        $unitLain = $this->penggunaDenganPeran('pegawai');
        $this->actingAs($unitLain)->get("/rencana-aksi/{$this->header->id}")->assertForbidden();

        // Deny baca kegiatan pada unit menutup seluruh payload kegiatan, termasuk anggaran dan total.
        $this->denyBacaKegiatan($this->fixture['pic']);
        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('kegiatan', null));
    }

    public function test_capability_kegiatan_dihitung_di_luar_transaksi_snapshot_read_only(): void
    {
        // Gerbang klaim mengambil row lock `FOR SHARE` yang ditolak transaksi READ ONLY. Di bawah RefreshDatabase isolasi itu
        // tidak pernah aktif, sehingga posisinya dijaga lewat level transaksi saat gerbang dipanggil.
        $level = null;
        $this->partialMock(GerbangKlaimKegiatan::class, function ($mock) use (&$level): void {
            $mock->shouldReceive('boleh')->once()->andReturnUsing(function () use (&$level): bool {
                $level = DB::transactionLevel();

                return true;
            });
        });
        $sebelum = DB::transactionLevel();

        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('kegiatan.can.tambah', true));
        $this->assertSame($sebelum, $level);
    }

    public function test_total_dan_anggaran_klaim_dibekukan_pada_versi_dan_tidak_berubah_setelah_anggaran_diubah(): void
    {
        $this->rapat->update(['anggaran' => 250000]);
        // Klaim pengukuran tidak dikunci saat pengajuan, sehingga total beku hanya dari klaim tahap RA yang dibekukan.
        $this->klaimPengukuran('Survei dari pengukuran', 500000);
        $this->actingAs($this->fixture['pic'])->post("/rencana-aksi/{$this->header->id}/ajukan", ['expected_versi' => $this->header->fresh()->versi])
            ->assertSessionHasNoErrors();
        $this->bimtek->update(['anggaran' => 9000000]);
        $beku = ['total' => '1750000', 'belum_diisi' => 0, 'per_periode' => [
            $this->fixture['periode1']->id => ['total' => '1500000', 'belum_diisi' => 0],
            $this->fixture['periode2']->id => ['total' => '250000', 'belum_diisi' => 0],
        ]];

        $this->actingAs($this->fixture['perencanaan'])->get("/rencana-aksi/{$this->header->id}/review")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.anggaran_kegiatan', $beku)
            ->where('rencanaAksi.klaim', fn ($klaim) => collect($klaim)->pluck('anggaran', 'nama')->sortKeys()->all()
                === ['Bimtek penjaminan mutu' => '1500000', 'Kegiatan pendukung' => '0', 'Rapat koordinasi' => '250000']));
        // Halaman RA tetap menghitung dari data kegiatan saat ini.
        $this->actingAs($this->fixture['perencanaan'])->get("/rencana-aksi/{$this->header->id}")
            ->assertInertia(fn ($page) => $page->where('kegiatan.total.total', '9750000'));

        $this->denyBacaKegiatan($this->fixture['perencanaan']);
        $this->actingAs($this->fixture['perencanaan'])->get("/rencana-aksi/{$this->header->id}/review")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.anggaran_kegiatan', null)->where('rencanaAksi.klaim', null));
    }

    private function kegiatan(string $nama, ?int $anggaran, Periode $periode, bool $klaimTahapRa = true): Kegiatan
    {
        $kegiatan = Kegiatan::create(['unit_id' => $this->fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $periode->id,
            'nama' => $nama, 'anggaran' => $anggaran, 'created_by' => $this->fixture['pic']->id]);
        $klaimTahapRa && KlaimKegiatan::create(['rencana_aksi_id' => $this->header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);

        return $kegiatan;
    }

    private function klaimPengukuran(string $nama, int $anggaran): void
    {
        $pengukuran = PengukuranKinerja::create(['indikator_id' => $this->fixture['indikator']->id, 'tahun' => 2026, 'periode_id' => $this->fixture['periode2']->id,
            'jadwal_snapshot_id' => $this->fixture['snapshot']->id, 'sumber_nilai' => 'manual', 'created_by' => $this->fixture['pic']->id]);
        $kegiatan = $this->kegiatan($nama, $anggaran, $this->fixture['periode2'], false);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'pengukuran', 'pengukuran_id' => $pengukuran->id, 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);
    }

    private function klaimIkuLain(Kegiatan $kegiatan): void
    {
        $indikator = $this->fixture['indikator']->replicate();
        $indikator->kode = 'I-UJI-LAIN';
        $indikator->save();
        $headerLain = RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->fixture['unit']->id,
            'jadwal_tahunan_id' => $this->fixture['jadwal']->id, 'penanggung_jawab_id' => $this->fixture['pic']->id,
            'status_alur' => RencanaAksi::STATUS_DRAFT, 'versi' => 1, 'created_by' => $this->fixture['pic']->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $headerLain->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);
    }

    private function denyBacaKegiatan(User $user): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', 'kegiatan:read')->value('id'), 'unit_id' => $this->fixture['unit']->id,
            'alasan' => 'Pengujian deny baca kegiatan', 'ditetapkan_oleh' => $this->fixture['perencanaan']->id, 'created_at' => now()]);
    }
}
