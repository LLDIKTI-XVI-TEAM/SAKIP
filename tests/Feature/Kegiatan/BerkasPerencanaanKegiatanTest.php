<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Berkas persyaratan tahap perencanaan kegiatan (RAB) berinduk kegiatan
 * (Q42 §42.3–§42.5): persyaratan global + khusus indikator RA pengklaim,
 * imutabilitas sejak RA pengklaim diajukan (pola Q36), berkas pengganti
 * append-only (Q25), dan akses detail kegiatan per unit (Q54).
 */
class BerkasPerencanaanKegiatanTest extends TestCase
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
        Storage::fake('local');
        $this->fixture = $this->buatFixtureManual();
        $this->header = $this->buatDraf($this->fixture);
        $this->kegiatan = Kegiatan::findOrFail(KlaimKegiatan::where('rencana_aksi_id', $this->header->id)->value('kegiatan_id'));
        $this->kegiatan->update(['anggaran' => 5000000]);
        $this->grant($this->fixture['pic'], 'kegiatan:update', $this->fixture['unit']->id, $this->fixture['perencanaan']);
        $this->rab = $this->persyaratan(['nama' => 'RAB', 'format_diizinkan' => 'pdf,xlsx', 'hanya_kegiatan_beranggaran' => true]);
    }

    public function test_pic_mengunggah_rab_dan_halaman_ubah_menampilkan_pemenuhan_serta_contoh(): void
    {
        $contoh = BuktiDukung::create(['berkasable_type' => 'jenis_berkas', 'berkasable_id' => $this->rab->id, 'mode' => 'file', 'nama_asli' => 'contoh-rab.xlsx',
            'path' => 'berkas/jenis_berkas/contoh.xlsx', 'mime' => 'application/vnd.ms-excel', 'ukuran_bytes' => 10, 'uploaded_by' => $this->fixture['perencanaan']->id, 'created_at' => now()]);

        $this->unggah(UploadedFile::fake()->create('rab.docx', 10))->assertSessionHasErrors('file');
        $this->unggah()->assertSessionHasNoErrors();

        $berkas = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();
        $this->assertSame([$this->rab->id, 'rab.pdf'], [$berkas->jenis_berkas_id, $berkas->nama_asli]);
        $audit = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $berkas->id)->sole();
        $this->assertSame($this->kegiatan->id, $audit->nilai_baru['kegiatan_id']);
        $this->assertSame('kegiatan:update', $audit->dasar_izin['permission']);
        $this->actingAs($this->fixture['pic'])->get("/kegiatan/{$this->kegiatan->id}/edit")->assertOk()->assertInertia(fn ($page) => $page
            ->where('berkasPerencanaan.persyaratan.0.id', $this->rab->id)
            ->where('berkasPerencanaan.persyaratan.0.pemenuhan.terpenuhi', true)
            ->where('berkasPerencanaan.persyaratan.0.berkas.0.id', $berkas->id)
            ->where('berkasPerencanaan.persyaratan.0.contoh.0.id', $contoh->id)
            ->where('berkasPerencanaan.alasan_tolak_unggah', null)
            ->where('berkasPerencanaan.alasan_tolak_hapus', null));
    }

    public function test_persyaratan_global_dan_indikator_pengklaim_berlaku_sedangkan_indikator_lain_ditolak(): void
    {
        $lain = IndikatorKinerja::create([...$this->fixture['indikator']->only(['sasaran_strategis_id', 'unit_id', 'satuan', 'tipe_perhitungan', 'arah',
            'presisi', 'desimal_tampilan', 'status', 'tahun_mulai_berlaku', 'created_by', 'created_by_role']), 'kode' => 'IKU-LAIN', 'nama' => 'Indikator lain']);
        $khusus = $this->persyaratan(['nama' => 'KAK indikator ini', 'indikator_id' => $this->fixture['indikator']->id]);
        $asing = $this->persyaratan(['nama' => 'KAK indikator lain', 'indikator_id' => $lain->id]);

        $this->unggah(null, $khusus)->assertSessionHasNoErrors();
        $this->unggah(null, $asing)->assertSessionHasErrors('jenis_berkas_id');
        $this->assertSame(1, BuktiDukung::where('berkasable_type', 'kegiatan')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.unggah_ditolak')->where('objek_id', $this->kegiatan->id)->count());
    }

    public function test_rab_tidak_dapat_dihapus_sejak_ra_pengklaim_diajukan_dan_dapat_lagi_setelah_dikembalikan(): void
    {
        $this->unggah()->assertSessionHasNoErrors();
        $berkas = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();

        $this->header->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);
        $this->hapus($berkas)->assertSessionHasErrors('berkas');
        $this->assertNull($berkas->fresh()->dihapus_pada);
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.hapus_ditolak')->where('objek_id', $this->kegiatan->id)->count());

        // Koreksi lewat berkas pengganti tetap diterima tanpa mengubah berkas lama, dan wajib beralasan (Q25).
        foreach ([[], ['alasan_koreksi' => '   ']] as $tanpaAlasan) {
            $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
                'mode' => 'file', 'jenis_berkas_id' => $this->rab->id, 'file' => UploadedFile::fake()->create('rab-revisi.xlsx', 10),
                'menggantikan_id' => $berkas->id, ...$tanpaAlasan,
            ])->assertSessionHasErrors('alasan_koreksi');
        }
        $this->assertFalse(BuktiDukung::where('menggantikan_id', $berkas->id)->exists());
        $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
            'mode' => 'file', 'jenis_berkas_id' => $this->rab->id, 'file' => UploadedFile::fake()->create('rab-revisi.xlsx', 10),
            'menggantikan_id' => $berkas->id, 'alasan_koreksi' => 'Nilai satuan diperbaiki',
        ])->assertSessionHasNoErrors();
        $pengganti = BuktiDukung::where('menggantikan_id', $berkas->id)->sole();
        $this->assertSame('Nilai satuan diperbaiki', $pengganti->alasan_koreksi);
        $this->assertNull($berkas->fresh()->dihapus_pada);

        $this->header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);
        $this->hapus($pengganti, '')->assertSessionHasErrors('alasan');
        $this->hapus($pengganti)->assertSessionHasNoErrors();
        $this->assertNotNull($pengganti->fresh()->dihapus_pada);
        Storage::disk('local')->assertExists($pengganti->path);
    }

    public function test_tanpa_akses_detail_kegiatan_unit_mutasi_ditolak_dan_unduhan_tertutup(): void
    {
        $this->unggah()->assertSessionHasNoErrors();
        $berkas = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();
        $unitLain = Unit::create(['nama' => 'Unit Lain', 'status' => 'aktif', 'created_by' => $this->fixture['perencanaan']->id]);
        $pegawaiLain = $this->penggunaDenganPeran('pegawai');
        $this->grant($pegawaiLain, 'kegiatan:update', $unitLain->id, $this->fixture['perencanaan']);

        $this->actingAs($pegawaiLain)->post("/kegiatan/{$this->kegiatan->id}/berkas", ['mode' => 'teks', 'jenis_berkas_id' => $this->rab->id, 'isi_teks' => 'RAB'])
            ->assertForbidden();
        $this->actingAs($pegawaiLain)->delete("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}", ['alasan' => 'Tidak berhak'])->assertForbidden();
        $this->actingAs($pegawaiLain)->get("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}")->assertForbidden();
        $this->assertSame(1, BuktiDukung::where('berkasable_type', 'kegiatan')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.unggah_ditolak')->where('objek_id', $this->kegiatan->id)->count());

        $this->actingAs($this->fixture['perencanaan'])->get("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}")->assertOk()->assertDownload('rab.pdf');
        $pimpinan = $this->penggunaDenganPeran('pimpinan');
        $this->actingAs($pimpinan)->get("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}")->assertOk();
    }

    private function unggah(?UploadedFile $file = null, ?JenisBerkas $persyaratan = null)
    {
        return $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
            'mode' => 'file', 'jenis_berkas_id' => ($persyaratan ?? $this->rab)->id, 'file' => $file ?? UploadedFile::fake()->create('rab.pdf', 10),
        ]);
    }

    private function hapus(BuktiDukung $berkas, string $alasan = 'RAB keliru diunggah')
    {
        return $this->actingAs($this->fixture['pic'])->delete("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}", ['alasan' => $alasan]);
    }

    /** @param array<string, mixed> $atribut */
    private function persyaratan(array $atribut): JenisBerkas
    {
        return $this->buatPersyaratanWajib($this->fixture, ['tahap' => 'kegiatan_perencanaan', 'indikator_id' => null, 'izinkan_teks' => true, ...$atribut]);
    }
}
