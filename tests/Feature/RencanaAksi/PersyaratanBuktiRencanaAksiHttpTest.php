<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\JenisBerkas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Persyaratan bukti tahap Rencana Aksi dibuat lewat menu Persyaratan Berkas
 * dan langsung menjadi gerbang pengajuan RA (PRD §18 butir 9, ISS-05.02).
 */
class PersyaratanBuktiRencanaAksiHttpTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RencanaAksi $header;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->buatFixtureManual();
        $this->fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        $this->header = $this->drafManual($this->fixture);
    }

    public function test_persyaratan_wajib_tahap_rencana_aksi_dari_menu_persyaratan_menahan_pengajuan_sampai_bukti_ditambahkan(): void
    {
        $this->actingAs($this->fixture['perencanaan'])->post('/jenis-berkas', [
            'nama' => 'Kerangka Acuan Kerja', 'tahap' => 'rencana_aksi', 'wajib' => true,
            'izinkan_file' => false, 'izinkan_tautan' => false, 'izinkan_teks' => true,
        ])->assertSessionHasNoErrors();
        $persyaratan = JenisBerkas::where('tahap', 'rencana_aksi')->sole();
        $pesan = 'Bukti wajib belum lengkap: Kerangka Acuan Kerja.';

        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('bukti.persyaratan.0.id', $persyaratan->id)
                ->where('bukti.ringkasan', ['lengkap' => false, 'total_wajib' => 1, 'terpenuhi_wajib' => 0]));
        $this->ajukan()->assertSessionHasErrors(['pengajuan' => $pesan]);
        $this->assertSame(RencanaAksi::STATUS_DRAFT, $this->header->fresh()->status_alur);
        $this->assertDatabaseCount('rencana_aksi_versi', 0);

        $this->actingAs($this->fixture['pic'])->post(route('rencana-aksi.bukti.store', $this->header->id), [
            'jenis_berkas_id' => $persyaratan->id, 'mode' => 'teks', 'isi_teks' => 'Kerangka acuan kegiatan triwulan.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->fixture['pic'])->get("/rencana-aksi/{$this->header->id}")
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.pengajuan.alasan', fn ($alasan) => ! collect($alasan)->contains($pesan)));
        $this->ajukan()->assertSessionHasNoErrors();
        $this->assertSame(RencanaAksi::STATUS_DIAJUKAN, $this->header->fresh()->status_alur);
    }

    private function ajukan(): TestResponse
    {
        return $this->actingAs($this->fixture['pic'])->post("/rencana-aksi/{$this->header->id}/ajukan", ['expected_versi' => $this->header->fresh()->versi]);
    }
}
