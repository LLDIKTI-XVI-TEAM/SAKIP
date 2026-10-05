import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http } from '@inertiajs/core';
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

const P1 = '33333333-3333-4333-8333-333333333333';
const P2 = '44444444-4444-4434-8434-444444444444';
const P3 = '55555555-5555-4555-8555-555555555555';
const P4 = '66666666-6666-4666-8666-666666666666';

function buatPayload(koreksi: RencanaAksiPayload['koreksi']): RencanaAksiPayload {
    const periode = [P1, P2, P3, P4].map((id, index) => ({
        id,
        nama: `Triwulan ${['I', 'II', 'III', 'IV'][index]}`,
        urutan: index + 1,
        efektif: true,
        status: 'efektif' as const,
        nilai: [{ komponen_id: null, kode: null, label: null, nilai: null, keterangan: null }],
        skor: { nilai: null, status_perhitungan: 'belum_diisi' },
        peringatan_turun: false,
        komponen_turun: [] as (string | null)[],
    }));
    return {
        id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        tahun: 2026,
        status_alur: 'draft',
        versi: 1,
        expected_versi: 1,
        expected_snapshot_id: '77777777-7777-4777-8777-777777777777',
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
            status: 'ditutup',
            rencana_aksi_mulai: '2026-03-01',
            rencana_aksi_selesai: '2026-03-31',
            penutupan: '2026-03-05',
        },
        penanggung_jawab: { id: 'pic-uji', nama: 'PIC Uji' },
        tipe_perhitungan: 'manual',
        presisi: 2,
        target_pk: '100.000000000000',
        baseline: null,
        komponen: [],
        periode,
        koreksi,
        deviasi_pk: {
            dapat_dinilai: false,
            ada: false,
            alasan_diperlukan: false,
            alasan_terisi: false,
            skor_periode_terakhir: null,
            target_pk: '100.000000000000',
            periode_id: null,
        },
        can: { view: true, update: true },
    };
}

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({
        status: 200,
        data: JSON.stringify({ periode: [], deviasi_pk: { dapat_dinilai: false, ada: false, alasan_diperlukan: false, alasan_terisi: false, skor_periode_terakhir: null, target_pk: null, periode_id: null } }),
        headers: {},
    });
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('F2 lingkup koreksi di UI', () => {
    it('koreksi 1 dari 4 via UI: hanya periode tercakup terkirim, luar lingkup dinonaktifkan', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload({ aktif: true, periode_ids: [P1] })} />);

        expect(screen.getByText(/Sesi koreksi aktif/)).toBeTruthy();

        const inputDalam = screen.getByRole<HTMLInputElement>('spinbutton', { name: 'Target Triwulan I (poin)' });
        const inputLuar = screen.getByRole<HTMLInputElement>('spinbutton', { name: 'Target Triwulan II (poin)' });
        expect(inputDalam.disabled).toBe(false);
        expect(inputLuar.disabled).toBe(true);
        expect(screen.getAllByText(/Di luar lingkup koreksi/).length).toBeGreaterThan(0);

        await user.type(inputDalam, '10');
        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));

        expect(router.post).toHaveBeenCalledTimes(1);
        const kirim = vi.mocked(router.post).mock.calls[0][1] as {
            targets: { periode_id: string; komponen_id: string | null; nilai: string | null }[];
        };
        expect(kirim.targets).toHaveLength(1);
        expect(kirim.targets[0]).toEqual({ periode_id: P1, komponen_id: null, nilai: '10', keterangan: null });
        expect(kirim.targets.find((item) => item.periode_id === P2)).toBeUndefined();
        expect(kirim.targets.find((item) => item.periode_id === P3)).toBeUndefined();
        expect(kirim.targets.find((item) => item.periode_id === P4)).toBeUndefined();
    });

    it('tanpa koreksi aktif semua periode efektif dapat disunting', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload({ aktif: false, periode_ids: null })} />);

        expect(screen.queryByText(/Sesi koreksi aktif/)).toBeNull();
        expect(screen.getByRole<HTMLInputElement>('spinbutton', { name: 'Target Triwulan II (poin)' }).disabled).toBe(false);

        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));
        const kirim = vi.mocked(router.post).mock.calls[0][1] as {
            targets: { periode_id: string }[];
        };
        expect(kirim.targets).toHaveLength(4);
    });
});
