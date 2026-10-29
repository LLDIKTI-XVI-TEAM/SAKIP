<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * Tautan lama kegiatan dipertahankan sebagai pengalihan deterministik ke
 * halaman kegiatan Rencana Aksi pemilik klaim tertua (Q74 §74.1 butir 5).
 */
class AlihkanKegiatanLamaTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
    }

    public function test_daftar_kegiatan_lama_dialihkan_ke_daftar_rencana_aksi(): void
    {
        $this->actingAs($this->actor)->get('/kegiatan')->assertRedirect(route('rencana-aksi.index'));
        // Parameter tahun lama dibuang karena daftar Rencana Aksi tidak memfilternya.
        $this->actingAs($this->actor)->get('/kegiatan?tahun=2026')->assertRedirect(route('rencana-aksi.index'));
    }

    public function test_tautan_ubah_dialihkan_ke_ra_pengklaim_dengan_anchor_baris(): void
    {
        $kegiatan = $this->kegiatan();
        $this->klaim($kegiatan, $this->rencanaAksi, now()->subMinute());

        $this->actingAs($this->actor)->get("/kegiatan/{$kegiatan->id}/edit")
            ->assertRedirect(route('rencana-aksi.kegiatan.index', ['rencanaAksi' => $this->rencanaAksi->id]).'#kegiatan-'.$kegiatan->id);
    }

    public function test_klaim_ganda_dialihkan_ke_klaim_tertua_secara_deterministik(): void
    {
        $kegiatan = $this->kegiatan();
        $tertua = $this->klaim($kegiatan, $this->rencanaAksi, now()->subMinutes(5));
        $lain = $this->rencanaAksiLain();
        $this->klaim($kegiatan, $lain, now()->subMinute());

        $this->actingAs($this->actor)->get("/kegiatan/{$kegiatan->id}/edit")
            ->assertRedirect(route('rencana-aksi.kegiatan.index', ['rencanaAksi' => $tertua->rencana_aksi_id]).'#kegiatan-'.$kegiatan->id);
    }

    public function test_tanpa_izin_baca_unit_kegiatan_ditolak_403_dan_diaudit(): void
    {
        $kegiatan = $this->kegiatan();
        $this->klaim($kegiatan, $this->rencanaAksi, now()->subMinute());
        // Deny per unit menang atas peran superadmin maupun grant, sehingga klaim
        // tidak boleh dibaca dan tujuannya tidak boleh bocor lewat `Location`.
        $this->denyPermission($this->actor, 'kegiatan:read', $this->unit->id);

        $response = $this->actingAs($this->actor)->get("/kegiatan/{$kegiatan->id}/edit");

        $response->assertForbidden()->assertHeaderMissing('Location');
        $audit = AuditLog::where('tindakan', 'kegiatan.baca_ditolak')->sole();
        $this->assertSame('kegiatan', $audit->objek_tipe);
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertNotEmpty($audit->dasar_izin);
        $this->assertSame('kegiatan:read', $audit->dasar_izin['permission']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
    }

    public function test_klaim_ganda_pada_aktor_tanpa_izin_tetap_403_tanpa_membocorkan_ra(): void
    {
        $kegiatan = $this->kegiatan();
        $this->klaim($kegiatan, $this->rencanaAksi, now()->subMinutes(5));
        $lain = $this->rencanaAksiLain();
        $this->klaim($kegiatan, $lain, now()->subMinute());
        $this->denyPermission($this->actor, 'kegiatan:read', $this->unit->id);

        $response = $this->actingAs($this->actor)->get("/kegiatan/{$kegiatan->id}/edit");

        // Penolakan tidak menjadi kanal enumerasi: tanpa `Location` dan isi 403
        // tidak memuat id RA pengklaim mana pun.
        $response->assertForbidden()->assertHeaderMissing('Location');
        $this->assertStringNotContainsString($this->rencanaAksi->id, (string) $response->getContent());
        $this->assertStringNotContainsString($lain->id, (string) $response->getContent());
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.baca_ditolak')->count());
    }

    public function test_kegiatan_tanpa_klaim_dialihkan_ke_daftar_ra_dengan_penjelasan(): void
    {
        $kegiatan = $this->kegiatan();

        $this->actingAs($this->actor)->get("/kegiatan/{$kegiatan->id}/edit")
            ->assertRedirect(route('rencana-aksi.index'))
            ->assertSessionHas('message', 'Kegiatan belum tertaut ke Rencana Aksi.');
    }

    public function test_kegiatan_tidak_ditemukan_dijawab_404(): void
    {
        $this->actingAs($this->actor)->get('/kegiatan/'.(string) Str::uuid().'/edit')->assertNotFound();
    }

    /** @param array<string, mixed> $atribut */
    private function kegiatan(array $atribut = []): Kegiatan
    {
        return Kegiatan::create(array_replace([
            'unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id,
            'nama' => 'Kegiatan lama', 'anggaran' => 0, 'created_by' => $this->actor->id,
        ], $atribut));
    }

    private function klaim(Kegiatan $kegiatan, RencanaAksi $header, mixed $createdAt): KlaimKegiatan
    {
        return KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id,
            'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id,
            'created_at' => $createdAt]);
    }

    private function rencanaAksiLain(): RencanaAksi
    {
        $indikator = $this->indikator->replicate();
        $indikator->kode = 'IKU-RA-LAIN';
        $indikator->save();

        return RencanaAksi::create(['indikator_id' => $indikator->id, 'unit_id' => $this->unit->id, 'jadwal_tahunan_id' => $this->jadwal->id,
            'penanggung_jawab_id' => $this->actor->id, 'tahun' => 2026, 'created_by' => $this->actor->id]);
    }
}
