import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
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
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

describe('Unit Delete Exception Handling', () => {
    beforeEach(() => {
        vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    const mockUnit = {
        id: 'unit-123',
        nama: 'Biro Administrasi Akademik',
        status: 'aktif' as const,
        is_active: true,
        version_token: 'valid-token',
        expected_nama: 'Biro Administrasi Akademik',
        expected_status: 'aktif',
        snapshot: { nama: 'Biro Administrasi Akademik', status: 'aktif' },
        indikators_count: 0,
        rencana_aksis_count: 0,
        kegiatans_count: 0,
        grants_count: 0,
        denies_count: 0,
        can: {
            update: true,
            delete: true,
        },
        is_deletable: true,
    };

    it('mempertahankan modal dan menampilkan penolakan 403 saat unit memiliki relasi baru atau wewenang tidak mencukupi', async () => {
        const user = userEvent.setup();
        render(<UnitIndex units={[mockUnit]} can={{ create: true }} />);

        // Klik tombol hapus
        const deleteBtn = screen.getByTitle('Hapus Unit Kosong (Superadmin)');
        await user.click(deleteBtn);

        // Modal hapus terbuka
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByText(/Hapus Unit: Biro Administrasi Akademik\?/)).toBeTruthy();

        // Masukkan alasan
        const textarea = screen.getByLabelText(/Alasan Penghapusan/);
        await user.type(textarea, 'Penghapusan unit uji coba');

        // Submit form
        const submitBtn = screen.getByRole('button', { name: 'Hapus Permanen' });
        await user.click(submitBtn);

        expect(router.delete).toHaveBeenCalledTimes(1);
        const options = vi.mocked(router.delete).mock.calls[0][1];

        // Simulasikan server merespons 403 HTTP Exception (konflik relasi data)
        const serverMessage = 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, snapshot jadwal, atau izin terkait.';
        let handledResult: unknown;
        await act(async () => {
            handledResult = options?.onHttpException?.({
                status: 403,
                data: { message: serverMessage },
                headers: {},
            });
        });

        // onHttpException harus mengembalikan false agar Inertia tidak menampilkan modal exception generik
        expect(handledResult).toBe(false);

        // Modal tetap terbuka
        expect(screen.getByRole('dialog')).toBeTruthy();

        // Alasan yang diketik pengguna tetap utuh tidak hilang
        expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan Penghapusan/ }).value).toBe('Penghapusan unit uji coba');

        // Pesan penolakan server tampil di alert banner
        const alert = screen.getByRole('alert');
        expect(alert.textContent).toContain(serverMessage);
    });

    it('mempertahankan modal dan menampilkan error validasi 422 saat alasan ditolak', async () => {
        const user = userEvent.setup();
        render(<UnitIndex units={[mockUnit]} can={{ create: true }} />);

        // Buka modal hapus
        await user.click(screen.getByTitle('Hapus Unit Kosong (Superadmin)'));
        const textarea = screen.getByLabelText(/Alasan Penghapusan/);
        await user.type(textarea, 'Alasan awal');

        await user.click(screen.getByRole('button', { name: 'Hapus Permanen' }));
        const options = vi.mocked(router.delete).mock.calls[0][1];

        // Simulasikan respons validasi 422
        await act(async () => {
            options?.onError?.({
                alasan: 'Alasan penghapusan unit minimal 5 karakter.',
            });
        });

        // Modal tetap terbuka
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getAllByText('Alasan penghapusan unit minimal 5 karakter.')[0]).toBeTruthy();
    });

    it('menutup modal dan mereset alasan hanya saat penghapusan berhasil (onSuccess)', async () => {
        const user = userEvent.setup();
        render(<UnitIndex units={[mockUnit]} can={{ create: true }} />);

        // Buka modal hapus
        await user.click(screen.getByTitle('Hapus Unit Kosong (Superadmin)'));
        const textarea = screen.getByLabelText(/Alasan Penghapusan/);
        await user.type(textarea, 'Unit sudah tidak digunakan');

        await user.click(screen.getByRole('button', { name: 'Hapus Permanen' }));
        const options = vi.mocked(router.delete).mock.calls[0][1];

        // Simulasikan sukses
        await act(async () => {
            options?.onSuccess?.({} as never);
        });

        // Modal tertutup
        expect(screen.queryByRole('dialog')).toBeNull();
    });
});
