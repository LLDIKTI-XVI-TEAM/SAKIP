import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createInertiaApp, router } from '@inertiajs/react';
import type { HttpRequestConfig, HttpResponse, Page } from '@inertiajs/core';
import { afterAll, afterEach, beforeAll, expect, it, vi } from 'vitest';
import RoleAssignmentIndex from '@/Pages/Access/RoleAssignmentIndex';
import RoleAssignmentResult from '@/Pages/Access/RoleAssignmentResult';
import type { SharedPageProps } from '@/types/auth';

const shims = [
    [HTMLDialogElement.prototype, 'showModal', function (this: HTMLDialogElement) { this.open = true; }],
    [HTMLDialogElement.prototype, 'close', function (this: HTMLDialogElement) { this.open = false; }],
    [HTMLElement.prototype, 'scrollTo', () => undefined],
    [window.performance, 'getEntriesByType', () => []],
] as const;
const originals = shims.map(([target, name]) => Object.getOwnPropertyDescriptor(target, name));
beforeAll(() => {
    shims.forEach(([target, name, value]) => Object.defineProperty(target, name, { configurable: true, value }));
    vi.stubGlobal('matchMedia', (media: string) => ({ media, matches: true, addEventListener() {}, removeEventListener() {} }));
    vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
});
afterEach(() => { router.cancelAll(); cleanup(); });
afterAll(() => {
    shims.forEach(([target, name], index) => {
        const descriptor = originals[index];
        if (descriptor) Object.defineProperty(target, name, descriptor);
        else Reflect.deleteProperty(target, name);
    });
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

it('mempertahankan draft dan mencegah replay ketika router nyata menerima hasil unknown', async () => {
    const auth: SharedPageProps['auth'] = {
        user: { id: 'operator', nama: 'Operator QA', email: 'operator@example.test', status: 'aktif', role: 'admin' },
        can: { dashboard: false, pengukuran: false, verifikasi: false, aktivasi: false, regulasi: false, assignRole: true, manageDeny: false, unit: false, grant: false, pengaturan: false, viewRolePermissions: false },
    };
    const roles = [{ id: 'pegawai', kode: 'pegawai', nama: 'Pegawai' }, { id: 'admin', kode: 'admin', nama: 'Admin' }];
    const initialPage: Page = {
        component: 'Access/RoleAssignmentIndex', url: '/akses/peran', version: null, flash: {}, rescuedProps: [], rememberedState: {},
        props: { errors: {}, auth, users: { data: [{ id: 'target', nama: 'Target QA', email: 'target@example.test', status: 'aktif', current_role: { ...roles[0], aktif: true }, assignment: { id: 'pivot', role_id: 'pegawai', audit_id: null }, has_active_pj: false }], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null }, roles, filters: { q: '' }, can: { assignRole: true }, confirmationUnavailable: false },
    };
    const unknownPage: Page = {
        // Kontrak HTTP yang disetujui: unknown berizin tetap Index walau URL delivery berasal dari Result.
        ...initialPage, url: '/akses/peran/hasil', props: { ...initialPage.props, confirmationUnavailable: true },
    };
    const request = vi.fn(async (_config: HttpRequestConfig): Promise<HttpResponse> => ({ status: 200, data: JSON.stringify(unknownPage), headers: { 'x-inertia': 'true' } }));
    await act(async () => {
        await createInertiaApp({ page: initialPage, progress: false, resolve: (name) => name === 'Access/RoleAssignmentIndex' ? RoleAssignmentIndex : RoleAssignmentResult, http: { request }, setup({ App, props }) { render(<App {...props} />); } });
    });
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Target QA' }));
    await user.selectOptions(screen.getByRole('combobox'), 'admin');
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Draft yang harus dipertahankan');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    await screen.findByText('Konfirmasi perubahan tidak tersedia');
    expect(screen.queryByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ })?.value).toBe('Draft yang harus dipertahankan');
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByRole<HTMLSelectElement>('combobox').value).toBe('admin');
    const submit = screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan peran' });
    await waitFor(() => { expect(submit.disabled).toBe(true); });
    await waitFor(() => { expect(document.activeElement).toBe(screen.getByRole('alert')); });
    await user.click(submit);
    expect(request).toHaveBeenCalledTimes(1);
    expect(request.mock.calls[0][0].method).toBe('post');
    expect(screen.queryByText(/Peran berhasil/)).toBeNull();
});
