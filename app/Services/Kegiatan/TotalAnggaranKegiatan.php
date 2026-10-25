<?php

namespace App\Services\Kegiatan;

use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Penjumlahan anggaran kegiatan (Q42 §42.2 butir 2, 4, 6) dari seluruh
 * klaim (tahap RA dan pengukuran). Anggaran kosong tidak dihitung sebagai 0
 * tetapi menandai total lewat `belum_diisi`. Kegiatan bersama tampil penuh
 * per IKU, sedangkan total per unit/tahun memakai kegiatan unik.
 *
 * Kegiatan asal `ditunda` tidak dihitung setelah salah satu kegiatan dalam
 * rantai lanjutannya (lanjutan, lanjutan dari lanjutan, dan seterusnya)
 * diklaim dalam lingkup total yang sama: RA yang sama (per RA), RA IKU yang
 * sama (per IKU), atau RA mana pun (per unit/tahun), agar anggarannya hanya
 * terhitung pada lanjutan; sebelum itu asal tetap terhitung (Q69 §69.6
 * butir 1–4). Karena semua total hanya menghitung kegiatan berklaim dalam
 * lingkupnya, anggaran tidak terhitung ganda, termasuk pada rantai yang
 * lanjutan tengahnya tidak diklaim.
 *
 * Caller aktual: `DaftarKegiatanRencanaAksi` (total live halaman RA) dan
 * `AjukanRencanaAksi` (total yang dibekukan ke snapshot versi dengan
 * `tahapRaSaja: true`). Service ini baca-saja tanpa lock, transaksi, maupun
 * audit; snapshot versi yang sudah dibekukan tidak dihitung ulang.
 * Penjumlahan dilakukan di database agar tidak memuat seluruh kegiatan ke memori.
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
        $kegiatan = fn (): Builder => $this->terhitung((clone $klaim)->select('kegiatan_id'));

        return [
            ...$this->ringkas($kegiatan()),
            'per_periode' => $this->agregat($kegiatan(), 'kegiatan.periode_id')
                ->mapWithKeys(fn (stdClass $baris): array => [(string) $baris->kelompok => $this->bentuk($baris)])->all(),
        ];
    }

    /** @return array{total: string, belum_diisi: int} Kegiatan bersama dihitung penuh pada setiap IKU. */
    public function perIku(string $indikatorId, int $tahun): array
    {
        return $this->ringkas($this->terhitung(DB::table('klaim_kegiatan')->join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
            ->where('rencana_aksi.indikator_id', $indikatorId)->select('klaim_kegiatan.kegiatan_id'))->where('kegiatan.tahun', $tahun));
    }

    /** @return array{total: string, belum_diisi: int} Kegiatan unik, sehingga kegiatan bersama dihitung sekali. */
    public function perUnitTahun(string $unitId, int $tahun): array
    {
        return $this->ringkas($this->terhitung(DB::table('klaim_kegiatan')->select('kegiatan_id'))
            ->where('kegiatan.unit_id', $unitId)->where('kegiatan.tahun', $tahun));
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

    /**
     * Kegiatan berklaim dalam lingkup, tanpa asal `ditunda` yang rantai lanjutannya sudah diklaim dalam lingkup yang sama (Q69 §69.6 butir 4).
     *
     * @param  QueryBuilder  $diklaim  Subquery `kegiatan_id` klaim lingkup total.
     * @return Builder<Kegiatan>
     */
    private function terhitung(QueryBuilder $diklaim): Builder
    {
        // Rantai lanjutan ditelusuri lewat kegiatan_asal_id (A → B → C); UNION berhenti pada baris yang sudah dikunjungi bila data membentuk siklus.
        return Kegiatan::query()->whereIn('kegiatan.id', $diklaim)
            ->whereNot(fn (Builder $query) => $query->where('kegiatan.status', 'ditunda')->whereRaw(<<<SQL
                EXISTS (
                    WITH RECURSIVE rantai (id) AS (
                        SELECT lanjutan.id FROM kegiatan AS lanjutan WHERE lanjutan.kegiatan_asal_id = kegiatan.id
                        UNION
                        SELECT berikut.id FROM kegiatan AS berikut JOIN rantai ON berikut.kegiatan_asal_id = rantai.id
                    )
                    SELECT 1 FROM rantai WHERE rantai.id IN ({$diklaim->toSql()})
                )
                SQL, $diklaim->getBindings()));
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
