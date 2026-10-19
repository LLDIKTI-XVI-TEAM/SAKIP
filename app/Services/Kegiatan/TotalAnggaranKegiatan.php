<?php

namespace App\Services\Kegiatan;

use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penjumlahan anggaran kegiatan (Q42 §42.2 butir 2, 4, 6) dari seluruh
 * klaim (tahap RA dan pengukuran). Anggaran kosong tidak dihitung sebagai 0
 * tetapi menandai total lewat `belum_diisi`. Kegiatan bersama tampil penuh
 * per IKU, sedangkan total per unit/tahun memakai kegiatan unik. Kegiatan
 * asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung agar
 * anggarannya hanya terhitung pada lanjutan.
 */
class TotalAnggaranKegiatan
{
    /**
     * @return array{total: string, belum_diisi: int, per_periode: array<string, array{total: string, belum_diisi: int}>}
     */
    public function perRencanaAksi(RencanaAksi $header): array
    {
        $kegiatan = $this->terhitung()
            ->whereIn('kegiatan.id', DB::table('klaim_kegiatan')->where('rencana_aksi_id', $header->id)->select('kegiatan_id'))
            ->get(['kegiatan.id', 'kegiatan.periode_id', 'kegiatan.anggaran']);

        return [
            ...$this->ringkas($kegiatan),
            'per_periode' => $kegiatan->groupBy(fn (Kegiatan $item): string => (string) $item->periode_id)
                ->map(fn (Collection $item): array => $this->ringkas($item))->all(),
        ];
    }

    /** @return array{total: string, belum_diisi: int} Kegiatan bersama dihitung penuh pada setiap IKU. */
    public function perIku(string $indikatorId, int $tahun): array
    {
        return $this->ringkas($this->terhitung()->where('kegiatan.tahun', $tahun)
            ->whereIn('kegiatan.id', DB::table('klaim_kegiatan')->join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
                ->where('rencana_aksi.indikator_id', $indikatorId)->select('klaim_kegiatan.kegiatan_id'))
            ->get(['kegiatan.id', 'kegiatan.anggaran']));
    }

    /** @return array{total: string, belum_diisi: int} Kegiatan unik, sehingga kegiatan bersama dihitung sekali. */
    public function perUnitTahun(string $unitId, int $tahun): array
    {
        return $this->ringkas($this->terhitung()->where('kegiatan.unit_id', $unitId)->where('kegiatan.tahun', $tahun)
            ->whereIn('kegiatan.id', DB::table('klaim_kegiatan')->select('kegiatan_id'))
            ->get(['kegiatan.id', 'kegiatan.anggaran']));
    }

    /**
     * Kegiatan yang diklaim lebih dari satu IKU.
     *
     * @param  list<string>  $kegiatanIds
     * @return list<string>
     */
    public function kegiatanBersama(array $kegiatanIds): array
    {
        if ($kegiatanIds === []) {
            return [];
        }

        return DB::table('klaim_kegiatan')->join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
            ->whereIn('klaim_kegiatan.kegiatan_id', $kegiatanIds)
            ->groupBy('klaim_kegiatan.kegiatan_id')
            ->havingRaw('COUNT(DISTINCT rencana_aksi.indikator_id) > 1')
            ->orderBy('klaim_kegiatan.kegiatan_id')
            ->pluck('klaim_kegiatan.kegiatan_id')->map(fn ($id): string => (string) $id)->all();
    }

    /** @return Builder<Kegiatan> */
    private function terhitung(): Builder
    {
        return Kegiatan::query()->whereNot(fn (Builder $query) => $query->where('kegiatan.status', 'ditunda')
            ->whereExists(fn ($lanjutan) => $lanjutan->from('kegiatan as lanjutan')->whereColumn('lanjutan.kegiatan_asal_id', 'kegiatan.id')));
    }

    /**
     * @param  Collection<int, Kegiatan>  $kegiatan  Sudah unik per kegiatan.
     * @return array{total: string, belum_diisi: int}
     */
    private function ringkas(Collection $kegiatan): array
    {
        // Cast decimal:0 memberi string rupiah bulat; bcadd menghindari pembulatan float.
        return [
            'total' => $kegiatan->whereNotNull('anggaran')->reduce(fn (string $total, Kegiatan $item): string => bcadd($total, (string) $item->anggaran, 0), '0'),
            'belum_diisi' => $kegiatan->whereNull('anggaran')->count(),
        ];
    }
}
