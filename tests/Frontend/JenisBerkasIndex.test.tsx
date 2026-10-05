import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import JenisBerkasIndex, { JenisBerkasItem } from '@/Pages/JenisBerkas/Index';

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});

afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

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

describe('JenisBerkasIndex UI & Reusable Component Standardization', () => {
    beforeEach(() => {
        vi.spyOn(router, 'post').mockImplementation(() => undefined);
        vi.spyOn(router, 'put').mockImplementation(() => undefined);
        vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    const mockJenisBerkas: JenisBerkasItem[] = [
        {
            id: 'jb-1',
            nama: 'Laporan Capaian Kinerja Triwulan',
            tahap: 'pengukuran',
            indikator_id: null,
            wajib: true,
            keterangan: 'Bukti dukung capaian indikator',
            izinkan_file: true,
            izinkan_tautan: true,
            izinkan_teks: false,
            semua_mode_wajib: false,
            urutan: 1,
            format_diizinkan: 'pdf,docx,xlsx',
            ukuran_maks_kb: 10240,
            aktif: true,
        },
    ];

    it('merender tombol Tambah Persyaratan di headerAction dan menghapus deskripsi banner berlebihan', () => {
        render(
            <JenisBerkasIndex
                jenisBerkasList={mockJenisBerkas}
                indikators={[]}
                can={{ create: true, update: true, delete: true }}
            />
        );

        // Tombol Tambah harus berada di slot headerAction
        const headerSlot = screen.getByTestId('header-action-slot');
        expect(headerSlot.textContent).toContain('Tambah Persyaratan');

        // Deskripsi filler yang diaudit sudah dihapus dari DOM
        expect(screen.queryByText(/Standar Bukti Dukung Kinerja & Kegiatan/i)).toBeNull();
        expect(screen.queryByText(/Kelola persyaratan bukti dukung per tahap kepatuhan/i)).toBeNull();
    });

    it('membuat nama persyaratan di tabel dapat diklik untuk membuka modal ubah', async () => {
        const user = userEvent.setup();
        render(
            <JenisBerkasIndex
                jenisBerkasList={mockJenisBerkas}
                indikators={[]}
                can={{ create: true, update: true, delete: true }}
            />
        );

        // Cari teks nama persyaratan di baris tabel dan klik
        const clickableNameBtn = screen.getByText('Laporan Capaian Kinerja Triwulan');
        await user.click(clickableNameBtn);

        // Modal ubah terbuka dengan header yang distandardisasi
        const dialog = screen.getByRole('dialog');
        expect(dialog).toBeTruthy();
        expect(within(dialog).getByText('Ubah Persyaratan Jenis Berkas')).toBeTruthy();
        // Memastikan prop description tidak ada di modal
        expect(screen.queryByText('Konfigurasi standar bukti dukung sesuai alur dan kepatuhan SAKIP')).toBeNull();
    });

    it('menggunakan EditIconButton dan DeleteIconButton di kolom aksi', async () => {
        const user = userEvent.setup();
        render(
            <JenisBerkasIndex
                jenisBerkasList={mockJenisBerkas}
                indikators={[]}
                can={{ create: true, update: true, delete: true }}
            />
        );

        // Tombol aksi harus memiliki accessible label standar
        const editBtn = screen.getByRole('button', { name: 'Edit persyaratan Laporan Capaian Kinerja Triwulan' });
        const deleteBtn = screen.getByRole('button', { name: 'Hapus persyaratan Laporan Capaian Kinerja Triwulan' });

        expect(editBtn).toBeTruthy();
        expect(deleteBtn).toBeTruthy();

        // Klik tombol hapus membuka AuditReasonModal dengan konfirmasi ringkas
        await user.click(deleteBtn);
        const dialog = screen.getByRole('dialog');
        expect(dialog).toBeTruthy();
        expect(within(dialog).getByText(/Apakah Anda yakin ingin menghapus persyaratan "Laporan Capaian Kinerja Triwulan"\?/i)).toBeTruthy();
        // Paragraf panjang sensitif yang diaudit harus sudah hilang
        expect(screen.queryByText(/Penghapusan katalog jenis berkas bersifat sensitif/i)).toBeNull();
    });

    it('merender komponen Pagination untuk tabel persyaratan jenis berkas', () => {
        render(
            <JenisBerkasIndex
                jenisBerkasList={mockJenisBerkas}
                indikators={[]}
                can={{ create: true, update: true, delete: true }}
            />
        );

        const meta = screen.getByTestId('pagination-meta');
        expect(meta).toBeTruthy();
        expect(meta.textContent).toContain('1 - 1 dari 1');
    });
});
