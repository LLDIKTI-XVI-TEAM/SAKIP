<?php

namespace Tests\Feature\RencanaAksi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/** Penutupan resmi jadwal mengunci tulis Rencana Aksi walau tanggal penutupan belum terlewati. */
class RencanaAksiJadwalDitutupTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    public function test_jadwal_ditutup_sebelum_tanggal_penutupan_menolak_tulis_tanpa_sesi_koreksi(): void
    {
        $fixture = $this->buatFixtureManual();
        // Kontrol positif sebelum penutupan: draf dibuat dan kedua periode tersimpan (versi 2).
        $header = $this->drafManual($fixture);
        $fixture['jadwal']->update(['status' => 'ditutup', 'closed_at' => now()]);
        $payload = ['expected_versi' => 2, 'expected_snapshot_id' => $fixture['snapshot']->id, 'expected_snapshot_versi' => 1,
            'targets' => [$this->manual($fixture['periode1'], 90)]];

        foreach ([$fixture['pic'], $fixture['perencanaan']] as $aktor) {
            $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/target", $payload)
                ->assertSessionHasErrors(['jendela' => 'Tahun jadwal telah ditutup; penyimpanan memerlukan sesi koreksi resmi.']);
        }
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])
            ->assertSessionHasErrors(['jendela' => 'Tahun jadwal telah ditutup; pengajuan memerlukan sesi koreksi resmi.']);
        $this->assertSame(2, $header->fresh()->versi);
    }

    public function test_daftar_memuat_jadwal_ditutup_read_only_setelah_jadwal_aktif(): void
    {
        $ditutup = $this->buatFixtureManual();
        $aktif = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $ditutup['jadwal']->update(['status' => 'ditutup', 'closed_at' => now()]);

        $this->actingAs($ditutup['perencanaan'])->get('/rencana-aksi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('daftar.0.indikator_id', $aktif['indikator']->id)
            ->where('daftar.1.indikator_id', $ditutup['indikator']->id)
            ->where('daftar.1.can.create', false));
    }
}
