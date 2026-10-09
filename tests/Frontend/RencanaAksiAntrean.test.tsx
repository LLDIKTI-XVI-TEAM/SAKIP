import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import Antrean from '@/Pages/RencanaAksi/Antrean';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));
vi.mock('@/hooks/useLabelUnit', () => ({ useLabelUnit: () => 'Unit Kerja' }));

afterEach(() => cleanup());

const pagination = { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null };

it('tab antrean aktif dan tautan tab lain benar', () => {
    render(<Antrean rencanaAksis={[]} pagination={pagination} status="antrean" />);

    expect(screen.getByRole('link', { name: 'Antrean' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('link', { name: 'Disahkan' }).getAttribute('href')).toBe('/rencana-aksi?status=disahkan');
    expect(screen.getByRole('link', { name: 'Penyusunan' }).getAttribute('href')).toBe('/rencana-aksi');
    expect(screen.getByText('Semua pengajuan telah diproses')).toBeTruthy();
});

it('tab disahkan menampilkan daftar monitoring read-only', () => {
    render(<Antrean rencanaAksis={[]} pagination={pagination} status="disahkan" />);

    expect(screen.getByRole('link', { name: 'Disahkan' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByText('Daftar Rencana Aksi Disahkan')).toBeTruthy();
    expect(screen.getByText('Belum ada rencana aksi disahkan')).toBeTruthy();
});

it('tautan Lihat per baris mengarah ke layar reviu terpisah', () => {
    render(<Antrean rencanaAksis={[{
        id: 'ra-9', versi: 1, status: 'diverifikasi', tahun: 2026, nomor_pengajuan: 1, jalur_pengajuan: 'pic',
        diajukan_pada: null, uraian: 'Uraian', indikator: { kode: 'IK-09', nama: 'Indikator reviu' },
        unit_kerja: { id: 'unit-9', nama: 'Unit reviu' }, pic: null, konteks_tidak_lengkap: [], bukti_count: 0,
        bukti_dukungs: [], target_periode: [], can: { view: true },
    }]} pagination={{ ...pagination, total: 1 }} status="antrean" />);

    expect(screen.getByRole('link', { name: 'Lihat' }).getAttribute('href')).toBe('/rencana-aksi/ra-9/reviu');
});
