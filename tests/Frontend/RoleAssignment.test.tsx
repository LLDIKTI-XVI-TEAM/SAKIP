import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import type { ActiveVisit, Page, PendingVisit } from '@inertiajs/core';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import RoleAssignmentIndex from '@/Pages/Access/RoleAssignmentIndex';
import RoleAssignmentResult from '@/Pages/Access/RoleAssignmentResult';

const currentPage = vi.hoisted(() => ({ component: 'Access/RoleAssignmentIndex', url: '/akses/peran', props: { auth: { user: { id: 'operator', role: 'pimpinan' } } }, flash: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', async (original) => ({ ...await original<typeof import('@inertiajs/react')>(), Head: () => null, usePage: () => currentPage }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
const roles = [{ id: 'pimpinan-id', kode: 'pimpinan', nama: 'Pimpinan' }, { id: 'pegawai-id', kode: 'pegawai', nama: 'Pegawai' }];
const token = { id: 'pivot-id', role_id: 'pegawai-id', audit_id: 'audit-id' };
const receiptA = '11111111-1111-4111-8111-111111111111';
const receiptB = '22222222-2222-4222-8222-222222222222';
const previewNotice = 'Pengguna ini masih menjadi Penanggung Jawab. Perubahan peran tidak mengakhiri penugasan atau mengubah grant izin.';
const resultNotice = 'Pengguna masih menjadi Penanggung Jawab. Penugasan dan grant izin tidak berubah.';
const unknownTitle = 'Konfirmasi perubahan tidak tersedia';
const response = (flash: Record<string, unknown>, url = `/akses/peran?receipt=${receiptA}`, component = 'Access/RoleAssignmentIndex'): Page => ({ component, props: { errors: {} }, url, version: null, rescuedProps: [], flash, rememberedState: {} });
const users = { data: [
    { id: 'user-a', nama: 'Ayu', email: 'ayu@example.test', status: 'aktif' as const, current_role: { ...roles[1], aktif: true }, assignment: token, has_active_pj: true },
    { id: 'user-b', nama: 'Budi', email: 'budi@example.test', status: 'nonaktif' as const, current_role: null, assignment: null, has_active_pj: false },
], links: [], from: 1, to: 2, total: 2 };
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
    currentPage.component = 'Access/RoleAssignmentIndex';
    currentPage.url = '/akses/peran';
    currentPage.flash = {};
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });
const page = (q = '') => render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q }} can={{ assignRole: true }} confirmationUnavailable={false} />);

it('mengirim single role dan snapshot, menjaga input/error, lalu mereset saat ganti target', async () => {
    const user = userEvent.setup();
    page();
    const trigger = screen.getByRole('button', { name: 'Ubah peran Ayu' });
    await user.click(trigger);
    expect(screen.queryByText(/Anda sedang mengubah peran akun sendiri/)).toBeNull();
    const select = screen.getByRole<HTMLSelectElement>('combobox', { name: /Peran tujuan/ });
    expect(select.multiple).toBe(false);
    await user.selectOptions(select, 'pimpinan-id');
    const reason = screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ });
    await user.type(reason, 'Penugasan disetujui');
    // jsdom tidak menghitung offsetParent sehingga trap Tab Modal tidak dapat diuji; fokus langsung ke tombol footer.
    act(() => { screen.getByRole('button', { name: 'Simpan peran' }).focus(); });
    await user.keyboard('{Enter}');
    expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/akses/peran/user-a', { role_id: 'pimpinan-id', alasan: 'Penugasan disetujui', expected_assignment: token }]);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onError?.({ alasan: 'Jelaskan alasan.' }); });
    expect(document.activeElement).toBe(reason);
    expect(reason.value).toBe('Penugasan disetujui');
    await act(async () => { options?.onError?.({ expected_assignment: 'Peran telah berubah. Muat ulang data.' }); });
    expect(screen.getByRole('alert').textContent).toContain('Peran telah berubah');
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    expect(document.activeElement).toBe(trigger);
    await user.click(screen.getByRole('button', { name: 'Tetapkan peran Budi' }));
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ }).value).toBe('');
    await user.selectOptions(screen.getByRole('combobox', { name: /Peran tujuan/ }), 'pimpinan-id');
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Penugasan pertama');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    expect(vi.mocked(router.post).mock.calls[1][1]).toEqual({ role_id: 'pimpinan-id', alasan: 'Penugasan pertama', expected_assignment: null });
    await act(async () => { vi.mocked(router.post).mock.calls[1][2]?.onSuccess?.(response({ roleAssignmentOutcome: { receipt_id: receiptA, status: 'assigned', has_active_pj: false } })); });
    expect(screen.queryByRole('dialog')).toBeNull();
});

it('memperingatkan perubahan peran sendiri dan menampilkan toast server yang dapat ditutup', async () => {
    const user = userEvent.setup();
    currentPage.props.auth.user.id = 'user-a';
    const view = page();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    await user.selectOptions(screen.getByRole('combobox', { name: /Peran tujuan/ }), 'pimpinan-id');
    expect(screen.getByText(/Anda sedang mengubah peran akun sendiri/)).toBeTruthy();
    expect(screen.getByText(/Anda mungkin tidak dapat mengembalikannya sendiri/)).toBeTruthy();
    expect(screen.getByRole('combobox', { name: /Peran tujuan/ }).getAttribute('aria-describedby')).toContain('self-role-warning');
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    currentPage.url = `/akses/peran?receipt=${receiptA}`;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status: 'changed', has_active_pj: false } };
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.getByRole('status').textContent).toContain('Peran berhasil diubah.');
    await user.click(screen.getByRole('button', { name: 'Tutup notifikasi' }));
    expect(screen.queryByRole('status')).toBeNull();
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status: 'changed', has_active_pj: false } };
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.queryByRole('status')).toBeNull();
    currentPage.url = `/akses/peran?receipt=${receiptB}`;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptB, status: 'changed', has_active_pj: false } };
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.getByRole('status')).toBeTruthy();
    currentPage.flash = {};
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
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
    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: /Peran tujuan/ }).disabled).toBe(true);
    await user.keyboard('{Escape}');
    await user.click(screen.getByRole('button', { name: 'Tutup dialog' }));
    expect(screen.getByRole('dialog')).toBeTruthy();
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

it('menyajikan daftar sebagai tabel di bawah heading bagian H2 dengan badge peran dan status akun', () => {
    page();
    expect(screen.getByRole('heading', { level: 2, name: 'Peran utama pengguna' })).toBeTruthy();
    expect(screen.getByText('Setiap pengguna memiliki paling banyak satu peran utama. Perubahan peran wajib disertai alasan dan tercatat di jejak audit.')).toBeTruthy();
    const table = screen.getByRole('table', { name: 'Daftar pengguna dan peran utama' });
    expect(within(table).getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Pengguna', 'Peran', 'Status akun', 'Aksi']);
    const [ayu, budi] = within(table).getAllByRole('row').slice(1);
    expect(within(ayu).getByText('Pegawai')).toBeTruthy();
    expect(within(ayu).getByText('Aktif')).toBeTruthy();
    expect(within(budi).getByText('Belum memiliki peran')).toBeTruthy();
    expect(within(budi).getByText('Nonaktif')).toBeTruthy();
    expect(screen.queryByRole('heading', { level: 3 })).toBeNull();
});

it('menandai peran nonaktif dan menyembunyikan kolom aksi bila server tidak memberi izin', () => {
    const inactive = { ...users, data: [{ ...users.data[0], current_role: { ...roles[1], aktif: false } }] };
    render(<RoleAssignmentIndex users={inactive} roles={roles} filters={{ q: '' }} can={{ assignRole: false }} confirmationUnavailable={false} />);
    expect(screen.getByText('Peran nonaktif')).toBeTruthy();
    expect(screen.queryByRole('columnheader', { name: 'Aksi' })).toBeNull();
    expect(screen.queryByRole('button', { name: /peran Ayu/ })).toBeNull();
});

it.each([
    ['ayu', 'Tidak ada pengguna yang sesuai', 'Coba kata kunci nama atau email lain.'],
    ['', 'Belum ada pengguna', 'Pengguna tampil di sini setelah login SSO pertama kali.'],
])('menjelaskan daftar kosong sesuai filter "%s"', (q, title, hint) => {
    render(<RoleAssignmentIndex users={{ ...users, data: [], from: null, to: null, total: 0 }} roles={roles} filters={{ q }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.getByText(title)).toBeTruthy();
    expect(screen.getByText(hint)).toBeTruthy();
});

it('menyediakan tombol ke halaman pertama hanya untuk halaman pengguna di luar jangkauan', async () => {
    const empty = { ...users, data: [], from: null, to: null };
    const view = render(<RoleAssignmentIndex users={{ ...empty, total: 0 }} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.getByText('Belum ada pengguna')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Ke halaman pertama' })).toBeNull();
    view.rerender(<RoleAssignmentIndex users={{ ...empty, total: 25 }} roles={roles} filters={{ q: 'ay', user: null }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.getByText('Tidak ada pengguna di halaman ini.')).toBeTruthy();
    expect(screen.queryByText('Tidak ada pengguna yang sesuai')).toBeNull();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Ke halaman pertama' }));
    expect(vi.mocked(router.get).mock.calls[0].slice(0, 2)).toEqual(['/akses/peran', { q: 'ay' }]);
});

it('memakai paginasi bersama untuk daftar lebih dari satu halaman', () => {
    const links = [
        { url: null, label: '&laquo; Previous', active: false }, { url: '/akses/peran?page=1', label: '1', active: true },
        { url: '/akses/peran?page=2', label: '2', active: false }, { url: '/akses/peran?page=2', label: 'Next &raquo;', active: false },
    ];
    render(<RoleAssignmentIndex users={{ ...users, links, from: 1, to: 20, total: 25 }} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    const nav = screen.getByRole('navigation', { name: 'Halaman pengguna' });
    expect(nav.textContent).toContain('Menampilkan 1–20 dari 25 data');
    expect(within(nav).getByRole('link', { name: 'Berikutnya ›' }).getAttribute('href')).toBe('/akses/peran?page=2');
});

it('menonaktifkan daftar dan paginasi selama filter pengguna dimuat agar aksi baris lama tidak dipakai dan pilihan autocomplete tidak dibatalkan', async () => {
    const user = userEvent.setup();
    const links = [
        { url: null, label: '&laquo; Previous', active: false }, { url: '/akses/peran?page=1', label: '1', active: true },
        { url: '/akses/peran?page=2', label: '2', active: false }, { url: '/akses/peran?page=2', label: 'Next &raquo;', active: false },
    ];
    render(<RoleAssignmentIndex users={{ ...users, links, from: 1, to: 20, total: 25 }} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    const nav = screen.getByRole('navigation', { name: 'Halaman pengguna' });
    const rowAction = screen.getByRole('button', { name: 'Ubah peran Ayu' });
    expect(nav.closest('[inert]')).toBeNull();
    expect(rowAction.closest('[inert]')).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    const visit = vi.mocked(router.get).mock.calls[0][2];
    act(() => visit?.onStart?.({} as PendingVisit));
    expect(nav.closest('[inert]')).not.toBeNull();
    expect(rowAction.closest('[inert]')).not.toBeNull();
    act(() => visit?.onFinish?.({} as ActiveVisit));
    expect(nav.closest('[inert]')).toBeNull();
    expect(rowAction.closest('[inert]')).toBeNull();
});

it('memberi tahu dan menawarkan coba lagi bila filter pengguna gagal dimuat, tanpa membuang pilihan', async () => {
    const user = userEvent.setup();
    render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    const failed = vi.mocked(router.get).mock.calls[0][2];
    act(() => failed?.onStart?.({} as PendingVisit));
    // Gangguan jaringan: Inertia memanggil onNetworkError lalu onFinish tanpa onSuccess.
    act(() => { failed?.onNetworkError?.(new Error('offline')); });
    act(() => failed?.onFinish?.({ cancelled: false, interrupted: false } as ActiveVisit));
    expect(screen.getByRole('alert').textContent).toContain('Daftar pengguna belum diperbarui. Periksa koneksi, lalu coba lagi.');
    expect(screen.queryByRole('button', { name: 'Ganti pengguna target' })).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Coba lagi' }));
    expect(vi.mocked(router.get).mock.calls.map((call) => call.slice(0, 2))).toEqual([['/akses/peran', {}], ['/akses/peran', {}]]);
    const retried = vi.mocked(router.get).mock.calls[1][2];
    act(() => retried?.onStart?.({} as PendingVisit));
    expect(screen.queryByRole('alert')).toBeNull();
    act(() => retried?.onSuccess?.({} as Page));
    act(() => retried?.onFinish?.({ cancelled: false, interrupted: false } as ActiveVisit));
    expect(screen.queryByRole('alert')).toBeNull();
});

const failureStatus = { session: 401, denied: 403, server: 500 } as const;
const failFilter = (kind: 'network' | keyof typeof failureStatus, call = 0) => {
    const failed = vi.mocked(router.get).mock.calls[call][2];
    act(() => failed?.onStart?.({} as PendingVisit));
    act(() => {
        if (kind === 'network') failed?.onNetworkError?.(new Error('offline'));
        else failed?.onHttpException?.({ status: failureStatus[kind], data: kind === 'session' ? { message: 'Autentikasi diperlukan.' } : '', headers: {} });
    });
    act(() => failed?.onFinish?.({ cancelled: false, interrupted: false } as ActiveVisit));
};
const optionsWith = (items: unknown[]) => vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(new Response(JSON.stringify({ items, page: 1, hasMore: false }), { status: 200 })));

it.each([
    ['network', 'Daftar pengguna belum diperbarui. Periksa koneksi, lalu coba lagi. Coba lagi'],
    ['denied', 'Akses ke daftar pengguna ditolak. Periksa akses sebelum mencoba kembali. Coba lagi'],
    ['server', 'Daftar pengguna belum diperbarui. Coba lagi'],
] as const)('membedakan pesan gagal filter pengguna (%s)', async (kind, text) => {
    const user = userEvent.setup();
    render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    failFilter(kind);
    expect(screen.getByRole('alert').textContent).toBe(text);
});

it('menahan dialog galat bawaan untuk kegagalan filter selain sesi tanpa menahan dialog sesi', async () => {
    const user = userEvent.setup();
    render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    const options = vi.mocked(router.get).mock.calls[0][2];
    // `false` menghentikan fallback Inertia; pesan di halaman yang menggantikannya.
    expect(options?.onHttpException?.({ status: 403, data: '<html></html>', headers: {} })).toBe(false);
    expect(options?.onHttpException?.({ status: 500, data: '<html></html>', headers: {} })).toBe(false);
    expect(options?.onHttpException?.({ status: 401, data: { message: 'Autentikasi diperlukan.' }, headers: {} })).not.toBe(false);
});

const leaving = (visit: Record<string, unknown> = {}) => ({ url: new URL('http://localhost/dashboard'), method: 'get', prefetch: false, ...visit });
const dispatchVisit = (name: 'start' | 'finish', visit: Record<string, unknown>) =>
    act(() => { document.dispatchEvent(new CustomEvent(`inertia:${name}`, { detail: { visit } })); });
const interruptedFilterOnBudi = async () => {
    window.history.replaceState({}, '', '/akses/peran');
    optionsWith([{ id: 'user-b', nama: 'Budi', email: 'budi@example.test', status: 'nonaktif', roles: [] }]);
    const user = userEvent.setup();
    page();
    await user.type(screen.getByRole('combobox', { name: 'Cari pengguna' }), 'bu');
    await user.click(await screen.findByRole('option', { name: /Budi/ }));
    const filter = vi.mocked(router.get).mock.calls[0][2];
    act(() => filter?.onStart?.({} as PendingVisit));
    // Klik sidebar: Inertia memotong filter yang sedang berjalan lalu memulai navigasi keluar.
    act(() => filter?.onFinish?.({ cancelled: false, interrupted: true } as ActiveVisit));
    dispatchVisit('start', leaving());
};
afterEach(() => { window.history.replaceState({}, '', '/'); });

it('mengirim ulang sekali filter yang dipotong navigasi ke halaman lain bila navigasi itu gagal', async () => {
    await interruptedFilterOnBudi();
    dispatchVisit('finish', leaving({ cancelled: false, interrupted: false }));
    expect(vi.mocked(router.get).mock.calls.map((call) => call.slice(0, 2))).toEqual([['/akses/peran', { user: 'user-b' }], ['/akses/peran', { user: 'user-b' }]]);
    dispatchVisit('finish', leaving({ cancelled: false, interrupted: false }));
    expect(vi.mocked(router.get)).toHaveBeenCalledTimes(2);
});

it.each([
    ['dibatalkan', { cancelled: true }, '/akses/peran'],
    ['berhasil pindah halaman', {}, '/dashboard'],
])('tidak mengirim ulang filter yang dipotong bila navigasi ke halaman lain %s', async (_label, flags, path) => {
    await interruptedFilterOnBudi();
    // Navigasi yang berhasil sudah mengganti URL sebelum event finish.
    window.history.replaceState({}, '', path);
    dispatchVisit('finish', leaving({ cancelled: false, interrupted: false, ...flags }));
    expect(vi.mocked(router.get)).toHaveBeenCalledTimes(1);
});

it('menyerahkan sesi kedaluwarsa saat memfilter ke dialog sesi tanpa pesan gagal di halaman', async () => {
    const user = userEvent.setup();
    render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    failFilter('session');
    expect(screen.queryByRole('alert')).toBeNull();
});

it('memindahkan fokus ke judul daftar saat Coba lagi filter ditekan', async () => {
    const user = userEvent.setup();
    render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    failFilter('network');
    await user.click(screen.getByRole('button', { name: 'Coba lagi' }));
    act(() => vi.mocked(router.get).mock.calls[1][2]?.onStart?.({} as PendingVisit));
    expect(document.activeElement).toBe(screen.getByRole('heading', { level: 2, name: 'Peran utama pengguna' }));
});

it('mempertahankan kata cari baru saat respons filter sebelumnya baru tiba', async () => {
    optionsWith([]);
    const user = userEvent.setup();
    const view = render(<RoleAssignmentIndex users={{ ...users, data: [users.data[0]] }} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    await user.type(screen.getByRole('combobox', { name: 'Cari pengguna' }), 'bud');
    // Inertia memasang props baru lebih dulu, lalu memanggil onSuccess dan onFinish.
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '', user: null }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    const visit = vi.mocked(router.get).mock.calls[0][2];
    act(() => visit?.onSuccess?.({} as Page));
    act(() => visit?.onFinish?.({ cancelled: false, interrupted: false } as ActiveVisit));
    expect(screen.getByRole<HTMLInputElement>('combobox', { name: 'Cari pengguna' }).value).toBe('bud');
});

it('membersihkan pesan gagal filter dan pilihan lama setelah peran berhasil disimpan', async () => {
    optionsWith([{ id: 'user-b', nama: 'Budi', email: 'budi@example.test', status: 'nonaktif', roles: [] }]);
    const user = userEvent.setup();
    page();
    await user.type(screen.getByRole('combobox', { name: 'Cari pengguna' }), 'bu');
    await user.click(await screen.findByRole('option', { name: /Budi/ }));
    failFilter('network');
    expect(screen.getByRole('alert')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Ganti pengguna target' })).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    await user.selectOptions(screen.getByRole('combobox', { name: /Peran tujuan/ }), 'pimpinan-id');
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Penugasan disetujui');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onSuccess?.(response({ roleAssignmentOutcome: { receipt_id: receiptA, status: 'changed', has_active_pj: false } })); });
    // Daftar dari server tidak memakai filter Budi; pesan dan chip lama tidak boleh tertinggal.
    expect(screen.queryByRole('alert')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Ganti pengguna target' })).toBeNull();
});

it('memecah email panjang di keterangan dialog agar tidak terpotong di layar sempit', async () => {
    const user = userEvent.setup();
    page();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    expect(within(screen.getByRole('dialog')).getByText('ayu@example.test').className).toContain('break-all');
});

it('menyaring tabel lewat autocomplete pengguna seperti field Penanggung Jawab', async () => {
    const lookup = vi.fn<typeof fetch>().mockResolvedValue(new Response(JSON.stringify({ items: [{ id: 'user-a', nama: 'Ayu', email: 'ayu@example.test', status: 'aktif', roles: ['Pegawai'] }], page: 1, hasMore: false }), { status: 200 }));
    vi.stubGlobal('fetch', lookup);
    const user = userEvent.setup();
    page();
    const box = screen.getByRole('combobox', { name: 'Cari pengguna' });
    await user.type(box, 'a');
    await user.type(box, 'y');
    await user.click(await screen.findByRole('option', { name: /Ayu/ }));
    expect(lookup).toHaveBeenCalledTimes(1);
    expect(String(lookup.mock.calls[0][0])).toBe('/akses/peran/opsi/pengguna?q=ay&page=1');
    expect(vi.mocked(router.get).mock.calls[0].slice(0, 2)).toEqual(['/akses/peran', { user: 'user-a' }]);
    expect(vi.mocked(router.get).mock.calls[0][2]).toMatchObject({ preserveState: true, preserveScroll: true, replace: true });
});

it('menampilkan pengguna terpilih dari filter dan mengembalikan semua pengguna saat diganti', async () => {
    const user = userEvent.setup();
    render(<RoleAssignmentIndex users={{ ...users, data: [users.data[0]] }} roles={roles} filters={{ q: '', user: 'user-a' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.queryByRole('combobox', { name: 'Cari pengguna' })).toBeNull();
    expect(within(screen.getByRole('search')).getByText('ayu@example.test')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Ganti pengguna target' }));
    expect(vi.mocked(router.get).mock.calls[0].slice(0, 2)).toEqual(['/akses/peran', {}]);
});

it('menyediakan jalan kembali ke semua pengguna untuk filter yang bukan pengguna terpilih', () => {
    page('ay');
    expect(screen.getByText(/Menampilkan hasil untuk “ay”/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Tampilkan semua' }).getAttribute('href')).toBe('/akses/peran');
    expect(screen.getByRole('combobox', { name: 'Cari pengguna' })).toBeTruthy();
});

it('menyediakan jalan kembali bila pengguna terpilih tidak lagi ditemukan', () => {
    render(<RoleAssignmentIndex users={{ ...users, data: [], from: null, to: null, total: 0 }} roles={roles} filters={{ q: '', user: 'user-hilang' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.getByText(/Pengguna terpilih tidak ditemukan/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Tampilkan semua' }).getAttribute('href')).toBe('/akses/peran');
    expect(screen.getByText('Tidak ada pengguna yang sesuai')).toBeTruthy();
});

it('menutup dialog dengan Escape saat tidak memproses dan mengembalikan fokus ke pemicu', async () => {
    const user = userEvent.setup();
    page();
    const trigger = screen.getByRole('button', { name: 'Ubah peran Ayu' });
    await user.click(trigger);
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(trigger);
});

it.each([
    ['assigned', 'Peran berhasil ditetapkan.'], ['changed', 'Peran berhasil diubah.'],
    ['unchanged', 'Peran tidak berubah.'],
] as const)('menampilkan receipt %s secara jujur tanpa akses daftar', (status, message) => {
    currentPage.component = 'Access/RoleAssignmentResult';
    currentPage.url = `/akses/peran/hasil?receipt=${receiptA}`;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status, has_active_pj: true } };
    render(<RoleAssignmentResult />);
    expect(screen.getByText(message)).toBeTruthy();
    expect(screen.getByText(resultNotice)).toBeTruthy();
    expect(screen.queryByRole('link', { name: 'Kembali ke daftar pengguna' })).toBeNull();
    expect(screen.getByText(/Akses pengelolaan peran tidak tersedia/)).toBeTruthy();
    expect(screen.getByText('PIMPINAN')).toBeTruthy();
});

it('menampilkan preview PJ tanpa membuatnya prasyarat submit, dan tidak menampilkan preview untuk non-PJ', async () => {
    const user = userEvent.setup(); page();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    expect(screen.getByText(previewNotice)).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan peran' }).disabled).toBe(false);
    expect(screen.queryByRole('checkbox')).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    await user.click(screen.getByRole('button', { name: 'Tetapkan peran Budi' }));
    expect(screen.queryByText(previewNotice)).toBeNull();
});

it('menjelaskan peran lama tanpa menjadikannya pilihan resmi atau memetakan otomatis', async () => {
    const user = userEvent.setup();
    const legacyUsers = { ...users, data: [{ ...users.data[0], current_role: { id: 'legacy-id', kode: 'pic', nama: 'PIC lama', aktif: true } }] };
    render(<RoleAssignmentIndex users={legacyUsers} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    expect(screen.getByText('Peran lama ini perlu ditetapkan ulang ke peran resmi.')).toBeTruthy();
    expect(screen.queryByRole('option', { name: 'PIC lama' })).toBeNull();
    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: /Peran tujuan/ }).value).toBe('');
});

it.each([
    ['/akses/peran', { receipt_id: receiptA, status: 'changed', has_active_pj: true }, 'Access/RoleAssignmentIndex'],
    [`/akses/peran?receipt=${receiptB}`, { receipt_id: receiptA, status: 'changed', has_active_pj: true }, 'Access/RoleAssignmentIndex'],
    [`/akses/peran?receipt=${receiptA}&receipt=${receiptA}`, { receipt_id: receiptA, status: 'changed', has_active_pj: true }, 'Access/RoleAssignmentIndex'],
    ['/akses/peran?receipt=invalid', { receipt_id: 'invalid', status: 'changed', has_active_pj: true }, 'Access/RoleAssignmentIndex'],
    [`/akses/peran?receipt=${receiptA}`, { receipt_id: receiptA, status: 'invalid', has_active_pj: true }, 'Access/RoleAssignmentIndex'],
    [`/akses/peran?receipt=${receiptA}`, { receipt_id: receiptA, status: 'changed', has_active_pj: 'true' }, 'Access/RoleAssignmentIndex'],
    [`/akses/peran?receipt=${receiptA}`, { receipt_id: receiptA, status: 'changed' }, 'Access/RoleAssignmentIndex'],
    [`/akses/peran?receipt=${receiptA}`, null, 'Access/RoleAssignmentIndex'],
    [`/akses/peran?receipt=${receiptA}`, { receipt_id: receiptA, status: 'changed', has_active_pj: true }, 'Access/RolePermissionIndex'],
] as const)('menjaga draft dan mengunci replay ketika respons %s tidak mengonfirmasi operasi', async (url, outcome, component) => {
    const user = userEvent.setup(); page();
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Draft aman');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onSuccess?.(response({ roleAssignmentOutcome: outcome }, url, component)); });
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan/ }).value).toBe('Draft aman');
    expect(screen.getByRole('alert').textContent).toBe(unknownTitle);
    expect(screen.getByText('Periksa peran terbaru pengguna di daftar sebelum mengulangi perubahan.')).toBeTruthy();
    expect(screen.queryByText('Peran berhasil diubah.')).toBeNull();
    expect(screen.queryByText(resultNotice)).toBeNull();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan peran' }).disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    expect(router.post).toHaveBeenCalledTimes(1);
});

it.each([true, false])('memakai snapshot hasil server untuk notice, terpisah dari preview PJ %s', async (preview) => {
    const user = userEvent.setup();
    const updatedUsers = { ...users, data: users.data.map((entry) => ({ ...entry, has_active_pj: preview })) };
    const view = render(<RoleAssignmentIndex users={updatedUsers} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    await user.click(screen.getByRole('button', { name: 'Ubah peran Ayu' }));
    await user.type(screen.getByRole('textbox', { name: /Alasan/ }), 'Uji snapshot');
    await user.click(screen.getByRole('button', { name: 'Simpan peran' }));
    // URL window masih halaman lama; korelasi harus memakai URL respons.
    expect(window.location.search).toBe('');
    const result = response({ roleAssignmentOutcome: { receipt_id: receiptA, status: 'changed', has_active_pj: !preview } });
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onSuccess?.(result); });
    currentPage.flash = result.flash;
    currentPage.url = result.url;
    view.rerender(<RoleAssignmentIndex users={updatedUsers} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByText('Peran berhasil diubah.')).toBeTruthy();
    expect(Boolean(screen.queryByText(resultNotice))).toBe(!preview);
});

it('menutup toast dan notice PJ secara terpisah per receipt, tanpa timer atau redisplay receipt sama', async () => {
    const user = userEvent.setup();
    currentPage.url = `/akses/peran?receipt=${receiptA}`;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status: 'unchanged', has_active_pj: true } };
    const view = page();
    expect(screen.getByText('Peran tidak berubah.')).toBeTruthy();
    expect(screen.getByText(resultNotice).getAttribute('role')).toBe('status');
    await user.click(screen.getByRole('button', { name: 'Tutup notifikasi' }));
    expect(screen.queryByText('Peran tidak berubah.')).toBeNull();
    expect(screen.getByText(resultNotice)).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Tutup pemberitahuan Penanggung Jawab' }));
    expect(screen.queryByText(resultNotice)).toBeNull();
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status: 'unchanged', has_active_pj: true } };
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.queryByText(resultNotice)).toBeNull();
    currentPage.url = `/akses/peran?receipt=${receiptB}`;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptB, status: 'unchanged', has_active_pj: true } };
    view.rerender(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={false} />);
    expect(screen.getByText(resultNotice)).toBeTruthy();
    expect(screen.getByText('Peran tidak berubah.')).toBeTruthy();
});

it.each(['/akses/peran', `/akses/peran?receipt=${receiptB}`])('tidak menerbitkan toast maupun PJ untuk outcome tanpa korelasi %s', (url) => {
    currentPage.url = url;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status: 'changed', has_active_pj: true } };
    page();
    expect(screen.queryByText('Peran berhasil diubah.')).toBeNull();
    expect(screen.queryByText(resultNotice)).toBeNull();
});

it('memberi tindak lanjut unknown tanpa akses daftar dan tanpa mengklaim hasil', () => {
    currentPage.component = 'Access/RoleAssignmentResult';
    render(<RoleAssignmentResult />);
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(unknownTitle);
    expect(screen.queryByText(/Peran berhasil/)).toBeNull();
    expect(screen.queryByText(resultNotice)).toBeNull();
    expect(screen.getByText('Minta administrator yang masih memiliki akses untuk memeriksa peran terbaru akun ini.')).toBeTruthy();
    expect(screen.queryByRole('link')).toBeNull();
});

it.each(['/akses/peran/hasil', `/akses/peran/hasil?receipt=${receiptB}`])('menolak outcome result yang reference-nya tidak cocok %s', (url) => {
    currentPage.component = 'Access/RoleAssignmentResult';
    currentPage.url = url;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status: 'changed', has_active_pj: true } };
    render(<RoleAssignmentResult />);
    expect(screen.getByText(unknownTitle)).toBeTruthy();
    expect(screen.queryByText('Peran berhasil diubah.')).toBeNull();
    expect(screen.queryByText(resultNotice)).toBeNull();
});

it('mengikat dismissal notice receipt minimal ke operasi berikutnya', async () => {
    const user = userEvent.setup();
    currentPage.component = 'Access/RoleAssignmentResult';
    currentPage.url = `/akses/peran/hasil?receipt=${receiptA}`;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptA, status: 'changed', has_active_pj: true } };
    const view = render(<RoleAssignmentResult />);
    await user.click(screen.getByRole('button', { name: 'Tutup pemberitahuan Penanggung Jawab' }));
    expect(screen.queryByText(resultNotice)).toBeNull();
    currentPage.url = `/akses/peran/hasil?receipt=${receiptB}`;
    currentPage.flash = { roleAssignmentOutcome: { receipt_id: receiptB, status: 'changed', has_active_pj: true } };
    view.rerender(<RoleAssignmentResult />);
    expect(screen.getByText(resultNotice)).toBeTruthy();
});

it.each([true, false])('menampilkan konfirmasi unknown pada kunjungan daftar sesuai prop server %s', (confirmationUnavailable) => {
    render(<RoleAssignmentIndex users={users} roles={roles} filters={{ q: '' }} can={{ assignRole: true }} confirmationUnavailable={confirmationUnavailable} />);
    expect(Boolean(screen.queryByRole('heading', { level: 2, name: unknownTitle }))).toBe(confirmationUnavailable);
    expect(Boolean(screen.queryByText('Periksa peran terbaru pengguna di daftar sebelum mengulangi perubahan.'))).toBe(confirmationUnavailable);
    expect(Boolean(screen.queryByRole('link', { name: 'Kembali ke daftar pengguna' }))).toBe(confirmationUnavailable);
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByRole('button', { name: 'Ubah peran Ayu' })).toBeTruthy();
    expect(screen.queryByText(/Peran berhasil/)).toBeNull();
    expect(screen.queryByText(resultNotice)).toBeNull();
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
