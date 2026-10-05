import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Index from '@/Pages/Renstra/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerAction }: { children: ReactNode; headerAction?: ReactNode }) => (
        <main>
            {headerAction}
            {children}
        </main>
    ),
}));

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});
afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

it.each([
    { dapatUnggah: false, tampil: false },
    { dapatUnggah: true, tampil: true },
])('modal tambah Renstra mengikuti izin unggah lampiran: $dapatUnggah', async ({ dapatUnggah, tampil }) => {
    const user = userEvent.setup();

    render(
        <Index
            renstra={{ data: [], current_page: 1, last_page: 1, from: null, to: null, total: 0, links: [] }}
            filters={{ q: '', status: null }}
            can={{ 'renstra:create': true, uploadAttachment: dapatUnggah }}
        />
    );

    await user.click(screen.getByRole('button', { name: 'Tambah Renstra' }));

    expect(screen.queryByRole('button', { name: 'Tambah Lampiran' }) !== null).toBe(tampil);
    expect(screen.queryByText('Anda tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.') !== null).toBe(!tampil);
});

it('modal tambah tanpa izin baca regulasi tidak mengirim regulasi_id kosong', async () => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(
        <Index
            renstra={{ data: [], current_page: 1, last_page: 1, from: null, to: null, total: 0, links: [] }}
            filters={{ q: '', status: null }}
            can={{ 'renstra:create': true, uploadAttachment: false, readRegulasi: false }}
        />
    );

    await user.click(screen.getByRole('button', { name: 'Tambah Renstra' }));
    fireEvent.submit(document.getElementById('form-tambah-renstra')!);

    const payload = vi.mocked(router.post).mock.calls[0][1];
    expect(payload).not.toHaveProperty('regulasi_id');
});

it('Index menyembunyikan jumlah lampiran tanpa izin baca tetapi mempertahankan capability hapus', () => {
    render(
        <Index
            renstra={{
                data: [{
                    id: 1,
                    kode: 'RENSTRA-UJI',
                    nama: 'Renstra pengujian',
                    tahun_mulai: 2025,
                    tahun_selesai: 2029,
                    status: 'draft',
                    is_aktif: false,
                    regulasi_id: null,
                    regulasi_nomor: null,
                    berkas_count: null,
                    can_delete: true,
                    pembuat: null,
                    created_at: null,
                    updated_at: null,
                }],
                current_page: 1, last_page: 1, from: 1, to: 1, total: 1, links: [],
            }}
            filters={{ q: '', status: null }}
            can={{}}
        />
    );

    expect(screen.getByText('Akses dibatasi')).toBeTruthy();
    expect(screen.queryByText(/\d+ lampiran/)).toBeNull();
    expect(screen.getByRole('button', { name: 'Hapus RENSTRA-UJI' })).toBeTruthy();
});
