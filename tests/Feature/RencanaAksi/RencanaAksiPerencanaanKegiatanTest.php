<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Gerbang pengajuan RA atas kegiatan berklaim tahap RA (Q42 §42.2–§42.4):
 * anggaran terisi dan persyaratan wajib tahap `kegiatan_perencanaan`
 * (global + khusus indikator RA) terpenuhi, RAB hanya untuk anggaran > 0,
 * dikecualikan untuk data awal 2026 dan periode lampau (Q46 §46.1).
 */
class RencanaAksiPerencanaanKegiatanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RencanaAksi $header;

    private Kegiatan $kegiatan;

    private JenisBerkas $rab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->buatFixtureManual();
        $this->fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        $this->header = $this->drafManual($this->fixture);
        $this->kegiatan = Kegiatan::findOrFail(KlaimKegiatan::where('rencana_aksi_id', $this->header->id)->value('kegiatan_id'));
        $this->kegiatan->update(['nama' => 'Bimtek penjaminan mutu', 'anggaran' => 5000000]);
        $this->rab = $this->persyaratan(['nama' => 'RAB', 'hanya_kegiatan_beranggaran' => true]);
    }

    public function test_kegiatan_beranggaran_tanpa_rab_ditolak_dengan_daftar_lalu_diterima_setelah_rab_diunggah(): void
    {
        $pesan = 'Kegiatan "Bimtek penjaminan mutu" belum memenuhi persyaratan perencanaan kegiatan: RAB.';

        $this->ajukan($this->fixture['pic'])->assertSessionHasErrors(['pengajuan' => $pesan]);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('objek_id', $this->header->id)->count());
        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.pengajuan.siap', false)
                ->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => collect($alasan)->contains($pesan)));

        $contoh = $this->berkas($this->rab, 'jenis_berkas', $this->rab->id);
        $rab = $this->berkas($this->rab);
        $this->ajukan($this->fixture['pic'])->assertSessionHasNoErrors();

        $beku = RencanaAksiVersi::sole()->snapshot['persyaratan_kegiatan'];
        $this->assertSame([$this->kegiatan->id, false], [$beku[0]['kegiatan_id'], $beku[0]['dikecualikan']]);
        $this->assertTrue($beku[0]['persyaratan'][0]['pemenuhan']['terpenuhi']);
        $this->assertSame([$rab->id], array_column($beku[0]['berkas'], 'id'));
        $this->assertStringNotContainsString($contoh->id, json_encode(RencanaAksiVersi::sole()->snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_kegiatan_beranggaran_nol_tanpa_rab_lolos_tetapi_persyaratan_tanpa_penanda_tetap_diperiksa(): void
    {
        $this->kegiatan->update(['anggaran' => 0]);
        $kak = $this->persyaratan(['nama' => 'KAK']);

        $this->ajukan($this->fixture['pic'])->assertSessionHasErrors(['pengajuan' => 'Kegiatan "Bimtek penjaminan mutu" belum memenuhi persyaratan perencanaan kegiatan: KAK.']);
        $this->berkas($kak);
        $this->ajukan($this->fixture['pic'])->assertSessionHasNoErrors();
        $this->assertSame(['KAK'], array_column(RencanaAksiVersi::sole()->snapshot['persyaratan_kegiatan'][0]['persyaratan'], 'nama'));
    }

    public function test_kegiatan_lama_tanpa_anggaran_menolak_pengajuan(): void
    {
        $this->kegiatan->update(['anggaran' => null]);

        // RAB hanya dituntut untuk anggaran lebih dari 0 (Q42 §42.3 butir 3); anggaran kosong cukup ditolak sebagai belum diisi.
        $this->ajukan($this->fixture['pic'])->assertSessionHasErrors('pengajuan');
        $this->assertSame(['Anggaran kegiatan "Bimtek penjaminan mutu" belum diisi.'], session('errors')->get('pengajuan'));
        $this->assertDatabaseCount('rencana_aksi_versi', 0);
    }

    public function test_persyaratan_khusus_indikator_lain_tidak_menghalangi_sedangkan_indikator_ra_ini_diperiksa(): void
    {
        $this->berkas($this->rab);
        $lain = IndikatorKinerja::create([...$this->fixture['indikator']->only(['sasaran_strategis_id', 'unit_id', 'satuan', 'tipe_perhitungan', 'arah',
            'presisi', 'desimal_tampilan', 'status', 'tahun_mulai_berlaku', 'created_by', 'created_by_role']), 'kode' => 'IKU-LAIN', 'nama' => 'Indikator lain']);
        $this->persyaratan(['nama' => 'Laporan indikator lain', 'indikator_id' => $lain->id]);
        $this->persyaratan(['nama' => 'Laporan indikator ini', 'indikator_id' => $this->fixture['indikator']->id]);

        $this->ajukan($this->fixture['pic'])->assertSessionHasErrors(['pengajuan' => 'Kegiatan "Bimtek penjaminan mutu" belum memenuhi persyaratan perencanaan kegiatan: Laporan indikator ini.']);
    }

    public function test_tanpa_izin_baca_kegiatan_nama_kegiatan_tidak_disebut(): void
    {
        $this->denyBacaKegiatan($this->fixture['pic']);

        $this->ajukan($this->fixture['pic'])->assertSessionHasErrors(['pengajuan' => '1 kegiatan belum beranggaran atau belum memenuhi persyaratan perencanaan kegiatan.']);
    }

    public function test_data_awal_2026_lewat_jalur_perencanaan_dikecualikan(): void
    {
        $this->kegiatan->update(['anggaran' => null]);

        $this->ajukan($this->fixture['perencanaan'])->assertSessionHasNoErrors();
        $this->assertTrue(RencanaAksiVersi::sole()->snapshot['persyaratan_kegiatan'][0]['dikecualikan']);
    }

    public function test_kegiatan_periode_lampau_dikecualikan(): void
    {
        // Pengisian periode selesai 31 Maret, sebelum aktivasi 5 April: periode kegiatan lampau saat aktivasi.
        $this->fixture['jadwal']->update(['activated_at' => '2026-04-05 09:00:00']);
        $this->kegiatan->update(['anggaran' => null]);

        $this->ajukan($this->fixture['pic'])->assertSessionHasNoErrors();
        $beku = RencanaAksiVersi::sole()->snapshot['persyaratan_kegiatan'][0];
        $this->assertTrue($beku['dikecualikan']);
        $this->assertSame([], $beku['persyaratan'], 'RAB tidak dinilai kurang selama anggaran belum diisi.');
    }

    public function test_tahap_pelaksanaan_kegiatan_tidak_tercampur_dengan_tahap_perencanaan(): void
    {
        $spj = $this->persyaratan(['nama' => 'Laporan pelaksanaan', 'tahap' => 'kegiatan']);

        $pelaksanaan = app(EvaluateEvidence::class)->untuk('kegiatan', (string) $this->fixture['indikator']->id, collect());
        $this->assertSame([$spj->id], array_column($pelaksanaan, 'id'));
        // Persyaratan pelaksanaan diperiksa saat kegiatan ditandai terlaksana, bukan saat RA diajukan.
        $this->berkas($this->rab);
        $this->ajukan($this->fixture['pic'])->assertSessionHasNoErrors();
    }

    private function ajukan(User $aktor): TestResponse
    {
        return $this->actingAs($aktor)->post("/rencana-aksi/{$this->header->id}/ajukan", ['expected_versi' => $this->header->fresh()->versi]);
    }

    /** @param array<string, mixed> $atribut */
    private function persyaratan(array $atribut): JenisBerkas
    {
        return $this->buatPersyaratanWajib($this->fixture, ['tahap' => 'kegiatan_perencanaan', 'indikator_id' => null, ...$atribut]);
    }

    private function berkas(JenisBerkas $persyaratan, string $tipe = 'kegiatan', ?string $induk = null): BuktiDukung
    {
        return BuktiDukung::create(['berkasable_type' => $tipe, 'berkasable_id' => $induk ?? $this->kegiatan->id,
            'jenis_berkas_id' => $tipe === 'kegiatan' ? $persyaratan->id : null, 'mode' => 'teks', 'isi_teks' => 'Rincian anggaran',
            'uploaded_by' => $this->fixture['pic']->id, 'created_at' => now()]);
    }

    private function denyBacaKegiatan(User $user): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', 'kegiatan:read')->value('id'), 'unit_id' => $this->fixture['unit']->id,
            'alasan' => 'Pengujian deny baca kegiatan', 'ditetapkan_oleh' => $this->fixture['perencanaan']->id, 'created_at' => now()]);
    }
}
