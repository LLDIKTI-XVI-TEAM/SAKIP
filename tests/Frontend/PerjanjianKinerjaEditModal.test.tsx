import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { PerjanjianKinerjaEditModal } from '@/Pages/PerjanjianKinerja/Partials/PerjanjianKinerjaEditModal';
import type { RenstraPkSummary } from '@/types/perjanjian-kinerja';

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

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

afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

const samplePk: RenstraPkSummary = {
    id: 'pk-uuid-1',
    renstra_id: 'renstra-uuid-1',
    tahun: 2026,
    nomor_pk: 'PK/LLDIKTI16/2026/001',
    tanggal_pk: '2026-01-15T00:00:00.000000Z',
    creator: {
        id: 'user-uuid-1',
        nama: 'Superadmin',
    },
    created_at: '2026-01-15T00:00:00.000000Z',
    updated_at: '2026-01-15T00:00:00.000000Z',
    renstra: {
        id: 'renstra-uuid-1',
        kode: 'RENSTRA-2025',
        nama: 'Renstra 2025-2029',
        tahun_mulai: 2025,
        tahun_selesai: 2029,
    },
};

const samplePk2: RenstraPkSummary = {
    id: 'pk-uuid-2',
    renstra_id: 'renstra-uuid-1',
    tahun: 2027,
    nomor_pk: 'PK/LLDIKTI16/2027/002',
    tanggal_pk: '2027-01-20T00:00:00.000000Z',
    creator: {
        id: 'user-uuid-1',
        nama: 'Superadmin',
    },
    created_at: '2027-01-20T00:00:00.000000Z',
    updated_at: '2027-01-20T00:00:00.000000Z',
    renstra: {
        id: 'renstra-uuid-1',
        kode: 'RENSTRA-2025',
        nama: 'Renstra 2025-2029',
        tahun_mulai: 2025,
        tahun_selesai: 2029,
    },
};

describe('PerjanjianKinerjaEditModal Lifecycle & Concurrency', () => {
    it('mengisi formulir dengan data awal saat modal dibuka', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        expect(nomorInput.value).toBe('PK/LLDIKTI16/2026/001');

        const tanggalInput = screen.getByLabelText<HTMLInputElement>(/Tanggal Penandatanganan/);
        expect(tanggalInput.value).toBe('2026-01-15');
    });

    it('mempertahankan input koreksi pengguna saat props pk di-refresh dengan object reference baru selagi modal terbuka', () => {
        const { rerender } = render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        act(() => {
            fireEvent.change(nomorInput, { target: { value: 'PK/KOREKSI/USER/999' } });
        });
        expect(nomorInput.value).toBe('PK/KOREKSI/USER/999');

        const alasanInput = screen.getByLabelText<HTMLInputElement>(/Alasan Perubahan/);
        act(() => {
            fireEvent.change(alasanInput, { target: { value: 'Koreksi nomor surat resmi' } });
        });
        expect(alasanInput.value).toBe('Koreksi nomor surat resmi');

        // Simulasikan props refresh dari Inertia (object reference baru untuk pk yang sama)
        const refreshedPk: RenstraPkSummary = {
            ...samplePk,
            updated_at: '2026-01-16T10:00:00.000000Z',
        };

        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={refreshedPk}
            />
        );

        // Input koreksi pengguna tidak boleh ter-reset ke data server
        expect(nomorInput.value).toBe('PK/KOREKSI/USER/999');
        expect(alasanInput.value).toBe('Koreksi nomor surat resmi');
    });

    it('mereset formulir ke data baru saat beralih ke PK yang berbeda', () => {
        const { rerender } = render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        expect(nomorInput.value).toBe('PK/LLDIKTI16/2026/001');

        // Beralih ke PK lain
        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk2}
            />
        );

        expect(nomorInput.value).toBe('PK/LLDIKTI16/2027/002');
        const tanggalInput = screen.getByLabelText<HTMLInputElement>(/Tanggal Penandatanganan/);
        expect(tanggalInput.value).toBe('2027-01-20');
    });

    it('mereset formulir ke nilai baseline saat modal ditutup lalu dibuka kembali', () => {
        const { rerender } = render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        act(() => {
            fireEvent.change(nomorInput, { target: { value: 'PK/SEMENTARA' } });
        });
        expect(nomorInput.value).toBe('PK/SEMENTARA');

        // Tutup modal
        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={false}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        // Buka kembali modal
        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const reopenedNomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        expect(reopenedNomorInput.value).toBe('PK/LLDIKTI16/2026/001');
    });

    it('menampilkan banner informasi saat jadwal tahunan aktif atau terkunci', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                isJadwalAktif={true}
            />
        );

        expect(screen.getByText(/^Jadwal tahunan aktif: lampiran yang ada tidak dapat dihapus/)).toBeTruthy();
    });

    it('menyembunyikan banner informasi saat jadwal tahunan belum aktif / tidak terkunci', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                isJadwalAktif={false}
            />
        );

        expect(screen.queryByText(/lampiran yang ada tidak dapat dihapus/)).toBeNull();
    });

    it('menampilkan banner jadwal ditutup terkunci saat jadwalStatus ditutup', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                isJadwalTerkunci={true}
                jadwalStatus="ditutup"
            />
        );

        expect(screen.getByText(/^Jadwal tahunan ditutup: lampiran yang ada tidak dapat dihapus/)).toBeTruthy();
    });
});

