/**
 * URL eksternal yang aman dijadikan tautan: hanya http/https.
 * Server sudah memvalidasi skema; ini lapisan kedua untuk data lama atau jalur impor.
 */
export function tautanAman(url: string | null | undefined): string | null {
    if (!url) return null;
    try {
        const parsed = new URL(url);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.href : null;
    } catch {
        return null;
    }
}
