import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { BuktiRencanaAksiPanel } from '@/Components/RencanaAksi/BuktiRencanaAksiPanel';
import type { BuktiRencanaAksiPageProps } from '@/types/rencana-aksi';

const mockPost = vi.fn();
const mockReset = vi.fn();
const mockClearErrors = vi.fn();

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        useForm: () => ({
            data: {
                jenis_berkas_id: '',
                mode: 'file',
                file: null,
                tautan: '',
                isi_teks: '',
                menggantikan_id: '',
                alasan_koreksi: '',
            },
            setData: vi.fn(),
            post: mockPost,
            processing: false,
            errors: {},
            reset: mockReset,
            clearErrors: mockClearErrors,
        }),
        router: {
            delete: vi.fn(),
        },
    };
});

// Setup mock route function for ziggy
(global as unknown as { route: (name: string, params?: unknown) => string }).route = (name: string, params?: unknown) => {
    return `/${name}/${JSON.stringify(params || {})}`;
};

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

const mockProps: BuktiRencanaAksiPageProps = {
    rencanaAksi: {
        id: 'ra-uuid-1',
        tahun: 2026,
        uraian: 'Rencana aksi pengujian frontend',
        status_alur: 'draft',
        versi: 1,
        is_disahkan: false,
        indikator: {
            id: 'ind-uuid-1',
            kode: 'IKU-01',
            nama: 'Persentase Layanan Tepat Waktu',
            satuan: 'persen',
        },
        unit: {
            id: 'unit-uuid-1',
            nama: 'Bagian Tata Usaha',
        },
        penanggungJawab: {
            id: 'user-uuid-1',
            nama: 'Adriel Walintukan',
        },
    },
    persyaratan: [
        {
            id: 'jb-uuid-1',
            nama: 'Dokumen Kerangka Acuan Kerja',
            keterangan: 'KAK yang telah ditandatangani pimpinan.',
            wajib: true,
            semua_mode_wajib: false,
            izinkan_file: true,
            izinkan_tautan: true,
            izinkan_teks: true,
            format_diizinkan: 'pdf,docx',
            ukuran_maks_kb: 5120,
            pemenuhan: {
                terpenuhi: true,
                mode_terpenuhi: ['file'],
                mode_kurang: [],
                mode_dikecualikan: [],
                tidak_dapat_dipenuhi: false,
                alasan_pengecualian: null,
            },
        },
        {
            id: 'jb-uuid-2',
            nama: 'Surat Keputusan Tim Pelaksana',
            keterangan: null,
            wajib: true,
            semua_mode_wajib: true,
            izinkan_file: true,
            izinkan_tautan: false,
            izinkan_teks: true,
            format_diizinkan: 'pdf',
            ukuran_maks_kb: 5120,
            pemenuhan: {
                terpenuhi: false,
                mode_terpenuhi: [],
                mode_kurang: ['file', 'teks'],
                mode_dikecualikan: [],
                tidak_dapat_dipenuhi: false,
                alasan_pengecualian: null,
            },
        },
    ],
    summary: {
        lengkap: false,
        total_wajib: 2,
        terpenuhi_wajib: 1,
        persyaratan: [],
    },
    daftarBukti: [
        {
            id: 'bukti-uuid-1',
            jenis_berkas_id: 'jb-uuid-1',
            nama_persyaratan: 'Dokumen Kerangka Acuan Kerja',
            mode: 'file',
            nama_asli: 'kak-final.pdf',
            mime: 'application/pdf',
            ukuran_bytes: 1048576,
            tautan: null,
            isi_teks: null,
            menggantikan_id: null,
            alasan_koreksi: null,
            uploaded_by: 'user-uuid-1',
            pengunggah_nama: 'Adriel Walintukan',
            created_at: '2026-03-10T10:00:00Z',
        },
    ],
    storageSettings: {
        unggahan_aktif: true,
        ukuran_maks_kb: 10240,
        format_diizinkan: 'pdf,docx,xlsx,jpg,jpeg,png',
    },
    can: {
        upload: true,
        delete: true,
    },
};

describe('BuktiRencanaAksiPanel Component', () => {
    it('merender ringkasan indikator dan status kelengkapan', () => {
        render(<BuktiRencanaAksiPanel {...mockProps} />);

        expect(screen.getByText(/IKU-01 - Persentase Layanan Tepat Waktu/i)).toBeInTheDocument();
        expect(screen.getByText(/Bagian Tata Usaha/i)).toBeInTheDocument();
        expect(screen.getByText(/Belum Lengkap \(1\/2\)/i)).toBeInTheDocument();
    });

    it('merender daftar persyaratan dan status pemenuhan masing-masing', () => {
        render(<BuktiRencanaAksiPanel {...mockProps} />);

        expect(screen.getByText('Dokumen Kerangka Acuan Kerja')).toBeInTheDocument();
        expect(screen.getByText('Surat Keputusan Tim Pelaksana')).toBeInTheDocument();
        expect(screen.getByText('Semua Mode Wajib')).toBeInTheDocument();

        // Ada badge Terpenuhi untuk KAK dan Kurang untuk SK Tim
        expect(screen.getByText('Terpenuhi')).toBeInTheDocument();
        expect(screen.getByText('Kurang')).toBeInTheDocument();
    });

    it('merender bukti fisik terunggah dengan tombol unduh', () => {
        render(<BuktiRencanaAksiPanel {...mockProps} />);

        expect(screen.getByText('kak-final.pdf')).toBeInTheDocument();
        expect(screen.getByText('(1 MB)')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Unduh/i })).toBeInTheDocument();
    });

    it('membuka modal tambah bukti saat tombol Tambah Bukti diklik', async () => {
        const user = userEvent.setup();
        render(<BuktiRencanaAksiPanel {...mockProps} />);

        const btnTambah = screen.getByRole('button', { name: /Tambah Bukti/i });
        await user.click(btnTambah);

        expect(screen.getByText('Tambah Bukti Dukung Rencana Aksi')).toBeInTheDocument();
    });

    it('menyembunyikan tombol mutasi jika rencana aksi berstatus disahkan', () => {
        const disahkanProps: BuktiRencanaAksiPageProps = {
            ...mockProps,
            rencanaAksi: {
                ...mockProps.rencanaAksi,
                status_alur: 'disahkan',
                is_disahkan: true,
            },
        };

        render(<BuktiRencanaAksiPanel {...disahkanProps} />);

        expect(screen.queryByRole('button', { name: /Tambah Bukti/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Penuhi/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Hapus Bukti/i })).not.toBeInTheDocument();
    });
});
