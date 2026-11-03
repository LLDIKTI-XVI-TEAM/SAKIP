<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Koreksi klaim tahap Rencana Aksi mengikuti siklus RA (Q26): perubahan pada
 * data kerja baru masuk versi pengajuan berikutnya, sedangkan snapshot versi
 * lama tetap utuh. Guard status dan jejak audit diuji lewat jalur HTTP nyata.
 */
class KoreksiKlaimVersiRencanaAksiTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    public function test_koreksi_klaim_setelah_buka_kembali_masuk_versi_berikutnya_dan_versi_lama_tetap(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $klaimAwal = $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan awal');

        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();
        $versiLama = RencanaAksiVersi::sole();
        $snapshotLama = $versiLama->snapshot;
        $this->assertSame([$klaimAwal->id], array_column($snapshotLama['klaim'], 'id'));

        $this->sahkanDanBukaKembali($fixture, $header, $versiLama);

        $this->actingAs($fixture['perencanaan'])
            ->delete("/kegiatan/{$klaimAwal->kegiatan_id}/klaim/{$klaimAwal->id}", ['alasan' => 'Klaim kegiatan tidak tepat.'])
            ->assertSessionHasNoErrors();

        $kegiatanBaru = Kegiatan::create([
            'unit_id' => $fixture['unit']->id,
            'tahun' => 2026,
            'periode_id' => $fixture['periode1']->id,
            'nama' => 'Kegiatan pengganti',
            'anggaran' => 0,
            'created_by' => $fixture['perencanaan']->id,
        ]);
        $this->actingAs($fixture['perencanaan'])
            ->post("/kegiatan/{$kegiatanBaru->id}/klaim", [
                'rencana_aksi_id' => $header->id,
                'arah_dampak' => 'menambah',
            ])->assertSessionHasNoErrors();

        $this->ajukan($fixture['pic'], $header)->assertSessionHasNoErrors();

        $versiBaru = RencanaAksiVersi::where('nomor', 2)->sole();
        $this->assertSame([$kegiatanBaru->id], array_column($versiBaru->snapshot['klaim'], 'kegiatan_id'));
        $this->assertEquals($snapshotLama, $versiLama->fresh()->snapshot);
        $this->assertDatabaseMissing('klaim_kegiatan', ['id' => $klaimAwal->id]);
        $this->assertDatabaseHas('klaim_kegiatan', ['kegiatan_id' => $kegiatanBaru->id, 'rencana_aksi_id' => $header->id]);
    }

    public function test_ganti_klaim_mencatat_pasangan_audit_hapus_dan_tambah_dengan_dasar_izin(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $klaimLama = $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan lama');
        // Tahap koreksi RA: status dikembalikan adalah jalur yang sama dengan hasil buka-kembali.
        $header->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);

        $this->actingAs($fixture['perencanaan'])
            ->delete("/kegiatan/{$klaimLama->kegiatan_id}/klaim/{$klaimLama->id}", ['alasan' => 'Klaim diganti.'])
            ->assertSessionHasNoErrors();

        $kegiatanBaru = Kegiatan::create([
            'unit_id' => $fixture['unit']->id,
            'tahun' => 2026,
            'periode_id' => $fixture['periode1']->id,
            'nama' => 'Kegiatan pengganti',
            'anggaran' => 0,
            'created_by' => $fixture['perencanaan']->id,
        ]);
        $this->actingAs($fixture['perencanaan'])
            ->post("/kegiatan/{$kegiatanBaru->id}/klaim", [
                'rencana_aksi_id' => $header->id,
                'arah_dampak' => 'mengurangi',
                'catatan' => 'Klaim pengganti.',
            ])->assertSessionHasNoErrors();

        $hapus = AuditLog::where('tindakan', 'klaim_kegiatan.hapus')->sole();
        $tambah = AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->sole();

        $this->assertSame($klaimLama->id, $hapus->objek_id);
        $this->assertSame($klaimLama->kegiatan_id, $hapus->nilai_lama['kegiatan_id']);
        $this->assertSame('Klaim diganti.', $hapus->alasan);
        $this->assertSame('rencana_aksi:update', $hapus->dasar_izin['rencana_aksi']['permission']);
        $this->assertSame('kegiatan:update', $hapus->dasar_izin['kegiatan']['permission']);

        $this->assertSame($kegiatanBaru->id, $tambah->nilai_baru['kegiatan_id']);
        $this->assertSame('mengurangi', $tambah->nilai_baru['arah_dampak']);
        $this->assertSame('Klaim pengganti.', $tambah->nilai_baru['catatan']);
        $this->assertSame($fixture['perencanaan']->id, $tambah->actor_id);
        $this->assertSame('rencana_aksi:update', $tambah->dasar_izin['rencana_aksi']['permission']);
    }

    public function test_koreksi_klaim_ditolak_saat_ra_diajukan_dan_diaudit_dengan_dasar_izin(): void
    {
        [$fixture, $header] = $this->drafTanpaKegiatan('2026-02-15 09:00:00');
        $klaim = $this->klaimKegiatan($fixture, $header, $fixture['periode1']->id, 'Kegiatan terkunci');
        $header->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);

        $this->actingAs($fixture['perencanaan'])
            ->delete("/kegiatan/{$klaim->kegiatan_id}/klaim/{$klaim->id}", ['alasan' => 'Klaim keliru.'])
            ->assertSessionHasErrors('status_alur');
        $this->actingAs($fixture['perencanaan'])
            ->post("/kegiatan/{$klaim->kegiatan_id}/klaim", ['rencana_aksi_id' => $header->id, 'arah_dampak' => 'menambah'])
            ->assertSessionHasErrors('status_alur');

        $this->assertDatabaseHas('klaim_kegiatan', ['id' => $klaim->id]);
        $hapusDitolak = AuditLog::where('tindakan', 'klaim_kegiatan.hapus_ditolak')->sole();
        $tambahDitolak = AuditLog::where('tindakan', 'klaim_kegiatan.tambah_ditolak')->sole();
        $this->assertSame('rencana_aksi:update', $hapusDitolak->dasar_izin['rencana_aksi']['permission']);
        $this->assertSame('kegiatan:update', $hapusDitolak->dasar_izin['kegiatan']['permission']);
        $this->assertSame('rencana_aksi:update', $tambahDitolak->dasar_izin['rencana_aksi']['permission']);
        $this->assertStringContainsString('draf atau dikembalikan', $hapusDitolak->alasan);
    }

    /**
     * Siklus review nyata: ajukan PIC lalu verifikasi, sahkan, dan buka-kembali
     * oleh Perencanaan. Setiap langkah memakai token versi pengajuan yang sama.
     *
     * @param  array<string, mixed>  $fixture
     */
    private function sahkanDanBukaKembali(array $fixture, RencanaAksi $header, RencanaAksiVersi $versi): void
    {
        foreach (['verifikasi', 'sahkan', 'buka-kembali'] as $aksi) {
            $data = [
                'expected_versi' => $header->fresh()->versi,
                'versi_pengajuan_id' => $versi->id,
            ];
            if ($aksi === 'buka-kembali') {
                $data['alasan'] = 'Klaim kegiatan perlu dikoreksi.';
            }

            $this->actingAs($fixture['perencanaan'])->postJson("/rencana-aksi/{$header->id}/{$aksi}", $data)->assertOk();
        }

        $this->assertSame(RencanaAksi::STATUS_DIKEMBALIKAN, $header->fresh()->status_alur);
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function drafTanpaKegiatan(string $aktivasi): array
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => $aktivasi]);
        $header = $this->drafManual($fixture);
        KlaimKegiatan::where('rencana_aksi_id', $header->id)->delete();

        return [$fixture, $header];
    }

    /** @param array<string, mixed> $fixture */
    private function klaimKegiatan(array $fixture, RencanaAksi $header, string $periodeId, string $nama): KlaimKegiatan
    {
        $kegiatan = Kegiatan::create([
            'unit_id' => $fixture['unit']->id,
            'tahun' => 2026,
            'periode_id' => $periodeId,
            'nama' => $nama,
            'anggaran' => 0,
            'created_by' => $fixture['pic']->id,
        ]);

        return KlaimKegiatan::create([
            'rencana_aksi_id' => $header->id,
            'kegiatan_id' => $kegiatan->id,
            'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi',
            'created_by' => $fixture['pic']->id,
            'created_at' => now(),
        ]);
    }

    private function ajukan(User $aktor, RencanaAksi $header): TestResponse
    {
        return $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $header->fresh()->versi]);
    }
}
