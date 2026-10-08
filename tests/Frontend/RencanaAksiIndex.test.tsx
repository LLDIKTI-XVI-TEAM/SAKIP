import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RencanaAksiIndex, { type BarisRencanaAksi } from '@/Pages/RencanaAksi/Index';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const satuHalaman = { current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null };
const milikSaya: BarisRencanaAksi = {
    indikator_id: 'ind-1',
    kode: 'IKU-3',
    nama: 'Tingkat kepuasan layanan',
    unit_nama: 'Bagian Umum',
    tahun: 2026,
    pj_nama: 'Budi',
    milik_saya: true,
    rencana_aksi: null,
    can: { buat: true, buka: false },
};

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Daftar Rencana Aksi', () => {
    it('hanya menampilkan aksi dari capability server, dan Buat mengirim indikator × tahun', async () => {
        const user = userEvent.setup();
        render(
            <RencanaAksiIndex
                daftar={[
                    milikSaya,
                    { ...milikSaya, indikator_id: 'ind-2', kode: 'IKU-5', milik_saya: false, rencana_aksi: { id: 'ra-2', status_alur: 'draft' }, can: { buat: false, buka: true } },
                    { ...milikSaya, indikator_id: 'ind-3', kode: 'IKU-7', milik_saya: false, can: { buat: false, buka: false } },
                ]}
                pagination={satuHalaman}
            />,
        );

        expect(screen.getAllByRole('button', { name: /Buat Rencana Aksi/ })).toHaveLength(1);
        const tombolBuat = screen.getByRole('button', { name: 'Buat Rencana Aksi IKU-3 2026' });
        const tautanBuka = screen.getByRole('link', { name: 'Buka Rencana Aksi IKU-5 2026' });
        expect(tautanBuka.getAttribute('href')).toBe('/rencana-aksi/ra-2');
        // Penanda "Anda" hanya pada baris milik sendiri (dirender untuk tata letak desktop dan mobile).
        expect(within(tombolBuat.closest('tr') as HTMLElement).queryAllByText('Anda').length).toBeGreaterThan(0);
        expect(within(tautanBuka.closest('tr') as HTMLElement).queryByText('Anda')).toBeNull();

        await user.click(tombolBuat);

        expect(router.post).toHaveBeenCalledTimes(1);
        expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/rencana-aksi/ensure-draft', { indikator_id: 'ind-1', tahun: 2026 }]);
    });

    it('daftar kosong cukup satu kalimat tanpa aksi', () => {
        render(<RencanaAksiIndex daftar={[]} pagination={satuHalaman} />);

        expect(screen.getByText('Belum ada indikator pada jadwal aktif.')).toBeTruthy();
        expect(screen.queryByRole('button')).toBeNull();
    });
});
