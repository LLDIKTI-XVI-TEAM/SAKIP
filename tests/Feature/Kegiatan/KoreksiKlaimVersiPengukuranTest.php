<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Versi pengukuran membekukan daftar klaim yang diperiksa. Perubahan pada data
 * kerja tidak boleh menulis ulang snapshot versi lama; sampai penulis klaim
 * tahap pengukuran tersedia, koreksi klaim pengukuran ditolak dengan audit.
 */
class KoreksiKlaimVersiPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_versi_pengukuran_lama_tetap_identik_setelah_klaim_data_kerja_berubah(): void
    {
        $klaim = $this->klaimPengukuran();
        $this->ajukan($this->actor, 85);
        $versiLama = $this->pengukuran->versions()->orderBy('nomor')->firstOrFail();
        $snapshotLama = $versiLama->snapshot;
        $this->assertSame([$klaim->id], array_column($snapshotLama['klaim'], 'id'));

        $this->kembalikan();

        // Belum ada jalur tulis klaim tahap pengukuran (ISS-06.05 bagian 2),
        // sehingga koreksi data kerja disimulasikan langsung pada baris kerja.
        $klaim->update(['arah_dampak' => 'mengurangi', 'catatan' => 'Koreksi data kerja.']);

        $this->ajukan($this->actor, 91);

        $this->assertEquals($snapshotLama, $versiLama->fresh()->snapshot);
        $versiBaru = $this->pengukuran->versions()->get()->last();
        $this->assertNotSame($versiLama->id, $versiBaru->id);
        $this->assertSame('mengurangi', $versiBaru->snapshot['klaim'][0]['arah_dampak']);
        $this->assertSame('Koreksi data kerja.', $versiBaru->snapshot['klaim'][0]['catatan']);
    }

    public function test_koreksi_klaim_tahap_pengukuran_tidak_tersedia_dan_penolakan_diaudit(): void
    {
        $klaim = $this->klaimPengukuran();
        $reviewer = $this->userWithRole('perencanaan');

        // Siklus nyata: pengukuran hanya diajukan setelah RA disahkan, sehingga
        // guard status RA menolak lebih dulu.
        $this->actingAs($reviewer)
            ->delete("/kegiatan/{$klaim->kegiatan_id}/klaim/{$klaim->id}", ['alasan' => 'Koreksi klaim pengukuran.'])
            ->assertSessionHasErrors('status_alur');
        $this->assertDatabaseHas('klaim_kegiatan', ['id' => $klaim->id]);

        // Tanpa status RA yang menghalangi, penolakan tetap datang dari sumber klaim.
        $this->plan->update(['status_alur' => 'draft']);
        $this->actingAs($reviewer)
            ->delete("/kegiatan/{$klaim->kegiatan_id}/klaim/{$klaim->id}", ['alasan' => 'Koreksi klaim pengukuran.'])
            ->assertSessionHasErrors('klaim');

        $this->assertDatabaseHas('klaim_kegiatan', ['id' => $klaim->id]);
        $this->assertSame(2, AuditLog::where('tindakan', 'klaim_kegiatan.hapus_ditolak')->count());

        // Jalur tambah yang ada selalu menulis klaim tahap RA, bukan pengukuran.
        $kegiatan = Kegiatan::create([
            'unit_id' => $this->unit->id,
            'tahun' => 2026,
            'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan tambahan',
            'anggaran' => 0,
            'created_by' => $this->actor->id,
        ]);
        $this->actingAs($this->actor)
            ->post("/kegiatan/{$kegiatan->id}/klaim", ['rencana_aksi_id' => $this->plan->id, 'arah_dampak' => 'menambah'])
            ->assertSessionHasNoErrors();

        $baru = KlaimKegiatan::where('kegiatan_id', $kegiatan->id)->sole();
        $this->assertSame('rencana_aksi', $baru->sumber_klaim);
        $this->assertNull($baru->pengukuran_id);
    }

    private function klaimPengukuran(): KlaimKegiatan
    {
        $kegiatan = Kegiatan::create([
            'unit_id' => $this->unit->id,
            'tahun' => 2026,
            'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan lanjutan',
            'anggaran' => 0,
            'created_by' => $this->actor->id,
        ]);

        return KlaimKegiatan::create([
            'rencana_aksi_id' => $this->plan->id,
            'kegiatan_id' => $kegiatan->id,
            'sumber_klaim' => 'pengukuran',
            'pengukuran_id' => $this->pengukuran->id,
            'arah_dampak' => 'menambah',
            'created_by' => $this->actor->id,
            'created_at' => now(),
        ]);
    }

    private function ajukan(User $aktor, int $nilai): void
    {
        $this->actingAs($aktor)->post('/pengukuran/'.$this->pengukuran->id, [
            'versi' => $this->pengukuran->fresh()->versi,
            'action' => 'ajukan',
            'nilai' => $nilai,
        ])->assertSessionHasNoErrors()->assertRedirect('/pengukuran');

        $this->pengukuran->refresh();
    }

    private function kembalikan(): void
    {
        $this->actingAs($this->userWithRole('perencanaan'))
            ->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan', [
                'versi' => $this->pengukuran->fresh()->versi,
                'catatan' => 'Koreksi klaim kegiatan.',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->pengukuran->refresh();
        $this->assertSame('dikembalikan', $this->pengukuran->status_alur);
    }
}
