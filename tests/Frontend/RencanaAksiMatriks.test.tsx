import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RencanaAksiShow from '@/Pages/RencanaAksi/Show';
import type { RencanaAksiShow as RencanaAksiPayload } from '@/Pages/RencanaAksi/types';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Head: () => null,
        usePage: () => ({ props: { pengaturan: {} } }),
    };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const PEMBILANG = '11111111-1111-4111-8111-111111111111';
const PENYEBUT = '22222222-2222-4222-8222-222222222222';
const PERIODE_1 = '33333333-3333-4333-8333-333333333333';
const PERIODE_2 = '44444444-4444-4434-8434-444444444444';

function buatPayload(overrides?: Partial<RencanaAksiPayload>): RencanaAksiPayload {
    return {
        id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tahun: 2026,
        status_alur: 'draft',
        versi: 1,
        expected_versi: 1,
        uraian: null,
        alasan_deviasi_pk: null,
        indikator: {
            id: 'indikator-uji',
            kode: 'I-UJI',
            nama: 'Indikator Uji',
            satuan: 'poin',
            arah: 'naik_baik',
            tipe_perhitungan: 'rasio_persen',
            presisi: 2,
            desimal_tampilan: 2,
            status: 'aktif',
            tahun_mulai_berlaku: 2025,
        },
        unit: { id: 'unit-uji', nama: 'Unit Uji' },
        jadwal: {
            id: 'jadwal-uji',
            tahun: 2026,
            status: 'aktif',
            rencana_aksi_mulai: '2026-03-01',
            rencana_aksi_selesai: '2026-03-31',
            penutupan: '2026-12-31',
        },
        penanggung_jawab: { id: 'pic-uji', nama: 'PIC Uji' },
        tipe_perhitungan: 'rasio_persen',
        presisi: 2,
        target_pk: '100.000000000000',
        baseline: null,
        komponen: [
            { komponen_id: PEMBILANG, kode: 'n', label: 'Pembilang', peran: 'pembilang', bobot: '1', urutan: 1 },
            { komponen_id: PENYEBUT, kode: 't', label: 'Penyebut', peran: 'penyebut', bobot: '1', urutan: 2 },
        ],
        periode: [
            {
                id: PERIODE_1,
                nama: 'Triwulan I',
                urutan: 1,
                efektif: true,
                status: 'efektif',
                nilai: [
                    { komponen_id: PEMBILANG, kode: 'n', label: 'Pembilang', nilai: '50', keterangan: null },
                    { komponen_id: PENYEBUT, kode: 't', label: 'Penyebut', nilai: '100', keterangan: null },
                ],
                skor: { nilai: '50.00', status_perhitungan: 'terhitung' },
                peringatan_turun: false,
                komponen_turun: [],
            },
            {
                id: PERIODE_2,
                nama: 'Triwulan II',
                urutan: 2,
                efektif: true,
                status: 'efektif',
                nilai: [
                    { komponen_id: PEMBILANG, kode: 'n', label: 'Pembilang', nilai: '80', keterangan: null },
                    { komponen_id: PENYEBUT, kode: 't', label: 'Penyebut', nilai: '100', keterangan: null },
                ],
                skor: { nilai: '80.00', status_perhitungan: 'terhitung' },
                peringatan_turun: false,
                komponen_turun: [],
            },
        ],
        deviasi_pk: {
            dapat_dinilai: true,
            ada: true,
            alasan_diperlukan: true,
            alasan_terisi: false,
            skor_periode_terakhir: '80.00',
            target_pk: '100.000000000000',
            periode_id: PERIODE_2,
        },
        can: { view: true, update: true },
        ...overrides,
    };
}

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Rencana aksi matriks target', () => {
    it('11.2 reaktif dari payload server tanpa hitung ulang di React', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        expect(screen.getByTestId(`skor-${PERIODE_1}`).textContent).toContain('50,00 poin');

        const input = screen.getByRole<HTMLInputElement>('spinbutton', { name: 'n · Pembilang · Triwulan I' });
        expect(input.value).toBe('50');
        await user.clear(input);
        await user.type(input, '30');

        // Skor server tetap; React dilarang menghitung ulang turunan.
        expect(screen.getByTestId(`skor-${PERIODE_1}`).textContent).toContain('50,00 poin');
        expect(screen.getAllByText(/Perubahan input belum mengubah hasil ini/).length).toBeGreaterThan(0);

        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));
        const kirim = vi.mocked(router.post).mock.calls[0][1] as {
            targets: { periode_id: string; komponen_id: string | null; nilai: string | null }[];
        };
        expect(kirim.targets).toContainEqual({ periode_id: PERIODE_1, komponen_id: PEMBILANG, nilai: '30', keterangan: null });
        expect(kirim.targets).toContainEqual({ periode_id: PERIODE_1, komponen_id: PENYEBUT, nilai: '100', keterangan: null });
        expect(JSON.stringify(kirim)).not.toContain('skor');
        expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/rencana-aksi/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee/target');
    });

    it('11.5 peringatan turun tampil tanpa memblokir penyimpanan', async () => {
        const user = userEvent.setup();
        const payload = buatPayload({
            periode: [
                {
                    id: PERIODE_1,
                    nama: 'Triwulan I',
                    urutan: 1,
                    efektif: true,
                    status: 'efektif',
                    nilai: [
                        { komponen_id: PEMBILANG, kode: 'n', label: 'Pembilang', nilai: '50', keterangan: null },
                        { komponen_id: PENYEBUT, kode: 't', label: 'Penyebut', nilai: '100', keterangan: null },
                    ],
                    skor: { nilai: '50.00', status_perhitungan: 'terhitung' },
                    peringatan_turun: false,
                    komponen_turun: [],
                },
                {
                    id: PERIODE_2,
                    nama: 'Triwulan II',
                    urutan: 2,
                    efektif: true,
                    status: 'efektif',
                    nilai: [
                        { komponen_id: PEMBILANG, kode: 'n', label: 'Pembilang', nilai: '30', keterangan: null },
                        { komponen_id: PENYEBUT, kode: 't', label: 'Penyebut', nilai: '100', keterangan: null },
                    ],
                    skor: { nilai: '30.00', status_perhitungan: 'terhitung' },
                    peringatan_turun: true,
                    komponen_turun: [PEMBILANG],
                },
            ],
        });
        render(<RencanaAksiShow rencanaAksi={payload} />);

        const peringatan = screen.getByTestId(`peringatan-${PERIODE_2}`);
        expect(peringatan.textContent).toContain('Nilai turun dari periode sebelumnya');
        expect(peringatan.textContent).toContain('tidak memblokir');

        const simpan = screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan Target' });
        expect(simpan.disabled).toBe(false);

        await user.click(simpan);
        expect(router.post).toHaveBeenCalledTimes(1);
    });

    it('membedakan nol eksplisit dari belum diisi dan menonaktifkan periode tidak berlaku', async () => {
        const user = userEvent.setup();
        const payload = buatPayload({
            tipe_perhitungan: 'manual',
            indikator: {
                id: 'indikator-uji',
                kode: 'I-UJI',
                nama: 'Indikator Uji',
                satuan: 'poin',
                arah: 'naik_baik',
                tipe_perhitungan: 'manual',
                presisi: 2,
                desimal_tampilan: 2,
                status: 'aktif',
                tahun_mulai_berlaku: 2025,
            },
            komponen: [],
            periode: [
                {
                    id: PERIODE_1,
                    nama: 'Triwulan I',
                    urutan: 1,
                    efektif: true,
                    status: 'efektif',
                    nilai: [{ komponen_id: null, kode: null, label: null, nilai: '0', keterangan: null }],
                    skor: { nilai: '0.00', status_perhitungan: 'terhitung' },
                    peringatan_turun: false,
                    komponen_turun: [],
                },
                {
                    id: PERIODE_2,
                    nama: 'Triwulan II',
                    urutan: 2,
                    efektif: true,
                    status: 'efektif',
                    nilai: [{ komponen_id: null, kode: null, label: null, nilai: null, keterangan: null }],
                    skor: { nilai: null, status_perhitungan: 'belum_diisi' },
                    peringatan_turun: false,
                    komponen_turun: [],
                },
                {
                    id: '55555555-5555-4555-8555-555555555555',
                    nama: 'Triwulan 0',
                    urutan: 0,
                    efektif: false,
                    status: 'tidak_berlaku',
                    nilai: [],
                    skor: { nilai: null, status_perhitungan: 'belum_diisi' },
                    peringatan_turun: false,
                    komponen_turun: [],
                },
            ],
            deviasi_pk: {
                dapat_dinilai: false,
                ada: false,
                alasan_diperlukan: false,
                alasan_terisi: false,
                skor_periode_terakhir: null,
                target_pk: '100.000000000000',
                periode_id: null,
            },
        });
        render(<RencanaAksiShow rencanaAksi={payload} />);

        expect(screen.getByRole<HTMLInputElement>('spinbutton', { name: 'Target Triwulan I (poin)' }).value).toBe('0');
        expect(screen.getByText('Nol eksplisit (berbeda dari kosong).')).toBeTruthy();
        expect(screen.getByRole<HTMLInputElement>('spinbutton', { name: 'Target Triwulan II (poin)' }).value).toBe('');
        expect(screen.getByTestId(`skor-${PERIODE_2}`).textContent).toContain('Belum diisi');
        expect(screen.getAllByText('Tidak berlaku').length).toBeGreaterThan(0);

        await user.clear(screen.getByRole('spinbutton', { name: 'Target Triwulan I (poin)' }));
        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));
        const kirim = vi.mocked(router.post).mock.calls[0][1] as {
            targets: { periode_id: string; komponen_id: string | null; nilai: string | null }[];
        };
        expect(kirim.targets).toContainEqual({ periode_id: PERIODE_1, komponen_id: null, nilai: null, keterangan: null });
        expect(kirim.targets.find((item) => item.periode_id === '55555555-5555-4555-8555-555555555555')).toBeUndefined();
    });

    it('menampilkan konflik versi dengan tombol muat ulang dan mempertahankan draf saat validasi gagal', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        const alasan = screen.getByRole('textbox', { name: /Alasan deviasi/ });
        await user.type(alasan, 'Realisasi lapangan di bawah target.');
        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));

        const onError = vi.mocked(router.post).mock.calls[0][2]?.onError;
        await act(async () => {
            onError?.({ expected_versi: 'Data telah berubah. Muat ulang sebelum mengulangi penyimpanan.' });
        });

        expect(screen.getByText('Data telah berubah di server')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Muat ulang data terbaru' })).toBeTruthy();
        expect((screen.getByRole('textbox', { name: /Alasan deviasi/ }) as HTMLTextAreaElement).value).toBe(
            'Realisasi lapangan di bawah target.',
        );

        await user.click(screen.getByRole('button', { name: 'Muat ulang data terbaru' }));
        expect(router.get).toHaveBeenCalledWith(
            '/rencana-aksi/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            {},
            expect.objectContaining({ preserveState: false }),
        );
    });

    it('T6 simpan berurutan tanpa reload memakai token versi terbaru', async () => {
        const user = userEvent.setup();
        const awal = buatPayload();
        const { rerender } = render(<RencanaAksiShow rencanaAksi={awal} />);

        // Simulasi props Inertia pasca-simpan pertama (versi naik 1 → 2).
        rerender(<RencanaAksiShow rencanaAksi={{ ...awal, versi: 2, expected_versi: 2 }} />);

        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));

        expect(router.post).toHaveBeenCalledTimes(1);
        const kirim = vi.mocked(router.post).mock.calls[0][1] as { expected_versi: number };
        expect(kirim.expected_versi).toBe(2);
    });
});
