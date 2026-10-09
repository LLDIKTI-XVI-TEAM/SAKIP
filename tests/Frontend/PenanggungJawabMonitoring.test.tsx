import type { ComponentProps, ReactNode } from 'react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/PenanggungJawab/Index';

const transport = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, className }: { href: string; children: ReactNode; className?: string }) => <a href={href} className={className}>{children}</a>,
    router: { get: transport.get },
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const props: ComponentProps<typeof Index> = {
    assignments: { data: [], next_page_url: '/penanggung-jawab?after=last-id&after_scope=scope' },
    filters: { q: '', tanggal_acuan: '2026-03-15', unit_id: 'unit-a' },
};
afterEach(() => { cleanup(); vi.clearAllMocks(); });

describe('Continuation monitoring PJ', () => {
    it('menjelaskan scan belum selesai dan menyediakan tautan pada halaman kosong', () => {
        render(<Index {...props} />);
        expect(screen.getByText('Belum ditemukan PJ dengan izin kerja kurang pada halaman ini')).toBeTruthy();
        expect(screen.getByText(/Masih ada penugasan yang belum diperiksa/)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Halaman berikutnya' }).getAttribute('href')).toBe(props.assignments.next_page_url);
        expect(screen.queryByText(/^Tidak ada PJ aktif/)).toBeNull();
    });

    it('membatasi pesan kosong pada halaman saat continuation berakhir', () => {
        render(<Index {...props} assignments={{ data: [], next_page_url: null }} />);
        expect(screen.getByText('Tidak ada PJ aktif dengan izin kerja kurang pada halaman ini')).toBeTruthy();
        expect(screen.queryByText(/Masih ada penugasan/)).toBeNull();
        expect(screen.queryByRole('link', { name: 'Halaman berikutnya' })).toBeNull();
    });

    it('pergantian filter memulai request tanpa cursor lama dan mempertahankan filter unit', () => {
        render(<Index {...props} />);
        fireEvent.change(screen.getByLabelText('Cari indikator, unit, atau PJ'), { target: { value: 'MON-00125' } });
        fireEvent.change(screen.getByLabelText(/Tanggal acuan/), { target: { value: '2026-04-01' } });
        fireEvent.click(screen.getByRole('button', { name: 'Terapkan' }));
        expect(transport.get.mock.calls[0][0]).toBe('/penanggung-jawab');
        expect(transport.get.mock.calls[0][1]).toEqual({ q: 'MON-00125', tanggal_acuan: '2026-04-01', unit_id: 'unit-a' });
    });

    it('mengunci penerapan filter selama request dan memulihkannya ketika request berakhir', () => {
        render(<Index {...props} />);
        const button = screen.getByRole('button', { name: 'Terapkan' }) as HTMLButtonElement;
        fireEvent.click(button);
        const options = transport.get.mock.calls[0][2];
        act(() => options.onStart());
        expect(button.disabled).toBe(true);
        act(() => options.onFinish());
        expect(button.disabled).toBe(false);
    });
});

it('menyediakan navigasi kembali dan menampilkan tanggal berlaku sesuai format tampilan', () => {
    const row = { indicator: { id: 'ind-1', kode: 'IKU-01', nama: 'Indikator QA', status: 'aktif' }, unit: { id: 'unit-a', nama: 'Unit QA', status: 'aktif' },
        pic: { id: 'user-a', nama: 'Siti Aminah' }, tanggal_mulai_berlaku: '2026-02-01', blocked_reason: null,
        readiness: { complete: false, available: [], missing: ['pengukuran:create'], permissions: [{ permission: 'pengukuran:create', label: 'Buat pengukuran', allowed: false, reason: 'no_allow' }] } };
    render(<Index assignments={{ data: [row], next_page_url: null }} filters={{ q: '', tanggal_acuan: '2026-03-15', unit_id: null }} />);
    expect(screen.getByRole('link', { name: 'Kembali ke Sasaran & Indikator' }).getAttribute('href')).toBe('/perencanaan/sasaran-indikator');
    expect(screen.getByText(/Berlaku 01 Februari 2026/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Lihat penugasan' }).getAttribute('href')).toBe('/perencanaan/indikator/ind-1/penanggung-jawab');
    expect(screen.queryByText('Hapus filter unit')).toBeNull();
});
