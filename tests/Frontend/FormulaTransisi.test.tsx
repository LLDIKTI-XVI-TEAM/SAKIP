import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { UserEvent } from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/Perencanaan/SasaranIndikator/Index';
import type {
    IndikatorKinerjaItem,
    FormulaKomponenItem,
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

const ALASAN = 'Penyesuaian formula sesuai arahan pimpinan.';

async function isiAlasan(user: UserEvent) {
    await user.type(screen.getByLabelText(/alasan perubahan formula/i), ALASAN);
}

const RASIO_KOMPONEN: FormulaKomponenItem[] = [
    { id: '00000000-0000-4000-8000-000000000001', kode: 'N', label: 'Capaian aktual', peran: 'pembilang', bobot: '1.000000000001', urutan: 1, satuan: 'Dokumen', aktif: true },
    { id: '00000000-0000-4000-8000-000000000002', kode: 'T', label: 'Total target', peran: 'penyebut', bobot: '1.000000000000', urutan: 2, satuan: 'Dokumen', aktif: true },
];

const JUMLAH_KOMPONEN: FormulaKomponenItem[] = [
    { id: '00000000-0000-4000-8000-000000000003', kode: 'A', label: 'Skor pertama', peran: 'penjumlah', bobot: '0.500000000000', urutan: 1, satuan: 'Skor', aktif: true },
    { id: '00000000-0000-4000-8000-000000000004', kode: 'B', label: 'Skor kedua', peran: 'penjumlah', bobot: '0.500000000000', urutan: 2, satuan: 'Skor', aktif: true },
];

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
        komponen: [],
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
    komponen_create: true,
    komponen_update: true,
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

describe('Editor formula atomik', () => {
    it.each([
        ['rasio_persen', RASIO_KOMPONEN],
        ['penjumlahan', JUMLAH_KOMPONEN],
    ] as const)('memulihkan ID komponen inactive saat manual kembali ke %s', async (tipe, komponen) => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ komponen: komponen.map((row) => ({ ...row, aktif: false })) }));
        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        expect(screen.queryByLabelText(/kode komponen 1/i)).toBeNull();
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), tipe);
        expect((screen.getByLabelText(/kode komponen 1/i) as HTMLInputElement).value).toBe(komponen[0].kode);
        expect((screen.getByLabelText(/kode komponen 2/i) as HTMLInputElement).value).toBe(komponen[1].kode);
        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));
        expect(patchSpy.mock.calls[0]?.[1]).toEqual({ tipe_perhitungan: tipe, komponen, expected_updated_at: UPDATED_AT, alasan: ALASAN });
    });

    it('menampilkan hanya komponen active saat formula existing nonmanual pertama dibuka', async () => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({
            tipe_perhitungan: 'rasio_persen',
            komponen: [...RASIO_KOMPONEN, { ...JUMLAH_KOMPONEN[0], aktif: false }],
        }));
        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        expect(screen.queryByLabelText(/kode komponen 3/i)).toBeNull();
        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));
        expect(patchSpy.mock.calls[0]?.[1]).toEqual({ tipe_perhitungan: 'rasio_persen', komponen: RASIO_KOMPONEN, expected_updated_at: UPDATED_AT, alasan: ALASAN });
    });

    it('tidak membuka transition dari manual ketika data komponen tidak dapat diakses', async () => {
        const user = userEvent.setup();
        renderIndex(makeIndikator({ komponen: null }));
        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        expect(screen.queryByLabelText(/tipe perhitungan target/i)).toBeNull();
        expect(screen.getByText(/Data komponen formula belum tersedia/i)).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Simpan Formula' }) as HTMLButtonElement).disabled).toBe(true);
    });

    it.each([undefined, null])('tidak menawarkan editor kosong bila props komponen formula existing %s', async (komponen) => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: 'rasio_persen', komponen }));
        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        expect(screen.getByText(/Data komponen formula belum tersedia/i)).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Tambah Komponen' })).toBeNull();
        expect((screen.getByRole('button', { name: 'Simpan Formula' }) as HTMLButtonElement).disabled).toBe(true);
        expect(patchSpy).not.toHaveBeenCalled();
    });

    it('mengirim satu mutation dan mempertahankan modal selama request belum selesai', async () => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: 'penjumlahan', komponen: JUMLAH_KOMPONEN }));
        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await isiAlasan(user);
        await user.dblClick(screen.getByRole('button', { name: 'Simpan Formula' }));
        await user.click(screen.getByRole('button', { name: 'Batal' }));
        expect(patchSpy).toHaveBeenCalledTimes(1);
        expect(screen.getByRole('heading', { name: 'Atur Formula — IKU-01' })).toBeTruthy();
    });

    it('mempertahankan modal dan input ketika callback sukses transport membawa error domain', async () => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: 'penjumlahan', komponen: JUMLAH_KOMPONEN }));
        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));
        const options = patchSpy.mock.calls[0]?.[2] as unknown as {
            onSuccess?: (page: { props: Record<string, unknown>; flash: { error: string } }) => void;
            onFinish?: () => void;
        };
        await act(async () => {
            options.onSuccess?.({ props: {}, flash: { error: 'Formula tidak tersimpan. Muat ulang halaman.' } });
            options.onFinish?.();
        });
        expect(screen.getByText('Formula tidak tersimpan. Muat ulang halaman.')).toBeTruthy();
        expect((screen.getByLabelText(/kode komponen 1/i) as HTMLInputElement).value).toBe('A');
        expect(screen.getByRole('heading', { name: 'Atur Formula — IKU-01' })).toBeTruthy();
    });

    it.each([
        ['rasio_persen', RASIO_KOMPONEN],
        ['penjumlahan', JUMLAH_KOMPONEN],
    ] as const)('menampilkan komponen existing %s dan mempertahankan identitas pada edit tipe sama', async (tipe, komponen) => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: tipe, komponen }));

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        expect((screen.getByLabelText(/kode komponen 1/i) as HTMLInputElement).value).toBe(komponen[0].kode);
        expect((screen.getByLabelText(/label komponen 2/i) as HTMLInputElement).value).toBe(komponen[1].label);
        expect((screen.getByLabelText(/bobot komponen 1/i) as HTMLInputElement).value).toBe(komponen[0].bobot);

        await user.clear(screen.getByLabelText(/label komponen 1/i));
        await user.type(screen.getByLabelText(/label komponen 1/i), 'Label dikoreksi');
        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(patchSpy).toHaveBeenCalledTimes(1);
        expect(patchSpy.mock.calls[0]?.[1]).toEqual({
            tipe_perhitungan: tipe,
            expected_updated_at: UPDATED_AT,
            alasan: ALASAN,
            komponen: [{ ...komponen[0], label: 'Label dikoreksi' }, komponen[1]],
        });
    });

    it('mengirim daftar final untuk transisi rasio ke penjumlahan tanpa menggandakan komponen existing', async () => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: 'rasio_persen', komponen: RASIO_KOMPONEN }));

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'penjumlahan');
        await user.selectOptions(screen.getByLabelText(/peran komponen 1/i), 'penjumlah');
        await user.click(screen.getByRole('button', { name: 'Hapus komponen 2' }));
        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(patchSpy.mock.calls[0]?.[1]).toEqual({
            tipe_perhitungan: 'penjumlahan', expected_updated_at: UPDATED_AT, alasan: ALASAN,
            komponen: [{ ...RASIO_KOMPONEN[0], peran: 'penjumlah' }],
        });
    });

    it('mengosongkan dan menyembunyikan komponen saat target manual serta mengirim daftar kosong', async () => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: 'rasio_persen', komponen: RASIO_KOMPONEN }));

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'manual');
        expect(screen.queryByLabelText(/kode komponen 1/i)).toBeNull();
        expect(screen.queryByRole('button', { name: 'Tambah Komponen' })).toBeNull();
        expect(screen.getByText(/komponen perhitungan existing akan dinonaktifkan/i)).toBeTruthy();
        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));
        expect(patchSpy.mock.calls[0]?.[1]).toEqual({ tipe_perhitungan: 'manual', komponen: [], expected_updated_at: UPDATED_AT, alasan: ALASAN });
    });

    it('mempertahankan input existing setelah error validasi dan stale dari server', async () => {
        const user = userEvent.setup();
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: 'penjumlahan', komponen: JUMLAH_KOMPONEN }));
        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.type(screen.getByLabelText(/label komponen 1/i), ' terkoreksi');
        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));
        const options = patchSpy.mock.calls[0]?.[2] as unknown as { onError?: (errors: Record<string, string>) => void };
        await act(async () => options.onError?.({ konflik: 'Versi formula berubah. Muat ulang halaman.', 'komponen.0.peran': 'Definisi formula tidak valid.' }));
        expect(screen.getByText('Versi formula berubah. Muat ulang halaman.')).toBeTruthy();
        expect((screen.getByLabelText(/label komponen 1/i) as HTMLInputElement).value).toBe('Skor pertama terkoreksi');
        expect((screen.getByLabelText(/label komponen 2/i) as HTMLInputElement).value).toBe('Skor kedua');
    });
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

    it('R8-01 parity: update-only menampilkan tombol Atur Formula', () => {
        renderIndex(makeIndikator(), { ...fullCan, indikator_update: true, komponen_read: true, komponen_create: false, komponen_update: true });
        expect(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' })).toBeTruthy();
    });

    it('R8-01 parity: create-tanpa-read menyembunyikan tombol Atur Formula', () => {
        renderIndex(makeIndikator(), { ...fullCan, indikator_update: true, komponen_read: false, komponen_create: true, komponen_update: false });
        expect(screen.queryByRole('button', { name: 'Atur formula indikator IKU-01' })).toBeNull();
    });

    it('R8-01 parity: read-only menyembunyikan tombol Atur Formula', () => {
        renderIndex(makeIndikator(), { ...fullCan, indikator_update: true, komponen_read: true, komponen_create: false, komponen_update: false });
        expect(screen.queryByRole('button', { name: 'Atur formula indikator IKU-01' })).toBeNull();
    });

    it('R8-01 parity: full menampilkan tombol Atur Formula', () => {
        renderIndex(makeIndikator(), { ...fullCan, indikator_update: true, komponen_read: true, komponen_create: true, komponen_update: true });
        expect(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' })).toBeTruthy();
    });

    it('membuka indikator manual dengan daftar komponen kosong tanpa baris palsu', async () => {
        const user = userEvent.setup();
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));

        expect(screen.getByRole('heading', { name: 'Atur Formula — IKU-01' })).toBeTruthy();
        expect(screen.getByLabelText(/tipe perhitungan target/i)).toBeTruthy();
        expect(screen.queryByLabelText(/kode komponen 1/i)).toBeNull();
        expect(screen.queryByRole('button', { name: 'Tambah Komponen' })).toBeNull();
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
        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(screen.getByText('Kode komponen wajib diisi.')).toBeTruthy();
        expect(screen.getByText('Label komponen wajib diisi.')).toBeTruthy();
        expect(screen.getByText('Alasan perubahan formula wajib diisi.')).toBeTruthy();
        expect(patchSpy).not.toHaveBeenCalled();
    });

    it('menolak submit tanpa alasan tanpa memanggil PATCH', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'rasio_persen');
        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));

        await user.type(screen.getByLabelText(/kode komponen 1/i), 'N');
        await user.type(screen.getByLabelText(/label komponen 1/i), 'Nilai pembilang');

        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.type(screen.getByLabelText(/kode komponen 2/i), 'T');
        await user.type(screen.getByLabelText(/label komponen 2/i), 'Nilai penyebut');
        await user.selectOptions(screen.getByLabelText(/peran komponen 2/i), 'penyebut');

        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(screen.getByText('Alasan perubahan formula wajib diisi.')).toBeTruthy();
        expect(patchSpy).not.toHaveBeenCalled();
    });

    it('menolak alasan terlalu pendek tanpa memanggil PATCH', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex(makeIndikator({ tipe_perhitungan: 'penjumlahan', komponen: JUMLAH_KOMPONEN }));

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.type(screen.getByLabelText(/alasan perubahan formula/i), 'abc');
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(screen.getByText('Alasan perubahan formula minimal 5 karakter.')).toBeTruthy();
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
        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));

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

    it('mengirim PATCH formula dengan tipe, komponen, expected_updated_at, dan alasan saat valid', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'rasio_persen');
        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));

        await user.type(screen.getByLabelText(/kode komponen 1/i), 'N');
        await user.type(screen.getByLabelText(/label komponen 1/i), 'Nilai pembilang');

        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.type(screen.getByLabelText(/kode komponen 2/i), 'T');
        await user.type(screen.getByLabelText(/label komponen 2/i), 'Nilai penyebut');
        await user.selectOptions(screen.getByLabelText(/peran komponen 2/i), 'penyebut');

        await isiAlasan(user);
        await user.click(screen.getByRole('button', { name: 'Simpan Formula' }));

        expect(patchSpy).toHaveBeenCalledTimes(1);
        expect(patchSpy.mock.calls[0]?.[0]).toBe('/perencanaan/indikator/ind-1/formula');

        const sentData = patchSpy.mock.calls[0]?.[1] as unknown as {
            tipe_perhitungan: string;
            komponen: Array<Record<string, unknown>>;
            expected_updated_at: string;
            alasan: string;
        };
        expect(sentData.tipe_perhitungan).toBe('rasio_persen');
        expect(sentData.expected_updated_at).toBe(UPDATED_AT);
        expect(sentData.alasan).toBe(ALASAN);
        expect(sentData.komponen).toHaveLength(2);
        expect(sentData.komponen[0]?.['kode']).toBe('N');
        expect(sentData.komponen[0]?.['peran']).toBe('pembilang');
        expect(sentData.komponen[1]?.['kode']).toBe('T');
        expect(sentData.komponen[1]?.['peran']).toBe('penyebut');
    });

    it('menampilkan error server apa adanya dalam role=alert', async () => {
        const user = userEvent.setup();
        vi.spyOn(router, 'patch').mockImplementation(
            (() => undefined) as unknown as typeof router.patch
        );
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
        await user.selectOptions(screen.getByLabelText(/tipe perhitungan target/i), 'penjumlahan');
        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.type(screen.getByLabelText(/kode komponen 1/i), 'N');
        await user.type(screen.getByLabelText(/label komponen 1/i), 'Nilai jumlah');
        await isiAlasan(user);
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

        const alerts = screen.getAllByRole('alert');
        const alert = alerts.find((el) => el.textContent?.includes('Penyimpanan formula ditolak server')) ?? alerts[0];
        expect(alert.textContent).toContain('Data indikator telah berubah. Muat ulang halaman.');
        expect(alert.textContent).toContain('Kode komponen sudah digunakan pada indikator ini.');
    });
});
