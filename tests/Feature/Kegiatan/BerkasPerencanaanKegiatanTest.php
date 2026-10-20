<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_mode_tautan_dan_teks_kegiatan_tersimpan_dan_audit_metadata(): void
    {
        $persyaratan = $this->persyaratan([
            'nama' => 'Bukti tautan dan teks', 'izinkan_file' => true, 'izinkan_tautan' => true, 'izinkan_teks' => true,
        ]);
        // Satu berkas mode file menjadi pembanding bahwa mode tautan/teks tidak menambah berkas di disk.
        $this->unggah(null, $persyaratan)->assertSessionHasNoErrors();
        $this->assertCount(1, Storage::disk('local')->allFiles());

        $tautan = 'https://lldikti16.kemdikbud.go.id/bukti/rab.pdf';
        $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
            'mode' => 'tautan', 'jenis_berkas_id' => $persyaratan->id, 'tautan' => $tautan,
        ])->assertSessionHasNoErrors();
        $isiTeks = 'RAB memuat rincian belanja.';
        $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
            'mode' => 'teks', 'jenis_berkas_id' => $persyaratan->id, 'isi_teks' => $isiTeks,
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, Storage::disk('local')->allFiles());
        $buktiTautan = BuktiDukung::where('berkasable_type', 'kegiatan')->where('mode', 'tautan')->sole();
        $this->assertSame($tautan, $buktiTautan->tautan);
        $this->assertNull($buktiTautan->isi_teks);
        $this->assertNull($buktiTautan->path);
        $this->assertNull($buktiTautan->nama_asli);
        $this->assertNull($buktiTautan->ukuran_bytes);
        $buktiTeks = BuktiDukung::where('berkasable_type', 'kegiatan')->where('mode', 'teks')->sole();
        $this->assertSame($isiTeks, $buktiTeks->isi_teks);
        $this->assertNull($buktiTeks->tautan);
        $this->assertNull($buktiTeks->path);
        $this->assertNull($buktiTeks->nama_asli);
        $this->assertNull($buktiTeks->ukuran_bytes);
        $buktiFile = BuktiDukung::where('berkasable_type', 'kegiatan')->where('mode', 'file')->sole();
        Storage::disk('local')->assertExists($buktiFile->path);

        $auditTeks = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $buktiTeks->id)->sole();
        $this->assertSame(27, $auditTeks->nilai_baru['panjang_teks']);
        $this->assertArrayNotHasKey('isi_teks', $auditTeks->nilai_baru);
        $this->assertStringNotContainsString($isiTeks, json_encode($auditTeks->nilai_baru, JSON_THROW_ON_ERROR));

        $auditTautan = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $buktiTautan->id)->sole();
        $this->assertSame($tautan, $auditTautan->nilai_baru['tautan']);
        $this->assertArrayNotHasKey('path', $auditTautan->nilai_baru);

        $auditFile = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $buktiFile->id)->sole();
        $this->assertSame('rab.pdf', $auditFile->nilai_baru['nama_asli']);
        $this->assertArrayNotHasKey('path', $auditFile->nilai_baru);
        $this->assertArrayNotHasKey('isi_teks', $auditFile->nilai_baru);
        $this->assertStringNotContainsString((string) $buktiFile->path, json_encode($auditFile->nilai_baru, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function test_persyaratan_tanpa_batas_memakai_ukuran_dan_format_konfigurasi(): void
    {
        config(['uploads.default_max_kb' => 200, 'uploads.allowed_extensions' => 'pdf']);
        $this->rab->update(['format_diizinkan' => null, 'ukuran_maks_kb' => null]);

        $this->unggah(UploadedFile::fake()->create('rab-besar.pdf', 201, 'application/pdf'))->assertSessionHasErrors('file');
        $this->unggah(UploadedFile::fake()->create('rab.xlsx', 100))->assertSessionHasErrors('file');
        $this->assertSame(0, BuktiDukung::where('berkasable_type', 'kegiatan')->count());

        $this->unggah(UploadedFile::fake()->create('rab.pdf', 200, 'application/pdf'))->assertSessionHasNoErrors();
        $berkas = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();
        $this->assertSame($this->rab->id, $berkas->jenis_berkas_id);
        $this->assertSame('rab.pdf', $berkas->nama_asli);
        Storage::disk('local')->assertExists($berkas->path);
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

    public function test_rab_tidak_dapat_dihapus_atau_diganti_sejak_ra_pengklaim_diajukan_dan_dapat_lagi_setelah_dikembalikan(): void
    {
        $this->unggah()->assertSessionHasNoErrors();
        $berkas = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();

        $this->header->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);
        $this->hapus($berkas)->assertSessionHasErrors('berkas');
        $this->assertNull($berkas->fresh()->dihapus_pada);
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.hapus_ditolak')->where('objek_id', $this->kegiatan->id)->count());
        // Berkas yang dibekukan versi RA harus tetap berlaku, jadi penggantian ditolak seperti penghapusan (pola Q36).
        $this->ganti($berkas, 'Nilai satuan diperbaiki')->assertSessionHasErrors('berkas');
        $this->assertFalse(BuktiDukung::where('menggantikan_id', $berkas->id)->exists());
        $this->actingAs($this->fixture['pic'])->get("/kegiatan/{$this->kegiatan->id}/edit")->assertInertia(fn ($page) => $page
            ->where('berkasPerencanaan.alasan_tolak_ganti', fn ($alasan) => str_contains((string) $alasan, 'tidak dapat dihapus atau diganti'))
            ->where('berkasPerencanaan.alasan_tolak_unggah', null));
        // Unggahan baru tetap diterima, mis. persyaratan indikator lain atas kegiatan bersama (Q42 §42.4 butir 4).
        $this->unggah(UploadedFile::fake()->create('rab-tambahan.pdf', 10))->assertSessionHasNoErrors();
        $this->assertSame(2, PersyaratanPerencanaanKegiatan::buktiBerlaku((string) $this->kegiatan->id)->count());

        // Setelah dikembalikan, koreksi lewat berkas pengganti diterima tanpa mengubah berkas lama, dan wajib beralasan (Q25).
        $this->header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);
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

        $this->hapus($pengganti, '')->assertSessionHasErrors('alasan');
        $this->hapus($pengganti)->assertSessionHasNoErrors();
        $this->assertNotNull($pengganti->fresh()->dihapus_pada);
        Storage::disk('local')->assertExists($pengganti->path);
    }

    public function test_berkas_kegiatan_terlaksana_tidak_dapat_dihapus_atau_diganti(): void
    {
        $this->unggah()->assertSessionHasNoErrors();
        $berkas = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();
        // RA pengklaim tetap draf: penolakan murni berasal dari status kegiatan yang sudah terlaksana.
        $this->kegiatan->update(['status' => 'terlaksana']);
        $this->assertSame('terlaksana', $this->kegiatan->fresh()->status);
        $this->assertSame(RencanaAksi::STATUS_DRAFT, $this->header->fresh()->status_alur);

        $this->actingAs($this->fixture['pic'])
            ->deleteJson("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}", ['alasan' => 'RAB keliru diunggah'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('berkas');
        $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
            'mode' => 'file', 'jenis_berkas_id' => $this->rab->id, 'file' => UploadedFile::fake()->create('rab-revisi.xlsx', 10),
            'menggantikan_id' => $berkas->id, 'alasan_koreksi' => 'Nilai satuan diperbaiki',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('berkas');

        $this->assertNull($berkas->fresh()->dihapus_pada);
        $this->assertFalse(BuktiDukung::where('menggantikan_id', $berkas->id)->exists());
        $this->assertSame(1, BuktiDukung::where('berkasable_type', 'kegiatan')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.hapus_ditolak')->where('objek_id', $this->kegiatan->id)->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.unggah_ditolak')->where('objek_id', $this->kegiatan->id)->count());
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

    public function test_unduhan_hanya_untuk_berkas_tahap_perencanaan_dan_deny_baca_berkas_menang(): void
    {
        $this->unggah()->assertSessionHasNoErrors();
        $rab = BuktiDukung::where('berkasable_type', 'kegiatan')->sole();
        Storage::disk('local')->put('berkas/kegiatan/spj.pdf', 'spj');
        $spj = BuktiDukung::create(['berkasable_type' => 'kegiatan', 'berkasable_id' => $this->kegiatan->id,
            'jenis_berkas_id' => $this->persyaratan(['nama' => 'Laporan pelaksanaan', 'tahap' => 'kegiatan'])->id, 'mode' => 'file',
            'path' => 'berkas/kegiatan/spj.pdf', 'nama_asli' => 'spj.pdf', 'uploaded_by' => $this->fixture['pic']->id, 'created_at' => now()]);
        Storage::disk('local')->put('berkas/kegiatan/bebas.pdf', 'bebas');
        $bebas = BuktiDukung::create(['berkasable_type' => 'kegiatan', 'berkasable_id' => $this->kegiatan->id, 'mode' => 'file',
            'path' => 'berkas/kegiatan/bebas.pdf', 'nama_asli' => 'bebas.pdf', 'uploaded_by' => $this->fixture['pic']->id, 'created_at' => now()]);
        $perencanaan = $this->fixture['perencanaan'];

        // Route RAB bukan jalur unduh bukti pelaksanaan maupun lampiran bebas.
        $this->actingAs($perencanaan)->get("/kegiatan/{$this->kegiatan->id}/berkas/{$spj->id}")->assertNotFound();
        $this->actingAs($perencanaan)->get("/kegiatan/{$this->kegiatan->id}/berkas/{$bebas->id}")->assertNotFound();
        $this->actingAs($perencanaan)->get("/kegiatan/{$this->kegiatan->id}/berkas/{$rab->id}")->assertOk();

        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $perencanaan->id,
            'permission_id' => Permission::where('kode', 'berkas:read')->value('id'), 'unit_id' => $this->fixture['unit']->id,
            'alasan' => 'Pengujian deny baca berkas', 'ditetapkan_oleh' => $perencanaan->id, 'created_at' => now()]);
        $this->actingAs($perencanaan)->get("/kegiatan/{$this->kegiatan->id}/berkas/{$rab->id}")->assertForbidden();
        $this->actingAs($perencanaan)->get("/kegiatan/{$this->kegiatan->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page->where('berkasPerencanaan.persyaratan.0.berkas', [])
                ->where('berkasPerencanaan.persyaratan.0.pemenuhan.terpenuhi', true));
    }

    private function unggah(?UploadedFile $file = null, ?JenisBerkas $persyaratan = null)
    {
        return $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
            'mode' => 'file', 'jenis_berkas_id' => ($persyaratan ?? $this->rab)->id, 'file' => $file ?? UploadedFile::fake()->create('rab.pdf', 10),
        ]);
    }

    private function ganti(BuktiDukung $berkas, string $alasan)
    {
        return $this->actingAs($this->fixture['pic'])->post("/kegiatan/{$this->kegiatan->id}/berkas", [
            'mode' => 'file', 'jenis_berkas_id' => $this->rab->id, 'file' => UploadedFile::fake()->create('rab-revisi.xlsx', 10),
            'menggantikan_id' => $berkas->id, 'alasan_koreksi' => $alasan,
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
