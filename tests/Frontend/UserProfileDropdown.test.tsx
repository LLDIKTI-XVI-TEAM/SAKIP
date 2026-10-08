import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import type { VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { createViewportMock, installDialogPolyfill } from './viewportFixtures';

const state = vi.hoisted(() => ({
    user: { id: 'admin-id', nama: 'Operator QA', email: 'admin@example.test', status: 'aktif' as const, role: 'admin' as string | null },
    url: '/dashboard',
}));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Link: ({ children, href, ...props }: { children: React.ReactNode; href?: string }) => <a href={href} {...props}>{children}</a>,
        usePage: () => ({
            props: {
                auth: { user: state.user, can: { dashboard: true, regulasi: true, renstra: true } },
                flash: {},
                pengaturan: { 'aplikasi.nama': 'SAKIP LLDIKTI XVI' },
            },
            flash: {},
            url: state.url,
        }),
    };
});

const DESKTOP_WIDTH = 1440;
const MOBILE_WIDTH = 390;
const viewport = createViewportMock(DESKTOP_WIDTH);
let restoreDialog: () => void;

beforeAll(() => {
    viewport.install();
    restoreDialog = installDialogPolyfill();
});
afterAll(() => {
    viewport.restore();
    restoreDialog();
});
beforeEach(() => {
    viewport.reset(DESKTOP_WIDTH);
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'clearHistory').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    state.user = { id: 'admin-id', nama: 'Operator QA', email: 'admin@example.test', status: 'aktif', role: 'admin' };
    state.url = '/dashboard';
});

const renderLayout = () => render(<AuthenticatedLayout title="Dashboard"><p>Konten halaman</p></AuthenticatedLayout>);
const profileTrigger = () => screen.getByRole('button', { name: /^Menu akun / });
const panelOf = (trigger: HTMLElement) => document.getElementById(trigger.getAttribute('aria-controls')!) as HTMLElement;
const postOptions = (call = 0) => vi.mocked(router.post).mock.calls[call][2] as VisitOptions;

const openProfile = async (user: ReturnType<typeof userEvent.setup>) => {
    const trigger = profileTrigger();
    await user.click(trigger);
    const panel = panelOf(trigger);
    expect(trigger.getAttribute('aria-expanded')).toBe('true');
    expect(panel.hasAttribute('hidden')).toBe(false);
    return { trigger, panel };
};

describe('UI-01-B — Profil header desktop', () => {
    it('menampilkan identitas dari auth.user dan membuka panel berisi dua aksi keluar', async () => {
        const user = userEvent.setup();
        renderLayout();

        const trigger = profileTrigger();
        expect(screen.getAllByRole('button', { name: /^Menu akun / })).toHaveLength(1);
        expect(trigger.getAttribute('aria-label')).toBe('Menu akun Operator QA (admin)');
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(trigger.closest('aside')).toBeNull();
        const panel = panelOf(trigger);
        expect(panel.hasAttribute('hidden')).toBe(true);
        expect(screen.queryByRole('button', { name: 'Keluar dari SAKIP' })).toBeNull();

        await user.click(trigger);

        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(within(panel).getByText('Operator QA')).toBeTruthy();
        expect(within(panel).getByText('admin')).toBeTruthy();
        expect(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' })).toBeTruthy();
        expect(within(panel).getByRole('button', { name: 'Keluar dari layanan terhubung' })).toBeTruthy();
        expect(panel.querySelector('[role="menu"], [role="menuitem"]')).toBeNull();
    });

    it('identitas mengikuti pengguna aktif dan menangani akun tanpa peran', () => {
        state.user = { id: 'u2', nama: 'Rina Perencana', email: 'rina@example.test', status: 'aktif', role: null };
        renderLayout();

        expect(profileTrigger().getAttribute('aria-label')).toBe('Menu akun Rina Perencana (Belum ada peran)');
        expect(within(panelOf(profileTrigger())).getByText('Belum ada peran')).toBeTruthy();
    });
});

describe('UI-01-C — Buka/tutup panel profil', () => {
    it('klik ulang dan klik di luar panel menutup dropdown', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);

        await user.click(trigger);
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hasAttribute('hidden')).toBe(true);

        await user.click(trigger);
        await user.click(screen.getByText('Konten halaman'));
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
    });

    it('Escape menutup dropdown dan mengembalikan fokus ke tombol profil', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);

        await user.tab();
        expect(document.activeElement).toBe(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }));
        await user.tab();
        expect(document.activeElement).toBe(within(panel).getByRole('button', { name: 'Keluar dari layanan terhubung' }));
        await user.tab({ shift: true });
        expect(document.activeElement).toBe(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }));

        await user.keyboard('{Escape}');

        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hasAttribute('hidden')).toBe(true);
        expect(document.activeElement).toBe(trigger);
    });

    it('Enter dan Space pada tombol profil membuka dan menutup dropdown', async () => {
        const user = userEvent.setup();
        renderLayout();
        const trigger = profileTrigger();

        trigger.focus();
        await user.keyboard('{Enter}');
        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        await user.keyboard(' ');
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
    });

    it('perpindahan halaman Inertia menutup dropdown dan listener dilepas saat unmount', async () => {
        const user = userEvent.setup();
        const addListener = vi.spyOn(document, 'addEventListener');
        const removeListener = vi.spyOn(document, 'removeEventListener');
        const { unmount } = renderLayout();
        const { trigger } = await openProfile(user);

        act(() => {
            document.dispatchEvent(new CustomEvent('inertia:navigate', { detail: { page: { url: '/regulasi', props: {} } } }));
        });
        expect(trigger.getAttribute('aria-expanded')).toBe('false');

        unmount();
        for (const type of ['pointerdown', 'inertia:navigate']) {
            const added = addListener.mock.calls.filter(([name]) => name === type).length;
            const removed = removeListener.mock.calls.filter(([name]) => name === type).length;
            expect(added, type).toBeGreaterThan(0);
            expect(removed, type).toBe(added);
        }
    });
});

describe('UI-01-D — Keluar dari SAKIP', () => {
    it('mengirim POST /logout satu kali, membersihkan history, dan menahan panel selama proses', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);
        const localButton = within(panel).getByRole('button', { name: 'Keluar dari SAKIP' });

        await user.click(localButton);

        expect(router.post).toHaveBeenCalledTimes(1);
        expect(router.post).toHaveBeenCalledWith('/logout', {}, expect.any(Object));
        act(() => postOptions().onStart?.({} as never));
        expect(router.clearHistory).toHaveBeenCalledTimes(1);

        expect(localButton.hasAttribute('disabled')).toBe(true);
        expect(panel.querySelector('[aria-busy="true"]')).not.toBeNull();
        await user.click(localButton);
        await user.click(screen.getByText('Konten halaman'));
        expect(router.post).toHaveBeenCalledTimes(1);
        expect(trigger.getAttribute('aria-expanded')).toBe('true');

        act(() => postOptions().onFinish?.({} as never));
        expect(panel.querySelector('[aria-busy="true"]')).toBeNull();
        expect(document.querySelector('a[href="/logout"], a[href="/logout/sso"]')).toBeNull();
    });
});

describe('UI-01-E — Keluar dari layanan terhubung', () => {
    it('membuka dialog konfirmasi tanpa menutup panel; Batal tidak mengirim, konfirmasi mengirim sekali', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);
        const ssoButton = within(panel).getByRole('button', { name: 'Keluar dari layanan terhubung' });

        await user.click(ssoButton);
        let dialog = screen.getByRole('dialog', { name: 'Keluar dari layanan terhubung?' });
        expect(within(dialog).getByText(/sesi layanan lain|layanan lain/)).toBeTruthy();
        expect(document.activeElement).toBe(within(dialog).getByRole('button', { name: 'Batal' }));
        expect(trigger.getAttribute('aria-expanded')).toBe('true');

        // Escape di dalam dialog ditangani dialog itu sendiri, bukan dropdown.
        await user.keyboard('{Escape}');
        expect(trigger.getAttribute('aria-expanded')).toBe('true');

        await user.click(within(dialog).getByRole('button', { name: 'Batal' }));
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(router.post).not.toHaveBeenCalled();
        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(ssoButton);

        await user.click(ssoButton);
        fireEvent(screen.getByRole('dialog'), new Event('cancel', { cancelable: true }));
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(router.post).not.toHaveBeenCalled();
        expect(trigger.getAttribute('aria-expanded')).toBe('true');

        await user.click(ssoButton);
        dialog = screen.getByRole('dialog');
        await user.click(within(dialog).getByRole('button', { name: 'Keluar dari layanan terhubung' }));

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(router.post).toHaveBeenCalledTimes(1);
        expect(router.post).toHaveBeenCalledWith('/logout/sso', {}, expect.any(Object));
        act(() => postOptions().onStart?.({} as never));
        expect(router.clearHistory).toHaveBeenCalledTimes(1);
    });
});

describe('UI-01-F — Kegagalan logout tetap terbaca', () => {
    it('419 menampilkan recovery, panel tidak tertutup selama proses, dan request kedua ditahan setelah dibuka ulang', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);

        await user.click(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }));
        await user.click(screen.getByText('Konten halaman'));
        expect(trigger.getAttribute('aria-expanded')).toBe('true');

        await act(async () => {
            postOptions().onHttpException?.({ status: 419, data: {}, headers: {} } as never);
            postOptions().onFinish?.({} as never);
        });

        const alert = within(panel).getByRole('alert');
        expect(alert.textContent).toContain('Keluar belum terkonfirmasi');
        expect(within(panel).getByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
        expect(trigger.getAttribute('aria-expanded')).toBe('true');

        await user.click(trigger);
        await user.click(trigger);
        expect(within(panel).getByRole('alert').textContent).toContain('Keluar belum terkonfirmasi');
        expect(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }).hasAttribute('disabled')).toBe(true);
        await user.click(within(panel).getByRole('button', { name: 'Keluar dari layanan terhubung' }));
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(router.post).toHaveBeenCalledTimes(1);
    });

    it('gangguan jaringan memunculkan pesan belum terkonfirmasi tanpa mengirim ulang', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { panel } = await openProfile(user);

        await user.click(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }));
        await act(async () => {
            postOptions().onNetworkError?.({} as never);
            postOptions().onFinish?.({} as never);
        });

        expect(within(panel).getByRole('alert').textContent).toContain('Keluar belum terkonfirmasi');
        await user.click(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }));
        expect(router.post).toHaveBeenCalledTimes(1);
    });

    it('Escape selama logout berjalan tidak menyembunyikan hasil kegagalan', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);

        await user.click(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }));
        await user.keyboard('{Escape}');
        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(panel.hasAttribute('hidden')).toBe(false);

        await act(async () => {
            postOptions().onHttpException?.({ status: 419, data: {}, headers: {} } as never);
            postOptions().onFinish?.({} as never);
        });
        expect(screen.getByRole('alert').textContent).toContain('Keluar belum terkonfirmasi');

        // Setelah request selesai, Escape kembali menutup panel dan memulihkan fokus.
        await user.keyboard('{Escape}');
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger);
    });

    it('klik ulang tombol profil selama logout berjalan tidak menyembunyikan hasil kegagalan', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);

        await user.click(within(panel).getByRole('button', { name: 'Keluar dari layanan terhubung' }));
        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Keluar dari layanan terhubung' }));
        await user.click(trigger);
        expect(trigger.getAttribute('aria-expanded')).toBe('true');

        await act(async () => {
            postOptions().onNetworkError?.({} as never);
            postOptions().onFinish?.({} as never);
        });
        expect(screen.getByRole('alert').textContent).toContain('Keluar belum terkonfirmasi');

        await user.click(trigger);
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(router.post).toHaveBeenCalledTimes(1);
    });
});

describe('UI-01-G — Akses profil pada viewport mobile', () => {
    beforeEach(() => viewport.reset(MOBILE_WIDTH));

    it('profil dan dua aksi keluar tersedia di header mobile walau sidebar tidak memuat logout', async () => {
        const user = userEvent.setup();
        renderLayout();
        const drawer = document.getElementById('application-navigation') as HTMLElement;

        expect(screen.getAllByRole('button', { name: /^Menu akun / })).toHaveLength(1);
        expect(drawer.contains(profileTrigger())).toBe(false);
        expect(drawer.textContent).not.toContain('Keluar dari');

        const { panel } = await openProfile(user);
        expect(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' })).toBeTruthy();
        expect(within(panel).getByRole('button', { name: 'Keluar dari layanan terhubung' })).toBeTruthy();

        await user.click(within(panel).getByRole('button', { name: 'Keluar dari layanan terhubung' }));
        const dialog = screen.getByRole('dialog', { name: 'Keluar dari layanan terhubung?' });
        await user.click(within(dialog).getByRole('button', { name: 'Batal' }));
        expect(router.post).not.toHaveBeenCalled();
    });

    it('membuka drawer menutup dan menonaktifkan profil; trap fokus drawer tidak bocor ke tombol profil', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger } = await openProfile(user);
        const menuButton = screen.getByRole('button', { name: 'Buka navigasi' });

        await user.click(menuButton);

        const drawer = document.getElementById('application-navigation') as HTMLElement;
        expect(drawer.getAttribute('aria-modal')).toBe('true');
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(trigger.closest('[inert]')).not.toBeNull();

        const closeButton = within(drawer).getByRole('button', { name: 'Tutup navigasi' });
        await waitFor(() => expect(document.activeElement).toBe(closeButton));
        for (let step = 0; step < 12; step += 1) {
            await user.tab();
            expect(drawer.contains(document.activeElement), `Tab ke-${step + 1}`).toBe(true);
        }

        await user.keyboard('{Escape}');
        expect(drawer.getAttribute('aria-hidden')).toBe('true');
        expect(document.activeElement).toBe(menuButton);
        expect(trigger.closest('[inert]')).toBeNull();

        await user.click(trigger);
        expect(trigger.getAttribute('aria-expanded')).toBe('true');
    });

    it('keyboard: Tab keluar dari panel ke tombol navigasi menutup dropdown', async () => {
        const user = userEvent.setup();
        renderLayout();
        const trigger = profileTrigger();

        trigger.focus();
        await user.keyboard('{Enter}');
        await user.tab();
        await user.tab();
        expect(document.activeElement?.textContent).toContain('Keluar dari layanan terhubung');

        await user.tab();

        expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Buka navigasi' }));
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
    });

    it('drawer tidak dapat dibuka selama logout berjalan sehingga hasil kegagalan tetap terlihat', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, panel } = await openProfile(user);
        const menuButton = screen.getByRole('button', { name: 'Buka navigasi' });
        const drawer = document.getElementById('application-navigation') as HTMLElement;

        await user.click(within(panel).getByRole('button', { name: 'Keluar dari SAKIP' }));
        expect(menuButton.hasAttribute('disabled')).toBe(true);

        await user.click(menuButton);
        expect(drawer.getAttribute('aria-modal')).toBeNull();
        expect(drawer.getAttribute('aria-hidden')).toBe('true');
        expect(trigger.closest('[inert]')).toBeNull();

        await act(async () => {
            postOptions().onNetworkError?.({} as never);
            postOptions().onFinish?.({} as never);
        });

        const alert = within(panel).getByRole('alert');
        expect(alert.textContent).toContain('Keluar belum terkonfirmasi');
        expect(panel.hasAttribute('hidden')).toBe(false);
        expect(alert.closest('[inert]')).toBeNull();
        expect(menuButton.hasAttribute('disabled')).toBe(false);

        // Setelah request selesai, navigasi kembali normal dan panel profil menutup seperti biasa.
        await user.click(menuButton);
        expect(drawer.getAttribute('aria-modal')).toBe('true');
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(router.post).toHaveBeenCalledTimes(1);
    });
});

describe('UI-01-H — Perubahan viewport dan ruang panel', () => {
    it('perpindahan desktop ↔ mobile tidak meninggalkan panel terbuka yang tidak terlihat', async () => {
        const user = userEvent.setup();
        renderLayout();
        await openProfile(user);

        viewport.setWidth(MOBILE_WIDTH);
        expect(screen.getAllByRole('button', { name: /^Menu akun / })).toHaveLength(1);
        expect(profileTrigger().getAttribute('aria-expanded')).toBe('false');
        expect(screen.queryByRole('button', { name: 'Keluar dari SAKIP' })).toBeNull();

        await openProfile(user);
        viewport.setWidth(DESKTOP_WIDTH);
        expect(screen.getAllByRole('button', { name: /^Menu akun / })).toHaveLength(1);
        expect(profileTrigger().getAttribute('aria-expanded')).toBe('false');
    });

    it.each([
        [DESKTOP_WIDTH, ['absolute', 'right-0', 'max-w-[calc(100vw-2rem)]']],
        [1280, ['absolute', 'right-0', 'max-w-[calc(100vw-2rem)]']],
        [768, ['absolute', 'right-0', 'max-w-[calc(100vw-2rem)]']],
        [MOBILE_WIDTH, ['fixed', 'right-4', 'w-[calc(100vw-2rem)]', 'max-w-xs']],
        [320, ['fixed', 'right-4', 'w-[calc(100vw-2rem)]', 'max-w-xs']],
    ])('lebar %ipx memakai panel rata kanan yang dibatasi lebar viewport', (width, classes) => {
        viewport.reset(width);
        renderLayout();
        const panel = panelOf(profileTrigger());
        classes.forEach((name) => expect(panel.classList.contains(name), name).toBe(true));
    });
});

describe('UI-01-I — Tidak ada regresi layout', () => {
    it('breadcrumb, judul, notifikasi, dan navigasi tetap tersedia bersama profil', () => {
        renderLayout();

        expect(screen.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Notifikasi akan segera tersedia' })).toBeTruthy();
        expect(screen.getByRole('navigation', { name: 'Navigasi utama' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Dashboard' }).getAttribute('href')).toBe('/dashboard');
        expect(profileTrigger()).toBeTruthy();
    });
});
