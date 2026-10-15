<?php

namespace App\Support;

use App\Models\RencanaAksiVersi;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * Perbandingan nilai target Rencana Aksi lintas jalur tulis dan baca.
 *
 * Satu helper dipakai `SimpanTargetPeriode`, `AjukanRencanaAksi`, dan
 * `IndexRencanaAksi` agar jawaban "nilai sama" dan "periode berubah dibanding
 * versi sah" tidak berbeda antarjalur. Perbandingan numerik memakai BigDecimal
 * (bukan `bccomp`) agar notasi yang lolos validasi `numeric` seperti eksponen
 * tidak melempar `ValueError` pada PHP 8.3+; nilai yang tidak dapat diparsing
 * dinilai tidak sama.
 */
final class PerbandinganNilaiRencanaAksi
{
    /**
     * Kesamaan nilai desimal: null hanya sama dengan null; string numerik
     * dibandingkan secara numerik agar `100` sama dengan `100.00`.
     */
    public static function nilaiSama(mixed $a, mixed $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }
        $sa = trim((string) $a);
        $sb = trim((string) $b);
        if ($sa === $sb) {
            return true;
        }
        if (! is_numeric($sa) || ! is_numeric($sb)) {
            return false;
        }
        try {
            return BigDecimal::of($sa)->isEqualTo(BigDecimal::of($sb));
        } catch (MathException) {
            return false;
        }
    }

    /**
     * Kesamaan sel masuk terhadap pembanding per komponen: setiap kunci pada
     * sisi masuk wajib ada pada pembanding dengan nilai yang sama.
     *
     * @param  array<string, mixed>  $masuk
     * @param  array<string, mixed>  $pembanding
     */
    public static function selSama(array $masuk, array $pembanding): bool
    {
        foreach ($masuk as $kid => $nilai) {
            if (! array_key_exists($kid, $pembanding)) {
                return false;
            }
            if (! self::nilaiSama($nilai, $pembanding[$kid])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Periode yang berubah dibanding versi sah terakhir (nomor terbesar
     * dengan `disahkan_at` + `disahkan_by` terisi). Tanpa versi sah
     * sebelumnya berarti tidak ada pembanding sehingga dinilai sebagai
     * daftar kosong. Perbandingan memakai `nilai` (termasuk nilai per
     * komponen untuk nonmanual) dan mengabaikan `keterangan`, sehingga
     * perubahan keterangan saja tidak dihitung berubah.
     *
     * @param  list<array{periode_id: string, nilai: mixed, komponen: list<array{komponen_id: string, nilai: mixed}>}>  $baris
     * @return list<string>
     */
    public static function periodeBerubahVsVersiSah(string $rencanaAksiId, array $baris): array
    {
        $versiSah = self::versiSah($rencanaAksiId);
        if (! $versiSah instanceof RencanaAksiVersi) {
            return [];
        }

        $snapshotSah = $versiSah->snapshot ?? [];
        $petaSah = [];
        foreach (is_array($snapshotSah['target_periode'] ?? null) ? $snapshotSah['target_periode'] : [] as $row) {
            if (is_array($row) && isset($row['periode_id'])) {
                $petaSah[(string) $row['periode_id']] = $row;
            }
        }
        $petaKini = [];
        foreach ($baris as $row) {
            if (is_array($row) && isset($row['periode_id'])) {
                $petaKini[(string) $row['periode_id']] = $row;
            }
        }

        $berubah = [];
        foreach ($petaKini as $periodeId => $kini) {
            $dulu = $petaSah[$periodeId] ?? null;
            if (! is_array($kini) || ! is_array($dulu)) {
                continue;
            }
            if (self::nilaiPeriodeBerubah($kini, $dulu)) {
                $berubah[] = (string) $periodeId;
            }
        }

        return array_values($berubah);
    }

    /**
     * Periode yang daftar klaim tahap RA-nya berubah dibanding versi sah
     * terakhir (Q41 §41.2: daftar kegiatan periode terkunci ikut dijaga).
     * Klaim dibandingkan per periode melalui kegiatan, komponen, arah dampak,
     * dan catatan. Versi sah tanpa key `klaim` (dibentuk sebelum klaim
     * dibekukan) tidak memiliki pembanding sehingga dinilai daftar kosong.
     *
     * @param  list<array<string, mixed>>  $klaimKini  Bentuk beku klaim saat ini.
     * @return list<string>
     */
    public static function periodeKlaimBerubahVsVersiSah(string $rencanaAksiId, array $klaimKini): array
    {
        $dulu = self::versiSah($rencanaAksiId)?->snapshot['klaim'] ?? null;
        if (! is_array($dulu)) {
            return [];
        }
        $peta = fn (array $rows) => collect($rows)->filter(fn ($k): bool => is_array($k))
            ->groupBy(fn (array $k): string => (string) ($k['periode_id'] ?? ''))
            ->map(fn ($grup) => $grup->map(fn (array $k): string => implode('|', [
                $k['kegiatan_id'] ?? '', $k['komponen_id'] ?? '', $k['arah_dampak'] ?? '', $k['catatan'] ?? '',
            ]))->sort()->values()->all());
        $sah = $peta($dulu);
        $kini = $peta($klaimKini);

        return $sah->keys()->merge($kini->keys())->unique()
            ->filter(fn (string $periodeId): bool => ($sah[$periodeId] ?? []) !== ($kini[$periodeId] ?? []))
            ->values()->all();
    }

    /** Versi sah terakhir: nomor terbesar dengan `disahkan_at` dan `disahkan_by` terisi. */
    private static function versiSah(string $rencanaAksiId): ?RencanaAksiVersi
    {
        return RencanaAksiVersi::where('rencana_aksi_id', $rencanaAksiId)
            ->whereNotNull('disahkan_at')
            ->whereNotNull('disahkan_by')
            ->orderByDesc('nomor')
            ->first();
    }

    /**
     * True bila nilai target periode berubah: manual membandingkan `nilai`
     * turunan, nonmanual membandingkan nilai per komponen yang ada di kedua
     * sisi (komponen yang hanya ada di satu sisi tidak memiliki pembanding
     * sehingga diabaikan). Beda struktur manual↔nonmanual berarti konteks
     * berubah (koreksi snapshot Perencanaan), bukan perubahan PIC.
     *
     * @param  array<string, mixed>  $kini
     * @param  array<string, mixed>  $dulu
     */
    private static function nilaiPeriodeBerubah(array $kini, array $dulu): bool
    {
        $komponenKini = is_array($kini['komponen'] ?? null) ? array_values(array_filter($kini['komponen'], is_array(...))) : [];
        $komponenDulu = is_array($dulu['komponen'] ?? null) ? array_values(array_filter($dulu['komponen'], is_array(...))) : [];

        if ($komponenKini === [] && $komponenDulu === []) {
            return ! self::nilaiSama($kini['nilai'] ?? null, $dulu['nilai'] ?? null);
        }
        if ($komponenKini === [] || $komponenDulu === []) {
            return false;
        }

        $petaDulu = [];
        foreach ($komponenDulu as $row) {
            if (isset($row['komponen_id'])) {
                $petaDulu[(string) $row['komponen_id']] = $row['nilai'] ?? null;
            }
        }
        foreach ($komponenKini as $row) {
            if (! isset($row['komponen_id'])) {
                continue;
            }
            $id = (string) $row['komponen_id'];
            if (! array_key_exists($id, $petaDulu)) {
                continue;
            }
            if (! self::nilaiSama($row['nilai'] ?? null, $petaDulu[$id])) {
                return true;
            }
        }

        return false;
    }
}
