<?php

namespace Tests\Feature\RencanaAksi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

    public function test_jadwal_ditutup_tanpa_sesi_koreksi_menyembunyikan_batas_kunci_dan_pesan_perencanaan(): void
    {
        $fixture = $this->buatFixtureManual();
        // Aktivasi sebelum batas TW I agar kedua periode normal (bukan lampau 2026).
        $fixture['jadwal']->forceFill(['activated_at' => '2026-02-15 01:00:00'])->save();
        $header = $this->buatDraf($fixture);
        $this->simpan($fixture, $header, 1, [$this->manual($fixture['periode2'], 100)]);
        $pesanPerencanaan = 'Target periode Triwulan I belum lengkap. Periode ini terkunci; target diisi Tim Perencanaan.';

        // Kontrol saat jadwal aktif: batas kunci dan pesan Tim Perencanaan tampil.
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.periode.0.batas_terkunci_pic', '2026-03-31')
                ->where('rencanaAksi.pengajuan.alasan', fn (Collection $alasan): bool => $alasan->contains($pesanPerencanaan)));

        // Tahun ditutup tanpa sesi koreksi: tidak ada aktor yang dapat menulis,
        // sehingga batas kunci PIC dan arahan ke Tim Perencanaan disembunyikan.
        $fixture['jadwal']->update(['status' => 'ditutup', 'closed_at' => now()]);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.periode.0.batas_terkunci_pic', null)
                ->where('rencanaAksi.periode.1.batas_terkunci_pic', null)
                ->where('rencanaAksi.pengajuan.alasan', fn (Collection $alasan): bool => $alasan->contains('Target periode Triwulan I belum lengkap.')
                    && $alasan->every(fn (string $teks): bool => ! str_contains($teks, 'target diisi Tim Perencanaan')))
                ->where('rencanaAksi.can.update', false)
                ->where('rencanaAksi.can.submit', false)
                ->where('rencanaAksi.periode_terkunci', [$fixture['periode1']->id, $fixture['periode2']->id]));

        // Kontras: sesi koreksi aktif membuka tulis lagi, jadi batas kunci PIC kembali dikirim.
        $fixture['jadwal']->update(['status' => 'aktif', 'closed_at' => null, 'koreksi_mulai' => '2026-04-01 00:00:00', 'koreksi_sampai' => '2026-04-30 23:59:59', 'lingkup_koreksi' => [
            'jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$fixture['indikator']->id], 'periode_ids' => [$fixture['periode1']->id],
        ]]);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('rencanaAksi.periode.0.batas_terkunci_pic', '2026-03-31'));
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
