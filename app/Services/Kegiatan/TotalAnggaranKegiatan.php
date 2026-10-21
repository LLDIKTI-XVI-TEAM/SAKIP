<?php

namespace App\Services\Kegiatan;

use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Penjumlahan anggaran kegiatan (Q42 §42.2 butir 2, 4, 6) dari seluruh
 * klaim (tahap RA dan pengukuran). Anggaran kosong tidak dihitung sebagai 0
 * tetapi menandai total lewat `belum_diisi`. Kegiatan bersama tampil penuh
 * per IKU, sedangkan total per unit/tahun memakai kegiatan unik. Kegiatan
 * asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung agar
 * anggarannya hanya terhitung pada lanjutan. Penjumlahan dilakukan di
 * database agar tidak memuat seluruh kegiatan ke memori.
 */
class TotalAnggaranKegiatan
{
    /**
     * @param  bool  $tahapRaSaja  Untuk total beku: hanya klaim tahap RA yang dikunci dan dibekukan saat pengajuan.
     * @return array{total: string, belum_diisi: int, per_periode: array<string, array{total: string, belum_diisi: int}>}
     */
    public function perRencanaAksi(RencanaAksi $header, bool $tahapRaSaja = false): array
    {
        $klaim = DB::table('klaim_kegiatan')->where('rencana_aksi_id', $header->id)->when($tahapRaSaja, fn ($query) => $query->where('sumber_klaim', 'rencana_aksi'));
        $kegiatan = fn (): Builder => $this->terhitung()->whereIn('kegiatan.id', (clone $klaim)->select('kegiatan_id'));

        return [
            ...$this->ringkas($kegiatan()),
            'per_periode' => $this->agregat($kegiatan(), 'kegiatan.periode_id')
                ->mapWithKeys(fn (stdClass $baris): array => [(string) $baris->kelompok => $this->bentuk($baris)])->all(),
        ];
    }

    /** @return array{total: string, belum_diisi: int} Kegiatan bersama dihitung penuh pada setiap IKU. */
    public function perIku(string $indikatorId, int $tahun): array
    {
        return $this->ringkas($this->terhitung()->where('kegiatan.tahun', $tahun)
            ->whereIn('kegiatan.id', DB::table('klaim_kegiatan')->join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
                ->where('rencana_aksi.indikator_id', $indikatorId)->select('klaim_kegiatan.kegiatan_id')));
    }

    /** @return array{total: string, belum_diisi: int} Kegiatan unik, sehingga kegiatan bersama dihitung sekali. */
    public function perUnitTahun(string $unitId, int $tahun): array
    {
        return $this->ringkas($this->terhitung()->where('kegiatan.unit_id', $unitId)->where('kegiatan.tahun', $tahun)
            ->whereIn('kegiatan.id', DB::table('klaim_kegiatan')->select('kegiatan_id')));
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
     * @param  Builder<Kegiatan>  $kegiatan  Baris `kegiatan` sehingga setiap kegiatan terhitung sekali.
     * @return array{total: string, belum_diisi: int}
     */
    private function ringkas(Builder $kegiatan): array
    {
        $baris = $this->agregat($kegiatan)->first();

        return $baris === null ? ['total' => '0', 'belum_diisi' => 0] : $this->bentuk($baris);
    }

    /**
     * @param  Builder<Kegiatan>  $kegiatan
     * @param  string|null  $kelompok  Kolom pengelompokan, dikembalikan sebagai `kelompok`.
     * @return Collection<int, stdClass>
     */
    private function agregat(Builder $kegiatan, ?string $kelompok = null): Collection
    {
        // SUM numeric PostgreSQL presisi; anggaran kosong tidak dijumlahkan tetapi dihitung terpisah.
        $query = $kegiatan->toBase()
            ->selectRaw('COALESCE(SUM(kegiatan.anggaran), 0) AS total, COUNT(*) FILTER (WHERE kegiatan.anggaran IS NULL) AS belum_diisi');

        return ($kelompok === null ? $query : $query->addSelect("{$kelompok} as kelompok")->groupBy($kelompok))->get();
    }

    /** @return array{total: string, belum_diisi: int} */
    private function bentuk(stdClass $baris): array
    {
        // Kolom numeric(30,12) dibulatkan ke rupiah bulat seperti cast `decimal:0` Kegiatan.
        return ['total' => bcadd((string) $baris->total, '0', 0), 'belum_diisi' => (int) $baris->belum_diisi];
    }
}
