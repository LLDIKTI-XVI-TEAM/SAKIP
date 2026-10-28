import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RencanaAksiIndex from '@/Pages/RencanaAksi/Index';
import type { BarisRencanaAksi } from '@/Pages/RencanaAksi/types';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const satuHalaman = { current_page: 1, last_page: 1, total: 3, prev_page_url: null, next_page_url: null };
const semuaStatus = { status: 'semua' as const };
const milikSaya: BarisRencanaAksi = {
    indikator_id: 'ind-1',
    kode: 'IKU-3',
    nama: 'Tingkat kepuasan layanan',
    unit_nama: 'Bagian Umum',
    tahun: 2026,
    pj_nama: 'Budi',
    milik_saya: true,
    rencana_aksi: null,
    can: { create: true, review: false },
};
const tanpaAksi: BarisRencanaAksi = { ...milikSaya, indikator_id: 'ind-3', kode: 'IKU-7', milik_saya: false, can: { create: false, review: false } };
const diajukan: BarisRencanaAksi = {
    ...milikSaya,
    indikator_id: 'ind-2',
    kode: 'IKU-5',
    milik_saya: false,
    rencana_aksi: { id: 'ra-2', status_alur: 'diajukan' },
    can: { create: false, review: true },
};

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Daftar Rencana Aksi', () => {
    it('satu menu titik-tiga per baris, dan Buat mengirim indikator × tahun', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiIndex daftar={[milikSaya, diajukan, tanpaAksi]} pagination={satuHalaman} filters={semuaStatus} />);

        // Baris tanpa header dan tanpa capability hanya menampilkan tanda hubung.
        expect(screen.getByText('—')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Aksi IKU-7 2026' })).toBeNull();

        const pemicuBuat = screen.getByRole('button', { name: 'Aksi IKU-3 2026' });
        expect(within(pemicuBuat.closest('tr') as HTMLElement).getByText('Anda')).toBeTruthy();
        await user.click(pemicuBuat);
        await user.click(screen.getByRole('menuitem', { name: 'Buat Rencana Aksi IKU-3 2026' }));

        expect(screen.queryByRole('menu')).toBeNull();
        expect(router.post).toHaveBeenCalledTimes(1);
        expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/rencana-aksi/ensure-draft', { indikator_id: 'ind-1', tahun: 2026 }]);
    });

    it('menu baris ber-header memuat Buka dan Reviu beserta tujuannya', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiIndex daftar={[diajukan]} pagination={satuHalaman} filters={semuaStatus} />);

        const pemicu = screen.getByRole('button', { name: 'Aksi IKU-5 2026' });
        expect(pemicu.getAttribute('aria-expanded')).toBe('false');
        await user.click(pemicu);

        expect(pemicu.getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('menuitem', { name: 'Buka Rencana Aksi IKU-5 2026' }).getAttribute('href')).toBe('/rencana-aksi/ra-2');
        expect(screen.getByRole('menuitem', { name: 'Reviu Rencana Aksi IKU-5 2026' }).getAttribute('href')).toBe('/rencana-aksi/ra-2/review');
    });

    it('item Reviu mengikuti capability server, Buka tetap ditawarkan', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiIndex daftar={[{ ...diajukan, can: { create: false, review: false } }]} pagination={satuHalaman} filters={semuaStatus} />);

        await user.click(screen.getByRole('button', { name: 'Aksi IKU-5 2026' }));

        expect(screen.getByRole('menuitem', { name: 'Buka Rencana Aksi IKU-5 2026' })).toBeTruthy();
        expect(screen.queryByRole('menuitem', { name: 'Reviu Rencana Aksi IKU-5 2026' })).toBeNull();
    });

    it('filter status memakai GET server dan menyebut jumlah rencana aksi', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiIndex daftar={[diajukan]} pagination={satuHalaman} filters={{ status: 'diajukan' }} />);

        expect(screen.getByText('rencana aksi')).toBeTruthy();
        await user.selectOptions(screen.getByLabelText('Status rencana aksi'), 'dikembalikan');

        expect(router.get).toHaveBeenCalledWith('/rencana-aksi', { status: 'dikembalikan' }, { preserveScroll: true });
        expect(router.post).not.toHaveBeenCalled();
    });

    it('throttle saat Buat menampilkan pesan singkat di baris tanpa modal', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiIndex daftar={[milikSaya]} pagination={satuHalaman} filters={semuaStatus} />);

        await user.click(screen.getByRole('button', { name: 'Aksi IKU-3 2026' }));
        await user.click(screen.getByRole('menuitem', { name: 'Buat Rencana Aksi IKU-3 2026' }));
        const opsi = vi.mocked(router.post).mock.calls[0][2] as { onHttpException: (response: { status: number }) => boolean | void };
        let hasil: boolean | void = undefined;
        act(() => {
            hasil = opsi.onHttpException({ status: 429 });
        });

        expect(hasil).toBe(false);
        expect(screen.getByRole('alert').textContent).toBe('Terlalu sering. Coba lagi sebentar.');
    });

    it('daftar kosong cukup satu kalimat tanpa aksi', () => {
        render(<RencanaAksiIndex daftar={[]} pagination={satuHalaman} filters={semuaStatus} />);

        expect(screen.getByText('Belum ada indikator pada jadwal aktif untuk unit yang dapat Anda akses.')).toBeTruthy();
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('daftar kosong saat berfilter mengarahkan memilih status lain', () => {
        render(<RencanaAksiIndex daftar={[]} pagination={{ ...satuHalaman, total: 0 }} filters={{ status: 'diajukan' }} />);

        expect(screen.getByText('Belum ada rencana aksi pada status yang dipilih untuk unit yang dapat Anda akses.')).toBeTruthy();
        expect(screen.getByText('Pilih status lain untuk melihat daftar yang tersedia.')).toBeTruthy();
        expect(screen.queryByRole('button')).toBeNull();
    });
});
