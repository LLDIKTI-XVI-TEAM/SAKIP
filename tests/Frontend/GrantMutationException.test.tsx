import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import GrantIndex from '@/Pages/Akses/GrantIndex';

const currentPage = vi.hoisted(() => ({
    props: {
        auth: { user: { id: 'admin-1', role: 'admin' } },
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

describe('Grant Mutation 403 Exception Handling', () => {
    beforeEach(() => {
        vi.spyOn(router, 'post').mockImplementation(() => undefined);
        vi.spyOn(router, 'delete').mockImplementation(() => undefined);
        vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({
            items: [{ id: 'user-2', nama: 'Pegawai Test', email: 'pegawai@example.test', roles: ['pegawai'] }],
            page: 1,
            hasMore: false,
        }), { status: 200 })));
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    const mockGrant = {
        id: 'grant-1',
        user_id: 'user-2',
        user_name: 'Pegawai Test',
        user_email: 'pegawai@example.test',
        user_roles: ['pegawai'],
        permission_id: 'perm-1',
        permission_kode: 'pengukuran:create',
        permission_keterangan: 'Input pengukuran unit',
        unit_id: 'unit-1',
        unit_nama: 'Bagian Akademik',
        alasan: 'Alasan penugasan awal',
        diberikan_oleh_nama: 'Admin SAKIP',
        created_at: '2026-03-20T10:00:00Z',
        can_revoke: true,
    };

    const mockProps = {
        grants: {
            data: [mockGrant],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            from: 1,
            to: 1,
            prev_page_url: null,
            next_page_url: null,
            total: 1,
        },
        units: [{ id: 'unit-1', nama: 'Bagian Akademik' }],
        unitPermissions: [{ id: 'perm-1', name: 'pengukuran:create', kode: 'pengukuran:create', keterangan: 'Input pengukuran unit' }],
        can: {
            create_grant: true,
            revoke_grant: true,
        },
    };

    it('menangani 403 pada penambahan grant: mempertahankan modal dan menampilkan penolakan wewenang dicabut', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...mockProps} />);

        // Buka modal tambah grant
        await user.click(screen.getByRole('button', { name: /Beri Grant Baru/i }));
        expect(screen.getByRole('dialog')).toBeTruthy();

        // Isi form
        const userSelect = await screen.findByLabelText(/Pilih Pengguna Target/i);
        await screen.findByRole('option', { name: /Pegawai Test/i });
        await user.selectOptions(userSelect, 'user-2');

        const permissionSelect = screen.getByLabelText(/Permission Unit-Scoped/i);
        await user.selectOptions(permissionSelect, 'perm-1');

        const unitSelect = screen.getByLabelText(/Unit Organisasi Target/i);
        await user.selectOptions(unitSelect, 'unit-1');

        const alasanInput = screen.getByLabelText(/Alasan Pemberian Izin/i);
        await user.type(alasanInput, 'Penugasan khusus input data triwulan');

        // Submit form
        await user.click(screen.getByRole('button', { name: /Simpan & Catat Audit/i }));

        expect(router.post).toHaveBeenCalled();
        const postCall = vi.mocked(router.post).mock.calls[0];
        expect(postCall[0]).toBe('/akses/grant');
        const options = postCall[2];

        // Simulasikan server merespons 403 karena hak akses:update aktor telah dicabut
        const serverMessage = 'Anda tidak berwenang mengelola pemberian izin unit.';
        let handledResult: unknown;
        await act(async () => {
            handledResult = options?.onHttpException?.({
                status: 403,
                data: { message: serverMessage },
                headers: {},
            });
        });

        // onHttpException harus mengembalikan false agar exception generik Inertia dicegah
        expect(handledResult).toBe(false);

        // Modal tetap terbuka
        expect(screen.getByRole('dialog')).toBeTruthy();

        // Pesan penolakan server tampil di alert banner modal
        const alert = screen.getByRole('alert');
        expect(alert.textContent).toContain(serverMessage);
    });

    it('menangani 403 pada pencabutan grant: mempertahankan modal konfirmasi dan menampilkan penolakan wewenang dicabut', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...mockProps} />);

        // Klik tombol cabut izin pada baris grant
        const revokeBtn = screen.getByTitle('Cabut Izin Unit');
        await user.click(revokeBtn);

        // Modal konfirmasi pencabutan terbuka
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByText(/Konfirmasi Pencabutan Izin/i)).toBeTruthy();

        // Isi alasan pencabutan
        const alasanInput = screen.getByLabelText(/Alasan Pencabutan Izin/i);
        await user.type(alasanInput, 'Masa penugasan triwulan telah berakhir');

        // Submit pencabutan
        await user.click(screen.getByRole('button', { name: /Cabut Izin Sekarang/i }));

        expect(router.delete).toHaveBeenCalled();
        const deleteCall = vi.mocked(router.delete).mock.calls[0];
        expect(deleteCall[0]).toBe(`/akses/grant/${mockGrant.id}`);
        const options = deleteCall[1];

        // Simulasikan server merespons 403 karena hak akses:update aktor telah dicabut
        const serverMessage = 'Anda tidak berwenang mengelola pencabutan izin unit.';
        let handledResult: unknown;
        await act(async () => {
            handledResult = options?.onHttpException?.({
                status: 403,
                data: { message: serverMessage },
                headers: {},
            });
        });

        // onHttpException harus mengembalikan false
        expect(handledResult).toBe(false);

        // Modal konfirmasi pencabutan tetap terbuka
        expect(screen.getByRole('dialog')).toBeTruthy();

        // Alasan pencabutan tetap utuh
        expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan Pencabutan Izin/i }).value).toBe('Masa penugasan triwulan telah berakhir');

        // Pesan penolakan server tampil di alert banner
        const alert = screen.getByRole('alert');
        expect(alert.textContent).toContain(serverMessage);
    });
});
