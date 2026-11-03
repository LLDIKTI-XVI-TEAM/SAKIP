import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import PengukuranIndex from '@/Pages/Pengukuran/Index';
import RencanaAksiIndex from '@/Pages/RencanaAksi/Index';
import type { PengingatHalaman } from '@/Components/PengingatKontekstual';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

afterEach(cleanup);

const item = { id: 'pribadi', signature: 'periode|2027-01-15|warning|2', tingkat: 'warning' as const,
    judul: '2 indikator Anda belum diajukan', keterangan: 'Batas pengisian 15 Januari 2027.' };
const pengingat: PengingatHalaman = { pribadi: [item],
    pk: [{ ...item, id: 'pk', judul: 'PK belum lengkap' }],
    ringkasan: [{ ...item, id: 'ringkasan', judul: 'Ringkasan seluruh unit' }],
};

it('daftar fokus menampilkan kewajiban personal dan PK serta tautan kembali ke seluruh daftar', () => {
    const { rerender } = render(<PengukuranIndex pengingat={pengingat} konteks={[]} pengukurans={[]}
        filters={{ jadwal_id: 'jadwal', periode_id: 'periode', fokus: 'kewajiban' }} can={{ selectPeriod: false }}
        pagination={{ links: [], total: 0, from: null, to: null }} />);
    expect(screen.getByRole('link', { name: 'Lihat semua' }).getAttribute('href')).toBe('/pengukuran');
    expect(screen.getByText('Tidak ada kewajiban yang belum diajukan pada periode ini.')).toBeTruthy();
    expect(screen.getByText(item.judul)).toBeTruthy();
    expect(screen.getByText('PK belum lengkap')).toBeTruthy();
    expect(screen.queryByText('Ringkasan seluruh unit')).toBeNull();

    rerender(<PengukuranIndex konteks={[]} pengukurans={[]} filters={{ jadwal_id: null, periode_id: null }}
        can={{ selectPeriod: false }} pagination={{ links: [], total: 0, from: null, to: null }} />);
    expect(screen.queryByRole('link', { name: 'Lihat semua' })).toBeNull();
});

it('daftar Rencana Aksi hanya memasang bagian kewajiban pribadi', () => {
    render(<RencanaAksiIndex pengingat={pengingat} daftar={[]} filters={{ status: 'semua' }}
        pagination={{ current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null }} />);
    expect(screen.getByText(item.judul)).toBeTruthy();
    expect(screen.queryByText('PK belum lengkap')).toBeNull();
    expect(screen.queryByText('Ringkasan seluruh unit')).toBeNull();
});
