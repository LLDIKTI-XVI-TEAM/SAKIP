import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/Perencanaan/SasaranIndikator/Index';
import type {
    IndikatorKinerjaItem,
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

const UPDATED_AT = '2026-10-02T10:00:00.000Z';

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
        updated_at: UPDATED_AT,
        ...overrides,
    };
}

function makeSasaran(indikator: IndikatorKinerjaItem): SasaranStrategisItem {
    return {
        id: 'sas-1',
        renstra_id: 'ren-1',
        kode: 'SS-01',
        deskripsi: 'Terwujudnya tata kelola yang akuntabel',
        urutan: 1,
        indikator_kinerjas: [indikator],
    };
}

const fullCan: SasaranIndikatorCapabilities = {
    sasaran_create: true,
    sasaran_update: true,
    sasaran_delete: true,
    indikator_create: true,
    indikator_read: true,
    indikator_update: true,
    indikator_delete: true,
    komponen_read: true,
};

function renderIndex(
    indikator: IndikatorKinerjaItem = makeIndikator(),
    can: SasaranIndikatorCapabilities = fullCan
) {
    render(
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
            sasarans={[makeSasaran(indikator)]}
            units={[
                { id: 'unit-a', nama: 'Unit A' },
                { id: 'unit-b', nama: 'Unit B' },
            ]}
            regulasis={[]}
            can={can}
        />
    );
}

describe('Transisi Formula Atomik (R4-01)', () => {
    it('menampilkan tombol Atur Formula untuk indikator manual bila can.indikator_update', () => {
        renderIndex();
        expect(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' })).toBeTruthy();
    });

    it('menampilkan tombol Atur Formula juga untuk indikator nonmanual', () => {
        renderIndex(makeIndikator({ tipe_perhitungan: 'rasio_persen' }));
        expect(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' })).toBeTruthy();
    });

    it('menyembunyikan tombol Atur Formula bila can.indikator_update false', () => {
        renderIndex(makeIndikator(), { ...fullCan, indikator_update: false });
        expect(screen.queryByRole('button', { name: 'Atur formula indikator IKU-01' })).toBeNull();
    });

    it('membuka modal formula dengan tipe target dan satu baris komponen awal', async () => {
        const user = userEvent.setup();
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));

        expect(screen.getByRole('heading', { name: 'Atur Formula — IKU-01' })).toBeTruthy();
        expect(screen.getByLabelText(/tipe perhitungan target/i)).toBeTruthy();
        expect(screen.getByLabelText(/kode komponen 1/i)).toBeTruthy();
        expect(screen.getByLabelText(/label komponen 1/i)).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Simpan Formula' })).toBeTruthy();
    });

    it('mengarahkan helper text edit-manual ke aksi Atur Formula', async () => {
        const user = userEvent.setup();
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Ubah indikator IKU-01' }));

        expect(screen.getByText(/Gunakan aksi "Atur Formula" pada tabel/i)).toBeTruthy();
    });

    it('menolak submit kosong (kode/label belum diisi) tanpa memanggil PATCH', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'rasio_persen');
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(screen.getByText('Kode komponen wajib diisi.')).toBeTruthy();
        expect(screen.getByText('Label komponen wajib diisi.')).toBeTruthy();
        expect(patchSpy).not.toHaveBeenCalled();
    });

    it('menolak kode duplikat dan bobot penyebut nol tanpa memanggil PATCH', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'rasio_persen');

        await user.type(screen.getByLabelText(/kode komponen 1/i), 'N');
        await user.type(screen.getByLabelText(/label komponen 1/i), 'Nilai pembilang');

        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.type(screen.getByLabelText(/kode komponen 2/i), 'N');
        await user.type(screen.getByLabelText(/label komponen 2/i), 'Nilai penyebut');
        await user.selectOptions(screen.getByLabelText(/peran komponen 2/i), 'penyebut');
        await user.clear(screen.getByLabelText(/bobot komponen 2/i));
        await user.type(screen.getByLabelText(/bobot komponen 2/i), '0');

        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(screen.getByText('Kode komponen tidak boleh duplikat dalam satu transisi.')).toBeTruthy();
        expect(
            screen.getByText('Bobot untuk komponen dengan peran penyebut wajib lebih besar dari 0.')
        ).toBeTruthy();
        expect(patchSpy).not.toHaveBeenCalled();
    });

    it('mengirim PATCH formula dengan tipe, komponen, dan expected_updated_at saat valid', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'rasio_persen');

        await user.type(screen.getByLabelText(/kode komponen 1/i), 'N');
        await user.type(screen.getByLabelText(/label komponen 1/i), 'Nilai pembilang');

        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.type(screen.getByLabelText(/kode komponen 2/i), 'T');
        await user.type(screen.getByLabelText(/label komponen 2/i), 'Nilai penyebut');
        await user.selectOptions(screen.getByLabelText(/peran komponen 2/i), 'penyebut');

        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(patchSpy).toHaveBeenCalledTimes(1);
        expect(patchSpy.mock.calls[0]?.[0]).toBe('/perencanaan/indikator/ind-1/formula');

        const sentData = patchSpy.mock.calls[0]?.[1] as unknown as {
            tipe_perhitungan: string;
            komponen: Array<Record<string, unknown>>;
            expected_updated_at: string;
        };
        expect(sentData.tipe_perhitungan).toBe('rasio_persen');
        expect(sentData.expected_updated_at).toBe(UPDATED_AT);
        expect(sentData.komponen).toHaveLength(2);
        expect(sentData.komponen[0]?.['kode']).toBe('N');
        expect(sentData.komponen[0]?.['peran']).toBe('pembilang');
        expect(sentData.komponen[1]?.['kode']).toBe('T');
        expect(sentData.komponen[1]?.['peran']).toBe('penyebut');
    });

    it('menampilkan error server apa adanya dalam role=alert', async () => {
        const user = userEvent.setup();
        vi.spyOn(router, 'patch').mockImplementation(
            ((...args: unknown[]) => {
                const options = args[2] as { onError?: (errs: Record<string, string>) => void };
                return undefined;
            }) as unknown as typeof router.patch
        );
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'penjumlahan');
        await user.type(screen.getByLabelText(/kode komponen 1/i), 'N');
        await user.type(screen.getByLabelText(/label komponen 1/i), 'Nilai jumlah');
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        const options = vi.mocked(router.patch).mock.calls[0]?.[2] as unknown as {
            onError?: (errs: Record<string, string>) => void;
        };
        await act(async () => {
            options?.onError?.({
                konflik: 'Data indikator telah berubah. Muat ulang halaman.',
                'komponen.0.kode': 'Kode komponen sudah digunakan pada indikator ini.',
            });
        });

        const alert = screen.getByRole('alert');
        expect(alert.textContent).toContain('Data indikator telah berubah. Muat ulang halaman.');
        expect(alert.textContent).toContain('Kode komponen sudah digunakan pada indikator ini.');
    });
});
