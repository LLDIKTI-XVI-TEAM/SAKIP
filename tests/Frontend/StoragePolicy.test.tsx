import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import StorageIndex, { formatBytes, type StorageIndexProps } from '@/Pages/Pengaturan/StorageIndex';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Head: () => null,
    };
});

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) =>
    Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name)
);

beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', {
        configurable: true,
        value: function (this: HTMLDialogElement) {
            this.open = true;
        },
    });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', {
        configurable: true,
        value: function (this: HTMLDialogElement) {
            this.open = false;
        },
    });
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

const defaultProps: StorageIndexProps = {
    settings: {
        berkas_unggahan_aktif: true,
        berkas_ukuran_maks_kb: 10240,
        berkas_format_diizinkan: 'pdf,docx,xlsx,jpg,jpeg,png',
        berkas_tautan_selalu_diizinkan: true,
        expected_updated_at: '2026-09-23T10:00:00.000Z',
    },
    metrics: {
        file_count: 42,
        file_total_bytes: 52428800, // 50 MB
        link_count: 15,
        text_count: 8,
        total_evidence_count: 65,
        by_induk: {
            rencana_aksi: {
                induk: 'rencana_aksi',
                label: 'Rencana Aksi',
                file_count: 10,
                file_bytes: 10485760,
                link_count: 3,
                text_count: 2,
                total_count: 15,
            },
            pengukuran: {
                induk: 'pengukuran',
                label: 'Pengukuran Kinerja',
                file_count: 20,
                file_bytes: 26214400,
                link_count: 7,
                text_count: 4,
                total_count: 31,
            },
            kegiatan: {
                induk: 'kegiatan',
                label: 'Kegiatan',
                file_count: 5,
                file_bytes: 5242880,
                link_count: 2,
                text_count: 1,
                total_count: 8,
            },
            renstra: {
                induk: 'renstra',
                label: 'Rencana Strategis (Renstra)',
                file_count: 2,
                file_bytes: 4194304,
                link_count: 1,
                text_count: 0,
                total_count: 3,
            },
            renstra_pk: {
                induk: 'renstra_pk',
                label: 'Perjanjian Kinerja (PK)',
                file_count: 2,
                file_bytes: 2097152,
                link_count: 1,
                text_count: 0,
                total_count: 3,
            },
            regulasi: {
                induk: 'regulasi',
                label: 'Dasar Aturan (Regulasi)',
                file_count: 3,
                file_bytes: 4194304,
                link_count: 1,
                text_count: 1,
                total_count: 5,
            },
        },
    },
    can: {
        update: true,
    },
};

describe('StorageIndex Component', () => {
    it('formatBytes mengonversi byte ke unit yang tepat', () => {
        expect(formatBytes(0)).toBe('0 B');
        expect(formatBytes(500)).toBe('500 B');
        expect(formatBytes(204800)).toBe('200 KB');
        expect(formatBytes(10485760)).toBe('10 MB');
        expect(formatBytes(1073741824)).toBe('1 GB');
    });

    it('merender metrik ringkasan storage dengan benar', () => {
        render(<StorageIndex {...defaultProps} />);

        expect(screen.getByTestId('total-bytes-display').textContent).toBe('50 MB');
        expect(screen.getByTestId('file-count-display').textContent).toBe('42');
        expect(screen.getByTestId('link-count-display').textContent).toBe('15');
        expect(screen.getByTestId('text-count-display').textContent).toBe('8');

        // Memeriksa keberadaan label induk
        expect(screen.getByText('Rencana Aksi')).toBeTruthy();
        expect(screen.getByText('Pengukuran Kinerja')).toBeTruthy();
        expect(screen.getByText('Dasar Aturan (Regulasi)')).toBeTruthy();
    });

    it('merender form input aktif dan membuka modal alasan audit saat tombol simpan diklik', async () => {
        const user = userEvent.setup();
        render(<StorageIndex {...defaultProps} />);

        const sizeInput = screen.getByLabelText(/Batas Ukuran Berkas Default/i) as HTMLInputElement;
        expect(sizeInput.value).toBe('10240');
        expect(sizeInput.disabled).toBe(false);

        const formatInput = screen.getByLabelText(/Daftar Format File Default/i) as HTMLInputElement;
        expect(formatInput.value).toBe('pdf,docx,xlsx,jpg,jpeg,png');
        expect(formatInput.disabled).toBe(false);

        // Klik tombol simpan
        const submitBtn = screen.getByRole('button', { name: /Simpan Kebijakan Storage/i });
        await user.click(submitBtn);

        // Modal alasan audit harus muncul
        expect(screen.getByText(/Konfirmasi Perubahan Kebijakan Storage/i)).toBeTruthy();
        expect(screen.getByRole('textbox', { name: /Alasan/i })).toBeTruthy();
    });

    it('merender mode read-only ketika can.update bernilai false', () => {
        const readOnlyProps: StorageIndexProps = {
            ...defaultProps,
            can: {
                update: false,
            },
        };

        render(<StorageIndex {...readOnlyProps} />);

        expect(screen.getByText('Mode Pratinjau (Hanya Baca)')).toBeTruthy();

        const switchBtn = screen.getByRole('switch', { name: /Status saklar unggahan berkas/i }) as HTMLButtonElement;
        expect(switchBtn).toBeTruthy();
        expect(switchBtn.disabled).toBe(true);

        const sizeInput = screen.getByLabelText(/Batas Ukuran Berkas Default/i) as HTMLInputElement;
        expect(sizeInput.disabled).toBe(true);

        const formatInput = screen.getByLabelText(/Daftar Format File Default/i) as HTMLInputElement;
        expect(formatInput.disabled).toBe(true);

        // Tombol simpan tidak boleh ada
        expect(screen.queryByRole('button', { name: /Simpan Kebijakan Storage/i })).toBeNull();
    });

    it('menampilkan peringatan khusus saat saklar unggahan file dimatikan', () => {
        const disabledUploadProps: StorageIndexProps = {
            ...defaultProps,
            settings: {
                ...defaultProps.settings,
                berkas_unggahan_aktif: false,
            },
        };

        render(<StorageIndex {...disabledUploadProps} />);

        expect(screen.getByText(/Saklar Unggahan File Global Sedang Dinonaktifkan/i)).toBeTruthy();
        expect(screen.getByText(/Unggahan Nonaktif/i)).toBeTruthy();
    });

    it('menyinkronkan formulir saat expected_updated_at pada props diperbarui', () => {
        const { rerender } = render(<StorageIndex {...defaultProps} />);

        const updatedProps: StorageIndexProps = {
            ...defaultProps,
            settings: {
                ...defaultProps.settings,
                berkas_ukuran_maks_kb: 20480,
                expected_updated_at: '2026-09-23T11:00:00.000Z',
            },
        };

        rerender(<StorageIndex {...updatedProps} />);

        const sizeInput = screen.getByLabelText(/Batas Ukuran Berkas Default/i) as HTMLInputElement;
        expect(sizeInput.value).toBe('20480');
    });
});
