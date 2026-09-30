<?php

namespace App\Support;

/**
 * Sanitasi alasan mentah untuk audit append-only.
 *
 * Policy membaca `request()->input('alasan')` sebelum validasi selesai,
 * sehingga nilai mentah tidak boleh ditulis tanpa batas ke jejak audit
 * yang tidak dapat diubah lagi. Helper ini memangkas spasi tepi,
 * membatasi panjang 1000 karakter, dan memakai pesan generik bila kosong.
 */
final class AlasanAudit
{
    public const BATAS_MAKS = 1000;

    public static function sanitasi(mixed $mentah, string $bawaan): string
    {
        if (! is_string($mentah)) {
            return $bawaan;
        }

        $rapi = trim($mentah);

        if ($rapi === '') {
            return $bawaan;
        }

        if (mb_strlen($rapi, 'UTF-8') > self::BATAS_MAKS) {
            return mb_substr($rapi, 0, self::BATAS_MAKS, 'UTF-8');
        }

        return $rapi;
    }
}
