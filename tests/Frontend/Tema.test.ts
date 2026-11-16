/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { setTema, temaAktif, useTema } from '@/lib/tema';

const html = document.documentElement;

afterEach(() => {
    delete html.dataset.tema;
    localStorage.clear();
    vi.restoreAllMocks();
});

describe('lib/tema', () => {
    it('default terang dan nilai tak dikenal dibaca sebagai terang', () => {
        expect(temaAktif()).toBe('terang');
        html.dataset.tema = 'biru';
        expect(temaAktif()).toBe('terang');
    });

    it('setTema memasang atribut dan menyimpan pilihan', () => {
        setTema('gelap');
        expect(html.dataset.tema).toBe('gelap');
        expect(localStorage.getItem('sakip-tema')).toBe('gelap');

        setTema('terang');
        expect(html.hasAttribute('data-tema')).toBe(false);
        expect(localStorage.getItem('sakip-tema')).toBe('terang');
    });

    it('penyimpanan yang diblokir tidak menggagalkan pergantian tema', () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('diblokir', 'SecurityError');
        });

        expect(() => setTema('gelap')).not.toThrow();
        expect(temaAktif()).toBe('gelap');
    });

    it('useTema ikut berganti saat setTema dipanggil', () => {
        const { result } = renderHook(() => useTema());
        expect(result.current).toBe('terang');

        act(() => setTema('gelap'));
        expect(result.current).toBe('gelap');

        act(() => setTema('terang'));
        expect(result.current).toBe('terang');
    });
});

describe('skrip tema di app.blade.php', () => {
    const skrip = readFileSync('resources/views/app.blade.php', 'utf8').match(/<script>([^<]*sakip-tema[^<]*)<\/script>/)?.[1] ?? '';
    const jalankan = () => new Function(skrip)();

    it.each([
        ['gelap', 'gelap'],
        ['terang', undefined],
        ['biru', undefined],
        [null, undefined],
    ])('nilai tersimpan %s memberi data-tema %s', (tersimpan, diharapkan) => {
        if (tersimpan !== null) localStorage.setItem('sakip-tema', tersimpan);
        jalankan();
        expect(html.dataset.tema).toBe(diharapkan);
    });

    it('localStorage yang diblokir tidak melempar galat', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new DOMException('diblokir', 'SecurityError');
        });

        expect(skrip).not.toBe('');
        expect(jalankan).not.toThrow();
        expect(html.hasAttribute('data-tema')).toBe(false);
    });
});
