import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import GrantIndex from '@/Pages/Akses/GrantIndex';

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

const currentPage = vi.hoisted(() => ({ props: { auth: { user: { id: 'admin-1' } }, errors: {} }, flash: {} }));

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => currentPage,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, title, breadcrumbs }: { children: ReactNode; title: string; breadcrumbs?: { label: string }[] }) => <main>
        <h1>{title}</h1>
        <nav aria-label="Breadcrumb">{breadcrumbs?.map((item) => item.label).join(' / ')}</nav>
        {children}
    </main>,
}));

const keterangan = 'Membuat/mengisi pengukuran capaian indikator pada unit tertentu';
const grant = (override: Record<string, unknown> = {}) => ({
    id: 'grant-1',
    user_id: 'user-2',
    user_name: 'Pegawai Test',
    user_email: 'pegawai@example.test',
    user_roles: ['Pegawai'],
    permission_id: 'perm-1',
    permission_kode: 'pengukuran:create',
    permission_keterangan: keterangan,
    unit_id: 'unit-1',
    unit_nama: 'Bagian Akademik',
    alasan: 'Penugasan pengisian triwulan',
    diberikan_oleh_nama: 'Admin SAKIP',
    created_at: '2026-03-20T10:00:00Z',
    tidak_berlaku: [] as string[],
    ...override,
});
type Props = Parameters<typeof GrantIndex>[0];
const props = (override: Partial<Props> = {}): Props => ({
    grants: { data: [grant()], links: [], from: 1, to: 1, total: 1 },
    filters: { search: '', unit_id: 'all' },
    units: [{ id: 'unit-1', nama: 'Bagian Akademik' }],
    unitPermissions: [{ id: 'perm-1', kode: 'pengukuran:create', keterangan }],
    can: { create_grant: true, revoke_grant: true },
    ...override,
});
const tokenOnly = (html: string) => {
    expect(html).not.toMatch(/#[0-9a-f]{6}\b/i);
    expect(html).not.toMatch(/text-\[(9|10|11)px\]/);
    expect(html).not.toMatch(/\b(?:bg|text|border|ring)-(?:slate|gray|blue|red|rose|amber)-\d{2,3}\b/);
};

beforeEach(() => {
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({
        items: [{ id: 'user-2', nama: 'Pegawai Test', email: 'pegawai@example.test', status: 'aktif', roles: ['Pegawai'] }],
        page: 1,
        hasMore: false,
    }), { status: 200 })));
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

async function bukaDanIsiFormBeri(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('button', { name: 'Beri izin unit' }));
    await user.type(await screen.findByPlaceholderText(/Cari nama atau email pengguna/i), 'Pegawai');
    await user.click(await screen.findByRole('option', { name: /Pegawai Test/i }));
    await user.selectOptions(screen.getByLabelText(/^Izin/), 'perm-1');
    await user.selectOptions(screen.getByLabelText(/^Unit/), 'unit-1');
    await user.type(screen.getByLabelText(/^Alasan pemberian/), 'Penugasan khusus input data triwulan');
    await user.click(screen.getByRole('button', { name: 'Simpan' }));
    return vi.mocked(router.post).mock.calls[0][2];
}

describe('Halaman Izin Unit', () => {
    it('memakai satu nama halaman pada judul dan breadcrumb', () => {
        render(<GrantIndex {...props()} />);
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Izin Unit');
        expect(screen.getByRole('navigation', { name: 'Breadcrumb' }).textContent).toBe('Izin Unit');
    });

    it('menampilkan penerima, izin berbahasa Indonesia, unit, status berlaku dari server, dan pemberian', () => {
        render(<GrantIndex {...props({ grants: { data: [
            grant(),
            grant({ id: 'grant-2', user_name: 'Tanpa Peran', user_roles: [], unit_nama: 'Unit Lama', tidak_berlaku: ['Unit nonaktif', 'Akun nonaktif'] }),
        ], links: [], from: 1, to: 2, total: 2 } })} />);
        const [, berlaku, tidakBerlaku] = screen.getAllByRole('row');
        expect(within(berlaku).getByText('Pegawai Test')).toBeTruthy();
        expect(within(berlaku).getByText('Pegawai')).toBeTruthy();
        expect(within(berlaku).getByText(keterangan)).toBeTruthy();
        expect(within(berlaku).getByText('Bagian Akademik')).toBeTruthy();
        expect(within(berlaku).getByText('Berlaku')).toBeTruthy();
        expect(within(berlaku).getByText('Penugasan pengisian triwulan')).toBeTruthy();
        expect(within(berlaku).getByText(/Admin SAKIP/)).toBeTruthy();
        expect(within(tidakBerlaku).getByText('Belum memiliki peran')).toBeTruthy();
        expect(within(tidakBerlaku).getByText('Tidak berlaku')).toBeTruthy();
        expect(within(tidakBerlaku).getByText('Unit nonaktif · Akun nonaktif')).toBeTruthy();
        expect(screen.queryByText('pengukuran:create')).toBeNull();
        expect(screen.queryByText('Semua Unit')).toBeNull();
    });

    it('tabel memakai grid HP tanpa scroll horizontal, dan halaman beserta form hanya memakai token design system', async () => {
        const user = userEvent.setup();
        const { container } = render(<GrantIndex {...props()} />);
        const table = screen.getByRole('table');
        expect(table.className).toContain('block');
        expect(table.className).toContain('md:table');
        expect(table.parentElement?.className).not.toContain('overflow-x-auto');
        tokenOnly(container.innerHTML);

        await bukaDanIsiFormBeri(user);
        tokenOnly(document.body.innerHTML);
    });

    it('filter berlabel dan menyaring unit di server', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        expect(screen.getByLabelText('Cari izin unit')).toBeTruthy();
        await user.selectOptions(screen.getByLabelText('Filter unit'), 'unit-1');
        expect(router.get).toHaveBeenCalledWith('/akses/grant', { search: undefined, unit_id: 'unit-1' }, expect.objectContaining({ preserveState: true }));
    });

    it('pesan kosong membedakan filter aktif dan belum ada data', () => {
        const kosong = { data: [], links: [], from: null, to: null, total: 0 };
        const { unmount } = render(<GrantIndex {...props({ grants: kosong, filters: { search: 'zzz', unit_id: 'all' } })} />);
        expect(screen.getByText('Tidak ada izin unit yang sesuai')).toBeTruthy();
        unmount();
        render(<GrantIndex {...props({ grants: kosong })} />);
        expect(screen.getByText('Belum ada izin unit')).toBeTruthy();
    });

    it('aksi mengikuti capability server', () => {
        const { unmount } = render(<GrantIndex {...props()} />);
        expect(screen.getByRole('button', { name: 'Cabut izin unit Pegawai Test' })).toBeTruthy();
        expect(screen.getByRole('columnheader', { name: 'Aksi' })).toBeTruthy();
        unmount();
        render(<GrantIndex {...props({ can: { create_grant: false, revoke_grant: false } })} />);
        expect(screen.queryByRole('button', { name: 'Beri izin unit' })).toBeNull();
        expect(screen.queryByRole('button', { name: /Cabut izin unit/ })).toBeNull();
        expect(screen.queryByRole('columnheader', { name: 'Aksi' })).toBeNull();
        expect(screen.queryByText('Tidak dapat dicabut')).toBeNull();
    });

    it('paginasi memakai komponen bersama', () => {
        render(<GrantIndex {...props({ grants: { data: [grant()], links: [
            { url: null, label: '&laquo; Previous', active: false },
            { url: '/akses/grant?page=1', label: '1', active: true },
            { url: '/akses/grant?page=2', label: '2', active: false },
            { url: '/akses/grant?page=2', label: 'Next &raquo;', active: false },
        ], from: 1, to: 15, total: 20 } })} />);
        const nav = screen.getByRole('navigation', { name: 'Halaman izin unit' });
        expect(within(nav).getByRole('link', { name: 'Berikutnya ›' }).getAttribute('href')).toBe('/akses/grant?page=2');
    });

    it('galat validasi tampil sekali di field, bukan juga di banner atas', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        const options = await bukaDanIsiFormBeri(user);
        const pesan = 'Alasan pemberian izin minimal 5 karakter.';
        await act(async () => { options?.onError?.({ alasan: pesan }); });
        expect(screen.getAllByText(pesan)).toHaveLength(1);
        expect(screen.getByLabelText(/^Alasan pemberian/).getAttribute('aria-invalid')).toBe('true');
    });

    it('koneksi terputus menyatakan hasil belum diketahui tanpa menandai field', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        const options = await bukaDanIsiFormBeri(user);
        let handled: unknown;
        await act(async () => { handled = options?.onNetworkError?.(new Error('offline') as never); });
        expect(handled).toBe(false);
        expect(within(screen.getByRole('dialog')).getByRole('alert').textContent).toContain('Hasil pemberian izin belum diketahui');
        expect(screen.getByLabelText(/^Alasan pemberian/).getAttribute('aria-invalid')).not.toBe('true');
    });
});
