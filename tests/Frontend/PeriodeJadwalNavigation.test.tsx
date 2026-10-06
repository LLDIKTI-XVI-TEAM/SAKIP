import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';

const state = vi.hoisted(() => ({ periode: false, jadwal: false }));
vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({
        props: { auth: { user: { nama: 'Operator QA', role: 'pegawai' }, can: state } },
        flash: {},
        url: '/jadwal',
    }),
}));

beforeEach(() => {
    vi.stubGlobal(
        'matchMedia',
        vi.fn().mockReturnValue({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() }),
    );
});
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    state.periode = false;
    state.jadwal = false;
});

it('menu Periode dan Jadwal mengikuti capability server secara terpisah', () => {
    const { rerender } = render(<AuthenticatedLayout>Kalender</AuthenticatedLayout>);
    expect(screen.queryByRole('link', { name: 'Master Periode' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Jadwal Tahunan' })).toBeNull();
    state.periode = true;
    rerender(<AuthenticatedLayout>Kalender</AuthenticatedLayout>);
    expect(screen.getByRole('link', { name: 'Master Periode' }).getAttribute('href')).toBe('/periode');
    expect(screen.queryByRole('link', { name: 'Jadwal Tahunan' })).toBeNull();
    state.periode = false;
    state.jadwal = true;
    rerender(<AuthenticatedLayout>Kalender</AuthenticatedLayout>);
    expect(screen.queryByRole('link', { name: 'Master Periode' })).toBeNull();
    expect(screen.getByRole('link', { name: 'Jadwal Tahunan' }).getAttribute('href')).toBe('/jadwal');
});
