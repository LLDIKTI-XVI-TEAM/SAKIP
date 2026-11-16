import { cleanup, render, screen } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import BuktiPanel from '@/Pages/RencanaAksi/BuktiPanel';
import type { RencanaAksiBukti } from '@/Pages/RencanaAksi/types';

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));

beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});

afterAll(() => {
    dialogMethods.forEach((name, index) => {
        const descriptor = originalDialogMethods[index];
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    });
});

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        usePage: () => ({ props: {} }),
        useForm: <T extends Record<string, unknown>>(initial: T) => ({
            data: initial,
            setData: vi.fn(),
            transform: vi.fn(),
            post: vi.fn(),
            processing: false,
            errors: {} as Record<string, string>,
            reset: vi.fn(),
            clearErrors: vi.fn(),
        }),
        router: { ...original.router, delete: vi.fn() },
    };
});

afterEach(cleanup);

const bukti: RencanaAksiBukti = {
    dikecualikan: false,
    persyaratan: [
        {
            id: 'jb-1',
            nama: 'Surat Keputusan Tim Pelaksana',
            keterangan: null,
            wajib: true,
            semua_mode_wajib: true,
            izinkan_file: true,
            izinkan_tautan: true,
            izinkan_teks: false,
            format_diizinkan: 'pdf',
            ukuran_maks_kb: 5120,
            pemenuhan: {
                terpenuhi: false,
                mode_terpenuhi: [],
                mode_kurang: ['file', 'tautan'],
                mode_dikecualikan: [],
                tidak_dapat_dipenuhi: false,
                alasan_pengecualian: null,
            },
        },
    ],
    ringkasan: { lengkap: false, total_wajib: 1, terpenuhi_wajib: 0 },
    daftar: [],
    unggahan: { ukuran_maks_kb: 10240, format_diizinkan: 'pdf' },
    can: { upload: false, delete: false },
};

describe('BuktiPanel rincian mode kurang', () => {
    it('menaruh rincian "Kurang" pada paragraf yang membungkus, bukan pada badge nowrap', () => {
        render(<BuktiPanel rencanaAksiId="ra-1" bukti={bukti} />);

        const badge = screen.getByText('Belum terpenuhi');
        expect(badge.textContent).toBe('Belum terpenuhi');

        const rincian = screen.getByText('Kurang: File, Tautan');
        expect(rincian.tagName).toBe('P');
        expect(rincian.classList.contains('break-words')).toBe(true);
        expect(rincian.classList.contains('text-xs')).toBe(true);
        expect(rincian.classList.contains('whitespace-nowrap')).toBe(false);
        expect(badge.contains(rincian)).toBe(false);
    });
});
