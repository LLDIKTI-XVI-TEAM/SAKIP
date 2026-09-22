import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Pending from '@/Pages/Auth/Pending';

const pageState = vi.hoisted(() => ({ flash: {} as { authRecoveryNotice?: 'no_replay' }, props: { auth: { can: { dashboard: false } } } }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: import('react').ReactNode }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null, usePage: () => pageState };
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Akun menunggu aktivasi', () => {
    it('menampilkan dan menyalin ID akun yang dipakai operator bootstrap', async () => {
        const id = '1380daa1-7af3-4884-aa0c-178614d7de78';
        const writeText = vi.fn().mockResolvedValue(undefined);
        const user = userEvent.setup();
        Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });

        render(<Pending auth={{ user: { id, nama: 'Calon Admin', email: 'calon@example.test', is_active: false } }} />);

        expect(screen.getByText(id)).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Salin ID akun' }));
        expect(writeText).toHaveBeenCalledWith(id);
        expect(screen.getByRole('status').textContent).toContain('ID akun tersalin');
    });
});

it('logout419 tidak mengklaim keluar, mengakhiri pending dan menahan POST berikutnya', async () => {
    const { router } = await import('@inertiajs/react');
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(<Pending auth={{ user: { id: 'qa', nama: 'QA', email: 'qa@example.test', is_active: false } }} />);
    await user.click(screen.getByRole('button', { name: 'Keluar' }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    await (await import('@testing-library/react')).act(async () => { options?.onHttpException?.({ status: 419, data: {}, headers: {} }); });
    expect(screen.getByRole('alert').textContent).toContain('Keluar belum terkonfirmasi');
    expect(screen.getByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
    expect(screen.queryByText(/berhasil keluar/i)).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Keluar' }));
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('Error retry hanya memilih login eksplisit dan Recovered tidak meminta izin dashboard', async () => {
    const { default: ErrorPage } = await import('@/Pages/Auth/Error');
    const { default: Recovered } = await import('@/Pages/Auth/Recovered');
    const { router } = await import('@inertiajs/react');
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    const { rerender } = render(<ErrorPage recoveryRetry />);
    expect(screen.getByRole('link', { name: 'Coba masuk kembali' }).getAttribute('href')).toBe('/login?recovery=1');
    rerender(<ErrorPage />);
    expect(screen.getByRole('link', { name: 'Coba masuk kembali' }).getAttribute('href')).toBe('/login');
    rerender(<Recovered />);
    expect(screen.queryByRole('link', { name: 'Buka dashboard' })).toBeNull();
    expect(screen.getByText(/Tidak ada formulir yang dikirim ulang/)).toBeTruthy();
    pageState.props.auth.can.dashboard = true;
    rerender(<Recovered />);
    expect(screen.getByRole('link', { name: 'Buka dashboard' })).toBeTruthy();
    expect(router.post).not.toHaveBeenCalled();
    pageState.props.auth.can.dashboard = false;
});
