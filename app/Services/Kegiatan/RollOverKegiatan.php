<?php

namespace App\Services\Kegiatan;

use App\Actions\Kegiatan\TambahKegiatanRencanaAksi;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Membentuk kegiatan lanjutan dari kegiatan yang ditunda beserta klaim tahap pengukurannya
 * (Q41 §41.4 butir 2, Q69 §69.5). Caller: `UbahStatusKegiatan` (pembentukan dan klaim jalur pertama)
 * dan `EnsureDraftPengukuran` (klaim jalur kedua saat baris pengukuran periode tujuan terbentuk).
 *
 * Dipanggil di dalam transaksi Action yang sudah mengunci kegiatan asal dan memvalidasi periode tujuan;
 * Service ini tidak membuka transaksi, tidak memeriksa izin, dan tidak menulis audit (audit milik Action).
 * Isi lanjutan memakai `TambahKegiatanRencanaAksi::FIELD_SALIN` ditambah unit dan tahun; tanggal rencana,
 * justifikasi, data pelaksanaan, klaim, dan berkas tidak disalin.
 *
 * Klaim lanjutan diteruskan dari seluruh klaim kegiatan asal (tahap RA maupun pengukuran, satu per RA ×
 * komponen) agar rantai lanjutan tetap tertaut ke RA yang sama, dan selalu menunjuk baris pengukuran
 * indikator RA × tahun × periode lanjutan apa pun status alurnya; versi beku tidak disentuh.
 */
class RollOverKegiatan
{
    /**
     * Menyerialkan pembentukan lanjutan dengan pembentukan baris pengukuran pada tahun × periode yang sama,
     * sehingga salah satu jalur selalu melihat hasil jalur lain dan tidak ada lanjutan yang tertinggal tanpa klaim.
     * Caller transisi mengambilnya sebelum mengunci header RA; caller pengukuran sesudah barisnya dibuat.
     */
    public static function kunciPeriode(int $tahun, string $periodeId): void
    {
        DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:kegiatan-lanjutan:'.$tahun.':'.strtolower($periodeId)]);
    }

    public function buat(Kegiatan $asal, Periode $tujuan, User $aktor): Kegiatan
    {
        return Kegiatan::create(Arr::only($asal->getAttributes(), [...TambahKegiatanRencanaAksi::FIELD_SALIN, 'unit_id', 'tahun']) + [
            'periode_id' => $tujuan->id, 'status' => 'rencana', 'kegiatan_asal_id' => $asal->id, 'created_by' => $aktor->id,
        ]);
    }

    /**
     * Klaim tahap pengukuran lanjutan untuk setiap RA pengklaim kegiatan asal yang baris pengukuran periode
     * lanjutannya sudah ada; `$hanya` membatasi ke satu baris pengukuran (jalur kedua).
     *
     * @return list<KlaimKegiatan>
     */
    public function klaimkan(Kegiatan $lanjutan, User $aktor, ?PengukuranKinerja $hanya = null): array
    {
        $sumber = KlaimKegiatan::join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
            ->where('klaim_kegiatan.kegiatan_id', $lanjutan->kegiatan_asal_id)->where('rencana_aksi.tahun', $lanjutan->tahun)
            ->when($hanya, fn ($query) => $query->where('rencana_aksi.indikator_id', $hanya->indikator_id))
            ->orderBy('klaim_kegiatan.created_at')->orderBy('klaim_kegiatan.id')
            ->get(['klaim_kegiatan.rencana_aksi_id', 'klaim_kegiatan.komponen_id', 'klaim_kegiatan.arah_dampak', 'klaim_kegiatan.catatan', 'rencana_aksi.indikator_id'])
            ->unique(fn (KlaimKegiatan $klaim): string => $klaim->rencana_aksi_id.'|'.$klaim->komponen_id);

        $dibuat = [];
        foreach ($sumber as $klaim) {
            $pengukuranId = $hanya?->id ?? PengukuranKinerja::where('indikator_id', $klaim->getAttribute('indikator_id'))
                ->where('tahun', $lanjutan->tahun)->where('periode_id', $lanjutan->periode_id)->value('id');
            $ada = KlaimKegiatan::where('rencana_aksi_id', $klaim->rencana_aksi_id)->where('kegiatan_id', $lanjutan->id)
                ->when($klaim->komponen_id === null, fn ($query) => $query->whereNull('komponen_id'), fn ($query) => $query->where('komponen_id', $klaim->komponen_id))
                ->exists();
            if ($pengukuranId === null || $ada) {
                continue;
            }
            $dibuat[] = KlaimKegiatan::create([...$klaim->only(['rencana_aksi_id', 'komponen_id', 'arah_dampak', 'catatan']),
                'kegiatan_id' => $lanjutan->id, 'sumber_klaim' => 'pengukuran', 'pengukuran_id' => $pengukuranId,
                'created_by' => $aktor->id, 'created_at' => now()]);
        }

        return $dibuat;
    }

    /**
     * Klaim lanjutan yang tertunda karena baris pengukuran periodenya belum ada saat ditunda (jalur kedua).
     *
     * @return list<array{klaim: KlaimKegiatan, kegiatan_asal_id: string}>
     */
    public function klaimTertunda(PengukuranKinerja $pengukuran, User $aktor): array
    {
        $lanjutan = Kegiatan::where('periode_id', $pengukuran->periode_id)->where('tahun', $pengukuran->tahun)->whereNotNull('kegiatan_asal_id')
            ->whereExists(fn ($query) => $query->from('klaim_kegiatan')->join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
                ->whereColumn('klaim_kegiatan.kegiatan_id', 'kegiatan.kegiatan_asal_id')
                ->where('rencana_aksi.indikator_id', $pengukuran->indikator_id)->where('rencana_aksi.tahun', $pengukuran->tahun))
            ->orderBy('id')->get();

        return $lanjutan->flatMap(fn (Kegiatan $kegiatan): array => array_map(
            fn (KlaimKegiatan $klaim): array => ['klaim' => $klaim, 'kegiatan_asal_id' => (string) $kegiatan->kegiatan_asal_id],
            $this->klaimkan($kegiatan, $aktor, $pengukuran),
        ))->values()->all();
    }
}
