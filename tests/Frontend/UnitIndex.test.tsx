import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import UnitIndex from '@/Pages/Unit/Index';

const currentPage = vi.hoisted(() => ({
    props: {
        auth: { user: { id: 'superadmin', role: 'superadmin' } },
        errors: {},
    },
    flash: {} as Record<string, unknown>,
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => currentPage,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerAction }: { children: ReactNode; headerAction?: ReactNode }) => (
        <div>
            <header data-testid="header-action-slot">{headerAction}</header>
            <main>{children}</main>
        </div>
    ),
}));

describe('UnitIndex Component UI & Reusable Components', () => {
    beforeEach(() => {
        vi.spyOn(router, 'post').mockImplementation(() => undefined);
        vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    const mockUnits = [
        {
            id: 'unit-1',
            nama: 'Bagian Kelembagaan dan Sistem Informasi',
            status: 'aktif' as const,
            is_active: true,
            version_token: 'v1',
            indikators_count: 1,
            rencana_aksis_count: 1,
            kegiatans_count: 0,
            grants_count: 0,
            denies_count: 0,
            can: { update: true, delete: false },
            is_deletable: false,
        },
        {
            id: 'unit-2',
            nama: 'Bagian Perencanaan dan Kerjasama',
            status: 'nonaktif' as const,
            is_active: false,
            version_token: 'v2',
            indikators_count: 0,
            rencana_aksis_count: 0,
            kegiatans_count: 0,
            grants_count: 0,
            denies_count: 0,
            can: { update: true, delete: true },
            is_deletable: true,
        },
    ];

    it('merender tombol Tambah Unit Kerja Baru di headerAction dan tidak merender judul ganda/deskripsi berlebihan', () => {
        render(<UnitIndex units={mockUnits} can={{ create: true }} />);

        // Tombol Tambah harus ada di headerAction
        const headerSlot = screen.getByTestId('header-action-slot');
        expect(headerSlot.textContent).toContain('Tambah Unit Kerja Baru');

        // Teks deskripsi berlebihan yang diaudit sudah dihapus
        expect(screen.queryByText(/Kelola unit kerja pemilik indikator kinerja/i)).toBeNull();
    });

    it('membuka ConfirmModal saat tombol toggle status diklik, bukan memanggil window.confirm', async () => {
        const user = userEvent.setup();
        const confirmSpy = vi.spyOn(window, 'confirm').mockImplementation(() => true);

        render(<UnitIndex units={mockUnits} can={{ create: true }} />);

        // Klik tombol status toggle untuk unit aktif
        const toggleBtn = screen.getByLabelText('Nonaktifkan Bagian Kelembagaan dan Sistem Informasi');
        await user.click(toggleBtn);

        // window.confirm TIDAK boleh dipanggil
        expect(confirmSpy).not.toHaveBeenCalled();

        // ConfirmModal harus terbuka dengan konfirmasi nonaktifkan
        const dialog = screen.getByRole('dialog');
        expect(dialog).toBeTruthy();
        expect(within(dialog).getByRole('heading', { name: 'Nonaktifkan Unit Kerja' })).toBeTruthy();
        expect(within(dialog).getByText(/Apakah Anda yakin ingin menonaktifkan "Bagian Kelembagaan dan Sistem Informasi"\?/i)).toBeTruthy();

        // Klik tombol konfirmasi
        const confirmBtn = within(dialog).getByRole('button', { name: 'Nonaktifkan Unit Kerja' });
        await user.click(confirmBtn);

        expect(router.post).toHaveBeenCalledWith(
            '/unit/unit-1',
            expect.objectContaining({
                nama: 'Bagian Kelembagaan dan Sistem Informasi',
                status: 'nonaktif',
            }),
            expect.any(Object)
        );
    });

    it('membuka modal edit dengan judul bersih "Edit Unit Kerja" tanpa nama unit yang terdouble di header', async () => {
        const user = userEvent.setup();
        render(<UnitIndex units={mockUnits} can={{ create: true }} />);

        // Klik tombol edit
        const editBtn = screen.getByLabelText('Edit Bagian Kelembagaan dan Sistem Informasi');
        await user.click(editBtn);

        // Modal Edit terbuka
        const dialog = screen.getByRole('dialog');
        expect(dialog).toBeTruthy();
        // Header harus bersih: "Edit Unit Kerja", bukan "Edit Unit Kerja: Bagian Kelembagaan..."
        expect(within(dialog).getByRole('heading', { name: /Edit Unit Kerja/i })).toBeTruthy();
        expect(screen.queryByText('Edit Unit Kerja: Bagian Kelembagaan dan Sistem Informasi')).toBeNull();

        // Form field harus memuat nama unit
        const input = within(dialog).getByLabelText(/Nama Unit Kerja/i) as HTMLInputElement;
        expect(input.value).toBe('Bagian Kelembagaan dan Sistem Informasi');
    });

    it('hanya menampilkan tombol hapus jika unit dapat dihapus (unit.can.delete)', () => {
        render(<UnitIndex units={mockUnits} can={{ create: true }} />);

        // unit-1 tidak dapat dihapus, tombol hapus tidak boleh dirender
        expect(screen.queryByRole('button', { name: 'Hapus Bagian Kelembagaan dan Sistem Informasi' })).toBeNull();

        // unit-2 dapat dihapus, tombol hapus harus dirender
        expect(screen.getByRole('button', { name: 'Hapus Bagian Perencanaan dan Kerjasama' })).toBeTruthy();
    });
});
