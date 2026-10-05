<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class TargetTahunanDecimal
{
    /** Kanonisasi desimal biasa tanpa float; baseline dan target berbagi kapasitas storage. */
    public static function normalize(?string $raw, string $field = 'target_tahunan'): ?string
    {
        $raw = trim($raw ?? '');
        if ($raw === '') {
            return null;
        }
        if (! preg_match('/\A[0-9]+(?:[.,][0-9]+)?\z/D', $raw)) {
            throw ValidationException::withMessages([$field => 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.']);
        }
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $raw), 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        if (strlen($whole) > 18 || strlen($fraction) > 12) {
            throw ValidationException::withMessages([$field => 'Nilai melebihi kapasitas 18 digit integer dan 12 digit desimal.']);
        }

        return $whole.($fraction !== '' ? '.'.$fraction : '');
    }

    /** Hanya target baru/berubah mengikuti presisi indikator; tidak membulatkan nilai historis. */
    public static function assertPrecision(?string $value, int $precision): void
    {
        if ($value !== null && strlen(explode('.', $value, 2)[1] ?? '') > $precision) {
            throw ValidationException::withMessages(['target_tahunan' => "Target maksimal {$precision} angka desimal bermakna."]);
        }
    }
}
