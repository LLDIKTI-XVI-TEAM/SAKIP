import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import ActivationIndex from '@/Pages/Auth/ActivationIndex';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const users = {
    data: [{ id: '1380daa1-7af3-4884-aa0c-178614d7de78', nama: 'Pengguna Uji', email: 'uji@example.test', created_at: '2026-09-20T00:00:00Z' }],
    current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null,
};
const response = (activationResult: unknown): Page => ({
    component: 'Auth/ActivationIndex', props: { errors: {}, activationResult }, url: '/akses/aktivasi', version: null, rescuedProps: [], flash: {}, rememberedState: {},
});

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native; fokus trap tetap diverifikasi lewat browser.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));
beforeEach(() => { vi.spyOn(router, 'post').mockImplementation(() => undefined); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe('Aktivasi pengguna', () => {
    it('mengirim alasan saja dan mempertahankan dialog serta input ketika validasi gagal', async () => {
        const user = userEvent.setup();
        render(<ActivationIndex users={users} canActivate />);
        await user.click(screen.getByRole('button', { name: 'Aktifkan Pengguna Uji' }));
        const reason = screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan aktivasi/ });
        await user.type(reason, 'Penugasan sudah diverifikasi');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi aktivasi' }));
        expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual([`/akses/aktivasi/${users.data[0].id}`, { alasan: 'Penugasan sudah diverifikasi' }]);

        await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ alasan: 'Alasan perlu diperjelas.' }); });
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(reason.value).toBe('Penugasan sudah diverifikasi');
        expect(reason.getAttribute('aria-invalid')).toBe('true');
        expect(document.activeElement).toBe(reason);

        await user.click(screen.getByRole('button', { name: 'Batal' }));
        await user.click(screen.getByRole('button', { name: 'Aktifkan Pengguna Uji' }));
        expect(screen.getByRole<HTMLTextAreaElement>('textbox').value).toBe('');
    });

    it('menutup dialog hanya setelah outcome aktivasi untuk target terkonfirmasi server', async () => {
        const user = userEvent.setup();
        render(<ActivationIndex users={users} canActivate />);
        const trigger = screen.getByRole('button', { name: 'Aktifkan Pengguna Uji' });
        await user.click(trigger);
        await user.type(screen.getByRole('textbox'), 'Identitas diverifikasi');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi aktivasi' }));
        const options = vi.mocked(router.post).mock.calls[0][2];
        await act(async () => { await options?.onSuccess?.(response(null)); });
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByRole<HTMLTextAreaElement>('textbox').value).toBe('Identitas diverifikasi');
        await act(async () => { await options?.onSuccess?.(response({ user_id: users.data[0].id, status: 'activated' })); });
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(document.activeElement).toBe(trigger);
    });
});
