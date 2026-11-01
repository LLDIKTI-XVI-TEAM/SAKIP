<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\KlaimKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

class SesiKoreksiRencanaAksiTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    public function test_aksi_resmi_hanya_membuka_target_tercakup_bagi_perencanaan_tanpa_membuka_pic_dan_klaim(): void
    {
        $f = $this->buatFixtureManual();
        $header = $this->drafManual($f);
        $this->bukaSesi($f);
        $payload = ['expected_versi' => $header->versi, 'expected_snapshot_id' => $f['snapshot']->id, 'expected_snapshot_versi' => 1,
            'targets' => [$this->manual($f['periode1'], 75)]];

        $this->actingAs($f['pic'])->post("/rencana-aksi/{$header->id}/target", $payload)->assertSessionHasErrors('jendela');
        $this->assertSame($header->versi, $header->fresh()->versi);
        $this->actingAs($f['perencanaan'])->post("/rencana-aksi/{$header->id}/target", [
            ...$payload, 'targets' => [$this->manual($f['periode2'], 75)],
        ])->assertSessionHasErrors('jendela');
        $this->actingAs($f['perencanaan'])->post("/rencana-aksi/{$header->id}/target", $payload)->assertSessionHasNoErrors();
        $this->assertSame($header->versi + 1, $header->fresh()->versi);
        $this->actingAs($f['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.update', false));

        $klaim = KlaimKegiatan::where('rencana_aksi_id', $header->id)->sole();
        $this->actingAs($f['perencanaan'])->delete("/kegiatan/{$klaim->kegiatan_id}/klaim/{$klaim->id}", ['alasan' => 'Koreksi klaim'])
            ->assertSessionHasErrors('jendela');
        $this->assertTrue($klaim->fresh() !== null);
    }

    public function test_kedaluwarsa_dan_tutup_ulang_menutup_ra_walau_tanggal_penutupan_asli_belum_lewat(): void
    {
        $f = $this->buatFixtureManual();
        $header = $this->drafManual($f);
        $this->bukaSesi($f);
        $sekarang = now()->copy();
        $payload = ['expected_versi' => $header->versi, 'expected_snapshot_id' => $f['snapshot']->id, 'expected_snapshot_versi' => 1,
            'targets' => [$this->manual($f['periode1'], 75)]];
        $this->travelTo($sekarang->copy()->addHours(2));
        $this->actingAs($f['perencanaan'])->post("/rencana-aksi/{$header->id}/target", $payload)->assertSessionHasErrors('jendela');
        $this->travelTo($sekarang);
        $this->actingAs($f['perencanaan'])->post("/jadwal/{$f['jadwal']->id}/tutup", [
            'expected_revisi' => $f['jadwal']->fresh()->revisi, 'alasan' => 'Koreksi selesai lebih awal',
        ])->assertSessionHasNoErrors();
        $this->actingAs($f['perencanaan'])->post("/rencana-aksi/{$header->id}/target", $payload)->assertSessionHasErrors('jendela');
        $this->assertSame($header->versi, $header->fresh()->versi);
    }

    private function bukaSesi(array $f): void
    {
        $this->actingAs($f['perencanaan'])->post("/jadwal/{$f['jadwal']->id}/tutup", [
            'expected_revisi' => $f['jadwal']->fresh()->revisi, 'alasan' => 'Penutupan untuk koreksi',
        ])->assertSessionHasNoErrors();
        $this->actingAs($f['perencanaan'])->post("/jadwal/{$f['jadwal']->id}/buka-kembali", [
            'expected_revisi' => $f['jadwal']->fresh()->revisi, 'alasan' => 'Perbaikan target resmi',
            'koreksi_mulai' => now()->subHour()->setTimezone('Asia/Makassar')->format('Y-m-d\TH:i'),
            'koreksi_sampai' => now()->addHour()->setTimezone('Asia/Makassar')->format('Y-m-d\TH:i'),
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$f['indikator']->id], 'periode_ids' => [$f['periode1']->id]],
        ])->assertSessionHasNoErrors();
    }
}
