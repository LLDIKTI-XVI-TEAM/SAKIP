import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Index from '@/Pages/Jadwal/Index';
import type { JadwalIndexProps } from '@/types/periode-jadwal';

const state = vi.hoisted(() => ({ errors: {} as Record<string, string> }));
vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { periode: true, jadwal: true } }, errors: state.errors } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

const props: JadwalIndexProps = {
    jadwal: { data: [], current_page: 1, last_page: 1, total: 0, from: null, to: null, links: [] },
    filters: { q: '', tahun: null, renstra_id: null, status: null, sort: 'tahun_desc' },
    can: { create: true },
};
beforeEach(() => {
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    state.errors = {};
});

it.each(['0', '10000'])('filter tahun %s mempertahankan input dan menampilkan error server yang aksesibel', (tahun) => {
    const { rerender } = render(<Index {...props} />);
    fireEvent.change(screen.getByLabelText('Tahun pelaporan'), { target: { value: tahun } });
    fireEvent.click(screen.getByRole('button', { name: 'Terapkan' }));
    state.errors = { tahun: 'Tahun harus berada antara 1 dan 9998.' };
    rerender(<Index {...props} />);
    const field = screen.getByLabelText<HTMLInputElement>('Tahun pelaporan');
    expect(field.value).toBe(tahun);
    expect(field.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(field.getAttribute('aria-describedby') ?? '')?.textContent).toBe(state.errors.tahun);
    expect(document.activeElement?.textContent).toContain('Periksa filter jadwal');
    expect(vi.mocked(router.get).mock.calls[0][2]?.preserveState).toBe('errors');
});

const baris = {
    id: 'jadwal-1',
    renstra: { id: 'renstra-1', nama: 'Renstra pengujian', status: 'aktif' as const },
    tahun: 2026,
    status: 'aktif' as const,
    revisi: 1,
    penutupan: '2026-12-31',
    can_update: false,
};

it('nilai filter tahun tidak disajikan sebagai Tahun Aktif dan tombol susun tetap tersedia', () => {
    render(
        <Index
            {...props}
            jadwal={{ ...props.jadwal, data: [baris], total: 1, from: 1, to: 1 }}
            filters={{ ...props.filters, tahun: 2027, status: 'aktif' }}
        />,
    );
    expect(screen.queryByText(/Tahun Aktif/)).toBeNull();
    expect(screen.queryByText(/Status Filter/)).toBeNull();
    expect(screen.getByRole('link', { name: 'Susun jadwal' }).getAttribute('href')).toBe('/jadwal/create');
});

it('tanggal penutupan ditampilkan terformat, bukan ISO mentah', () => {
    render(<Index {...props} jadwal={{ ...props.jadwal, data: [baris], total: 1, from: 1, to: 1 }} />);
    expect(screen.getByText('31 Desember 2026')).toBeTruthy();
    expect(screen.queryByText('2026-12-31')).toBeNull();
    expect(screen.getByRole('link', { name: 'Lihat jadwal Renstra pengujian 2026' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
});

it('menampilkan placeholder pencarian dan tombol reset filter ketika filter aktif', () => {
    render(<Index {...props} />);
    expect(screen.getByPlaceholderText('Cari nama atau kode Renstra...')).toBeTruthy();
    expect(screen.getByPlaceholderText('Contoh: 2026')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Reset' })).toBeNull();

    fireEvent.change(screen.getByLabelText('Cari Renstra'), { target: { value: 'Renstra 2025' } });
    const resetButton = screen.getByRole('button', { name: 'Reset' });
    fireEvent.click(resetButton);

    expect(screen.getByLabelText<HTMLInputElement>('Cari Renstra').value).toBe('');
    expect(router.get).toHaveBeenCalledWith(
        '/jadwal',
        { q: '', tahun: '', status: '', sort: 'tahun_desc' },
        { replace: true, preserveState: 'errors' },
    );
});

