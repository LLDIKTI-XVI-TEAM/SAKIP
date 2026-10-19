<?php

namespace App\Support;

use Brick\Math\BigDecimal;

/** Deviasi target PK terhadap target Renstra tahun yang sama menurut arah indikator (Q63 §63.2). */
final class DeviasiTargetRenstra
{
    public const RENSTRA_BELUM_ADA = 'renstra_belum_ada';

    public const TIDAK_DINILAI = 'tidak_dinilai';

    public const SESUAI = 'sesuai';

    public const MENYIMPANG = 'menyimpang';

    /** Nilai tersimpan dibandingkan apa adanya: tanpa pembulatan atau toleransi presisi, nilai sama tidak menyimpang. */
    public static function status(string $arah, ?string $targetPk, ?string $targetRenstra): string
    {
        if ($targetRenstra === null) {
            return self::RENSTRA_BELUM_ADA;
        }
        if ($targetPk === null) {
            return self::TIDAK_DINILAI;
        }
        $banding = BigDecimal::of($targetPk)->compareTo(BigDecimal::of($targetRenstra));

        // Tanpa cabang default: arah di luar kontrak gagal tertutup.
        return match ($arah) {
            'naik_baik' => $banding < 0 ? self::MENYIMPANG : self::SESUAI,
            'turun_baik' => $banding > 0 ? self::MENYIMPANG : self::SESUAI,
        };
    }
}
