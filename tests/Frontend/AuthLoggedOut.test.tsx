import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, expect, it, vi } from 'vitest';
import LoggedOut from '@/Pages/Auth/LoggedOut';

const state = vi.hoisted(() => ({ props: { auth: { user: null as object | null } }, flash: {} as { logoutNotice?: string } }));
vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => state,
}));
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

afterEach(() => { cleanup(); vi.restoreAllMocks(); state.props.auth.user = null; state.flash = {}; });

it('landing tidak mengklaim sesi SSO berakhir, menyediakan login eksplisit dan logout SSO', async () => {
    const { router } = await import('@inertiajs/react');
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    const user = userEvent.setup();
    state.flash.logoutNotice = 'sso_unavailable';
    render(<LoggedOut />);
    expect(screen.getByRole('heading', { name: 'Anda telah keluar' })).toBeTruthy();
    expect(screen.getByText(/Sesi SSO mungkin masih aktif/)).toBeTruthy();
    expect(screen.getByRole('alert').textContent).toContain('Logout SSO belum terkonfirmasi');
    expect(screen.getByRole('link', { name: 'Masuk kembali melalui SSO' }).getAttribute('href')).toBe('/login');
    await user.click(screen.getByRole('button', { name: 'Keluar dari layanan terhubung' }));
    expect(router.post).not.toHaveBeenCalled();
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Keluar dari layanan terhubung' }));
    expect(router.post).toHaveBeenCalledWith('/logout/sso', {}, expect.any(Object));
});

it('GET landing dengan sesi sah tidak mengaku pengguna sudah keluar', () => {
    state.props.auth.user = { id: 'fixture' };
    render(<LoggedOut />);
    expect(screen.getByRole('heading', { name: 'Sesi SAKIP masih aktif' })).toBeTruthy();
    expect(screen.queryByText(/Sesi SAKIP di perangkat ini sudah berakhir/)).toBeNull();
    expect(screen.getByRole('button', { name: 'Keluar dari SAKIP' })).toBeTruthy();
});
