<?php

namespace App\Services\Storage;

/** Batas teknis unggahan tidak membaca maupun mengubah histori setelan aplikasi. */
final class UploadLimits
{
    private const MAX_KB = 102400;

    private const DEFAULT_KB = 10240;

    private const FORMATS = 'pdf,docx,xlsx,jpg,jpeg,png';

    public static function maxKb(): int
    {
        return min(self::positiveInteger(config('uploads.max_kb'), self::MAX_KB), self::MAX_KB);
    }

    /** @return array{ukuran_maks_kb: int, format_diizinkan: string} */
    public static function resolve(?int $sizeKb = null, ?string $formats = null): array
    {
        $formats = $formats ?: (string) config('uploads.allowed_extensions', self::FORMATS);
        $tokens = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($formats))))));
        $normalized = implode(',', $tokens);

        return [
            'ukuran_maks_kb' => min($sizeKb ?? self::positiveInteger(config('uploads.default_max_kb'), self::DEFAULT_KB), self::maxKb()),
            'format_diizinkan' => preg_match('/^[a-z0-9]+(,[a-z0-9]+)*$/D', $normalized) === 1 ? $normalized : self::FORMATS,
        ];
    }

    /** Validasi isi dan ekstensi dilakukan bersama; nama file saja tidak membuktikan format dokumen. @return list<string> */
    public static function fileRules(?int $sizeKb = null, ?string $formats = null): array
    {
        $limits = self::resolve($sizeKb, $formats);

        return ['file', 'max:'.$limits['ukuran_maks_kb'], 'mimes:'.$limits['format_diizinkan'], 'extensions:'.$limits['format_diizinkan']];
    }

    private static function positiveInteger(mixed $value, int $fallback): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false && $integer > 0 ? $integer : $fallback;
    }
}
