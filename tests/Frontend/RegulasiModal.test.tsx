import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
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
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
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

        // Modal terbuka dengan judul
        expect(screen.getByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeTruthy();

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
        expect(vi.mocked(router.post).mock.calls[0][2]?.preserveState).toBe('errors');
    });

    it('mempertahankan state recovery dan draft saat modal ditutup lalu dibuka kembali setelah mutation outcome unknown', async () => {
        const user = userEvent.setup();
        vi.spyOn(router, 'post').mockImplementation(() => undefined);

        render(
            <Index
                regulasi={{ data: dummyItems, current_page: 1, last_page: 1, total: 1, from: 1, to: 1, links: [] }}
                filters={{ q: '', status: null }}
                can={{ 'regulasi:create': true, 'regulasi:read': true }}
            />
        );

        // 1 & 2. Buka modal
        await user.click(screen.getByRole('button', { name: /Tambah dasar aturan/i }));
        expect(screen.getByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeTruthy();

        // 3. Isi field form
        await user.type(screen.getByLabelText(/^Nomor/i), '123/TEST/2026');
        await user.type(screen.getByLabelText(/^Tentang/i), 'Regulasi Pengujian Recovery');
        expect((screen.getByLabelText(/^Nomor/i) as HTMLInputElement).value).toBe('123/TEST/2026');

        // 4. Submit POST /regulasi
        await user.click(screen.getByRole('button', { name: /Simpan Dasar Aturan/i }));
        expect(router.post).toHaveBeenCalledTimes(1);

        // 5. Simulasikan onNetworkError
        const options = vi.mocked(router.post).mock.calls[0][2];
        await act(async () => {
            options?.onNetworkError?.(new Error('offline'));
        });

        // 6. Pastikan recovery notice muncul
        expect(screen.getByRole('alert').textContent).toContain('belum dapat dipastikan');

        // 7. Pastikan tombol Simpan disabled
        const simpanBtn = screen.getByRole<HTMLButtonElement>('button', { name: /Simpan Dasar Aturan/i });
        expect(simpanBtn.disabled).toBe(true);

        // 8. Tutup modal via Batal
        await user.click(screen.getByRole('button', { name: 'Batal' }));
        expect(screen.queryByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeNull();

        // 9. Buka modal kembali
        await user.click(screen.getByRole('button', { name: /Tambah dasar aturan/i }));
        expect(screen.getByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeTruthy();

        // 10. Pastikan recovery masih berlaku, notice tetap tampil, dan draft tersimpan
        expect(screen.getByRole('alert').textContent).toContain('belum dapat dipastikan');
        expect((screen.getByLabelText(/^Nomor/i) as HTMLInputElement).value).toBe('123/TEST/2026');
        expect((screen.getByLabelText(/^Tentang/i) as HTMLTextAreaElement).value).toBe('Regulasi Pengujian Recovery');

        // 11. Pastikan submit tetap disabled
        const reopenedSimpanBtn = screen.getByRole<HTMLButtonElement>('button', { name: /Simpan Dasar Aturan/i });
        expect(reopenedSimpanBtn.disabled).toBe(true);

        // 12. Coba klik submit
        await user.click(reopenedSimpanBtn);

        // 13. Pastikan router.post hanya pernah dipanggil satu kali
        expect(router.post).toHaveBeenCalledTimes(1);
    });

    it('menyinkronkan filter UI saat incoming filters props diperbarui setelah create berhasil', async () => {
        const user = userEvent.setup();
        vi.spyOn(router, 'post').mockImplementation(() => undefined);

        const { rerender } = render(
            <Index
                regulasi={{ data: dummyItems, current_page: 1, last_page: 1, total: 1, from: 1, to: 1, links: [] }}
                filters={{ q: 'kepmen', status: 'aktif' }}
                can={{ 'regulasi:create': true, 'regulasi:read': true }}
            />
        );

        // Initial UI: input filter terisi sesuai initial props
        const searchInput = screen.getByLabelText<HTMLInputElement>(/Cari regulasi/i);
        const statusSelect = screen.getByLabelText<HTMLSelectElement>(/Status/i);
        expect(searchInput.value).toBe('kepmen');
        expect(statusSelect.value).toBe('aktif');

        // Buka modal create dan submit
        await user.click(screen.getByRole('button', { name: /Tambah dasar aturan/i }));
        await user.type(screen.getByLabelText(/^Nomor/i), 'NEW/REG/2026');
        await user.type(screen.getByLabelText(/^Tentang/i), 'Regulasi Baru');
        await user.click(screen.getByRole('button', { name: /Simpan Dasar Aturan/i }));

        expect(router.post).toHaveBeenCalledTimes(1);
        const postOptions = vi.mocked(router.post).mock.calls[0][2];
        expect(postOptions?.preserveState).toBe('errors');

        // Simulasikan onSuccess yang menutup modal
        await act(async () => {
            postOptions?.onSuccess?.({} as any);
        });
        expect(screen.queryByRole('heading', { name: 'Tambah Dasar Aturan' })).toBeNull();

        // Redirect backend ke /regulasi mengirim canonical props tanpa query filter
        rerender(
            <Index
                regulasi={{ data: dummyItems, current_page: 1, last_page: 1, total: 1, from: 1, to: 1, links: [] }}
                filters={{ q: '', status: null }}
                can={{ 'regulasi:create': true, 'regulasi:read': true }}
            />
        );

        // Filter UI harus sinkron dengan props terbaru
        expect(searchInput.value).toBe('');
        expect(statusSelect.value).toBe('');
    });
});
