import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import PengaturanIndex from '@/Pages/Pengaturan/Index';
import type { PengaturanIndexProps } from '@/types/pengaturan';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Head: () => null,
        usePage: () => ({
            props: {
                auth: { user: { id: 'admin-id', nama: 'Admin', email: 'admin@example.test', is_active: true, role: 'admin' }, can: { pengaturan: true } },
                flash: {},
            },
        }),
    };
});

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, title }: { children: ReactNode; title?: string }) => (
        <main>
            {title && <h1>{title}</h1>}
            {children}
        </main>
    ),
}));

const mockProps: PengaturanIndexProps = {
    grouped: {
        instansi: [
            {
                kunci: 'instansi.nama',
                nilai: 'Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
                tipe: 'string',
                grup: 'instansi',
                label: 'Nama Instansi',
                updated_at: '2026-09-20T10:00:00Z',
                updated_by: { id: 'admin-1', nama: 'Superadmin' },
            },
            {
                kunci: 'instansi.alamat',
                nilai: 'Jl. Kampus Barat, Gorontalo',
                tipe: 'text',
                grup: 'instansi',
                label: 'Alamat Instansi',
                updated_at: '2026-09-20T10:00:00Z',
                updated_by: { id: 'admin-1', nama: 'Superadmin' },
            },
        ],
        aplikasi: [
            {
                kunci: 'aplikasi.nama',
                nilai: 'SAKIP LLDIKTI XVI',
                tipe: 'string',
                grup: 'aplikasi',
                label: 'Nama Aplikasi',
                updated_at: '2026-09-20T10:00:00Z',
                updated_by: { id: 'admin-1', nama: 'Superadmin' },
            },
        ],
        tampilan: [
            {
                kunci: 'tampilan.zona_waktu',
                nilai: 'Asia/Makassar',
                tipe: 'string',
                grup: 'tampilan',
                label: 'Zona Waktu',
                updated_at: '2026-09-20T10:00:00Z',
                updated_by: { id: 'admin-1', nama: 'Superadmin' },
            },
        ],
        laporan: [
            {
                kunci: 'laporan.header',
                nilai: 'KEMENTERIAN PENDIDIKAN TINGGI',
                tipe: 'text',
                grup: 'laporan',
                label: 'Header Laporan',
                updated_at: '2026-09-20T10:00:00Z',
                updated_by: { id: 'admin-1', nama: 'Superadmin' },
            },
        ],
    },
    values: {
        'instansi.nama': 'Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
        'instansi.alamat': 'Jl. Kampus Barat, Gorontalo',
        'instansi.telepon': '(0435) 821123',
        'instansi.surel': 'lldikti16@kemdikbud.go.id',
        'instansi.laman': 'https://lldikti16.kemdikbud.go.id',
        'instansi.logo': '/img/dikti16-favicon-blue-150x150.png',
        'aplikasi.nama': 'SAKIP LLDIKTI XVI',
        'aplikasi.label_unit': 'Unit Kerja',
        'tampilan.zona_waktu': 'Asia/Makassar',
        'tampilan.format_tanggal': 'd F Y',
        'tampilan.format_angka': 'id_ID',
        'laporan.header': 'KEMENTERIAN PENDIDIKAN TINGGI',
        'laporan.footer': 'Dicetak dari SAKIP LLDIKTI XVI',
    },
};

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));

beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});

afterAll(() => {
    dialogMethods.forEach((name, index) => {
        const descriptor = originalDialogMethods[index];
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    });
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('PengaturanIndex Frontend', () => {
    it('merender judul halaman dan 4 tab pengaturan', () => {
        render(<PengaturanIndex {...mockProps} />);

        expect(screen.getByRole('heading', { level: 1, name: 'Pengaturan Sistem' })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Identitas Instansi/ })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Identitas Aplikasi/ })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Preferensi Tampilan/ })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Format Laporan/ })).toBeTruthy();
    });

    it('dapat berpindah tab dan menampilkan input yang sesuai', async () => {
        const user = userEvent.setup();
        render(<PengaturanIndex {...mockProps} />);

        // Default tab adalah Identitas Instansi
        expect(screen.getByLabelText(/Nama Instansi/)).toBeTruthy();

        // Pindah ke Identitas Aplikasi
        await user.click(screen.getByRole('button', { name: /Identitas Aplikasi/ }));
        expect(screen.getByLabelText(/Nama Aplikasi/)).toBeTruthy();
        expect(screen.getByLabelText(/Label Nomenklatur Unit Kerja/)).toBeTruthy();

        // Pindah ke Preferensi Tampilan
        await user.click(screen.getByRole('button', { name: /Preferensi Tampilan/ }));
        expect(screen.getByLabelText(/Zona Waktu Standar/)).toBeTruthy();
        expect(screen.getByLabelText(/Format Tanggal Standar/)).toBeTruthy();

        // Pindah ke Format Laporan
        await user.click(screen.getByRole('button', { name: /Format Laporan/ }));
        expect(screen.getByLabelText(/Header \/ Kop Laporan/)).toBeTruthy();
        expect(screen.getByLabelText(/Footer \/ Catatan Kaki Laporan/)).toBeTruthy();
    });

    it('menampilkan dialog konfirmasi audit ketika tombol simpan diklik setelah ada perubahan', async () => {
        const user = userEvent.setup();
        render(<PengaturanIndex {...mockProps} />);

        const inputNama = screen.getByLabelText<HTMLInputElement>(/Nama Instansi/);
        await user.clear(inputNama);
        await user.type(inputNama, 'Nama Instansi Diperbarui');

        const saveButton = screen.getByRole('button', { name: /Simpan Pengaturan/ });
        expect(saveButton.hasAttribute('disabled')).toBe(false);

        await user.click(saveButton);

        // Dialog konfirmasi audit harus terbuka
        expect(screen.getByText('Konfirmasi Perubahan Pengaturan')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Konfirmasi & Simpan' })).toBeTruthy();
    });

    it('menolak submit konfirmasi jika alasan audit kurang dari 5 karakter', async () => {
        const user = userEvent.setup();
        render(<PengaturanIndex {...mockProps} />);

        const inputNama = screen.getByLabelText<HTMLInputElement>(/Nama Instansi/);
        await user.clear(inputNama);
        await user.type(inputNama, 'Nama Instansi Diperbarui');

        await user.click(screen.getByRole('button', { name: /Simpan Pengaturan/ }));

        // Isi alasan kurang dari 5 karakter
        const inputAlasan = screen.getByLabelText(/Alasan perubahan/i);
        await user.type(inputAlasan, 'test');

        await user.click(screen.getByRole('button', { name: 'Konfirmasi & Simpan' }));

        expect(screen.getByText(/Harap berikan alasan pembaruan minimal 5 karakter/i)).toBeTruthy();
    });

    it('mereset input ke nilai baseline ketika tombol kembalikan diklik', async () => {
        const user = userEvent.setup();
        render(<PengaturanIndex {...mockProps} />);

        const inputNama = screen.getByLabelText<HTMLInputElement>(/Nama Instansi/);
        await user.clear(inputNama);
        await user.type(inputNama, 'Perubahan Sementara');
        expect(inputNama.value).toBe('Perubahan Sementara');

        const resetButton = screen.getByRole('button', { name: /Kembalikan/ });
        expect(resetButton.hasAttribute('disabled')).toBe(false);
        await user.click(resetButton);

        expect(inputNama.value).toBe('Lembaga Layanan Pendidikan Tinggi Wilayah XVI');
    });
});
