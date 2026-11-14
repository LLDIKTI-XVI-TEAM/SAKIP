/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

type Tema = Record<string, string>;
type NamaTema = 'terang' | 'gelap';

// Vitest tidak memproses CSS, jadi ?raw untuk app.css kosong; baca berkasnya langsung.
const css = readFileSync('resources/css/app.css', 'utf8');
const sumberJs = import.meta.glob<string>('../../resources/js/**/*.{ts,tsx}', { query: '?raw', import: 'default', eager: true });

function warnaDi(blok: string | undefined): Tema {
    return Object.fromEntries(
        [...(blok ?? '').matchAll(/--color-([a-z-]+):\s*(#[0-9a-f]{6})\s*;/gi)].map(([, nama, hex]) => [nama, hex.toUpperCase()]),
    );
}

const terang = warnaDi(css.match(/@theme\s*\{([^}]*)\}/)?.[1]);
const gelap = warnaDi(css.match(/@media screen\s*\{\s*:root\[data-tema="gelap"\]\s*\{([^}]*)\}/)?.[1]);

// Token lama tanpa pemakaian di resources/js; belum punya makna di tema gelap, jadi dijaga tetap tidak dipakai.
const TANPA_NILAI_GELAP = ['primary-dark', 'primary-mid', 'primary-light'];

function rgb(tema: Tema, nama: string): number[] {
    const hex = tema[nama];
    expect(hex, `token --color-${nama}`).toBeDefined();

    return [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
}

// "warning@15/surface" = warning dengan opasitas 15% di atas surface, seperti bg-warning/15 di dalam kartu.
function warna(tema: Tema, spek: string): number[] {
    const [, nama, persen, latar] = spek.match(/^([a-z-]+)(?:@(\d+)\/([a-z-]+))?$/) ?? [];
    const depan = rgb(tema, nama);
    if (persen === undefined) return depan;

    const alpha = Number(persen) / 100;
    const bawah = rgb(tema, latar);

    return depan.map((nilai, i) => alpha * nilai + (1 - alpha) * bawah[i]);
}

function luminans(warnaRgb: number[]): number {
    const [r, g, b] = warnaRgb.map((c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function kontras(tema: Tema, depan: string, latar: string): number {
    const [terangan, gelapan] = [luminans(warna(tema, depan)), luminans(warna(tema, latar))].sort((a, b) => b - a);

    return (terangan + 0.05) / (gelapan + 0.05);
}

const K9 = 'kegagalan tema terang yang sudah ada sebelum Q89 (K9, issue terpisah)';
const KEDUA_TEMA = 'gagal di kedua tema sejak sebelum Q89; perbaikannya ikut issue K9';

// [label, depan, latar, ambang, tema yang dikecualikan beserta alasan]
const PASANGAN: [string, string, string, number, Partial<Record<NamaTema, string>>?][] = [
    ['ink di atas page', 'ink', 'page', 4.5],
    ['ink di atas surface (kartu)', 'ink', 'surface', 4.5],
    ['ink di atas soft (judul tabel)', 'ink', 'soft', 4.5],
    ['ink di atas field (isian)', 'ink', 'field', 4.5],
    ['muted di atas page', 'muted', 'page', 4.5],
    ['muted di atas surface', 'muted', 'surface', 4.5],
    ['muted di atas soft', 'muted', 'soft', 4.5, { terang: K9 }],
    ['muted di atas field (placeholder)', 'muted', 'field', 4.5],
    ['primary di atas surface (tautan, tab aktif)', 'primary', 'surface', 4.5],
    ['primary di atas soft', 'primary', 'soft', 4.5],
    ['primary di atas primary/10 (badge)', 'primary', 'primary@10/surface', 4.5],
    ['on-primary di atas primary (tombol)', 'on-primary', 'primary', 4.5],
    ['on-primary di atas primary/90 (hover)', 'on-primary', 'primary@90/surface', 4.5],
    ['on-secondary di atas secondary (tombol emas)', 'on-secondary', 'secondary', 4.5],
    ['ink di atas secondary/15 (badge emas)', 'ink', 'secondary@15/surface', 4.5],
    ['success-dark di atas success/15 (badge)', 'success-dark', 'success@15/surface', 4.5],
    ['warning-dark di atas warning/15 (badge)', 'warning-dark', 'warning@15/surface', 4.5, { terang: K9 }],
    ['danger-dark di atas danger/15 (badge)', 'danger-dark', 'danger@15/surface', 4.5],
    ['info-dark di atas info/15 (badge)', 'info-dark', 'info@15/surface', 4.5],
    ['warning-dark di atas warning/10 (banner)', 'warning-dark', 'warning@10/surface', 4.5],
    ['info-dark di atas info/10 (banner)', 'info-dark', 'info@10/surface', 4.5],
    ['danger di atas danger/10 (alert error)', 'danger', 'danger@10/surface', 4.5, { terang: K9 }],
    ['danger di atas surface (pesan error)', 'danger', 'surface', 4.5],
    ['success di atas surface (teks sukses)', 'success', 'surface', 4.5, { terang: K9 }],
    ['on-danger di atas danger (tombol hapus)', 'on-danger', 'danger', 4.5],
    ['on-danger di atas danger/90 (hover tombol hapus)', 'on-danger', 'danger@90/surface', 4.5, { terang: K9 }],
    ['sidebar-ink di atas sidebar', 'sidebar-ink', 'sidebar', 4.5],
    ['sidebar-ink/75 di atas sidebar (nama instansi)', 'sidebar-ink@75/sidebar', 'sidebar', 4.5],
    ['sidebar-ink/70 di atas sidebar (submenu)', 'sidebar-ink@70/sidebar', 'sidebar', 4.5],
    ['sidebar-ink di atas sorotan aktif /20', 'sidebar-ink', 'sidebar-ink@20/sidebar', 4.5],
    ['border terhadap surface (garis isian)', 'border', 'surface', 3, { terang: KEDUA_TEMA, gelap: KEDUA_TEMA }],
    ['sorotan menu aktif /15 terhadap sidebar', 'sidebar-ink@15/sidebar', 'sidebar', 3, { terang: KEDUA_TEMA, gelap: KEDUA_TEMA }],
    ['titik warning terhadap surface', 'warning', 'surface', 3, { terang: K9 }],
    ['titik secondary terhadap surface', 'secondary', 'surface', 3, { terang: K9 }],
    ['titik info terhadap surface', 'info', 'surface', 3],
];

describe('token tema (resources/css/app.css)', () => {
    it('setiap token warna di @theme punya nilai di blok tema gelap', () => {
        const tanpaPasangan = Object.keys(terang).filter((nama) => !(nama in gelap) && !TANPA_NILAI_GELAP.includes(nama));

        expect(Object.keys(terang).length).toBeGreaterThan(0);
        expect(tanpaPasangan).toEqual([]);
    });

    it('blok tema gelap hanya berlaku di layar dan memakai skema warna gelap', () => {
        expect(css).toMatch(/@media screen\s*\{\s*:root\[data-tema="gelap"\]\s*\{[^}]*color-scheme:\s*dark\s*;/);
    });

    // ApexCharts menulis warna token tema aktif ke atribut SVG saat render; saat cetak token kembali terang, jadi teks dan garis grafik ditimpa (Q89 K6).
    it.each([
        ['.apexcharts-text', 'fill', 'muted'],
        ['.apexcharts-text.apexcharts-datalabel-value', 'fill', 'ink'],
        ['.apexcharts-legend-text', 'color', 'muted'],
        ['.apexcharts-gridline', 'stroke', 'border'],
        ['.apexcharts-grid-borders line', 'stroke', 'border'],
        ['.apexcharts-pie-area', 'stroke', 'surface'],
    ])('cetak: %s memakai %s token %s', (selektor, properti, token) => {
        const cetak = (css.match(/@media print\s*\{([\s\S]*?)\n\}/)?.[1] ?? '').replace(/\/\*[\s\S]*?\*\//g, '');
        const aturan = [...cetak.matchAll(/([^{}]+)\{([^}]*)\}/g)].find(([, sel]) => sel.split(',').map((s) => s.trim()).includes(selektor));

        expect(aturan?.[2], `aturan cetak untuk ${selektor}`).toMatch(new RegExp(`${properti}:\\s*var\\(--color-${token}\\)`));
    });

    it('token tanpa nilai gelap tidak dipakai di resources/js', () => {
        const pola = new RegExp(`-(?:${TANPA_NILAI_GELAP.join('|')})\\b`);
        const pemakai = Object.entries(sumberJs)
            .filter(([, isi]) => pola.test(isi))
            .map(([berkas]) => berkas);

        expect(Object.keys(sumberJs).length).toBeGreaterThan(100);
        expect(pemakai).toEqual([]);
    });

    describe.each([
        ['terang', terang],
        ['gelap', gelap],
    ] as const)('kontras WCAG tema %s', (namaTema, tema) => {
        it.each(PASANGAN)('%s', (_label, depan, latar, ambang, pengecualian) => {
            const nilai = kontras(tema, depan, latar);

            if (pengecualian?.[namaTema]) {
                // Pengecualian harus tetap gagal; bila sudah lolos, hapus dari daftar agar tidak menutupi regresi.
                expect(nilai).toBeLessThan(ambang);
            } else {
                expect(nilai).toBeGreaterThanOrEqual(ambang);
            }
        });
    });
});
