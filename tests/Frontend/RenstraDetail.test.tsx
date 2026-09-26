import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Edit from '@/Pages/Renstra/Edit';
import Show from '@/Pages/Renstra/Show';
import type { RenstraDetail } from '@/types/renstra';

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});
afterEach(() => cleanup());
afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

const renstra: RenstraDetail = {
    id: 'renstra-test',
    kode: 'RENSTRA-TEST',
    nama: 'Renstra pengujian',
    tahun_mulai: 2025,
    tahun_selesai: 2029,
    status: 'aktif',
    is_aktif: true,
    deskripsi: null,
    dasar_hukum: 'Peraturan pengujian',
    regulasi_id: null,
    regulasi: null,
    pembuat: null,
    created_at: null,
    updated_at: null,
};

it('form edit Renstra aktif tidak menawarkan lampiran baru', () => {
    render(<Edit renstra={renstra} regulasiPilihan={[]} can={{ uploadAttachment: false }} />);

    expect(screen.getByRole('link', { name: 'Kembali' }).getAttribute('href')).toBe('/renstra/renstra-test');
    expect(screen.queryByRole('button', { name: 'Tambah Lampiran' })).toBeNull();
    expect(screen.getByText('Lampiran baru hanya dapat ditambahkan saat Renstra berstatus draft.')).toBeTruthy();
});

it('halaman detail memakai URL unduh yang dikirim server', () => {
    render(<Show renstra={{
        ...renstra,
        berkas: [{
            id: 7,
            mode: 'file',
            nama_asli: 'naskah.pdf',
            mime: 'application/pdf',
            ukuran_bytes: 123,
            tautan: null,
            isi_teks: null,
            download_url: '/unduh/naskah-yang-diizinkan',
        }],
    }} />);

    expect(screen.getByRole('link', { name: 'Unduh' }).getAttribute('href')).toBe('/unduh/naskah-yang-diizinkan');
});
