<?php

namespace App\Services\Perencanaan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pembangkit kode berurutan untuk master Perencanaan (Sasaran & Indikator).
 *
 * Kode dibangkitkan server-side dari nomor tertinggi yang sudah terpakai —
 * bukan input klien dan bukan acak — dengan pola `<PREFIX>-<nomor>` dan
 * padding minimal dua digit (`SS-01`, `IK-01`; nomor >99 melebar otomatis).
 *
 * Deret bersifat global lintas aplikasi: nomor dihitung dari seluruh baris
 * tabel, bukan per Renstra. Kode legacy yang tidak mengikuti pola (mis.
 * `SS-RA-FIXTURE` atau `IKU-3` yang merujuk nomor IKU resmi) diabaikan
 * sehingga tidak menggeser deret.
 *
 * Pemanggil WAJIB berada di dalam transaksi dan memanggil {@see kunci()}
 * sebelum menghitung kode, karena `MAX + 1` tanpa serialisasi adalah
 * check-then-insert yang rentan race. Kunci diambil sebelum kunci baris
 * lain agar urutan kunci antar transaksi konsisten dan tidak deadlock;
 * kunci dilepas otomatis saat transaksi commit/rollback.
 */
class KodeUrutService
{
    public const PREFIX_SASARAN = 'SS';

    public const PREFIX_INDIKATOR = 'IK';

    /** Lebar minimum nomor; nomor lebih besar melebar tanpa dipotong. */
    private const LEBAR_MINIMUM = 2;

    /**
     * Serialisasi pembuatan kode untuk satu deret memakai advisory lock
     * transaksional PostgreSQL (pola yang sama dengan mutasi master
     * pengaturan). Pada driver lain lock dilewati; produksi dan pengujian
     * berjalan di PostgreSQL.
     */
    public function kunci(string $deret): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ["sakip:kode:{$deret}"]);
    }

    /**
     * Nomor berikutnya beserta kode terformat dari kumpulan kode yang ada.
     *
     * @param  Collection<int, mixed>  $kodeAda
     * @return array{kode: string, nomor: int}
     */
    public function berikutnya(string $prefix, Collection $kodeAda): array
    {
        $nomor = $this->nomorTertinggi($prefix, $kodeAda) + 1;

        return ['kode' => $this->format($prefix, $nomor), 'nomor' => $nomor];
    }

    /** Format kode berurutan: `<PREFIX>-<nomor>` dengan padding minimal dua digit. */
    public function format(string $prefix, int $nomor): string
    {
        return $prefix.'-'.str_pad((string) $nomor, self::LEBAR_MINIMUM, '0', STR_PAD_LEFT);
    }

    /** Nomor pada kode bila mengikuti pola deret; null untuk kode legacy di luar pola. */
    public function nomorDari(string $prefix, mixed $kode): ?int
    {
        if (! is_string($kode)) {
            return null;
        }

        return preg_match('/^'.preg_quote($prefix, '/').'-([0-9]+)$/', $kode, $cocok) === 1
            ? (int) $cocok[1]
            : null;
    }

    /** @param Collection<int, mixed> $kodeAda */
    private function nomorTertinggi(string $prefix, Collection $kodeAda): int
    {
        $tertinggi = 0;

        foreach ($kodeAda as $kode) {
            $nomor = $this->nomorDari($prefix, $kode);
            if ($nomor !== null && $nomor > $tertinggi) {
                $tertinggi = $nomor;
            }
        }

        return $tertinggi;
    }
}
