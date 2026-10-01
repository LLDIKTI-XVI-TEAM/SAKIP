<?php

namespace App\Support;

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
}
