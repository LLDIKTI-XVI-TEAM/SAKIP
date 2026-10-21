<?php

namespace Tests\Concerns;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Unit;
use Illuminate\Support\Arr;

/** Pelengkap CreatesPengukuranFixture untuk skenario dashboard; dipakai bersama trait tersebut. */
trait CreatesDashboardFixture
{
    protected function tambahPeriode(int $urutan, string $mulai, string $selesai): PeriodeJadwal
    {
        $periode = Periode::create(['nama' => 'Triwulan '.['I', 'II', 'III', 'IV'][$urutan - 1], 'urutan' => $urutan, 'aktif' => true, 'is_nilai_akhir' => $urutan === 4]);

        return PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id,
            'pengisian_mulai' => $mulai, 'pengisian_selesai' => $selesai, 'reviu_mulai' => $selesai, 'reviu_selesai' => $selesai]);
    }

    /** Indikator kedua beserta snapshot jadwal fixture; unit master dan unit snapshot dapat berbeda. */
    protected function indikatorBaru(string $kode, array $indikator = [], array $snapshot = []): JadwalSnapshot
    {
        $model = IndikatorKinerja::create([...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'unit_id', 'satuan', 'tipe_perhitungan', 'created_by_role']),
            'kode' => $kode, 'nama' => 'Indikator '.$kode, 'status' => 'aktif', 'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id, ...$indikator]);

        return JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'periode_mulai_id', 'unit_id', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'indikator_id' => $model->id, 'nama' => 'Indikator '.$kode, ...$snapshot]);
    }

    protected function headerUntuk(JadwalSnapshot $snapshot, ?string $periodeId = null): PengukuranKinerja
    {
        return PengukuranKinerja::create([...$this->pengukuran->only(['tahun', 'sumber_nilai', 'progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut', 'created_by']),
            'indikator_id' => $snapshot->indikator_id, 'periode_id' => $periodeId ?? $this->pengukuran->periode_id, 'jadwal_snapshot_id' => $snapshot->id]);
    }

    /** Versi disahkan dengan isi beku yang dibaca dashboard; header ikut berstatus disahkan. */
    protected function sahkan(PengukuranKinerja $pengukuran, array $snapshot): KinerjaSnapshot
    {
        return $this->versiPengajuan($pengukuran, $snapshot, 'disahkan');
    }

    /** Versi pengajuan yang belum disahkan (diajukan/diverifikasi); isinya beku sejak diajukan. */
    protected function ajukan(PengukuranKinerja $pengukuran, array $snapshot, string $status = 'diajukan'): KinerjaSnapshot
    {
        return $this->versiPengajuan($pengukuran, $snapshot, $status);
    }

    private function versiPengajuan(PengukuranKinerja $pengukuran, array $snapshot, string $status): KinerjaSnapshot
    {
        $disahkan = $status === 'disahkan';
        $konteks = JadwalSnapshot::with('unit')->findOrFail($pengukuran->jadwal_snapshot_id);
        $versi = KinerjaSnapshot::create(['pengukuran_id' => $pengukuran->id, 'rencana_aksi_versi_id' => $this->planVersion->id, 'jadwal_snapshot_id' => $konteks->id,
            'nomor' => (int) KinerjaSnapshot::where('pengukuran_id', $pengukuran->id)->max('nomor') + 1, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'disahkan_by' => $disahkan ? $this->actor->id : null, 'disahkan_at' => $disahkan ? now() : null,
            'snapshot' => array_replace_recursive([
                'nilai' => null, 'status_perhitungan' => 'terhitung', 'target' => '70', 'tanpa_target_periode' => false, 'target_pk' => '70',
                'indikator' => [...Arr::only($konteks->toArray(), ['nama', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']), 'kode' => $pengukuran->indikator->kode],
                'unit_kerja' => ['id' => $konteks->unit_id, 'nama' => $konteks->unit->nama], 'pic' => null,
            ], $snapshot)]);
        $pengukuran->forceFill(['status_alur' => $status])->save();

        return $versi;
    }

    protected function unitBaru(string $nama): Unit
    {
        return Unit::create(['nama' => $nama, 'created_by' => $this->actor->id]);
    }
}
