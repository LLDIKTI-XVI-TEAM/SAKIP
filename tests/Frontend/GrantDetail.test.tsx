import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import GrantDetail from '@/Pages/Akses/GrantDetail';

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

const currentPage = vi.hoisted(() => ({ props: { auth: { user: { id: 'admin-1' }, can: { audit: false } }, errors: {} }, flash: {} }));

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

type Props = Parameters<typeof GrantDetail>[0];
const props = (override: Partial<Props> = {}): Props => ({
    kelompok: {
        key: 'user-2:unit-1',
        user_id: 'user-2',
        user_name: 'Pegawai Test',
        user_email: 'pegawai@example.test',
        user_roles: ['Pegawai'],
        unit_id: 'unit-1',
        unit_nama: 'Bagian Akademik',
        tidak_berlaku: [],
        izin: [
            { grant_id: 'grant-1', permission_id: 'perm-1', label: 'Mengisi pengukuran', aktif: true, alasan: 'Penugasan pengukuran triwulan', diberikan_oleh_nama: 'Admin SAKIP', created_at: '2026-10-18T02:00:00Z' },
            { grant_id: 'grant-3', permission_id: 'perm-3', label: 'Membuat kegiatan', aktif: false, alasan: 'Pemberian izin unit tanpa alasan tambahan.', diberikan_oleh_nama: 'Superadmin SAKIP', created_at: '2026-10-17T02:00:00Z' },
        ],
    },
    unitPermissions: [
        { id: 'perm-1', kode: 'pengukuran:create', label: 'Mengisi pengukuran' },
        { id: 'perm-2', kode: 'pengukuran:update', label: 'Mengubah pengukuran' },
    ],
    can: { kelola_grant: true, revoke_grant: true },
    ...override,
});

beforeEach(() => {
    currentPage.props.auth.can.audit = false;
    vi.spyOn(router, 'put').mockImplementation(() => undefined);
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Detail Izin Unit', () => {
    it('mengikuti pola halaman detail: tautan kembali, identitas, dan breadcrumb ke daftar', () => {
        render(<GrantDetail {...props()} />);
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Detail Izin Unit');
        expect(screen.getByRole('navigation', { name: 'Breadcrumb' }).textContent).toBe('Izin Unit / Pegawai Test');
        expect(screen.getByRole('link', { name: /Kembali ke daftar izin unit/ }).getAttribute('href')).toBe('/akses/grant');
        expect(screen.getByRole('heading', { level: 2, name: 'Pegawai Test' })).toBeTruthy();
        expect(screen.getByText('pegawai@example.test')).toBeTruthy();
        expect(screen.getAllByText('Bagian Akademik').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Berlaku').length).toBeGreaterThan(0);
    });

    it('menampilkan rincian setiap izin: status, alasan, pemberi, dan waktu', () => {
        render(<GrantDetail {...props()} />);
        const [, pertama, kedua] = screen.getAllByRole('row');
        expect(within(pertama).getByText('Mengisi pengukuran')).toBeTruthy();
        expect(within(pertama).getByText('Aktif')).toBeTruthy();
        expect(within(pertama).getByText('Penugasan pengukuran triwulan')).toBeTruthy();
        expect(within(pertama).getByText('Admin SAKIP')).toBeTruthy();
        expect(within(pertama).getByRole('time').getAttribute('dateTime')).toBe('2026-10-18T02:00:00Z');
        expect(within(kedua).getByText('Izin nonaktif')).toBeTruthy();
        expect(screen.getByRole('table').className).toContain('md:table');
        expect(screen.queryByRole('link', { name: /Jejak audit/ })).toBeNull();
    });

    it('izin yang terkena deny eksplisit tampil Ditolak, bukan Aktif', () => {
        const awal = props();
        render(<GrantDetail {...props({ kelompok: { ...awal.kelompok, izin: [{ ...awal.kelompok.izin[0], ditolak: true }, awal.kelompok.izin[1]] } })} />);
        const [, pertama, kedua] = screen.getAllByRole('row');
        expect(within(pertama).getByText('Ditolak')).toBeTruthy();
        expect(within(pertama).queryByText('Aktif')).toBeNull();
        expect(within(kedua).getByText('Izin nonaktif')).toBeTruthy();
    });

    it('tautan jejak audit per izin hanya tampil bila server memberi kemampuan baca audit', () => {
        currentPage.props.auth.can.audit = true;
        render(<GrantDetail {...props()} />);
        expect(screen.getByRole('link', { name: 'Jejak audit Mengisi pengukuran' }).getAttribute('href')).toBe('/audit?objek_tipe=user_permission_granted&objek_id=grant-1');
    });

    it('aksi Kelola dan Cabut semua mengikuti capability server dan memakai endpoint kelola', async () => {
        const user = userEvent.setup();
        const { unmount } = render(<GrantDetail {...props()} />);
        await user.click(screen.getByRole('button', { name: 'Cabut semua' }));
        const dialog = screen.getByRole('dialog', { name: 'Cabut semua izin unit Pegawai Test?' });
        await user.type(within(dialog).getByRole('textbox'), 'Penugasan selesai');
        await user.click(within(dialog).getByRole('button', { name: 'Cabut semua' }));
        expect(router.put).toHaveBeenCalledWith('/akses/grant/kelola', expect.objectContaining({ permission_ids: [], expected_permission_ids: ['perm-1', 'perm-3'] }), expect.anything());
        unmount();

        render(<GrantDetail {...props({ can: { kelola_grant: false, revoke_grant: false } })} />);
        expect(screen.queryByRole('button', { name: 'Kelola izin' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Cabut semua' })).toBeNull();
    });

    it('Kelola menampilkan izin nonaktif yang masih dimiliki agar tidak ikut tercabut diam-diam', async () => {
        const user = userEvent.setup();
        render(<GrantDetail {...props()} />);
        await user.click(screen.getByRole('button', { name: 'Kelola izin' }));
        const dialog = screen.getByRole('dialog', { name: /Kelola izin unit/ });
        expect(within(dialog).getByRole<HTMLInputElement>('checkbox', { name: 'Membuat kegiatan (nonaktif)' }).checked).toBe(true);
        expect(within(dialog).getByRole('status').textContent).toBe('Belum ada perubahan.');
    });
});
