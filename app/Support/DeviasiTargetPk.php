<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pembanding skor periode efektif terakhir rencana aksi dengan target PK beku.
 *
 * Kedua sisi dibulatkan HALF_UP pada presisi snapshot sebelum dibandingkan,
 * sehingga yang dinilai adalah angka yang sama dengan yang ditampilkan.
 * `bccomp` dengan skala presisi tidak dipakai lagi karena memotong desimal:
 * target PK 76.255 terbaca 76.25 sehingga skor 76.26 dinilai menyimpang,
 * padahal keduanya tampil 76.26. Satu helper dipakai halaman, pratinjau, dan
 * gerbang pengajuan agar jawaban "ada deviasi" tidak berbeda antarjalur.
 *
 * Sisi `null`/non-numerik (target PK belum ditetapkan atau skor belum
 * terhitung) menghasilkan `tidak_dapat_dinilai`: tidak ada deviasi yang wajib
 * dijelaskan. Sisi yang numerik tetap dibulatkan agar keluaran seragam.
 */
final class DeviasiTargetPk
{
    public const SETARA = 'setara';

    public const DEVIASI = 'deviasi';

    public const TIDAK_DAPAT_DINILAI = 'tidak_dapat_dinilai';

    /**
     * `skor`/`target_pk` pada keluaran adalah hasil pembulatan yang benar-benar
     * dibandingkan, bukan nilai mentah.
     *
     * @return array{status: string, skor: ?string, target_pk: ?string, presisi: int}
     */
    public static function bandingkan(?string $skor, ?string $targetPk, int $presisi): array
    {
        $skorBulat = self::bulatkan($skor, $presisi);
        $targetBulat = self::bulatkan($targetPk, $presisi);

        $status = match (true) {
            $skorBulat === null || $targetBulat === null => self::TIDAK_DAPAT_DINILAI,
            $skorBulat->isEqualTo($targetBulat) => self::SETARA,
            default => self::DEVIASI,
        };

        return [
            'status' => $status,
            'skor' => $skorBulat === null ? null : (string) $skorBulat,
            'target_pk' => $targetBulat === null ? null : (string) $targetBulat,
            'presisi' => $presisi,
        ];
    }

    /**
     * Bentuk props `deviasi_pk` halaman Show dan pratinjau dari hasil
     * `bandingkan`. Deviasi adalah data peringatan; alasannya baru diwajibkan
     * gerbang pengajuan.
     *
     * @param  array{status: string, skor: ?string, target_pk: ?string, presisi: int}  $hasil
     * @return array{dapat_dinilai: bool, ada: bool, alasan_diperlukan: bool, alasan_terisi: bool, skor_periode_terakhir: string|null, target_pk: string|null, periode_id: string|null}
     */
    public static function props(array $hasil, ?string $alasanDeviasi, ?string $periodeId): array
    {
        $ada = $hasil['status'] === self::DEVIASI;

        return [
            'dapat_dinilai' => $hasil['status'] !== self::TIDAK_DAPAT_DINILAI,
            'ada' => $ada,
            'alasan_diperlukan' => $ada,
            'alasan_terisi' => trim((string) $alasanDeviasi) !== '',
            'skor_periode_terakhir' => $hasil['skor'],
            'target_pk' => $hasil['target_pk'],
            'periode_id' => $periodeId,
        ];
    }

    private static function bulatkan(?string $nilai, int $presisi): ?BigDecimal
    {
        return is_numeric($nilai) ? BigDecimal::of($nilai)->toScale($presisi, RoundingMode::HalfUp) : null;
    }
}
