import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import UnitIndex from '@/Pages/Unit/Index';

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native yang dipakai AuditReasonModal.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 'superadmin', role: 'superadmin' } }, errors: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

const unit = (id: string, nama: string, aktif: boolean, terkait: boolean) => ({
    id,
    nama,
    status: aktif ? ('aktif' as const) : ('nonaktif' as const),
    is_active: aktif,
    version_token: `token-${id}`,
    indikators_count: terkait ? 6 : 0,
    rencana_aksis_count: terkait ? 12 : 0,
    kegiatans_count: terkait ? 31 : 0,
    grants_count: terkait ? 3 : 0,
    is_deletable: !terkait,
    can: { update: true, delete: !terkait },
});

const units = [unit('u1', 'Bagian Umum', true, true), unit('u2', 'Tim Kerja Sistem Informasi', false, false)];

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

it('menyaring unit lewat kolom pencarian dan status yang berlabel', async () => {
    const user = userEvent.setup();
    render(<UnitIndex units={units} can={{ create: true }} />);

    expect(screen.getByRole('button', { name: /Tambah unit kerja/ })).toBeTruthy();
    await user.selectOptions(screen.getByLabelText('Status'), 'nonaktif');
    expect(screen.queryByText('Bagian Umum')).toBeNull();
    expect(screen.getByText('Tim Kerja Sistem Informasi')).toBeTruthy();

    await user.type(screen.getByLabelText('Cari unit kerja'), 'umum');
    expect(screen.getByText('Tidak ada unit kerja yang cocok')).toBeTruthy();
});

it('memberi nama tombol aksi per unit dan menjelaskan unit yang tidak dapat dihapus', () => {
    render(<UnitIndex units={units} can={{ create: true }} />);

    expect(screen.getByRole('button', { name: 'Edit Bagian Umum' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Hapus Tim Kerja Sistem Informasi' })).toBeTruthy();
    // Tombol tetap dapat difokus agar alasannya terbaca lewat keyboard dan pembaca layar.
    const terkunci = screen.getByRole('button', { name: /Bagian Umum tidak dapat dihapus/ });
    expect(terkunci.getAttribute('aria-disabled')).toBe('true');
    expect((terkunci as HTMLButtonElement).disabled).toBe(false);
});

it('mengonfirmasi perubahan status lewat dialog aplikasi, bukan confirm bawaan browser', async () => {
    const user = userEvent.setup();
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
    render(<UnitIndex units={units} can={{ create: true }} />);

    await user.click(screen.getByRole('button', { name: 'Nonaktifkan Bagian Umum' }));
    expect(confirm).not.toHaveBeenCalled();
    expect(router.post).not.toHaveBeenCalled();

    const dialog = screen.getByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: 'Nonaktifkan' }));
    expect(router.post).toHaveBeenCalledWith(
        '/unit/u1',
        expect.objectContaining({ status: 'nonaktif', expected_status: 'aktif', version_token: 'token-u1' }),
        expect.anything(),
    );
});

it('menampilkan penolakan server di dialog ubah status tanpa menutupnya', async () => {
    const user = userEvent.setup();
    render(<UnitIndex units={units} can={{ create: true }} />);

    await user.click(screen.getByRole('button', { name: 'Nonaktifkan Bagian Umum' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Nonaktifkan' }));
    const options = vi.mocked(router.post).mock.calls[0][2];

    // Konflik versi: admin lain sudah lebih dulu mengubah unit yang sama.
    await act(async () => {
        options?.onError?.({ version_token: 'Data unit sudah berubah. Muat ulang halaman.' });
    });

    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByRole('alert').textContent).toContain('Data unit sudah berubah. Muat ulang halaman.');
});

// Edit gagal: galatnya tampil di banner halaman dan tetap ada setelah modal edit ditutup.
async function gagalkanEdit(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('button', { name: 'Edit Bagian Umum' }));
    await user.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    await act(async () => {
        vi.mocked(router.post).mock.calls[0][2]?.onError?.({ version_token: 'Data unit sudah berubah. Muat ulang halaman.' });
    });
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    expect(screen.getByRole('button', { name: 'Tutup pesan kesalahan' })).toBeTruthy();
}

it('membersihkan galat edit lama setelah status unit berhasil diubah', async () => {
    const user = userEvent.setup();
    render(<UnitIndex units={units} can={{ create: true }} />);
    await gagalkanEdit(user);

    await user.click(screen.getByRole('button', { name: 'Nonaktifkan Bagian Umum' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Nonaktifkan' }));
    await act(async () => {
        vi.mocked(router.post).mock.calls[1][2]?.onSuccess?.({} as never);
    });

    expect(screen.queryByRole('button', { name: 'Tutup pesan kesalahan' })).toBeNull();
});

it('membersihkan galat edit lama setelah unit berhasil dihapus', async () => {
    vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(<UnitIndex units={units} can={{ create: true }} />);
    await gagalkanEdit(user);

    await user.click(screen.getByRole('button', { name: 'Hapus Tim Kerja Sistem Informasi' }));
    await user.type(screen.getByLabelText(/Alasan perubahan/), 'Unit sudah dilebur');
    await user.click(screen.getByRole('button', { name: 'Hapus permanen' }));
    await act(async () => {
        vi.mocked(router.delete).mock.calls[0][1]?.onSuccess?.({} as never);
    });

    expect(screen.queryByRole('button', { name: 'Tutup pesan kesalahan' })).toBeNull();
});

it('membedakan master yang masih kosong dari hasil pencarian yang kosong', () => {
    render(<UnitIndex units={[]} can={{ create: true }} />);

    expect(screen.getByText('Belum ada unit kerja')).toBeTruthy();
    expect(screen.queryByText(/kriteria pencarian/)).toBeNull();
});
