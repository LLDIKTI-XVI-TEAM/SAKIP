import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Create from '@/Pages/Renstra/Create';
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
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});
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

it('form tambah mempertahankan perubahan field dan lampiran dalam satu batch', () => {
    render(<Create regulasiPilihan={[]} can={{ uploadAttachment: true, readRegulasi: true }} />);

    const nama = screen.getByLabelText<HTMLInputElement>(/Nama Rencana Strategis/);
    const kode = screen.getByLabelText<HTMLInputElement>(/Kode Dokumen/);
    act(() => {
        fireEvent.change(nama, { target: { value: 'Renstra baru' } });
        fireEvent.change(kode, { target: { value: 'RENSTRA-BARU' } });
    });
    expect(nama.value).toBe('Renstra baru');
    expect(kode.value).toBe('RENSTRA-BARU');

    const tambah = screen.getByRole('button', { name: 'Tambah Lampiran' });
    act(() => {
        fireEvent.click(tambah);
        fireEvent.click(tambah);
    });
    expect(screen.getAllByRole('button', { name: 'Hapus Lampiran' })).toHaveLength(2);

    act(() => {
        screen.getAllByRole('button', { name: 'Teks' }).forEach((button) => fireEvent.click(button));
    });
    const isiLampiran = screen.getAllByPlaceholderText<HTMLTextAreaElement>(/Tuliskan naskah atau catatan ringkas lampiran/);
    act(() => {
        fireEvent.change(isiLampiran[0], { target: { value: 'Naskah pertama' } });
        fireEvent.change(isiLampiran[1], { target: { value: 'Naskah kedua' } });
    });
    expect(isiLampiran.map((field) => field.value)).toEqual(['Naskah pertama', 'Naskah kedua']);
});

it('form edit tanpa izin baca regulasi menyembunyikan pilihan dan tidak mengirim regulasi_id', () => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<Edit renstra={{ ...renstra, status: 'draft', is_aktif: false }} regulasiPilihan={[]} can={{ uploadAttachment: false, readRegulasi: false }} />);

    expect(screen.queryByLabelText('Rujukan Regulasi Utama')).toBeNull();
    expect(screen.getByText('Rujukan regulasi yang sudah tersimpan tetap dipertahankan.')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

    const payload = vi.mocked(router.post).mock.calls[0][1];
    expect(payload).not.toHaveProperty('regulasi_id');
});

it('form tambah tanpa izin baca regulasi tidak mengirim regulasi_id kosong', () => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<Create regulasiPilihan={[]} can={{ uploadAttachment: false, readRegulasi: false }} />);

    expect(screen.queryByLabelText('Rujukan Regulasi Utama')).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Simpan Renstra' }));

    const payload = vi.mocked(router.post).mock.calls[0][1];
    expect(payload).not.toHaveProperty('regulasi_id');
});

it('halaman detail menampilkan jenis, nomor, tahun, dan tentang regulasi rujukan', () => {
    render(<Show renstra={{
        ...renstra,
        regulasi_id: 11,
        regulasi: {
            id: 11,
            jenis: 'permen',
            nomor: 'Permen 123/2024',
            tahun: 2024,
            tentang: 'Standar Akuntabilitas',
        },
    }} />);

    expect(screen.getByText('Jenis')).toBeTruthy();
    expect(screen.getByText('Peraturan Menteri')).toBeTruthy();
    expect(screen.getByText('Permen 123/2024 (2024)')).toBeTruthy();
    expect(screen.getByText('Standar Akuntabilitas')).toBeTruthy();
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
