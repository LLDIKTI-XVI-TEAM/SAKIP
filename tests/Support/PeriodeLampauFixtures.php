<?php

namespace Tests\Support;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Fixture bersama matriks periode lampau 2026 (ISS-03.05): jadwal dengan
 * TW I–III lampau saat aktivasi (pengisian selesai 31 Maret, aktivasi 5
 * April) dan TW IV normal (pengisian sampai 31 Desember), snapshot target
 * PK manual, PIC bergrant `rencana_aksi:create/update/ajukan` beserta
 * penugasan PJ efektif, dan aktor Perencanaan. Waktu uji berada di
 * 10 April 2026 (setelah jadwal aktif, sebelum pengisian TW IV selesai).
 *
 * Status lampau dihitung `PeriodeTanpaTarget::isPeriodeLampau` dari
 * `pengisian_selesai < activated_at` (WITA), bukan dari tanggal uji.
 */
trait PeriodeLampauFixtures
{
    use RencanaAksiFixtures;

    /**
     * @return array<string, mixed>
     */
    private function siapkanPeriodeLampau2026(?string $targetPk = '100'): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $sufiks = Str::random(4);
        $unit = Unit::create(['nama' => 'Unit Uji Lampau '.$sufiks, 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:ajukan', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-LAMPAU-'.$sufiks, 'nama' => 'Renstra Uji Lampau', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-LAMPAU', 'deskripsi' => 'Sasaran uji lampau']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-LAMPAU-'.Str::random(4),
            'nama' => 'Indikator Manual Uji Lampau',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::firstOrCreate(['nama' => 'Triwulan I', 'urutan' => 1], ['aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::firstOrCreate(['nama' => 'Triwulan II', 'urutan' => 2], ['aktif' => true, 'is_nilai_akhir' => false]);
        $periode3 = Periode::firstOrCreate(['nama' => 'Triwulan III', 'urutan' => 3], ['aktif' => true, 'is_nilai_akhir' => false]);
        $periode4 = Periode::firstOrCreate(['nama' => 'Triwulan IV', 'urutan' => 4], ['aktif' => true, 'is_nilai_akhir' => true]);

        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => '2026-04-05 09:00:00',
        ]);
        // TW I–III: pengisian sudah lewat saat aktivasi 5 April (lampau).
        foreach ([$periode1, $periode2, $periode3] as $periode) {
            PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => '2026-03-31',
                'reviu_mulai' => '2026-04-01',
                'reviu_selesai' => '2026-04-30',
            ]);
        }
        // TW IV: pengisian belum lewat saat aktivasi (normal).
        PeriodeJadwal::create([
            'jadwal_id' => $jadwal->id,
            'periode_id' => $periode4->id,
            'pengisian_mulai' => '2026-10-01',
            'pengisian_selesai' => '2026-12-31',
            'reviu_mulai' => '2027-01-01',
            'reviu_selesai' => '2027-01-31',
        ]);

        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'periode_mulai_id' => $periode1->id,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => $targetPk,
        ]);
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'periode3', 'periode4', 'jadwal', 'snapshot');
    }

    /**
     * Membuat draf RA di jadwal lampau beserta satu klaim kegiatan tahap RA
     * (disisipkan langsung agar versi header tidak berubah, sama dengan
     * `RencanaAksiFixtures::buatDraf`).
     *
     * @param  array<string, mixed>  $fixture
     */
    private function buatDrafLampau(array $fixture, ?User $aktor = null): RencanaAksi
    {
        $this->actingAs($aktor ?? $fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $kegiatan = Kegiatan::create(['unit_id' => $fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $fixture['periode4']->id,
            'nama' => 'Kegiatan pendukung', 'created_by' => $fixture['pic']->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $fixture['pic']->id, 'created_at' => now()]);

        return $header;
    }

    /**
     * Satu sel target manual (komponen null) untuk `SimpanTargetPeriode`.
     *
     * @return array<string, mixed>
     */
    private function sel(Periode $periode, int|string|null $nilai): array
    {
        return ['periode_id' => $periode->id, 'komponen_id' => null, 'nilai' => $nilai, 'keterangan' => null];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  list<array<string, mixed>>  $targets
     * @param  array<string, mixed>  $tambahan  Field payload lain, mis. `alasan_deviasi_pk`.
     */
    private function simpanMatriks(array $fixture, RencanaAksi $header, array $targets, ?User $aktor = null, array $tambahan = []): TestResponse
    {
        return $this->actingAs($aktor ?? $fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->fresh()->versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
            ...$tambahan,
        ]);
    }

    private function ajukanDraf(User $aktor, RencanaAksi $header): TestResponse
    {
        return $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $header->fresh()->versi]);
    }
}
