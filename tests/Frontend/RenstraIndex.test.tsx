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
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
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

it('Index menampilkan nomor regulasi tanpa relasi dan menyembunyikan jumlah lampiran tanpa izin baca', () => {
    render(
        <Index
            renstra={{
                data: [{
                    id: 'renstra-test',
                    kode: 'RENSTRA-UJI',
                    nama: 'Renstra pengujian',
                    tahun_mulai: 2025,
                    tahun_selesai: 2029,
                    status: 'draft',
                    is_aktif: false,
                    regulasi_id: '11111111-1111-4111-8111-111111111111',
                    regulasi_nomor: 'Permen 123/2024',
                    berkas_count: null,
                    can_delete: true,
                    can_update: false,
                    created_at: null,
                    updated_at: null,
                }],
                current_page: 1, last_page: 1, from: 1, to: 1, total: 1, links: [],
            }}
            filters={{ q: '', status: null }}
            can={{ readRegulasi: true }}
        />
    );

    expect(screen.getByText('Akses dibatasi')).toBeTruthy();
    expect(screen.getByText('Permen 123/2024')).toBeTruthy();
    expect(screen.queryByText(/\d+ lampiran/)).toBeNull();
    expect(screen.getByRole('button', { name: 'Hapus RENSTRA-UJI' })).toBeTruthy();
});
