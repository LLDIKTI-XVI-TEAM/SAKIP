import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RekomendasiIndex, { type BarisRekomendasi } from '@/Pages/Rekomendasi/Index';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null, Link: ({ href, children, ...props }: { href: string; children: ReactNode }) => <a href={href} {...props}>{children}</a>, usePage: () => ({ props: { pengaturan: {} } }) };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const pilihan = [{ id: 'jad-1', tahun: 2026, status: 'aktif' as const, periode: [{ id: 'per-1', nama_periode: 'Triwulan I' }] }];
const disetujui = (isi: string, cadangan: boolean) => ({ id: `rek-${isi}`, isi, rujukan_sumber: null, disetujui_oleh: cadangan ? 'Tim Perencanaan' : 'Kepala LLDIKTI',
    disetujui_oleh_role: cadangan ? 'perencanaan' : 'pimpinan', disetujui_pada: '2026-03-15T09:00:00+08:00', persetujuan_cadangan: cadangan });
const baris: BarisRekomendasi = {
    indikator: { id: 'ind-1', kode: 'IKU 1.1', nama: 'Indikator Uji', unit: 'Unit Pengujian' }, tahun: 2026, periode_id: 'per-1',
    berlaku: disetujui('Arahan terbaru.', true), riwayat: [disetujui('Arahan lama.', false)],
    draf: { id: 'draf-1', isi: 'Usulan perubahan.', rujukan_sumber: 'Laporan Kinerja Triwulan I 2026', versi: 3, penyusun: 'Tim Perencanaan' },
};
const tampilkan = (props: Partial<Parameters<typeof RekomendasiIndex>[0]> = {}) => render(
    <RekomendasiIndex pilihan_jadwal={pilihan} filters={{ jadwal_id: 'jad-1', periode_id: 'per-1' }} persetujuan_cadangan={false} rekomendasi={[baris]} {...props} />,
);

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Halaman Rekomendasi Pimpinan', () => {
    it('satu tautan detail mengutamakan rekomendasi berlaku lalu draf, dan baris kosong tetap dapat ditulis', () => {
        const { rerender } = tampilkan();
        const link = screen.getByRole('link', { name: 'Detail rekomendasi IKU 1.1' });
        expect(link.getAttribute('href')).toBe(`/rekomendasi/${baris.berlaku?.id}`);
        expect(link.textContent).toBe('');
        expect(screen.getByRole('button', { name: 'Tinjau draf IKU 1.1' }).textContent).toBe('');
        rerender(<RekomendasiIndex pilihan_jadwal={pilihan} filters={{ jadwal_id: 'jad-1', periode_id: 'per-1' }} persetujuan_cadangan={false}
            rekomendasi={[{ ...baris, berlaku: null, riwayat: [] }]} />);
        expect(screen.getByRole('link', { name: 'Detail rekomendasi IKU 1.1' }).getAttribute('href')).toBe('/rekomendasi/draf-1');
        rerender(<RekomendasiIndex pilihan_jadwal={pilihan} filters={{ jadwal_id: 'jad-1', periode_id: 'per-1' }} persetujuan_cadangan={false}
            rekomendasi={[{ ...baris, berlaku: null, riwayat: [], draf: null }]} />);
        expect(screen.queryByRole('link')).toBeNull();
        expect(screen.getByRole('button', { name: 'Tulis rekomendasi IKU 1.1' })).toBeTruthy();
    });
    it('menampilkan rekomendasi berlaku dengan penanda cadangan, draf, dan riwayat tanpa scroll horizontal', () => {
        tampilkan();

        const tabel = screen.getByRole('table');
        expect(tabel.className).toContain('block');
        expect(tabel.className).toContain('md:table');
        const row = within(tabel).getByText('IKU 1.1').closest('tr') as HTMLElement;
        expect(within(row).getByText('Arahan terbaru.')).toBeTruthy();
        expect(within(row).getByText('Persetujuan cadangan · Perencanaan')).toBeTruthy();
        expect(within(row).getByText('Usulan perubahan.')).toBeTruthy();
        expect(within(row).getByText('Menunggu persetujuan')).toBeTruthy();
        expect(within(row).getByText('Riwayat persetujuan (1)')).toBeTruthy();
        expect(within(row).getByText('Disetujui Pimpinan')).toBeTruthy();
    });

    it('draf ditinjau lalu disetujui atau disimpan dengan token versi draf', async () => {
        const user = userEvent.setup();
        tampilkan();

        await user.click(screen.getByRole('button', { name: 'Tinjau draf IKU 1.1' }));
        const isi = screen.getByRole('textbox', { name: 'Isi rekomendasi' }) as HTMLTextAreaElement;
        expect(isi.value).toBe('Usulan perubahan.');
        expect(screen.queryByText(/persetujuan cadangan karena Anda bukan Pimpinan/)).toBeNull();
        await user.clear(isi);
        await user.type(isi, 'Isi final Pimpinan.');
        await user.click(screen.getByRole('button', { name: 'Setujui' }));
        expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/rekomendasi');
        expect(vi.mocked(router.post).mock.calls[0][1]).toEqual({ indikator_id: 'ind-1', tahun: 2026, periode_id: 'per-1', status: 'disetujui',
            isi: 'Isi final Pimpinan.', rujukan_sumber: 'Laporan Kinerja Triwulan I 2026', versi_draf: 3, berlaku_id: 'rek-Arahan terbaru.' });

        await user.click(screen.getByRole('button', { name: 'Simpan draf' }));
        expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ status: 'draf', versi_draf: 3, berlaku_id: 'rek-Arahan terbaru.' });
    });

    it('rekomendasi baru tanpa draf memakai token kosong, menandai persetujuan cadangan, dan menampilkan konflik', async () => {
        const user = userEvent.setup();
        tampilkan({ persetujuan_cadangan: true, rekomendasi: [{ ...baris, berlaku: null, riwayat: [], draf: null }] });

        await user.click(screen.getByRole('button', { name: 'Tulis rekomendasi IKU 1.1' }));
        expect(screen.getByText('Persetujuan Anda dicatat sebagai persetujuan cadangan karena Anda bukan Pimpinan.')).toBeTruthy();
        await user.type(screen.getByRole('textbox', { name: 'Isi rekomendasi' }), 'Arahan langsung.');
        await user.type(screen.getByRole('textbox', { name: 'Rujukan sumber (opsional)' }), 'Laporan Kinerja Triwulan I 2026');
        await user.click(screen.getByRole('button', { name: 'Setujui' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ status: 'disetujui', isi: 'Arahan langsung.', rujukan_sumber: 'Laporan Kinerja Triwulan I 2026', versi_draf: null, berlaku_id: null });

        await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ konflik: 'Rekomendasi telah diperbarui pengguna lain. Muat ulang halaman sebelum menyimpan.' }); });
        expect(screen.getByRole('alert').textContent).toContain('Muat ulang halaman');
    });

    it('menampilkan keadaan kosong saat jadwal belum tersedia', () => {
        tampilkan({ pilihan_jadwal: [], filters: { jadwal_id: null, periode_id: null }, rekomendasi: [] });

        expect(screen.getByText('Jadwal aktif belum tersedia')).toBeTruthy();
        expect(screen.queryByRole('combobox', { name: 'Jadwal' })).toBeNull();
    });
});
