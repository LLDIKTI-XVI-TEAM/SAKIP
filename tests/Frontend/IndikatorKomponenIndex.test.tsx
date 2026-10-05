import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import KomponenIndex, {
    IndikatorKinerjaData,
    KomponenItem,
    FormulaContractData,
    DefinisiValidationData,
} from '@/Pages/Indikator/Komponen/Index';

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

describe('IndikatorKomponenIndex UI & Reusable Component Standardization', () => {
    beforeEach(() => {
        vi.spyOn(router, 'post').mockImplementation(() => undefined);
        vi.spyOn(router, 'put').mockImplementation(() => undefined);
        vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    const mockIndikator: IndikatorKinerjaData = {
        id: 'ind-1',
        kode: 'IKU-01',
        nama: 'Persentase Lulusan yang Mendapatkan Pekerjaan',
        satuan: '%',
        arah: 'naik_baik',
        tipe_perhitungan: 'rasio_persen',
        desimal_tampilan: 2,
        presisi: 2,
        is_aktif: true,
        unit: {
            id: 'unit-1',
            nama: 'Direktorat Akademik dan Kemahasiswaan',
        },
    };

    const mockKomponen: KomponenItem[] = [
        {
            id: 'komp-1',
            indikator_id: 'ind-1',
            kode: 'LULUS_KERJA',
            label: 'Jumlah Lulusan yang Bekerja',
            peran: 'pembilang',
            bobot: '1.0000',
            urutan: 1,
            satuan: 'Orang',
            aktif: true,
        },
        {
            id: 'komp-2',
            indikator_id: 'ind-1',
            kode: 'TOTAL_LULUS',
            label: 'Jumlah Total Lulusan',
            peran: 'penyebut',
            bobot: '1.0000',
            urutan: 2,
            satuan: 'Orang',
            aktif: true,
        },
    ];

    const mockFormulaContract: FormulaContractData = {
        tipe_perhitungan: 'rasio_persen',
        formula_text: '(LULUS_KERJA / TOTAL_LULUS) * 100',
        is_valid: true,
        messages: [],
        komponen_list: [
            {
                kode: 'LULUS_KERJA',
                label: 'Jumlah Lulusan yang Bekerja',
                peran: 'pembilang',
                bobot: 1.0,
                urutan: 1,
                satuan: 'Orang',
            },
            {
                kode: 'TOTAL_LULUS',
                label: 'Jumlah Total Lulusan',
                peran: 'penyebut',
                bobot: 1.0,
                urutan: 2,
                satuan: 'Orang',
            },
        ],
    };

    const mockValidation: DefinisiValidationData = {
        is_valid: true,
        messages: [],
    };

    it('merender tombol Tambah Komponen di headerAction dan menghilangkan banner/jargon berlebihan', () => {
        render(
            <KomponenIndex
                indikator={mockIndikator}
                komponen={mockKomponen}
                formulaContract={mockFormulaContract}
                validation={mockValidation}
                can={{ create: true, update: true, delete: true }}
            />
        );

        // Tombol Tambah harus berada di slot headerAction
        const headerSlot = screen.getByTestId('header-action-slot');
        expect(headerSlot.textContent).toContain('Tambah Komponen');

        // Jargon backend / deskripsi berlebihan yang diaudit sudah hilang
        expect(screen.queryByText(/sumber kebenaran tunggal/i)).toBeNull();
        expect(screen.queryByText(/evaluasi server-side/i)).toBeNull();
        expect(screen.queryByText(/simulasi klien/i)).toBeNull();
        expect(screen.queryByText('Formula baku evaluasi capaian kinerja indikator.')).toBeNull();
        expect(screen.queryByText('Penjumlahan Tertimbang')).toBeNull();

        // Tombol BackButton dirender
        expect(screen.getByText('Kembali ke Indikator Kinerja')).toBeTruthy();
    });

    it('membuat label komponen di tabel dapat diklik untuk membuka modal ubah', async () => {
        const user = userEvent.setup();
        render(
            <KomponenIndex
                indikator={mockIndikator}
                komponen={mockKomponen}
                formulaContract={mockFormulaContract}
                validation={mockValidation}
                can={{ create: true, update: true, delete: true }}
            />
        );

        // Cari label komponen di baris tabel dan klik
        const clickableLabelBtn = screen.getByText('Jumlah Lulusan yang Bekerja');
        await user.click(clickableLabelBtn);

        // Modal ubah terbuka dengan header yang distandardisasi
        const dialog = screen.getByRole('dialog');
        expect(dialog).toBeTruthy();
        expect(within(dialog).getByText('Edit Komponen Indikator')).toBeTruthy();
        // Memastikan prop description tidak ada di modal
        expect(screen.queryByText('Perbarui definisi komponen indikator data-driven. Perubahan memerlukan pengisian alasan audit.')).toBeNull();
    });

    it('menggunakan EditIconButton dan DeleteIconButton di kolom aksi', async () => {
        const user = userEvent.setup();
        render(
            <KomponenIndex
                indikator={mockIndikator}
                komponen={mockKomponen}
                formulaContract={mockFormulaContract}
                validation={mockValidation}
                can={{ create: true, update: true, delete: true }}
            />
        );

        // Tombol aksi harus memiliki accessible label standar
        const editBtn = screen.getByRole('button', { name: 'Edit komponen LULUS_KERJA' });
        const deleteBtn = screen.getByRole('button', { name: 'Hapus komponen LULUS_KERJA' });

        expect(editBtn).toBeTruthy();
        expect(deleteBtn).toBeTruthy();

        // Klik tombol hapus membuka AuditReasonModal dengan konfirmasi ringkas
        await user.click(deleteBtn);
        const dialog = screen.getByRole('dialog');
        expect(dialog).toBeTruthy();
        expect(within(dialog).getByText(/Apakah Anda yakin ingin menghapus komponen "LULUS_KERJA \(Jumlah Lulusan yang Bekerja\)"\?/i)).toBeTruthy();
        // Teks alasan panjang berlebihan tidak muncul
        expect(screen.queryByText(/Tindakan ini dicatat ke log audit dan memerlukan alasan resmi/i)).toBeNull();
    });

    it('merender komponen Pagination untuk tabel komponen angka', () => {
        render(
            <KomponenIndex
                indikator={mockIndikator}
                komponen={mockKomponen}
                formulaContract={mockFormulaContract}
                validation={mockValidation}
                can={{ create: true, update: true, delete: true }}
            />
        );

        const meta = screen.getByTestId('pagination-meta');
        expect(meta).toBeTruthy();
        expect(meta.textContent).toContain('1 - 2 dari 2');
    });
});
