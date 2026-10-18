import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
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
        user_roles: ['Pegawai'],
        permission_id: 'perm-1',
        permission_kode: 'pengukuran:create',
        permission_keterangan: 'Input pengukuran unit',
        unit_id: 'unit-1',
        unit_nama: 'Bagian Akademik',
        alasan: 'Alasan penugasan awal',
        diberikan_oleh_nama: 'Admin SAKIP',
        created_at: '2026-03-20T10:00:00Z',
        tidak_berlaku: [],
    };

    const mockProps = {
        grants: { data: [mockGrant], links: [], from: 1, to: 1, total: 1 },
        filters: { search: '', unit_id: 'all' },
        units: [{ id: 'unit-1', nama: 'Bagian Akademik' }],
        unitPermissions: [{ id: 'perm-1', kode: 'pengukuran:create', keterangan: 'Input pengukuran unit' }],
        can: {
            create_grant: true,
            revoke_grant: true,
        },
    };

    it('menangani 403 pada penambahan grant: mempertahankan modal dan menampilkan penolakan wewenang dicabut', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...mockProps} />);

        // Buka modal pemberian izin unit
        await user.click(screen.getByRole('button', { name: 'Beri izin unit' }));
        expect(screen.getByRole('dialog')).toBeTruthy();

        // Isi form
        const userInput = await screen.findByPlaceholderText(/Cari nama atau email pengguna.../i);
        await user.type(userInput, 'Pegawai');
        const userOption = await screen.findByRole('option', { name: /Pegawai Test/i });
        await user.click(userOption);

        await user.selectOptions(screen.getByLabelText(/^Izin/), 'perm-1');
        await user.selectOptions(screen.getByLabelText(/^Unit/), 'unit-1');
        await user.type(screen.getByLabelText(/^Alasan pemberian/), 'Penugasan khusus input data triwulan');

        // Submit form
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(router.post).toHaveBeenCalled();
        const postCall = vi.mocked(router.post).mock.calls[0];
        expect(postCall[0]).toBe('/akses/grant');
        const options = postCall[2];

        // Simulasikan server merespons 403 karena izin delegasi aktor telah dicabut
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

        // Modal tetap terbuka dan pesan penolakan tampil sekali di banner modal, bukan pada field alasan
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByRole('alert').textContent).toContain(serverMessage);
        expect(screen.getAllByText(serverMessage)).toHaveLength(1);
        expect(screen.getByLabelText(/^Alasan pemberian/).getAttribute('aria-invalid')).not.toBe('true');
    });

    it('menangani 403 pada pencabutan grant: mempertahankan modal konfirmasi dan menampilkan penolakan wewenang dicabut', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...mockProps} />);

        // Klik tombol cabut izin pada baris grant
        await user.click(screen.getByRole('button', { name: 'Cabut izin unit Pegawai Test' }));

        // Modal alasan pencabutan terbuka dan tombol konfirmasi menunggu alasan
        const dialog = screen.getByRole('dialog', { name: 'Cabut izin unit Pegawai Test?' });
        const confirm = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Cabut izin' });
        expect(confirm.disabled).toBe(true);

        // Isi alasan pencabutan
        const alasanInput = within(dialog).getByRole<HTMLTextAreaElement>('textbox');
        await user.type(alasanInput, 'Masa penugasan triwulan telah berakhir');

        // Submit pencabutan
        await user.click(confirm);

        expect(router.delete).toHaveBeenCalled();
        const deleteCall = vi.mocked(router.delete).mock.calls[0];
        expect(deleteCall[0]).toBe(`/akses/grant/${mockGrant.id}`);
        const options = deleteCall[1];

        // Simulasikan server merespons 403 karena izin delegasi aktor telah dicabut
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

        // Modal tetap terbuka dengan alasan utuh dan tidak ditandai salah
        expect(screen.getByRole('dialog', { name: 'Cabut izin unit Pegawai Test?' })).toBeTruthy();
        expect(alasanInput.value).toBe('Masa penugasan triwulan telah berakhir');
        expect(alasanInput.getAttribute('aria-invalid')).toBe('false');

        // Pesan penolakan server tampil di banner modal
        expect(within(dialog).getByRole('alert').textContent).toContain(serverMessage);
    });
});
