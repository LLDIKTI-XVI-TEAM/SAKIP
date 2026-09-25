import { describe, expect, it } from 'vitest';
import { formatTanggal } from '@/hooks/useFormatTanggal';

describe('formatTanggal', () => {
    it('mengembalikan tanda strip untuk nilai null atau undefined', () => {
        expect(formatTanggal(null)).toBe('—');
        expect(formatTanggal(undefined)).toBe('—');
        expect(formatTanggal('')).toBe('—');
    });

    it('memformat hari dengan 2 digit padding untuk format d F Y pada hari 1-9', () => {
        // Tanggal 1 September 2026 UTC
        const date = new Date('2026-09-01T08:00:00Z');
        const formatted = formatTanggal(date, {}, 'Asia/Makassar', 'd F Y', 'id-ID');
        expect(formatted).toBe('01 September 2026');
    });

    it('memformat hari 2 digit untuk format Y-m-d dan d/m/Y', () => {
        const date = new Date('2026-09-05T08:00:00Z');
        expect(formatTanggal(date, {}, 'Asia/Makassar', 'Y-m-d', 'id-ID')).toBe('2026-09-05');
        expect(formatTanggal(date, {}, 'Asia/Makassar', 'd/m/Y', 'id-ID')).toBe('05/09/2026');
    });

    it('menyertakan nama hari dan jam jika diminta pada options', () => {
        const date = new Date('2026-09-01T08:30:00Z');
        const formatted = formatTanggal(date, { withDay: true, withTime: true }, 'UTC', 'd F Y', 'id-ID');
        expect(formatted).toContain('01 September 2026');
        expect(formatted).toContain('08:30');
    });
});
