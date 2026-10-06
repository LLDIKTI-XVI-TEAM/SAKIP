<?php

namespace App\Support;

use Closure;

class AuditReason
{
    public static function sanitize(mixed $reason): string
    {
        if (! is_string($reason)) {
            return '';
        }

        // Pertahankan teks setelah NUL, serta tab dan baris baru; batas panjang milik pemanggil.
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', mb_convert_encoding($reason, 'UTF-8', 'UTF-8')) ?? '';
    }

    /** Tolak teks resmi yang rusak sebelum mutasi; newline dan tab tetap diizinkan. */
    public static function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) !== 0) {
            $fail('Teks harus berupa UTF-8 yang valid tanpa karakter kontrol ilegal.');
        }
    }
}
