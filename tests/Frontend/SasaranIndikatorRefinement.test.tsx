import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/Perencanaan/SasaranIndikator/Index';
import type {
    IndikatorKinerjaItem,
    RegulasiOption,
    SasaranIndikatorCapabilities,
    SasaranStrategisItem,
} from '@/types/sasaran-indikator';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const defaultRegulasi: RegulasiOption = {
    id: 'reg-1',
    jenis: 'kepmen',
    nomor: '358/M/KEP/2025',
    tahun: 2025,
    tentang: 'Indikator Kinerja Utama Perguruan Tinggi',
};

function makeIndikator(overrides: Partial<IndikatorKinerjaItem> = {}): IndikatorKinerjaItem {
    return {
        id: 'ind-1',
        sasaran_strategis_id: 'sas-1',
        regulasi_id: null,
        kode: 'IKU-01',
        nama: 'Persentase capaian kinerja',
        definisi_operasional: null,
        satuan: '%',
        unit_id: 'unit-a',
        unit_nama: 'Unit A',
        arah: 'naik_baik',
        tipe_perhitungan: 'manual',
        presisi: 2,
        desimal_tampilan: 2,
        wajib_catatan: false,
        status: 'aktif',
        ...overrides,
    };
}

function makeSasaran(indikators: IndikatorKinerjaItem[]): SasaranStrategisItem {
    return {
        id: 'sas-1',
        renstra_id: 'ren-1',
        kode: 'SS-01',
        deskripsi: 'Terwujudnya tata kelola yang akuntabel',
        urutan: 1,
        indikator_kinerjas: indikators,
    };
}

const defaultCan: SasaranIndikatorCapabilities = {
    sasaran_create: true,
    sasaran_update: true,
    sasaran_delete: true,
    indikator_create: true,
    indikator_read: true,
    indikator_update: true,
    indikator_delete: true,
    regulasi_read: true,
    komponen_read: true,
};

function renderIndex(
    indikators: IndikatorKinerjaItem[] = [makeIndikator()],
    can: SasaranIndikatorCapabilities = defaultCan
) {
    return render(
        <Index
            renstras={[
                {
                    id: 'ren-1',
                    kode: 'REN-2025',
                    nama: 'Renstra 2025-2029',
                    tahun_mulai: 2025,
                    tahun_selesai: 2029,
                    is_aktif: true,
                },
            ]}
            selectedRenstraId="ren-1"
            sasarans={[makeSasaran(indikators)]}
            units={[
                { id: 'unit-a', nama: 'Unit A' },
            ]}
            regulasis={[defaultRegulasi]}
            can={can}
        />
    );
}

describe('Sasaran & Indikator UI Refinement Presentation Tests', () => {
    it('TEST-UI-01: Indikator dengan regulasi menampilkan identitas regulasi ringkas pada daftar', () => {
        const ind = makeIndikator({
            regulasi_id: defaultRegulasi.id,
            regulasi: defaultRegulasi,
        });

        renderIndex([ind]);

        expect(screen.getByText('Kepmen 358/M/KEP/2025')).toBeTruthy();
    });

    it('TEST-UI-02: Indikator tanpa regulasi tidak menampilkan metadata regulasi kosong atau placeholder bocor', () => {
        const ind = makeIndikator({
            regulasi_id: null,
            regulasi: null,
        });

        renderIndex([ind]);

        expect(screen.queryByText(/Kepmen/i)).toBeNull();
        expect(screen.queryByText(/Tidak ada regulasi/i)).toBeNull();
        expect(screen.queryByText(/undefined/i)).toBeNull();
        expect(screen.queryByText(/null/i)).toBeNull();
    });

    it('TEST-UI-03: Indikator status = arsip menampilkan badge eksplisit Arsip', () => {
        const indArsip = makeIndikator({
            kode: 'IKU-ARSIP',
            status: 'arsip',
        });

        renderIndex([indArsip]);

        const badge = screen.getByText('Arsip');
        expect(badge).toBeTruthy();
    });

    it('TEST-UI-04: Indikator rasio_persen menampilkan label Rasio Persen', () => {
        const ind = makeIndikator({
            tipe_perhitungan: 'rasio_persen',
        });

        renderIndex([ind]);

        expect(screen.getByText('Rasio Persen')).toBeTruthy();
    });

    it('TEST-UI-05: Indikator penjumlahan menampilkan label Penjumlahan', () => {
        const ind = makeIndikator({
            tipe_perhitungan: 'penjumlahan',
        });

        renderIndex([ind]);

        expect(screen.getByText('Penjumlahan')).toBeTruthy();
    });

    it('TEST-UI-06: Indikator manual menampilkan label Manual', () => {
        const ind = makeIndikator({
            tipe_perhitungan: 'manual',
        });

        renderIndex([ind]);

        expect(screen.getByText('Manual')).toBeTruthy();
    });

    it('TEST-UI-07: Permission gate action existing tetap bekerja dan menyembunyikan aksi jika can = false', () => {
        const ind = makeIndikator({
            kode: 'IKU-PERM',
            tipe_perhitungan: 'rasio_persen',
        });

        // Saat permission diizinkan
        const { unmount } = renderIndex([ind], {
            ...defaultCan,
            indikator_update: true,
            indikator_delete: true,
            komponen_read: true,
            komponen_create: true,
        });

        expect(screen.getByLabelText('Atur formula indikator IKU-PERM')).toBeTruthy();
        expect(screen.getByLabelText('Ubah indikator IKU-PERM')).toBeTruthy();
        expect(screen.getByLabelText('Pindah unit indikator IKU-PERM')).toBeTruthy();
        expect(screen.getByLabelText('Hapus indikator IKU-PERM')).toBeTruthy();
        expect(screen.getByLabelText('Kelola komponen IKU-PERM')).toBeTruthy();

        unmount();

        // Saat permission dilarang
        renderIndex([ind], {
            ...defaultCan,
            indikator_update: false,
            indikator_delete: false,
            komponen_read: false,
        });

        expect(screen.queryByLabelText('Atur formula indikator IKU-PERM')).toBeNull();
        expect(screen.queryByLabelText('Ubah indikator IKU-PERM')).toBeNull();
        expect(screen.queryByLabelText('Pindah unit indikator IKU-PERM')).toBeNull();
        expect(screen.queryByLabelText('Hapus indikator IKU-PERM')).toBeNull();
        expect(screen.queryByLabelText('Kelola komponen IKU-PERM')).toBeNull();
    });
});
