import { describe, expect, it } from 'vitest';

const sumber = import.meta.glob<string>('../../resources/js/**/*.{ts,tsx}', { query: '?raw', import: 'default', eager: true });

// Warna literal dan varian dark: tidak ikut tema gelap (Q89); pakai token on-primary, on-danger, sidebar-ink, overlay, dan field.
const LITERAL = /\b(?:text-white|bg-white|bg-black|ring-offset-white)\b|\b(?:bg|text|border|ring)-white\/|\bring-black\/|\bbg-surface\/(?:10|15|20)\b|\bbg-ink\/|\bdark:[a-z[]/;

describe('warna literal di resources/js', () => {
    it.each(['text-white', 'text-white/80', 'bg-white', 'bg-black/60', 'border-white/10', 'focus:ring-white/40', 'ring-black/5', 'hover:bg-surface/10', 'bg-surface/20', 'bg-ink/60', 'backdrop:bg-ink/50', 'focus:ring-offset-white', 'dark:bg-surface', 'md:dark:text-ink'])(
        'pola menolak %s',
        (kelas) => {
            expect(LITERAL.test(`className="p-2 ${kelas} rounded"`)).toBe(true);
        },
    );

    it.each(['text-on-primary', 'text-sidebar-ink/80', 'bg-sidebar-ink/10', 'bg-overlay/60', 'backdrop:bg-overlay/50', 'bg-surface', 'bg-surface/60', 'text-ink', 'bg-field', 'focus:ring-offset-2', 'text-success-dark'])(
        'pola mengizinkan %s',
        (kelas) => {
            expect(LITERAL.test(`className="p-2 ${kelas} rounded"`)).toBe(false);
        },
    );

    it('tidak ada warna literal yang tidak ikut tema', () => {
        const temuan = Object.entries(sumber).flatMap(([berkas, isi]) =>
            isi.split('\n').flatMap((baris, i) => (LITERAL.test(baris) ? [`${berkas.replace('../../resources/js/', '')}:${i + 1}`] : [])),
        );

        expect(Object.keys(sumber).length).toBeGreaterThan(100);
        expect(temuan).toEqual([]);
    });
});
