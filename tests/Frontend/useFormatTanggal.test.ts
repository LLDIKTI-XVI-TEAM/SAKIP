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

describe('formatTanggal untuk tanggal murni YYYY-MM-DD', () => {
    // `America/Los_Angeles` (barat UTC) hanya pengunci regresi: implementasi meng-anchor tanggal murni ke UTC
    // sehingga hari kalender tidak bergeser ke 30 Maret seperti bila diparse `new Date('2026-03-31')` lalu diformat berzona.
    it.each(['UTC', 'Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'America/Los_Angeles'])(
        'memformat 2026-03-31 sebagai 31 Maret 2026 pada zona tampilan %s',
        (zona) => {
            expect(formatTanggal('2026-03-31', {}, zona, 'd F Y', 'id-ID')).toBe('31 Maret 2026');
        }
    );

    it('menjaga batas awal dan akhir tahun pada zona tampilan paling timur', () => {
        expect(formatTanggal('2026-01-01', {}, 'Asia/Jayapura', 'd F Y', 'id-ID')).toBe('01 Januari 2026');
        expect(formatTanggal('2026-12-31', {}, 'Asia/Jayapura', 'd F Y', 'id-ID')).toBe('31 Desember 2026');
    });

    it('tetap mendukung format d/m/Y, Y-m-d, dan withDay', () => {
        expect(formatTanggal('2026-03-31', {}, 'Asia/Jakarta', 'd/m/Y', 'id-ID')).toBe('31/03/2026');
        expect(formatTanggal('2026-03-31', {}, 'Asia/Jakarta', 'Y-m-d', 'id-ID')).toBe('2026-03-31');
        expect(formatTanggal('2026-03-31', { withDay: true }, 'Asia/Jakarta', 'd F Y', 'id-ID')).toBe('Selasa, 31 Maret 2026');
    });

    it('menolak tanggal murni yang tidak ada di kalender', () => {
        expect(formatTanggal('2026-02-31')).toBe('—');
        expect(formatTanggal('2026-13-01')).toBe('—');
        expect(formatTanggal('2026-00-10')).toBe('—');
        expect(formatTanggal('2026-04-00')).toBe('—');
    });

    it('mempertahankan perilaku timestamp string terhadap zona tampilan', () => {
        // Instan 31 Maret 17:00 UTC jatuh pada 1 April di WITA (UTC+8).
        expect(formatTanggal('2026-03-31T17:00:00Z', {}, 'Asia/Makassar', 'd F Y', 'id-ID')).toBe('01 April 2026');
    });
});
