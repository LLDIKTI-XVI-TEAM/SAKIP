<?php

namespace App\Services\Rekomendasi;

use App\Models\PengukuranKinerja;
use App\Models\RekomendasiPimpinan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Pembaca tunggal rekomendasi yang berlaku bagi pengukuran, gerbang tindak lanjut, dan laporan; draf tidak pernah dikembalikan. */
class RekomendasiBerlaku
{
    /**
     * Hanya di dalam transaksi: penulis eksklusif dan pembaca pengajuan bersama memakai kombinasi yang sama,
     * termasuk saat belum ada baris. Kunci dilepas otomatis ketika transaksi selesai.
     */
    public function kunciKombinasi(string $indikatorId, int $tahun, string $periodeId, bool $bersama = false): void
    {
        DB::select('select '.($bersama ? 'pg_advisory_xact_lock_shared' : 'pg_advisory_xact_lock').'(hashtextextended(?, 0))',
            ['sakip:rekomendasi:'.strtolower($indikatorId).':'.$tahun.':'.strtolower($periodeId)]);
    }

    /** Baris disetujui dengan waktu persetujuan terbaru (Q51 §51.1 butir 2). */
    public function untuk(string $indikatorId, int $tahun, string $periodeId): ?RekomendasiPimpinan
    {
        return RekomendasiPimpinan::where(['indikator_id' => $indikatorId, 'tahun' => $tahun, 'periode_id' => $periodeId])
            ->where('status', RekomendasiPimpinan::STATUS_DISETUJUI)->orderByDesc('disetujui_pada')->orderByDesc('id')->first();
    }

    /**
     * Rekomendasi periode tepat sebelum periode pengukuran: periode sebelumnya pada jadwal yang sama, atau periode
     * terakhir jadwal tahun sebelumnya yang juga mengukur indikator ini (lintas tahun, Q51 §51.1 butir 4).
     */
    public function sebelum(PengukuranKinerja $pengukuran, bool $kunci = false): ?RekomendasiPimpinan
    {
        $sebelumnya = $this->periodeSebelum($pengukuran);

        if (! $sebelumnya) {
            return null;
        }
        if ($kunci) {
            // Ambil lock lebih dulu agar SELECT berikutnya melihat commit penulis yang selesai selama menunggu.
            $this->kunciKombinasi($pengukuran->indikator_id, $sebelumnya['tahun'], $sebelumnya['periode_id'], bersama: true);
        }

        return $this->untuk($pengukuran->indikator_id, $sebelumnya['tahun'], $sebelumnya['periode_id']);
    }

    /**
     * Kalender bersama rekomendasi dan peringatan; ketiadaan data pada periode terpilih tidak mengubah pembanding.
     *
     * @return array{jadwal_id: string, tahun: int, periode_id: string, nama: string}|null
     */
    public function periodeSebelum(PengukuranKinerja $pengukuran): ?array
    {
        $periode = DB::table('jadwal_periode as jp')->join('periode as p', 'p.id', '=', 'jp.periode_id')->join('jadwal_tahunan as j', 'j.id', '=', 'jp.jadwal_id')
            ->where(fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('jp.jadwal_id', $pengukuran->jadwalSnapshot->jadwal_id)->where('p.urutan', '<', $pengukuran->periode->urutan))
                ->orWhere(fn (Builder $q) => $q->where('j.tahun', $pengukuran->tahun - 1)->whereIn('j.status', ['aktif', 'ditutup'])
                    ->whereExists(fn (Builder $s) => $s->from('jadwal_snapshot as s')->whereColumn('s.jadwal_id', 'j.id')->where('s.indikator_id', $pengukuran->indikator_id))))
            ->orderByDesc('j.tahun')->orderByDesc('p.urutan')->select(['jp.jadwal_id', 'j.tahun', 'jp.periode_id', 'p.nama'])->first();

        return $periode ? ['jadwal_id' => $periode->jadwal_id, 'tahun' => (int) $periode->tahun,
            'periode_id' => $periode->periode_id, 'nama' => $periode->nama] : null;
    }

    /**
     * Bentuk tampilan rekomendasi periode sebelumnya; dibekukan pada versi pengajuan agar tampil berdampingan dengan
     * tindak lanjut yang dibekukan bersamanya.
     *
     * @return array{id: string, isi: string, tahun: int, periode_id: string, disetujui_pada: string}|null
     */
    public function tampilanSebelum(PengukuranKinerja $pengukuran, bool $kunci = false): ?array
    {
        $rekomendasi = $this->sebelum($pengukuran, $kunci);

        return $rekomendasi ? [...$rekomendasi->only(['id', 'isi', 'tahun', 'periode_id']), 'disetujui_pada' => $rekomendasi->disetujui_pada->toIso8601String()] : null;
    }
}
