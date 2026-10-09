import type { ReactNode } from 'react';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http, HttpResponseError } from '@inertiajs/core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RencanaAksiShow from '@/Pages/RencanaAksi/Show';
import TargetPreview from '@/Pages/RencanaAksi/TargetPreview';
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

const PERIODE_1 = '33333333-3333-4333-8333-333333333333';

function buatPayload(): RencanaAksiPayload {
    return {
        id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tahun: 2026,
        status_alur: 'draft',
        versi: 1,
        expected_versi: 1,
        expected_snapshot_id: '55555555-5555-4555-8555-555555555555',
        expected_snapshot_versi: 1,
        uraian: null,
        alasan_deviasi_pk: null,
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
        tipe_perhitungan: 'manual',
        presisi: 2,
        target_pk: '100.000000000000',
        baseline: null,
        komponen: [],
        periode: [
            {
                id: PERIODE_1,
                nama: 'Triwulan I',
                urutan: 1,
                efektif: true,
                status: 'efektif',
                nilai: [{ komponen_id: null, kode: null, label: null, nilai: '20', keterangan: null }],
                skor: { nilai: '20.00', status_perhitungan: 'terhitung' },
                peringatan_turun: false,
                komponen_turun: [],
            },
        ],
        koreksi: { aktif: false, periode_ids: [] },
        deviasi_pk: {
            dapat_dinilai: true,
            ada: true,
            alasan_diperlukan: true,
            alasan_terisi: false,
            skor_periode_terakhir: '20.00',
            target_pk: '100.000000000000',
            periode_id: PERIODE_1,
        },
        can: { view: true, update: true },
    };
}

function responsPreview(skor: string | null, status = 'terhitung') {
    return {
        status: 200,
        data: JSON.stringify({
            periode: [{ id: PERIODE_1, efektif: true, skor: { nilai: skor, status_perhitungan: status }, peringatan_turun: false, komponen_turun: [] }],
            deviasi_pk: {
                dapat_dinilai: true,
                ada: true,
                alasan_diperlukan: true,
                alasan_terisi: false,
                skor_periode_terakhir: skor,
                target_pk: '100.000000000000',
                periode_id: PERIODE_1,
            },
        }),
        headers: {},
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

describe('F5 pratinjau reaktif rencana aksi', () => {
    it('memetakan galat HTTP pratinjau tanpa formula di React', async () => {
        const request = vi.spyOn(http.getClient(), 'request');
        request.mockRejectedValueOnce(new HttpResponseError('Ditolak', { status: 403, data: '', headers: {} }));
        const props = {
            id: 'ra-uji',
            targets: [{ periode_id: PERIODE_1, komponen_id: null, nilai: '10' }],
            alasanDeviasi: '',
            satuan: 'poin',
            desimalTampilan: 2,
            komponen: [],
            namaPeriode: () => 'Triwulan I',
            expectedVersi: 1,
            expectedSnapshotId: '55555555-5555-4555-8555-555555555555',
            expectedSnapshotVersi: 1,
        };
        render(<TargetPreview {...props} />);
        await screen.findByText(/Akses pratinjau ditolak/);

        request.mockRejectedValueOnce(new HttpResponseError('Tak valid', { status: 422, data: '', headers: {} }));
        cleanup();
        render(<TargetPreview {...props} />);
        await screen.findByText(/Input pratinjau tidak valid/);

        request.mockRejectedValueOnce(new HttpResponseError('Usang', { status: 409, data: '', headers: {} }));
        cleanup();
        render(<TargetPreview {...props} />);
        await screen.findByText(/Data telah berubah/);
    });

    it('edit nilai memicu preview debounce tanpa POST simpan dan tanpa draf', async () => {
        const user = userEvent.setup();
        const request = vi.spyOn(http.getClient(), 'request');
        // F5: pratinjau dinamis mengikuti nilai yang dikirim (tanpa formula di React).
        request.mockImplementation(async (args) => {
            const body = JSON.parse(String((args as { data: string }).data));
            const nilai = body.targets?.[0]?.nilai ?? null;
            const skor = nilai === null || nilai === '' ? null : `${Number(nilai).toFixed(2)}`;
            return responsPreview(skor);
        });
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        // Skor tersimpan tetap dari server.
        expect(screen.getByTestId(`skor-${PERIODE_1}`).textContent).toContain('20,00 poin');

        // Pratinjau awal memakai nilai tersimpan.
        await screen.findByTestId(`preview-skor-${PERIODE_1}`);

        // Edit 20 → 30: pratinjau bereaksi setelah debounce.
        const input = screen.getByRole<HTMLInputElement>('spinbutton', { name: 'Target Triwulan I (poin)' });
        await user.clear(input);
        await user.type(input, '30');

        await waitFor(() => expect(screen.getByTestId(`preview-skor-${PERIODE_1}`).textContent).toContain('30,00 poin'));

        // Skor tersimpan tak berubah; tanpa POST simpan/draf.
        expect(screen.getByTestId(`skor-${PERIODE_1}`).textContent).toContain('20,00 poin');
        expect(router.post).not.toHaveBeenCalled();
        const terakhir = request.mock.calls[request.mock.calls.length - 1][0];
        expect(terakhir.url).toBe('/rencana-aksi/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee/preview');
        expect(JSON.parse(String(terakhir.data))).toEqual({
            expected_versi: 1,
            expected_snapshot_id: '55555555-5555-4555-8555-555555555555',
            expected_snapshot_versi: 1,
            targets: [{ periode_id: PERIODE_1, komponen_id: null, nilai: '30', keterangan: null }],
            alasan_deviasi_pk: null,
        });
    });
});
