import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import Pending from '@/Pages/Auth/Pending';

const pageState = vi.hoisted(() => ({ flash: {} as { authRecoveryNotice?: 'no_replay' }, props: { auth: { can: { dashboard: false } } } }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: import('react').ReactNode }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null, usePage: () => pageState };
});

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

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

        render(<Pending pendingReason="activation" auth={{ user: { id, nama: 'Calon Admin', email: 'calon@example.test', status: 'nonaktif' } }} />);

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
    render(<Pending pendingReason="activation" auth={{ user: { id: 'qa', nama: 'QA', email: 'qa@example.test', status: 'nonaktif' } }} />);
    await user.click(screen.getByRole('button', { name: 'Keluar dari SAKIP' }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    await (await import('@testing-library/react')).act(async () => { options?.onHttpException?.({ status: 419, data: {}, headers: {} }); });
    expect(screen.getByRole('alert').textContent).toContain('Keluar belum terkonfirmasi');
    expect(screen.getByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
    expect(screen.queryByText(/berhasil keluar/i)).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Keluar dari SAKIP' }));
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('akun aktif tanpa peran mendapat petunjuk penetapan peran dan dua aksi logout saling menahan', async () => {
    const { router } = await import('@inertiajs/react');
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(<Pending pendingReason="role" auth={{ user: { id: 'qa', nama: 'QA', email: 'qa@example.test', status: 'aktif' } }} />);
    expect(screen.getByRole('heading', { name: 'Menunggu penetapan peran' })).toBeTruthy();
    expect(screen.getByText('Aktif — belum memiliki peran')).toBeTruthy();
    expect(screen.queryByText(/SIMPEG/)).toBeNull();
    const trigger = screen.getByRole('button', { name: 'Keluar dari layanan terhubung' });
    await user.click(trigger);
    const dialog = screen.getByRole('dialog', { name: 'Keluar dari layanan terhubung?' });
    expect(within(dialog).getByText(/SIMPEG/)).toBeTruthy();
    expect(document.activeElement).toBe(within(dialog).getByRole('button', { name: 'Batal' }));
    expect(router.post).not.toHaveBeenCalled();
    await user.click(within(dialog).getByRole('button', { name: 'Batal' }));
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(trigger);
    await user.click(trigger);
    fireEvent(screen.getByRole('dialog'), new Event('cancel', { cancelable: true }));
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(router.post).not.toHaveBeenCalled();
    await user.click(trigger);
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Keluar dari layanan terhubung' }));
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(router.post).toHaveBeenCalledWith('/logout/sso', {}, expect.any(Object));
    await user.click(screen.getByRole('button', { name: 'Keluar dari SAKIP' }));
    expect(router.post).toHaveBeenCalledTimes(1);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await (await import('@testing-library/react')).act(async () => { options?.onCancel?.(); });
    expect(screen.getByRole('alert').textContent).toContain('belum terkonfirmasi');
    await user.click(screen.getByRole('button', { name: 'Keluar dari layanan terhubung' }));
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
    expect(screen.getByRole('link', { name: 'Periksa status akun' }).getAttribute('href')).toBe('/auth/pending');
    pageState.props.auth.can.dashboard = true;
    rerender(<Recovered />);
    expect(screen.getByRole('link', { name: 'Buka dashboard' })).toBeTruthy();
    expect(router.post).not.toHaveBeenCalled();
    pageState.props.auth.can.dashboard = false;
});
