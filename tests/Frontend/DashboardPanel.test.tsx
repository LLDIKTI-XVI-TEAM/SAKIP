import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { PanelKegiatan } from '@/Components/Dashboard/PanelKegiatan';
import { PanelRencanaAksi } from '@/Components/Dashboard/PanelRencanaAksi';
import type { DashboardKegiatan } from '@/types/dashboard';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { pengaturan: {} } }),
}));

afterEach(cleanup);

const kegiatan = (overrides: Partial<DashboardKegiatan> = {}): DashboardKegiatan => ({
    per_status: { rencana: 3, terlaksana: 5, tidak_terlaksana: 1, ditunda: 0, batal: 2 },
    terlambat_jumlah: 2,
    per_iku: [{ id: 'i1', kode: 'IKU 1.1', nama: 'Persentase PT terakreditasi', jumlah: { rencana: 2, terlaksana: 1, tidak_terlaksana: 0, ditunda: 0, batal: 1 } }],
    terlambat: [{ id: 'k1', nama: 'Bimtek akreditasi', unit: 'Bagian Kelembagaan', tanggal_rencana: '2026-03-01' }],
    ...overrides,
});

it('panel kegiatan berlabel belum diverifikasi dan memberi peringatan kegiatan terlambat', () => {
    render(<PanelKegiatan kegiatan={kegiatan()} periodeNama="Triwulan I 2026" />);

    const panel = screen.getByRole('region', { name: /Status kegiatan Triwulan I 2026/ });
    expect(within(panel).getByText('Belum diverifikasi')).toBeTruthy();
    expect(within(panel).getByText('2 kegiatan melewati tanggal rencana dan belum terlaksana')).toBeTruthy();
    expect(within(panel).getByText('Bimtek akreditasi')).toBeTruthy();
    expect(panel.querySelector('[data-status="tidak_terlaksana"]')?.textContent).toBe('Tidak terlaksana1');
    expect(panel.querySelector('[data-status="batal"]')?.textContent).toBe('Batal2');
    const iku = within(panel).getByRole('table', { name: /Kegiatan per IKU/ });
    expect(iku.className).toContain('block md:table');
    expect(within(iku).getByText('IKU 1.1')).toBeTruthy();
});

it('tanpa izin baca kegiatan, jumlah terlambat tetap tampil tetapi daftar nama tidak', () => {
    render(<PanelKegiatan kegiatan={kegiatan({ terlambat: null })} periodeNama="Triwulan I 2026" />);

    expect(screen.getByText('2 kegiatan melewati tanggal rencana dan belum terlaksana')).toBeTruthy();
    expect(screen.queryByText('Bimtek akreditasi')).toBeNull();
    expect(screen.queryByRole('list', { name: /Kegiatan terlambat/ })).toBeNull();
});

it('tanpa kegiatan terlambat tidak ada peringatan', () => {
    render(<PanelKegiatan kegiatan={kegiatan({ terlambat_jumlah: 0, terlambat: [] })} periodeNama="Triwulan I 2026" />);

    expect(screen.queryByText(/melewati tanggal rencana/)).toBeNull();
});

it('panel rencana aksi menampilkan indikator belum disusun', () => {
    render(<PanelRencanaAksi progres={{ belum_disusun: 15, draft: 2, diajukan: 1, diverifikasi: 0, dikembalikan: 0, disahkan: 5 }} tahun={2026} />);

    const panel = screen.getByRole('region', { name: /Progres Rencana Aksi 2026/ });
    expect(panel.querySelector('[data-status="belum_disusun"]')?.textContent).toBe('Belum disusun15');
    expect(panel.querySelector('[data-status="disahkan"]')?.textContent).toBe('Disahkan5');
});

it('dua IKU berkode sama tetap tampil sebagai dua baris', () => {
    const jumlah = { rencana: 1, terlaksana: 0, tidak_terlaksana: 0, ditunda: 0, batal: 0 };
    const errors: unknown[] = [];
    const asli = console.error;
    console.error = (...args: unknown[]) => errors.push(args);
    render(<PanelKegiatan kegiatan={kegiatan({ per_iku: [{ id: 'i1', kode: 'IKU 1.1', nama: 'Lama', jumlah }, { id: 'i2', kode: 'IKU 1.1', nama: 'Baru', jumlah }] })} periodeNama="Triwulan I 2026" />);
    console.error = asli;

    expect(screen.getByText('Lama')).toBeTruthy();
    expect(screen.getByText('Baru')).toBeTruthy();
    expect(errors.some((e) => String(e).includes('same key'))).toBe(false);
});
