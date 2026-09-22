import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import type { Page, PendingVisit } from '@inertiajs/core';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import RoleAssignmentIndex from '@/Pages/Access/RoleAssignmentIndex';
import RoleAssignmentResult from '@/Pages/Access/RoleAssignmentResult';

const currentPage = vi.hoisted(() => ({ props: { auth: { user: { id: 'operator', role: 'pic' } } }, flash: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', async (original) => ({ ...await original<typeof import('@inertiajs/react')>(), Head: () => null, usePage: () => currentPage }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
const roles = [{ id: 'pic-id', kode: 'pic', nama: 'PIC' }, { id: 'pegawai-id', kode: 'pegawai', nama: 'Pegawai' }];
const token = { id: 'pivot-id', role_id: 'pegawai-id', audit_id: 'audit-id' };
const users = { data: [
    { id: 'user-a', nama: 'Ayu', email: 'ayu@example.test', is_active: true, current_role: { ...roles[1], aktif: true }, assignment: token },
    { id: 'user-b', nama: 'Budi', email: 'budi@example.test', is_active: false, current_role: null, assignment: null },
], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null };
const methods = ['showModal', 'close'] as const;
const originals = methods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => methods.forEach((name, index) => {
    const descriptor = originals[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));
beforeEach(() => {
    currentPage.props.auth.user.id = 'operator';
    currentPage.flash = {};
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
const page = (q = '') => render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q }} can={{ assignRole: true }} />);

it('mengirim single role dan snapshot, menjaga input/error, lalu mereset saat ganti target', async () => {
    const user = userEvent.setup();
    page('ayu@example.test');
    const trigger = screen.getByRole('button', { name: 'Ubah peran Ayu' });
    await user.click(trigger);
    expect(screen.queryByText(/Anda sedang mengubah peran akun sendiri/)).toBeNull();
    const select = screen.getByRole<HTMLSelectElement>('combobox');
    expect(select.multiple).toBe(false);
    await user.selectOptions(select, 'pic-id');
    const reason = screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ });
    await user.type(reason, 'Penugasan disetujui');
    await user.tab();
    await user.tab();
    await user.keyboard('{Enter}');
    expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/akses/peran/user-a', { role_id: 'pic-id', alasan: 'Penugasan disetujui', expected_assignment: token }]);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onError?.({ alasan: 'Jelaskan alasan.' }); });
    expect(document.activeElement).toBe(reason);
    expect(reason.value).toBe('Penugasan disetujui');
    expect(screen.getByRole<HTMLInputElement>('searchbox').value).toBe('ayu@example.test');
    await act(async () => { options?.onError?.({ expected_assignment: 'Peran telah berubah. Muat ulang data.' }); });
    expect(screen.getByRole('alert').textContent).toContain('Peran telah berubah');
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    expect(document.activeElement).toBe(trigger);
    await user.click(screen.getByRole('button', { name: 'Tetapkan peran Budi' }));
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ }).value).toBe('');
    await user.selectOptions(screen.getByRole('combobox'), 'pic-id');
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Penugasan pertama');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    expect(vi.mocked(router.post).mock.calls[1][1]).toEqual({ role_id: 'pic-id', alasan: 'Penugasan pertama', expected_assignment: null });
    const response: Page = { component: 'Access/RoleAssignmentIndex', props: { errors: {} }, url: '/akses/peran', version: null, rescuedProps: [], flash: { roleAssignmentStatus: 'assigned' }, rememberedState: {} };
    await act(async () => { vi.mocked(router.post).mock.calls[1][2]?.onSuccess?.(response); });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByRole<HTMLInputElement>('searchbox').value).toBe('');
});

it('memperingatkan perubahan peran sendiri dan menampilkan toast server yang dapat ditutup', async () => {
    const user = userEvent.setup();
    currentPage.props.auth.user.id = 'user-a';
    const view = page();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    await user.selectOptions(screen.getByRole('combobox'), 'pic-id');
    expect(screen.getByText(/Anda sedang mengubah peran akun sendiri/)).toBeTruthy();
    expect(screen.getByText(/Anda mungkin tidak dapat mengembalikannya sendiri/)).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    currentPage.flash = { roleAssignmentStatus: 'changed' };
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} />);
    expect(screen.getByRole('status').textContent).toContain('Peran berhasil diubah.');
    await user.click(screen.getByRole('button', { name: 'Tutup notifikasi' }));
    expect(screen.queryByRole('status')).toBeNull();
    currentPage.flash = { roleAssignmentStatus: 'changed' };
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} />);
    expect(screen.getByRole('status')).toBeTruthy();
    currentPage.flash = {};
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} />);
    expect(screen.queryByRole('status')).toBeNull();
});

it('menahan submit/dismiss saat pending dan menawarkan muat ulang setelah hasil tidak pasti', async () => {
    const user = userEvent.setup();
    page();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Perubahan uji');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    const visit: PendingVisit = {
        id: 'visit-test', url: new URL('http://localhost/akses/peran/user-a'), method: 'post', data: {},
        completed: false, cancelled: false, interrupted: false, replace: false, preserveScroll: true,
        preserveState: true, only: [], except: [], headers: {}, errorBag: null, forceFormData: false,
        queryStringArrayFormat: 'brackets', async: false, showProgress: true, prefetch: false, fresh: false,
        reset: [], preserveUrl: false, preserveErrors: false, invalidateCacheTags: [], viewTransition: false,
        component: null, pageProps: null, cached: false,
    };
    await act(async () => { options?.onStart?.(visit); });
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Batal' }).disabled).toBe(true);
    expect(screen.getByRole<HTMLSelectElement>('combobox').disabled).toBe(true);
    const cancel = new Event('cancel', { cancelable: true });
    screen.getByRole('dialog').dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(true);
    await act(async () => { options?.onError?.({ alasan: 'Jelaskan alasan penetapan.' }); });
    await act(async () => { options?.onFinish?.({
        ...visit, completed: true,
        onCancelToken: vi.fn(), onBefore: vi.fn(), onBeforeUpdate: vi.fn(), onStart: vi.fn(),
        onProgress: vi.fn(), onFinish: vi.fn(), onCancel: vi.fn(), onSuccess: vi.fn(), onError: vi.fn(),
        onHttpException: vi.fn(), onNetworkError: vi.fn(), onFlash: vi.fn(), onPrefetched: vi.fn(), onPrefetching: vi.fn(),
    }); });
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ }).disabled).toBe(false);
    expect(document.activeElement).toBe(screen.getByRole('textbox', { name: /Alasan/ }));
    await act(async () => { options?.onNetworkError?.(new Error('offline')); });
    expect(screen.getByText(/Koneksi terputus/).textContent).toContain('belum diketahui');
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ }).value).toBe('Perubahan uji');
    await act(async () => { options?.onHttpException?.({ status: 403, data: '', headers: {} }); });
    expect(screen.getByText(/Izin tindakan ditolak/)).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Muat ulang data' })).toBeTruthy();
    expect(vi.mocked(router.post).mock.calls).toHaveLength(1);
});

it.each([
    ['assigned', 'Peran berhasil ditetapkan.'], ['changed', 'Peran berhasil diubah.'],
    ['unchanged', 'Peran tidak berubah.'], [null, 'Tidak ada hasil penetapan peran untuk ditampilkan.'],
] as const)('menampilkan receipt %s secara jujur tanpa akses daftar', (status, message) => {
    render(<RoleAssignmentResult status={status} canReturn={false} />);
    expect(screen.getByText(message)).toBeTruthy();
    expect(screen.queryByRole('link', { name: 'Kembali ke penetapan peran' })).toBeNull();
    expect(screen.getByText(/Menu mengikuti izin peran Anda saat ini/)).toBeTruthy();
});

it('recovery401 mempertahankan alasan, mengonsumsi callback lokal dan mengunci submit', async () => {
    const user = userEvent.setup(); page();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Draft recovery');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { expect(options?.onHttpException?.({ status: 401, headers: {}, data: { recovery: { reason: 'authentication_required', rejected: { method: 'POST', path: '/akses/peran/user-a', before_action: true } } } })).toBe(false); });
    expect(screen.getByRole('link', { name: 'Masuk ulang' })).toBeTruthy();
    expect(screen.getByRole('alert').textContent).toContain('ditolak');
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ }).value).toBe('Draft recovery');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan peran' }).disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    expect(router.post).toHaveBeenCalledTimes(1);
});
