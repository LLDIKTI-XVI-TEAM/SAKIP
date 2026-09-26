import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/Regulasi/Index';
import type { RegulasiSummary } from '@/types/regulasi';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function () {
        this.open = false;
    };
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const dummyItems: RegulasiSummary[] = [
    {
        id: 1,
        jenis: 'kepmen',
        nomor: '358/M/KEP/2025',
        tahun: 2025,
        tentang: 'Indikator Kinerja Utama',
        aktif: true,
        berkas_count: 1,
        tanggal: '2025-08-01',
        tautan_sumber: 'https://jdih.example.go.id/358',
        pembuat: 'Perencanaan',
        updated_at: null,
    },
];

describe('Regulasi Modal UI/UX', () => {
    it('membuka modal Tambah Dasar Aturan ketika tombol ditekan tanpa navigasi halaman', async () => {
        const user = userEvent.setup();
        render(
            <Index
                regulasi={{ data: dummyItems, current_page: 1, last_page: 1, total: 1, from: 1, to: 1, links: [] }}
                filters={{ q: '', status: null }}
                can={{ 'regulasi:create': true, 'regulasi:read': true }}
            />
        );

        // Sebelum klik, modal belum ada di DOM / belum terbuka
        expect(screen.queryByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeNull();

        // Klik tombol Tambah dasar aturan
        const tambahBtn = screen.getByRole('button', { name: /Tambah dasar aturan/i });
        await user.click(tambahBtn);

        // Modal terbuka dengan judul dan deskripsi
        expect(screen.getByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeTruthy();
        expect(screen.getByText('Tambahkan dasar hukum atau regulasi yang menjadi rujukan dalam penyusunan SAKIP.')).toBeTruthy();

        // Field form tersedia di dalam modal
        expect(screen.getByLabelText(/Jenis regulasi/i)).toBeTruthy();
        expect(screen.getByLabelText(/^Nomor/i)).toBeTruthy();
        expect(screen.getByLabelText(/^Tahun/i)).toBeTruthy();
        expect(screen.getByLabelText(/Tanggal penetapan/i)).toBeTruthy();
        expect(screen.getByLabelText(/Tautan sumber resmi/i)).toBeTruthy();
        expect(screen.getByLabelText(/^Tentang/i)).toBeTruthy();
        expect(screen.getByLabelText(/Catatan internal/i)).toBeTruthy();
        expect(screen.getByLabelText(/Regulasi aktif/i)).toBeTruthy();

        // Footer tombol Batal dan Simpan Dasar Aturan
        expect(screen.getByRole('button', { name: 'Batal' })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Simpan Dasar Aturan/i })).toBeTruthy();
    });

    it('menutup modal ketika tombol Batal ditekan', async () => {
        const user = userEvent.setup();
        render(
            <Index
                regulasi={{ data: dummyItems, current_page: 1, last_page: 1, total: 1, from: 1, to: 1, links: [] }}
                filters={{ q: '', status: null }}
                can={{ 'regulasi:create': true }}
            />
        );

        // Buka modal
        await user.click(screen.getByRole('button', { name: /Tambah dasar aturan/i }));
        expect(screen.getByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeTruthy();

        // Klik Batal
        await user.click(screen.getByRole('button', { name: 'Batal' }));

        // Modal tertutup
        expect(screen.queryByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeNull();
    });

    it('tidak menampilkan tombol Tambah dasar aturan jika user tidak memiliki permission regulasi:create', () => {
        render(
            <Index
                regulasi={{ data: dummyItems, current_page: 1, last_page: 1, total: 1, from: 1, to: 1, links: [] }}
                filters={{ q: '', status: null }}
                can={{ 'regulasi:create': false }}
            />
        );

        expect(screen.queryByRole('button', { name: /Tambah dasar aturan/i })).toBeNull();
        expect(screen.queryByRole('link', { name: /Tambah dasar aturan/i })).toBeNull();
    });

    it('mengirimkan form ke endpoint POST /regulasi dengan forceFormData saat disimpan', async () => {
        const user = userEvent.setup();
        vi.spyOn(router, 'post').mockImplementation(() => undefined);

        render(
            <Index
                regulasi={{ data: dummyItems, current_page: 1, last_page: 1, total: 1, from: 1, to: 1, links: [] }}
                filters={{ q: '', status: null }}
                can={{ 'regulasi:create': true }}
            />
        );

        // Buka modal
        await user.click(screen.getByRole('button', { name: /Tambah dasar aturan/i }));

        // Isi field
        await user.type(screen.getByLabelText(/^Nomor/i), '123/TEST/2026');
        await user.type(screen.getByLabelText(/^Tentang/i), 'Regulasi Pengujian Modal');

        // Submit
        await user.click(screen.getByRole('button', { name: /Simpan Dasar Aturan/i }));

        expect(router.post).toHaveBeenCalledTimes(1);
        expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/regulasi');
        expect(vi.mocked(router.post).mock.calls[0][2]?.forceFormData).toBe(true);
    });
});
