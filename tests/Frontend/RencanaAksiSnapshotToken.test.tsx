import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http } from '@inertiajs/core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RencanaAksiShow from '@/Pages/RencanaAksi/Show';
import type { RencanaAksiShow as RencanaAksiShowProps } from '@/Pages/RencanaAksi/types';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

// jsdom tanpa pretendToBeVisual tak menyediakan requestAnimationFrame yang
// dipakai Show.tsx untuk fokus ringkasan galat; stub sinkron test-only.
if (typeof window.requestAnimationFrame !== 'function') {
    window.requestAnimationFrame = ((callback: FrameRequestCallback): number => {
        callback(0);
        return 0;
    }) as typeof window.requestAnimationFrame;
}

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

beforeEach(() => {
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, data: JSON.stringify({ periode: [], deviasi_pk: { dapat_dinilai: false, ada: false, alasan_diperlukan: false, alasan_terisi: false, skor_periode_terakhir: null, target_pk: null, periode_id: null } }), headers: {} });
});

const SNAP_ID = '00000000-0000-4000-8000-00000000aa01';
const KONFLIK_SNAPSHOT = 'Konteks indikator berubah (snapshot koreksi baru terbit). Muat ulang sebelum mengulangi penyimpanan.';

const RENCANA_AKSI: RencanaAksiShowProps = {
    id: 'ra-f4-uji',
    tahun: 2026,
    status_alur: 'draft',
    versi: 1,
    expected_versi: 1,
    expected_snapshot_id: SNAP_ID,
    expected_snapshot_versi: 1,
    uraian: null,
    alasan_deviasi_pk: null,
    indikator: {
        id: 'ind-f4',
        kode: 'IKU-F4',
        nama: 'Indikator Uji Token Snapshot',
        satuan: 'poin',
        arah: 'naik_baik',
        tipe_perhitungan: 'manual',
        presisi: 2,
        desimal_tampilan: 2,
        status: 'aktif',
        tahun_mulai_berlaku: 2025,
    },
    unit: { id: 'unit-a', nama: 'Unit A' },
    jadwal: {
        id: 'jadwal-f4',
        tahun: 2026,
        status: 'aktif',
        rencana_aksi_mulai: '2026-03-01',
        rencana_aksi_selesai: '2026-03-31',
        penutupan: '2026-12-31',
    },
    penanggung_jawab: { id: 'user-pic', nama: 'PIC Uji' },
    tipe_perhitungan: 'manual',
    presisi: 2,
    target_pk: '100.000000000000',
    baseline: null,
    komponen: [],
    periode: [
        {
            id: 'per-f4-1',
            nama: 'Triwulan I',
            urutan: 1,
            efektif: true,
            status: 'efektif',
            nilai: [{ komponen_id: null, kode: null, label: null, nilai: null, keterangan: null }],
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
    koreksi: { aktif: false, periode_ids: null },
    can: { view: true, update: true },
};

function renderPage(props: RencanaAksiShowProps = RENCANA_AKSI) {
    render(<RencanaAksiShow rencanaAksi={props} />);
}

describe('Token konkurensi snapshot RencanaAksi', () => {
    it('mengirim kembali expected_snapshot_id dan expected_snapshot_versi tanpa menghitung skor', async () => {
        const user = userEvent.setup();
        const postSpy = vi.spyOn(router, 'post').mockImplementation((() => undefined) as unknown as typeof router.post);
        renderPage();

        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));

        expect(postSpy).toHaveBeenCalledTimes(1);
        expect(postSpy.mock.calls[0]?.[0]).toBe('/rencana-aksi/ra-f4-uji/target');
        expect(postSpy.mock.calls[0]?.[1]).toEqual(expect.objectContaining({
            expected_versi: 1,
            expected_snapshot_id: SNAP_ID,
            expected_snapshot_versi: 1,
        }));
    });

    it('menampilkan spanduk konflik 409 snapshot dengan tombol muat ulang', async () => {
        const user = userEvent.setup();
        const postSpy = vi.spyOn(router, 'post').mockImplementation((() => undefined) as unknown as typeof router.post);
        renderPage();

        await user.click(screen.getByRole('button', { name: 'Simpan Target' }));

        const options = postSpy.mock.calls[0]?.[2] as unknown as { onError?: (errors: Record<string, string>) => void };
        await act(async () => options.onError?.({ expected_snapshot_id: KONFLIK_SNAPSHOT }));

        expect(screen.getByText('Data telah berubah di server')).toBeTruthy();
        expect(screen.getAllByText(KONFLIK_SNAPSHOT).length).toBeGreaterThan(0);
        expect(screen.getByRole('button', { name: 'Muat ulang data terbaru' })).toBeTruthy();
    });
});
