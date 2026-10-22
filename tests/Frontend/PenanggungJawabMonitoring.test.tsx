import type { ComponentProps, ReactNode } from 'react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/PenanggungJawab/Index';

const transport = vi.hoisted(() => ({ get: vi.fn(), bacaIndikator: true }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, className, 'aria-label': ariaLabel, 'aria-current': ariaCurrent }: { href: string; children: ReactNode; className?: string; 'aria-label'?: string; 'aria-current'?: 'page' }) =>
        <a href={href} className={className} aria-label={ariaLabel} aria-current={ariaCurrent}>{children}</a>,
    router: { get: transport.get },
    usePage: () => ({ props: { pengaturan: {}, errors: {}, auth: { can: { sasaranIndikator: transport.bacaIndikator } } } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

type Props = ComponentProps<typeof Index>;
const izin = (allowed: boolean) => ['pengukuran:create', 'pengukuran:update', 'rencana_aksi:create', 'rencana_aksi:update', 'rencana_aksi:ajukan', 'kegiatan:create', 'kegiatan:update']
    .map((permission) => ({ permission, label: permission, allowed, reason: allowed ? 'allow' : 'inactive_user' }));
const lengkap = { indicator: { id: 'ind-1', kode: 'IKU 1.1', nama: 'Indikator Umum', status: 'aktif' }, unit: { id: 'unit-a', nama: 'Bagian Umum', status: 'aktif' },
    pic: { id: 'user-a', nama: 'Budi Santoso', status: 'aktif' }, tanggal_mulai_berlaku: '2026-02-01', blocked_reason: null,
    readiness: { complete: true, permissions: izin(true), available: izin(true).map((item) => item.permission), missing: [] } };
const kurang = { indicator: { id: 'ind-2', kode: 'IKU 2.1', nama: 'Indikator Kelembagaan', status: 'arsip' }, unit: { id: 'unit-b', nama: 'Bagian Kelembagaan', status: 'aktif' },
    pic: { id: 'user-b', nama: 'Sari Dewi', status: 'nonaktif' }, tanggal_mulai_berlaku: '2026-01-01', blocked_reason: 'Indikator sudah diarsipkan.',
    readiness: { complete: false, permissions: izin(false), available: [], missing: izin(false).map((item) => item.permission) } };
const props: Props = {
    assignments: { data: [lengkap, kurang], from: 1, to: 2, total: 2, links: [] },
    filters: { q: '', tanggal_acuan: '2026-03-15', unit_id: null, indikator_id: null, izin: null },
    pilihan: {
        unit: [{ id: 'unit-b', nama: 'Bagian Kelembagaan' }, { id: 'unit-a', nama: 'Bagian Umum' }],
        indikator: [{ id: 'ind-1', kode: 'IKU 1.1', nama: 'Indikator Umum', unit_id: 'unit-a' }, { id: 'ind-2', kode: 'IKU 2.1', nama: 'Indikator Kelembagaan', unit_id: 'unit-b' }],
    },
};
const opsi = (label: string) => [...(screen.getByLabelText(label) as HTMLSelectElement).options].map((option) => option.text);
afterEach(() => { cleanup(); vi.clearAllMocks(); transport.bacaIndikator = true; });

describe('Tabel monitoring PJ (Q68)', () => {
    it('menampilkan PJ izin lengkap dan kurang beserta status akun dan alasan blokir', () => {
        render(<Index {...props} />);
        const tabel = screen.getByRole('table', { name: /Penanggung jawab efektif/ });
        const [, barisLengkap, barisKurang] = within(tabel).getAllByRole('row');
        expect(within(barisLengkap).getByText('Budi Santoso')).toBeTruthy();
        expect(within(barisLengkap).getByText('Lengkap')).toBeTruthy();
        expect(within(barisLengkap).getByText('01 Februari 2026')).toBeTruthy();
        expect(within(barisKurang).getByText('Nonaktif')).toBeTruthy();
        expect(within(barisKurang).getByText('7 izin belum tersedia')).toBeTruthy();
        expect(within(barisKurang).getByText('Indikator sudah diarsipkan.')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Lihat penugasan IKU 2.1' }).getAttribute('href')).toBe('/perencanaan/indikator/ind-2/penanggung-jawab');
    });

    it('memakai pola kartu mobile agar tidak ada scroll horizontal', () => {
        render(<Index {...props} />);
        const tabel = screen.getByRole('table');
        expect(tabel.className).toContain('block');
        expect(tabel.className).toContain('md:table');
        expect(tabel.parentElement?.className).toContain('overflow-x-visible');
        expect(tabel.querySelector('thead')?.className).toContain('max-md:sr-only');
    });

    it('mengirim hanya isian filter yang terisi', () => {
        render(<Index {...props} />);
        fireEvent.click(screen.getByRole('button', { name: 'Terapkan' }));
        expect(transport.get.mock.calls[0][0]).toBe('/penanggung-jawab');
        expect(transport.get.mock.calls[0][1]).toEqual({ tanggal_acuan: '2026-03-15' });

        fireEvent.change(screen.getByLabelText('Cari PJ, indikator, atau unit'), { target: { value: 'Sari' } });
        fireEvent.change(screen.getByLabelText('Unit'), { target: { value: 'unit-b' } });
        fireEvent.change(screen.getByLabelText('Indikator'), { target: { value: 'ind-2' } });
        fireEvent.change(screen.getByLabelText('Izin kerja'), { target: { value: 'kurang' } });
        fireEvent.change(screen.getByLabelText(/Tanggal acuan/), { target: { value: '2026-04-01' } });
        fireEvent.click(screen.getByRole('button', { name: 'Terapkan' }));
        expect(transport.get.mock.calls[1][1]).toEqual({ q: 'Sari', unit_id: 'unit-b', indikator_id: 'ind-2', izin: 'kurang', tanggal_acuan: '2026-04-01' });
    });

    it('menyempitkan pilihan indikator sesuai unit dan mengosongkan indikator lintas unit', () => {
        render(<Index {...props} />);
        expect(opsi('Indikator')).toEqual(['Semua indikator', 'IKU 1.1 — Indikator Umum', 'IKU 2.1 — Indikator Kelembagaan']);
        fireEvent.change(screen.getByLabelText('Indikator'), { target: { value: 'ind-1' } });
        fireEvent.change(screen.getByLabelText('Unit'), { target: { value: 'unit-b' } });
        expect(opsi('Indikator')).toEqual(['Semua indikator', 'IKU 2.1 — Indikator Kelembagaan']);
        expect((screen.getByLabelText('Indikator') as HTMLSelectElement).value).toBe('');
        expect(opsi('Izin kerja')).toEqual(['Semua', 'Belum lengkap', 'Lengkap']);
    });

    it('mengunci tombol Terapkan selama request dan memulihkannya ketika selesai', () => {
        render(<Index {...props} />);
        const button = screen.getByRole('button', { name: 'Terapkan' }) as HTMLButtonElement;
        fireEvent.click(button);
        const options = transport.get.mock.calls[0][2];
        act(() => options.onStart());
        expect(button.disabled).toBe(true);
        act(() => options.onFinish());
        expect(button.disabled).toBe(false);
    });

    it('menampilkan keadaan kosong dengan tautan atur ulang', () => {
        render(<Index {...props} assignments={{ data: [], from: null, to: null, total: 0, links: [] }} />);
        expect(screen.queryByRole('table')).toBeNull();
        expect(screen.getByText('Belum ada penanggung jawab yang sesuai')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Atur ulang' }).getAttribute('href')).toBe('/penanggung-jawab');
    });

    it('menampilkan paginasi bersama bila hasil lebih dari satu halaman', () => {
        const links = [{ url: null, label: '&laquo; Previous', active: false }, { url: '/penanggung-jawab?page=1', label: '1', active: true },
            { url: '/penanggung-jawab?page=2', label: '2', active: false }, { url: '/penanggung-jawab?page=2', label: 'Next &raquo;', active: false }];
        render(<Index {...props} assignments={{ ...props.assignments, from: 1, to: 20, total: 21, links }} />);
        const nav = screen.getByRole('navigation', { name: 'Paginasi monitoring penanggung jawab' });
        expect(nav.textContent).toContain('dari 21 data');
        expect(within(nav).getByRole('link', { name: '2' }).getAttribute('href')).toBe('/penanggung-jawab?page=2');
    });
});

it('menyediakan navigasi kembali ke Sasaran & Indikator', () => {
    render(<Index {...props} />);
    expect(screen.getByRole('link', { name: 'Kembali ke Sasaran & Indikator' }).getAttribute('href')).toBe('/perencanaan/sasaran-indikator');
});

it('tanpa indikator:read tidak menautkan kembali ke Sasaran & Indikator', () => {
    transport.bacaIndikator = false;
    render(<Index {...props} />);
    expect(screen.queryByRole('link', { name: 'Kembali ke Sasaran & Indikator' })).toBeNull();
});
