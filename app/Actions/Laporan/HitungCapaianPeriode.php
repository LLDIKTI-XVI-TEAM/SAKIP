<?php

namespace App\Actions\Laporan;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class HitungCapaianPeriode
{
    /** Rasio hanya bermakna pada target positif; pembulatan sekali pada hasil, tanpa batas 0–100. */
    public function handle(string|int|float|null $realisasi, string|int|float|null $target, string $arah): ?string
    {
        if ($realisasi === null || $target === null || BigDecimal::of((string) $target)->isLessThanOrEqualTo('0')) {
            return null;
        }
        $r = BigDecimal::of((string) $realisasi);
        $t = BigDecimal::of((string) $target);
        $pembilang = match ($arah) {
            'naik_baik' => $r,
            'turun_baik' => $t->multipliedBy('2')->minus($r),
            default => throw new InvalidArgumentException('Arah indikator tidak dikenal.'),
        };

        return (string) $pembilang->multipliedBy('100')->dividedBy($t, 2, RoundingMode::HalfUp);
    }
}
