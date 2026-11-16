<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Koreksi bukti kegiatan append-only (Q25): bukti yang sudah dibekukan versi pengukuran tidak dapat dihapus,
 * koreksi lewat berkas pengganti, versi sah lama tetap dibaca laporan, dan pengajuan berikutnya membekukan pengganti.
 */
class KoreksiBuktiKegiatanTest extends TestCase
{
    use CreatesPengukuranFixture { setUp as siapkanPengukuran; }
    use RefreshDatabase;

    private Kegiatan $kegiatan;

    private JenisBerkas $syarat;

    protected function setUp(): void
    {
        $this->siapkanPengukuran();
        $this->kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Bimtek sintetis', 'tujuan' => 'Pengujian koreksi bukti', 'anggaran' => 0, 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $this->kegiatan->id, 'sumber_klaim' => 'rencana_aksi',
            'arah_dampak' => 'menambah', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $this->syarat = JenisBerkas::create(['nama' => 'Dokumentasi', 'tahap' => 'kegiatan', 'indikator_id' => null, 'wajib' => false,
            'semua_mode_wajib' => false, 'izinkan_file' => false, 'izinkan_tautan' => false, 'izinkan_teks' => true, 'aktif' => true, 'urutan' => 1, 'created_by' => $this->actor->id]);
    }

    public function test_bukti_beku_tidak_dapat_dihapus_dan_versi_sah_lama_tetap_dibaca_sampai_pengganti_diajukan(): void
    {
        $this->bukti(['isi_teks' => 'Bukti awal'])->assertSessionHasNoErrors();
        $awal = BuktiDukung::where('berkasable_id', $this->kegiatan->id)->sole();
        // Kontrol positif: bukti yang belum dibekukan versi mana pun masih dapat dihapus.
        $this->bukti(['isi_teks' => 'Bukti sementara'])->assertSessionHasNoErrors();
        $this->hapus(BuktiDukung::where('isi_teks', 'Bukti sementara')->sole())->assertSessionHasNoErrors();

        $this->ajukan()->assertSessionHasNoErrors();
        $v1 = KinerjaSnapshot::sole();
        $this->assertSame([(string) $awal->id], $this->idBukti($v1));

        // AC-2: tidak ada jalan menghapus bukti yang dirujuk versi pengukuran.
        $this->hapus($awal)->assertSessionHasErrors('berkas');
        $this->assertNull($awal->fresh()->dihapus_pada);
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.hapus_ditolak')->where('objek_id', $this->kegiatan->id)->count());
        $this->actingAs($this->actor)->post('/verifikasi/'.$this->pengukuran->id.'/verifikasi', ['versi' => $this->token()])->assertSessionHasNoErrors();
        $this->actingAs($this->actor)->post('/verifikasi/'.$this->pengukuran->id.'/sahkan', ['versi' => $this->token()])->assertSessionHasNoErrors();
        $this->assertNotNull($v1->fresh()->disahkan_at);

        // AC-1: koreksi lewat pengganti beralasan; versi sah tidak berubah.
        $this->bukti(['isi_teks' => 'Bukti koreksi', 'menggantikan_id' => $awal->id, 'alasan_koreksi' => 'Foto tertukar.'])->assertSessionHasNoErrors();
        $pengganti = BuktiDukung::where('menggantikan_id', $awal->id)->sole();
        $this->assertSame($v1->snapshot, $v1->fresh()->snapshot);

        $this->actingAs($this->actor)->post('/verifikasi/'.$this->pengukuran->id.'/buka-kembali', ['alasan' => 'Koreksi bukti kegiatan.', 'versi' => $this->token()])
            ->assertSessionHasNoErrors();
        $this->ajukan()->assertSessionHasNoErrors();
        $v2 = $this->pengukuran->fresh()->latestVersion;
        $this->assertSame(2, $v2->nomor);
        $this->assertNull($v2->disahkan_at);

        // AC-4: pengajuan berikutnya membekukan pengganti beserta hubungan dan alasannya.
        $this->assertSame([(string) $pengganti->id], $this->idBukti($v2));
        $beku = $v2->snapshot['klaim'][0]['kegiatan']['bukti_dukungs'][0];
        $this->assertSame([(string) $awal->id, 'Foto tertukar.', 'Bukti koreksi'], [$beku['menggantikan_id'], $beku['alasan_koreksi'], $beku['isi_teks']]);
        $this->assertSame([(string) $awal->id], $this->idBukti($v1->fresh()));

        // AC-3: laporan resmi tetap membaca versi sah lama selama versi koreksi belum disahkan.
        $this->actingAs($this->actor)->get('/laporan/rekap?jadwal_id='.$this->jadwal->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('baris.0.versi.id', $v1->id)->where('baris.0.status_laporan', 'disahkan'));

        $this->hapus($pengganti)->assertSessionHasErrors('berkas');
        $this->assertNull($pengganti->fresh()->dihapus_pada);
        $this->actingAs($this->actor)->get('/rencana-aksi/'.$this->plan->id.'/kegiatan/'.$this->kegiatan->id.'/realisasi')->assertOk()
            ->assertInertia(fn ($page) => $page->where('berkasPelaksanaan.persyaratan.0.berkas.0.id', $pengganti->id)
                ->where('berkasPelaksanaan.persyaratan.0.berkas.0.dibekukan', true));
    }

    public function test_status_beku_seluruh_berkas_dibaca_dengan_satu_query_versi_pengukuran(): void
    {
        foreach (['Bukti satu', 'Bukti dua', 'Bukti tiga'] as $isi) {
            $this->bukti(['isi_teks' => $isi])->assertSessionHasNoErrors();
        }
        $this->ajukan()->assertSessionHasNoErrors();
        $this->bukti(['isi_teks' => 'Bukti susulan'])->assertSessionHasNoErrors();

        $query = 0;
        DB::listen(function ($event) use (&$query): void {
            $query += str_contains($event->sql, 'pengukuran_versi') ? 1 : 0;
        });
        $this->actingAs($this->actor)->get('/rencana-aksi/'.$this->plan->id.'/kegiatan/'.$this->kegiatan->id.'/realisasi')->assertOk()
            ->assertInertia(fn ($page) => $page->where('berkasPelaksanaan.persyaratan.0.berkas', fn ($berkas) => collect($berkas)
                ->mapWithKeys(fn ($b) => [$b['isi_teks'] => $b['dibekukan']])->all() === ['Bukti satu' => true, 'Bukti dua' => true, 'Bukti tiga' => true, 'Bukti susulan' => false]));
        $this->assertSame(1, $query);
    }

    public function test_basis_data_menolak_pengganti_aktif_kedua_untuk_berkas_kegiatan_yang_sama(): void
    {
        $this->bukti(['isi_teks' => 'Bukti awal'])->assertSessionHasNoErrors();
        $awal = BuktiDukung::where('berkasable_id', $this->kegiatan->id)->sole();
        $pengganti = fn (): BuktiDukung => BuktiDukung::create([...$awal->only(['berkasable_type', 'berkasable_id', 'jenis_berkas_id', 'mode', 'uploaded_by']),
            'menggantikan_id' => $awal->id, 'alasan_koreksi' => 'Koreksi.', 'isi_teks' => 'Pengganti', 'created_at' => now()]);
        $pertama = $pengganti();

        try {
            DB::transaction(fn () => $pengganti());
            $this->fail('Pengganti aktif kedua harus ditolak basis data.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0] ?? null);
            $this->assertStringContainsString('berkas_kegiatan_satu_pengganti', $exception->getMessage());
        }

        // Pengganti yang dihapus lunak tidak lagi berlaku, sehingga koreksi baru atas berkas yang sama sah.
        $pertama->update(['dihapus_pada' => now(), 'dihapus_oleh' => $this->actor->id]);
        $pengganti();
        $this->assertSame(2, BuktiDukung::where('menggantikan_id', $awal->id)->count());
    }

    private function bukti(array $data): TestResponse
    {
        return $this->actingAs($this->actor)->post("/kegiatan/{$this->kegiatan->id}/berkas", ['mode' => 'teks', 'jenis_berkas_id' => $this->syarat->id, ...$data]);
    }

    private function hapus(BuktiDukung $berkas): TestResponse
    {
        return $this->actingAs($this->actor)->delete("/kegiatan/{$this->kegiatan->id}/berkas/{$berkas->id}", ['alasan' => 'Salah unggah']);
    }

    private function ajukan(): TestResponse
    {
        return $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => $this->token(), 'action' => 'ajukan', 'nilai' => 85]);
    }

    private function token(): int
    {
        return (int) DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->value('versi');
    }

    /** @return list<string> */
    private function idBukti(KinerjaSnapshot $versi): array
    {
        return array_column($versi->snapshot['klaim'][0]['kegiatan']['bukti_dukungs'], 'id');
    }
}
