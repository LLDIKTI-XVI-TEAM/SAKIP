<?php

namespace Tests\Feature\Pengukuran;

use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\KinerjaSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Bukti dukung pengukuran: metadata berkas privat, path nonpublik, mode
 * tautan/teks, validasi mode persyaratan, dan ketiadaan endpoint hapus
 * (ISS-11.02–ISS-11.04; Q36 dan Q44).
 */
class BuktiDukungPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_delete_bukti_pengukuran_setelah_diajukan_tidak_tersedia_dan_tidak_mengubah_data(): void
    {
        $bukti = BuktiDukung::create([
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $this->pengukuran->id,
            'mode' => 'teks',
            'isi_teks' => 'Bukti pengukuran sintetis.',
            'uploaded_by' => $this->actor->id,
            'created_at' => now(),
        ]);
        $url = '/pengukuran/'.$this->pengukuran->id;

        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();

        $pengukuran = $this->pengukuran->fresh();
        $snapshot = KinerjaSnapshot::sole()->snapshot;
        $berkasSebelum = (array) DB::table('berkas')->where('id', $bukti->id)->sole();
        $auditSebelum = DB::table('audit_log')->count();

        $response = $this->delete($url.'/bukti/'.$bukti->id);

        $this->assertContains($response->status(), [404, 405], 'Endpoint hapus bukti pengukuran tidak boleh tersedia.');
        $this->assertSame($berkasSebelum, (array) DB::table('berkas')->where('id', $bukti->id)->sole());
        $this->assertNull($berkasSebelum['dihapus_pada']);
        $this->assertNull($berkasSebelum['dihapus_oleh']);
        $this->assertSame($snapshot, KinerjaSnapshot::sole()->snapshot);
        $this->assertSame([$bukti->id], array_column($snapshot['bukti_dukungs'], 'id'));
        $this->assertSame(2, $pengukuran->versi);
        $this->assertSame('diajukan', $pengukuran->status_alur);
        $this->assertSame(1, $pengukuran->versions()->count());
        $this->assertSame($auditSebelum, DB::table('audit_log')->count());
    }

    public function test_metadata_berkas_privat_nama_asli_mime_ukuran_dan_path_aman(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $namaAsli = 'Laporan Kinerja Triwulan I.pdf';

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, [
            'versi' => 1,
            'action' => 'draft',
            'nilai' => 85,
            'bukti' => ['mode' => 'file', 'file' => UploadedFile::fake()->create($namaAsli, 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $berkas = BuktiDukung::sole();
        $this->assertSame($namaAsli, $berkas->nama_asli);
        $this->assertSame('application/pdf', $berkas->mime);
        $this->assertSame(10 * 1024, $berkas->ukuran_bytes);
        $this->assertNotNull($berkas->path);
        $path = (string) $berkas->path;
        $this->assertStringStartsWith('berkas/', $path);
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertStringNotContainsString('Laporan', $path);
        $this->assertStringNotContainsString(' ', $path);
        $this->assertStringNotContainsString('..', $path);
        $this->assertNotSame($namaAsli, basename($path));
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_path_privat_tidak_dapat_diakses_sebagai_url_publik(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $isiPrivat = 'ISI-PRIVAT-SINTETIS';
        // Isi harus benar-benar PDF agar aturan `mimes:pdf` lolos karena isi
        // berkas, bukan semata-mata karena ekstensi nama berkas.
        $pdfPrivat = "%PDF-1.4\n% {$isiPrivat}\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            ."trailer\n<< /Root 1 0 R /Size 3 >>\nstartxref\n0\n%%EOF\n";

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, [
            'versi' => 1,
            'action' => 'draft',
            'nilai' => 85,
            'bukti' => ['mode' => 'file', 'file' => UploadedFile::fake()->createWithContent('bukti-privat.pdf', $pdfPrivat)],
        ])->assertSessionHasNoErrors();

        $berkas = BuktiDukung::sole();
        $this->assertNotNull($berkas->path);
        $path = (string) $berkas->path;
        $this->assertStringContainsString($isiPrivat, Storage::disk('local')->get($path));
        $this->assertFileDoesNotExist(public_path($path));
        $this->assertFileDoesNotExist(public_path('storage/'.$path));

        // Tanpa sesi pun path privat harus tetap tertutup.
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->assertGuest();

        foreach (['/storage/'.$path, '/'.$path, '/storage/app/private/'.$path] as $url) {
            $response = $this->get($url);

            // Route `storage/{path}` milik framework memakai signed URL; tanpa
            // signature hasilnya 403. URL lain tidak memiliki route (404).
            $this->assertContains($response->status(), [403, 404], "Path privat tidak boleh dapat diakses: {$url}");
            $response->assertDontSee($path);
            $response->assertDontSee($isiPrivat);
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_mode_tautan_dan_teks_pengukuran_tersimpan_tanpa_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $persyaratan = JenisBerkas::create([
            'nama' => 'Bukti tautan atau teks',
            'tahap' => 'pengukuran',
            'wajib' => false,
            'izinkan_file' => false,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
            'created_by' => $this->actor->id,
        ]);
        $url = '/pengukuran/'.$this->pengukuran->id;

        $this->actingAs($this->actor)->post($url, [
            'versi' => 1,
            'action' => 'draft',
            'nilai' => 85,
            'bukti' => ['jenis_berkas_id' => $persyaratan->id, 'mode' => 'tautan', 'tautan' => 'https://example.test/bukti-pengukuran'],
        ])->assertSessionHasNoErrors();

        $tautan = BuktiDukung::where('mode', 'tautan')->sole();
        $this->assertSame('https://example.test/bukti-pengukuran', $tautan->tautan);
        $this->assertSame($persyaratan->id, $tautan->jenis_berkas_id);
        $this->assertNull($tautan->isi_teks);
        $this->assertNull($tautan->nama_asli);
        $this->assertNull($tautan->path);
        $this->assertNull($tautan->mime);
        $this->assertNull($tautan->ukuran_bytes);

        $this->post($url, [
            'versi' => 2,
            'action' => 'draft',
            'nilai' => 85,
            'bukti' => ['jenis_berkas_id' => $persyaratan->id, 'mode' => 'teks', 'isi_teks' => 'Keterangan bukti pengukuran.'],
        ])->assertSessionHasNoErrors();

        $teks = BuktiDukung::where('mode', 'teks')->sole();
        $this->assertSame('Keterangan bukti pengukuran.', $teks->isi_teks);
        $this->assertSame($persyaratan->id, $teks->jenis_berkas_id);
        $this->assertNull($teks->tautan);
        $this->assertNull($teks->nama_asli);
        $this->assertNull($teks->path);
        $this->assertNull($teks->mime);
        $this->assertNull($teks->ukuran_bytes);
        $this->assertDatabaseCount('berkas', 2);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_mode_di_luar_izinkan_persyaratan_ditolak_pengukuran(): void
    {
        $persyaratan = JenisBerkas::create([
            'nama' => 'Bukti hanya file',
            'tahap' => 'pengukuran',
            'wajib' => false,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'created_by' => $this->actor->id,
        ]);
        $url = '/pengukuran/'.$this->pengukuran->id;

        foreach ([
            'tautan' => ['mode' => 'tautan', 'tautan' => 'https://example.test/ditolak'],
            'teks' => ['mode' => 'teks', 'isi_teks' => 'Teks ditolak.'],
        ] as $mode => $bukti) {
            $this->actingAs($this->actor)->postJson($url, [
                'versi' => 1,
                'action' => 'draft',
                'nilai' => 85,
                'bukti' => ['jenis_berkas_id' => $persyaratan->id, ...$bukti],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['bukti.mode' => 'Mode atau persyaratan bukti tidak berlaku untuk pengukuran ini.']);

            $this->assertDatabaseCount('berkas', 0);
        }

        $this->assertSame(1, $this->pengukuran->fresh()->versi);
        $this->assertDatabaseCount('pengukuran_versi', 0);
    }
}
