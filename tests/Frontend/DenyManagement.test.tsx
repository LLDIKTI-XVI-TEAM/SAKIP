import type { ReactNode } from 'react';
import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import type { Page, PendingVisit } from '@inertiajs/core';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import DenyIndex from '@/Pages/Access/DenyIndex';
import DenyResult from '@/Pages/Access/DenyResult';
import type { DenyIndexProps, DenyRow } from '@/types/deny';

const currentPage = vi.hoisted(() => ({ props: { auth: { user: { id: 'operator' } } }, flash: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', async (original) => ({ ...await original<typeof import('@inertiajs/react')>(), Head: () => null, usePage: () => currentPage }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
const target = { id: 'target', nama: 'Ayu', email: 'ayu@example.test', is_active: false };
const permission = { id: 'permission', kode: 'pengukuran:update', keterangan: null, butuh_scope: 'unit' as const };
const unit = { id: 'unit', nama: 'Unit A', status: 'nonaktif' as const };
const deny: DenyRow = { id: 'deny-original', user: target, permission: { ...permission, aktif: false }, unit, alasan: 'Alasan awal', ditetapkan_oleh: { id: 'operator', nama: 'Operator' }, created_at: '2026-01-01T00:00:00Z' };
const props: DenyIndexProps = { denies: [deny], pagination: { current_page: 1, prev_page_url: null, next_page_url: null }, filters: { q: '' }, can: { manageDeny: true } };
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
    currentPage.props.auth.user.id = 'operator'; currentPage.flash = {};
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('fetch', vi.fn(async (url: string) => new Response(JSON.stringify({ items: url.includes('pengguna') ? [target] : url.includes('izin') ? [permission] : [unit], page: 1, hasMore: false }), { status: 200 })));
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

async function fillCreate() {
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'Tambah deny' }));
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Pengguna' }), 'target');
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Izin' }), 'permission');
    await user.type(screen.getByRole('textbox', { name: /Alasan pembatasan/ }), 'Evaluasi akses');
    return user;
}

it('mengirim scope unit lalu membersihkan unit saat global, mempertahankan input dan fokus validasi', async () => {
    render(<DenyIndex {...props} />);
    const user = await fillCreate();
    expect(screen.queryByText(/Anda membatasi izin akun sendiri/)).toBeNull();
    await user.click(screen.getByRole('radio', { name: 'Unit tertentu' }));
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan deny' }).disabled).toBe(true);
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Unit' }), 'unit');
    await user.click(screen.getByRole('button', { name: 'Simpan deny' }));
    expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/akses/deny', { user_id: 'target', permission_id: 'permission', unit_id: 'unit', alasan: 'Evaluasi akses' }]);
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ alasan: 'Jelaskan alasan.' }); });
    expect(document.activeElement).toBe(screen.getByRole('textbox', { name: /Alasan pembatasan/ }));
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan pembatasan/ }).value).toBe('Evaluasi akses');
    await user.click(screen.getByRole('radio', { name: 'Global' }));
    await user.click(screen.getByRole('button', { name: 'Simpan deny' }));
    expect(vi.mocked(router.post).mock.calls[1][1]).toEqual({ user_id: 'target', permission_id: 'permission', unit_id: null, alasan: 'Evaluasi akses' });
});

it('mengikat revoke ke UUID lama, menahan pending/dismiss, serta meminta reload ketika stale', async () => {
    render(<DenyIndex {...props} />);
    const user = userEvent.setup();
    const trigger = screen.getByRole('button', { name: /Cabut deny Ayu/ });
    await user.click(trigger);
    expect(screen.getByText(/Mencabut deny tidak otomatis memberikan izin/)).toBeTruthy();
    await user.type(screen.getByRole('textbox', { name: /Alasan pencabutan/ }), 'Evaluasi selesai');
    await user.click(screen.getByRole('button', { name: 'Konfirmasi cabut deny' }));
    expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/akses/deny/deny-original/cabut', { alasan: 'Evaluasi selesai' }]);
    const options = vi.mocked(router.post).mock.calls[0][2];
    const visit: PendingVisit = { id: 'test', url: new URL('http://localhost/akses/deny'), method: 'post', data: {}, completed: false, cancelled: false, interrupted: false, replace: false, preserveScroll: true, preserveState: true, only: [], except: [], headers: {}, errorBag: null, forceFormData: false, queryStringArrayFormat: 'brackets', async: false, showProgress: true, prefetch: false, fresh: false, reset: [], preserveUrl: false, preserveErrors: false, invalidateCacheTags: [], viewTransition: false, component: null, pageProps: null, cached: false };
    await act(async () => { options?.onStart?.(visit); });
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Batal' }).disabled).toBe(true);
    const cancel = new Event('cancel', { cancelable: true }); screen.getByRole('dialog').dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(true);
    await act(async () => { options?.onError?.({ deny_id: 'Deny telah dicabut. Muat ulang data.' }); });
    await act(async () => { options?.onFinish?.({ ...visit, completed: true, onCancelToken: vi.fn(), onBefore: vi.fn(), onBeforeUpdate: vi.fn(), onStart: vi.fn(), onProgress: vi.fn(), onFinish: vi.fn(), onCancel: vi.fn(), onSuccess: vi.fn(), onError: vi.fn(), onHttpException: vi.fn(), onNetworkError: vi.fn(), onFlash: vi.fn(), onPrefetched: vi.fn(), onPrefetching: vi.fn() }); });
    expect(screen.getByRole('alert').textContent).toContain('Deny telah dicabut');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Konfirmasi cabut deny' }).disabled).toBe(true);
    expect(screen.getByRole('button', { name: 'Muat ulang daftar' })).toBeTruthy();
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan pencabutan/ }).value).toBe('Evaluasi selesai');
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    expect(document.activeElement).toBe(trigger);
});

it('menjaga form saat respons belum pasti dan tidak menganggap callback sukses tanpa receipt sebagai sukses', async () => {
    currentPage.props.auth.user.id = 'target';
    render(<DenyIndex {...props} />);
    const user = await fillCreate();
    expect(screen.getByText(/Anda membatasi izin akun sendiri/)).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Simpan deny' }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    const response: Page = { component: 'Access/DenyIndex', props: { errors: {} }, url: '/akses/deny', version: null, rescuedProps: [], flash: {}, rememberedState: {} };
    await act(async () => { options?.onSuccess?.(response); });
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByRole('alert').textContent).toContain('belum diketahui');
    await act(async () => { options?.onNetworkError?.(new Error('offline')); });
    expect(screen.getByRole('alert').textContent).toContain('Koneksi terputus');
    for (const status of [401, 403, 419]) {
        await act(async () => { options?.onHttpException?.({ status, data: '', headers: {} }); });
        expect(screen.getByRole('alert').textContent).toContain('Sesi atau izin');
    }
    await user.click(screen.getByRole('button', { name: 'Simpan deny' }));
    expect(vi.mocked(router.post).mock.calls).toHaveLength(1);
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Batal' }).disabled).toBe(true);
    const cancel = new Event('cancel', { cancelable: true }); screen.getByRole('dialog').dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(true);
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan pembatasan/ }).value).toBe('Evaluasi akses');
});

it('lookup mengabaikan respons lama dan menampilkan kegagalan izin sebagai error, bukan opsi kosong', async () => {
    let finishOld: ((response: Response) => void) | undefined;
    vi.mocked(fetch).mockImplementation(async (url) => {
        if (String(url).includes('q=lama')) return new Promise<Response>((resolve) => { finishOld = resolve; });
        if (String(url).includes('q=tolak')) return new Response('', { status: 403 });
        return new Response(JSON.stringify({ items: String(url).includes('pengguna') ? [target] : [permission], page: 1, hasMore: false }));
    });
    render(<DenyIndex {...props} />);
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'Tambah deny' }));
    const search = screen.getByRole('searchbox', { name: 'Cari pengguna' });
    await user.type(search, 'lama'); await user.click(screen.getByRole('button', { name: 'Cari pengguna' }));
    await user.clear(search); await user.type(search, 'baru'); await user.click(screen.getByRole('button', { name: 'Cari pengguna' }));
    await waitFor(() => expect(screen.getByRole('option', { name: /Ayu/ })).toBeTruthy());
    await act(async () => { finishOld?.(new Response(JSON.stringify({ items: [{ ...target, nama: 'Respons Lama' }], page: 1, hasMore: false }))); });
    expect(screen.queryByRole('option', { name: /Respons Lama/ })).toBeNull();
    await user.clear(search); await user.type(search, 'tolak'); await user.click(screen.getByRole('button', { name: 'Cari pengguna' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Sesi atau izin');
    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'Pengguna' }).disabled).toBe(true);
});

it('toast hanya mengikuti flash server dan receipt tanpa izin tidak menawarkan pengelolaan', async () => {
    currentPage.flash = { denyStatus: 'revoked' };
    const view = render(<DenyIndex {...props} />);
    expect(screen.getByRole('status').textContent).toContain('Deny berhasil dicabut');
    await userEvent.setup().click(screen.getByRole('button', { name: 'Tutup notifikasi' }));
    expect(screen.queryByRole('status')).toBeNull();
    view.unmount();
    const receipt = render(<DenyResult status="created" canReturn={false} />);
    expect(screen.getByRole('status').textContent).toContain('Deny berhasil ditambahkan');
    expect(screen.queryByRole('link')).toBeNull();
    expect(screen.getByText(/pengelola lain/)).toBeTruthy();
    receipt.rerender(<DenyResult status={null} canReturn={false} />);
    expect(screen.getByRole('status').textContent).toContain('Tidak ada hasil');
});
