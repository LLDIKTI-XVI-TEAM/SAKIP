import type { ReactNode } from 'react';
import { act, cleanup, render, screen, waitFor, within } from '@testing-library/react';
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
const target = { id: 'target', nama: 'Ayu', email: 'ayu@example.test', status: 'nonaktif' as const };
const permission = { id: 'permission', kode: 'pengukuran:update', keterangan: null, butuh_scope: 'unit' as const };
const unit = { id: 'unit', nama: 'Unit A', status: 'nonaktif' as const };
const deny: DenyRow = { id: 'deny-original', user: target, permission: { ...permission, aktif: false }, unit, alasan: 'Alasan awal', ditetapkan_oleh: { id: 'operator', nama: 'Operator' }, created_at: '2026-01-01T00:00:00Z' };
const props: DenyIndexProps = { denies: [deny], pagination: { links: [], from: 1, to: 1, total: 1 }, filters: { q: '' }, can: { manageDeny: true } };
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
    await user.click(screen.getByRole('button', { name: 'Tambah pembatasan' }));
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Pengguna' }), 'target');
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Izin' }), 'permission');
    await user.type(screen.getByRole('textbox', { name: /Alasan pembatasan/ }), 'Evaluasi akses');
    return user;
}

it('mengirim scope unit lalu membersihkan unit saat global, mempertahankan input dan fokus validasi', async () => {
    render(<DenyIndex {...props} />);
    const user = await fillCreate();
    expect(within(screen.getByRole('dialog')).getByRole('heading', { name: 'Tambah pembatasan' })).toBeTruthy();
    expect(screen.getByRole('dialog').textContent).not.toMatch(/deny/i);
    expect(screen.queryByText(/Anda membatasi izin akun sendiri/)).toBeNull();
    await user.click(screen.getByRole('radio', { name: 'Unit tertentu' }));
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan pembatasan' }).disabled).toBe(true);
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Unit' }), 'unit');
    await user.click(screen.getByRole('button', { name: 'Simpan pembatasan' }));
    expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/akses/deny', { user_id: 'target', permission_id: 'permission', unit_id: 'unit', alasan: 'Evaluasi akses' }]);
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ alasan: 'Jelaskan alasan.' }); });
    expect(document.activeElement).toBe(screen.getByRole('textbox', { name: /Alasan pembatasan/ }));
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan pembatasan/ }).value).toBe('Evaluasi akses');
    await user.click(screen.getByRole('radio', { name: 'Global' }));
    await user.click(screen.getByRole('button', { name: 'Simpan pembatasan' }));
    expect(vi.mocked(router.post).mock.calls[1][1]).toEqual({ user_id: 'target', permission_id: 'permission', unit_id: null, alasan: 'Evaluasi akses' });
});

it('mengikat revoke ke UUID lama, menahan pending/dismiss, serta meminta reload ketika stale', async () => {
    render(<DenyIndex {...props} />);
    const user = userEvent.setup();
    const trigger = screen.getByRole('button', { name: 'Cabut pembatasan Ayu pengukuran:update' });
    expect(trigger.closest('td')?.querySelector('[role="tooltip"]')?.textContent).toBe('Cabut pembatasan');
    await user.click(trigger);
    expect(within(screen.getByRole('dialog')).getByRole('heading', { name: 'Cabut pembatasan' })).toBeTruthy();
    expect(screen.getByRole('textbox', { name: /Alasan pencabutan pembatasan/ })).toBeTruthy();
    expect(screen.getByRole('dialog').textContent).not.toMatch(/deny/i);
    expect(screen.getByText(/Mencabut pembatasan tidak otomatis memberikan izin/)).toBeTruthy();
    await user.type(screen.getByRole('textbox', { name: /Alasan pencabutan/ }), 'Evaluasi selesai');
    await user.click(screen.getByRole('button', { name: 'Konfirmasi cabut pembatasan' }));
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
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Konfirmasi cabut pembatasan' }).disabled).toBe(true);
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
    await user.click(screen.getByRole('button', { name: 'Simpan pembatasan' }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    const response: Page = { component: 'Access/DenyIndex', props: { errors: {} }, url: '/akses/deny', version: null, rescuedProps: [], flash: {}, rememberedState: {} };
    await act(async () => { options?.onSuccess?.(response); });
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByRole('alert').textContent).toContain('belum diketahui');
    await act(async () => { options?.onNetworkError?.(new Error('offline')); });
    expect(screen.getByRole('alert').textContent).toContain('Koneksi terputus');
    for (const status of [403, 500]) {
        await act(async () => { options?.onHttpException?.({ status, data: '', headers: {} }); });
        expect(screen.getByRole('alert').textContent).toContain(status === 403 ? 'Izin tindakan ditolak' : 'belum dapat dipastikan');
    }
    await user.click(screen.getByRole('button', { name: 'Simpan pembatasan' }));
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
    await user.click(screen.getByRole('button', { name: 'Tambah pembatasan' }));
    const search = screen.getByRole('searchbox', { name: 'Cari pengguna' });
    await user.type(search, 'lama'); await user.click(screen.getByRole('button', { name: 'Cari pengguna' }));
    await user.clear(search); await user.type(search, 'baru'); await user.click(screen.getByRole('button', { name: 'Cari pengguna' }));
    await waitFor(() => expect(screen.getByRole('option', { name: /Ayu/ })).toBeTruthy());
    await act(async () => { finishOld?.(new Response(JSON.stringify({ items: [{ ...target, nama: 'Respons Lama' }], page: 1, hasMore: false }))); });
    expect(screen.queryByRole('option', { name: /Respons Lama/ })).toBeNull();
    await user.clear(search); await user.type(search, 'tolak'); await user.click(screen.getByRole('button', { name: 'Cari pengguna' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Izin pembacaan ditolak');
    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'Pengguna' }).disabled).toBe(true);
});

it('toast hanya mengikuti flash server dan receipt tanpa izin tidak menawarkan pengelolaan', async () => {
    currentPage.flash = { denyStatus: 'revoked' };
    const view = render(<DenyIndex {...props} />);
    expect(screen.getByRole('status').textContent).toContain('Pembatasan berhasil dicabut');
    await userEvent.setup().click(screen.getByRole('button', { name: 'Tutup notifikasi' }));
    expect(screen.queryByRole('status')).toBeNull();
    view.unmount();
    const receipt = render(<DenyResult status="created" canReturn={false} />);
    expect(screen.getByRole('status').textContent).toContain('Pembatasan berhasil ditambahkan');
    expect(document.body.textContent).not.toMatch(/deny/i);
    expect(screen.queryByRole('link')).toBeNull();
    expect(screen.getByText(/pengelola lain/)).toBeTruthy();
    receipt.rerender(<DenyResult status={null} canReturn={false} />);
    expect(screen.getByRole('status').textContent).toContain('Tidak ada hasil');
});

it.each([401, 419])('lookup %s mempertahankan pilihan dan menghentikan pembacaan serta mutation', async (status) => {
    render(<DenyIndex {...props} />);
    const user = await fillCreate();
    vi.mocked(fetch).mockResolvedValue(new Response('{}', { status }));
    await user.click(screen.getByRole('button', { name: 'Cari pengguna' }));
    await screen.findByRole(status === 401 ? 'link' : 'button', { name: status === 401 ? 'Masuk ulang' : 'Muat ulang halaman' });
    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'Pengguna' }).value).toBe('target');
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan pembatasan/ }).value).toBe('Evaluasi akses');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan pembatasan' }).disabled).toBe(true);
    const count = vi.mocked(fetch).mock.calls.length;
    await user.click(screen.getByRole('button', { name: 'Cari izin' }));
    expect(fetch).toHaveBeenCalledTimes(count);
    expect(router.post).not.toHaveBeenCalled();
});
it.each([false, true])('correlation create/revoke %s dan draft terjaga pada recovery', async (revoke) => {
    render(<DenyIndex {...props} />);
    const user = revoke ? userEvent.setup() : await fillCreate();
    if (revoke) {
        await user.click(screen.getByRole('button', { name: /Cabut pembatasan Ayu/ }));
        await user.type(screen.getByRole('textbox', { name: /Alasan pencabutan/ }), 'Draft pencabutan');
    }
    const label = revoke ? 'Konfirmasi cabut pembatasan' : 'Simpan pembatasan';
    await user.click(screen.getByRole('button', { name: label }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onHttpException?.({ status: 401, headers: {}, data: { recovery: { reason: 'authentication_required', rejected: { method: 'POST', path: revoke ? '/akses/deny/deny-original/cabut' : '/akses/deny', before_action: true } } } }); });
    expect(screen.getByRole('alert').textContent).toContain('ditolak');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: label }).disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: label }));
    expect(router.post).toHaveBeenCalledTimes(1);
});

const alasanPanjang = `Baris pertama alasan.\nBaris kedua ${'sangat panjang '.repeat(60)}`;
const globalDeny: DenyRow = { id: 'deny-global', user: { id: 'budi', nama: 'Budi', email: 'budi@example.test', status: 'aktif' }, permission: { id: 'p2', kode: 'renstra:update', keterangan: null, butuh_scope: 'global', aktif: true }, unit: null, alasan: alasanPanjang, ditetapkan_oleh: { id: 'penetap', nama: 'Penetap Kedua' }, created_at: '2026-02-01T08:30:00Z' };
const links = [{ url: null, label: '&laquo; Previous', active: false }, { url: '/akses/deny?page=1', label: '1', active: true }, { url: '/akses/deny?page=2', label: '2', active: false }, { url: '/akses/deny?page=2', label: 'Next &raquo;', active: false }];

it('judul kartu daftar memuat jumlah dan penjelasan; tabel enam kolom berpola kartu mobile tanpa entri H3', () => {
    render(<DenyIndex {...props} denies={[deny, globalDeny]} pagination={{ links: [], from: 1, to: 2, total: 2 }} />);
    expect(screen.getByRole('heading', { level: 2, name: 'Daftar pembatasan izin' })).toBeTruthy();
    expect(screen.queryByRole('heading', { level: 3 })).toBeNull();
    expect(screen.getByText('2 pembatasan')).toBeTruthy();
    expect(screen.getByText('Pembatasan berlaku meskipun peran atau izin unit mengizinkan.')).toBeTruthy();
    expect(screen.getByRole('search').querySelector('input[type="search"]')?.id).toBe('deny-list-search');
    const table = screen.getByRole('table');
    expect(table.className).toContain('md:table');
    expect(within(table).getAllByRole('columnheader').map((head) => head.textContent)).toEqual(['Pengguna', 'Izin', 'Cakupan', 'Alasan', 'Ditetapkan', 'Aksi']);
    const [, ayu, budi] = within(table).getAllByRole('row');
    expect(ayu.className).toContain('md:table-row');
    for (const text of ['Ayu', 'ayu@example.test', 'Akun nonaktif', 'pengukuran:update', 'Izin nonaktif', 'Unit A', 'Unit nonaktif', 'Alasan awal', 'Operator']) expect(within(ayu).getByText(text)).toBeTruthy();
    expect(within(ayu).queryByText('Global')).toBeNull();
    expect(ayu.querySelector('time')?.getAttribute('dateTime')).toBe('2026-01-01T00:00:00Z');
    expect(within(budi).getByText('Global')).toBeTruthy();
    expect(within(budi).getByText('Penetap Kedua')).toBeTruthy();
    expect(within(budi).queryByText(/nonaktif/)).toBeNull();
    expect(document.body.textContent).not.toMatch(/deny/i);
});

it('alasan panjang terpotong dua baris dan dibuka atau diringkas di baris itu tanpa popup', async () => {
    // jsdom tidak menghitung tata letak: teks lebih dari 200 karakter dianggap melebihi dua baris.
    Object.defineProperty(HTMLElement.prototype, 'scrollHeight', { configurable: true, get(this: HTMLElement) { return (this.textContent ?? '').length > 200 ? 80 : 32; } });
    Object.defineProperty(HTMLElement.prototype, 'clientHeight', { configurable: true, get: () => 32 });
    try {
        render(<DenyIndex {...props} denies={[deny, globalDeny]} />);
        const [, ayu, budi] = within(screen.getByRole('table')).getAllByRole('row');
        expect(within(ayu).queryByRole('button', { name: 'Selengkapnya' })).toBeNull();
        const toggle = within(budi).getByRole('button', { name: 'Selengkapnya' });
        const text = document.getElementById(toggle.getAttribute('aria-controls') ?? '');
        expect(text?.textContent).toBe(alasanPanjang);
        expect(text?.className).toContain('line-clamp-2');
        expect(text?.className).toContain('whitespace-pre-wrap');
        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        const user = userEvent.setup();
        await user.click(toggle);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(toggle.textContent).toBe('Ringkas');
        expect(text?.className).not.toContain('line-clamp-2');
        expect(text?.className).toContain('whitespace-pre-wrap');
        expect(screen.queryByRole('dialog')).toBeNull();
        await user.click(toggle);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        expect(toggle.textContent).toBe('Selengkapnya');
        expect(text?.className).toContain('line-clamp-2');
    } finally {
        Reflect.deleteProperty(HTMLElement.prototype, 'scrollHeight');
        Reflect.deleteProperty(HTMLElement.prototype, 'clientHeight');
    }
});

it('paginasi bersama menampilkan jumlah; baris kosong membedakan pencarian kosong, daftar kosong, dan halaman terlewati', async () => {
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
    const view = render(<DenyIndex {...props} pagination={{ links, from: 1, to: 20, total: 21 }} />);
    const nav = screen.getByRole('navigation', { name: 'Halaman pembatasan izin' });
    expect(nav.textContent).toContain('Menampilkan 1–20 dari 21 data');
    expect(within(nav).getByRole('link', { name: '2' }).getAttribute('href')).toBe('/akses/deny?page=2');
    expect(screen.getByText('21 pembatasan')).toBeTruthy();

    view.rerender(<DenyIndex {...props} denies={[]} filters={{ q: 'Ayu' }} pagination={{ links, from: null, to: null, total: 21 }} />);
    expect(screen.getByText('Tidak ada pembatasan di halaman ini.')).toBeTruthy();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Ke halaman pertama' }));
    expect(router.get).toHaveBeenCalledWith('/akses/deny', { q: 'Ayu' });

    view.rerender(<DenyIndex {...props} denies={[]} filters={{ q: 'zzz' }} pagination={{ links: [], from: null, to: null, total: 0 }} />);
    expect(screen.getByText('Tidak ada pembatasan izin yang sesuai')).toBeTruthy();
    expect(screen.queryByRole('navigation', { name: 'Halaman pembatasan izin' })).toBeNull();
    expect(screen.getByText('0 pembatasan')).toBeTruthy();

    view.rerender(<DenyIndex {...props} denies={[]} pagination={{ links: [], from: null, to: null, total: 0 }} />);
    expect(screen.getByText('Belum ada pembatasan izin')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Ke halaman pertama' })).toBeNull();
});
