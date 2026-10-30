<?php

namespace Tests\Support;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Fixture pengukuran periode lampau 2026 (ISS-07.05). Melengkapi fixture
 * periode lampau bersama (TW I–III lampau, TW IV normal, waktu uji 10 April)
 * dengan grant unit-scoped `pengukuran:create/update` untuk PIC, pembentukan
 * draf pengukuran dari snapshot jadwal, pengajuan lewat route nyata, serta
 * Rencana Aksi sah per periode yang dibutuhkan gerbang periode normal.
 *
 * Semua tanggal sintetis mengikuti `siapkanPeriodeLampau2026`; tidak ada
 * `today()` langsung, seed development, atau akun riil.
 */
trait PengukuranPeriodeLampauFixtures
{
    use PeriodeLampauFixtures;

    /**
     * @return array<string, mixed>
     */
    private function siapkanPengukuranPeriodeLampau2026(): array
    {
        $fixture = $this->siapkanPeriodeLampau2026();
        $this->grant($fixture['pic'], 'pengukuran:create', $fixture['unit']->id, $fixture['perencanaan']);
        $this->grant($fixture['pic'], 'pengukuran:update', $fixture['unit']->id, $fixture['perencanaan']);

        return $fixture;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function buatDrafPengukuran(array $fixture, Periode $periode, ?User $aktor = null, ?JadwalSnapshot $snapshot = null): TestResponse
    {
        return $this->actingAs($aktor ?? $fixture['perencanaan'])->post('/pengukuran/ensure-draft', [
            'jadwal_snapshot_id' => ($snapshot ?? $fixture['snapshot'])->id,
            'periode_id' => $periode->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function drafPengukuran(array $fixture, Periode $periode, ?User $aktor = null, ?JadwalSnapshot $snapshot = null): PengukuranKinerja
    {
        $this->buatDrafPengukuran($fixture, $periode, $aktor, $snapshot)->assertRedirect();

        return PengukuranKinerja::where('indikator_id', ($snapshot ?? $fixture['snapshot'])->indikator_id)
            ->where('periode_id', $periode->id)->sole();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function kirimPengukuran(PengukuranKinerja $pengukuran, User $aktor, array $payload): TestResponse
    {
        return $this->actingAs($aktor)->postJson('/pengukuran/'.$pengukuran->id, [
            'versi' => $pengukuran->fresh()->versi,
            'action' => 'ajukan',
            ...$payload,
        ]);
    }

    /** @return array<string, string> */
    private function narasiLengkap(): array
    {
        return ['progres_kegiatan' => 'Progres pengukuran.', 'kendala_masalah' => 'Kendala pengukuran.',
            'strategi_tindak_lanjut' => 'Strategi pengukuran.'];
    }

    /**
     * Rencana Aksi disahkan sintetis beserta target periode yang konsisten dengan
     * target snapshot; hanya prasyarat gerbang periode normal, bukan alur RA.
     *
     * @param  array<string, mixed>  $fixture
     */
    private function sahkanRencanaAksi(array $fixture, Periode $periode, int|string $target = 100): RencanaAksi
    {
        $header = RencanaAksi::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
            'unit_id' => $fixture['unit']->id,
            'jadwal_tahunan_id' => $fixture['jadwal']->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
            'penanggung_jawab_id' => $fixture['perencanaan']->id,
            'created_by' => $fixture['perencanaan']->id,
            'status_alur' => RencanaAksi::STATUS_DISAHKAN,
            'disahkan_by' => $fixture['perencanaan']->id,
            'disahkan_at' => now(),
        ]);
        RencanaAksiVersi::create([
            'rencana_aksi_id' => $header->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
            'nomor' => 1,
            'diajukan_by' => $fixture['perencanaan']->id,
            'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan',
            'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'snapshot' => ['target_periode' => [[
                'periode_id' => $periode->id,
                'nilai' => $target,
                'status_perhitungan' => 'terhitung',
                'komponen' => [],
            ]]],
            'disahkan_by' => $fixture['perencanaan']->id,
            'disahkan_at' => now(),
        ]);

        return $header;
    }

    /**
     * Indikator penjumlahan sintetis pada jadwal yang sama, agar pengajuan
     * periode lampau dapat memakai `sumber_nilai = komponen`.
     *
     * @param  array<string, mixed>  $fixture
     * @return array{0: IndikatorKinerja, 1: JadwalSnapshot, 2: IndikatorKomponen}
     */
    private function indikatorPenjumlahanLampau(array $fixture): array
    {
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $fixture['sasaran']->id,
            'unit_id' => $fixture['unit']->id,
            'kode' => 'I-LAMPAU-JUMLAH-'.Str::random(4),
            'nama' => 'Indikator Penjumlahan Uji Lampau',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'penjumlahan',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $fixture['perencanaan']->id,
            'created_by_role' => 'perencanaan',
        ]);
        $komponen = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'p',
            'label' => 'Penjumlah',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $fixture['perencanaan']->id,
        ]);
        $snapshot = JadwalSnapshot::create([
            ...$fixture['snapshot']->only(['jadwal_id', 'periode_mulai_id', 'unit_id', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'target']),
            'indikator_id' => $indikator->id,
            'nama' => $indikator->nama,
            'tipe_perhitungan' => 'penjumlahan',
        ]);
        JadwalSnapshotKomponen::create([
            'jadwal_snapshot_id' => $snapshot->id,
            'komponen_id' => $komponen->id,
            'kode' => $komponen->kode,
            'label' => $komponen->label,
            'peran' => $komponen->peran,
            'bobot' => 1,
            'urutan' => 1,
        ]);

        return [$indikator, $snapshot, $komponen];
    }
}
