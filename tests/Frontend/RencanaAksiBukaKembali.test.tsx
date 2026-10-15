import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import Review from '@/Pages/RencanaAksi/Review';
import type { ReviewRencanaAksi } from '@/Pages/RencanaAksi/review-types';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...await importOriginal<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => ({ props: {} }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => {
    for (const [name, descriptor] of [['showModal', originalShowModal], ['close', originalClose]] as const) {
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    }
});
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const pagination = { current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null };
const disahkan: ReviewRencanaAksi = {
    id: 'ra-1', tahun: 2026, status_alur: 'disahkan', expected_versi: 9,
    versi_pengajuan: { id: 'versi-3', nomor: 3, diajukan_at: '2026-10-01T01:00:00Z', jalur_pengajuan: 'pic', pengaju: { id: 'user-1', nama: 'Pengaju Lama' } },
    indikator: { kode: 'IKU-1', nama: 'Label indikator dibekukan', satuan: '%', desimal_tampilan: 2 },
    unit_kerja: { id: 'unit-1', nama: 'Unit versi pengajuan' }, pic: { id: 'user-1', nama: 'PJ saat pengajuan' },
    uraian: 'Substansi versi pengajuan', alasan_deviasi_pk: null,
    target_periode: [{ periode_id: 'p-1', periode_nama: 'Triwulan I', periode_urutan: 1, nilai: '10.25', status_perhitungan: 'terhitung', keterangan: null, komponen: [] }],
    klaim: null,
    bukti_dukungs: [],
    can: { verifikasi: false, kembalikan: false, sahkan: false, buka_kembali: true, evidence: true },
    businessErrors: { verifikasi: [], kembalikan: [], sahkan: [], buka_kembali: [] },
    pengesahan: { oleh: 'Perencana Pengesah', pada: '2026-10-12T05:00:00Z', self_approval: false },
    dibuka_kembali: false,
    histori: [{ id: 'versi-3', nomor: 3, diajukan_at: '2026-10-01T01:00:00Z', jalur_pengajuan: 'pic' }], pagination,
};
const renderReview = (override: Partial<ReviewRencanaAksi> = {}) => render(<Review rencanaAksi={{ ...disahkan, ...override }} />);

describe('Buka kembali rencana aksi disahkan', () => {
    it('tombol hanya muncul bila server mengizinkan can.buka_kembali', () => {
        const { unmount } = renderReview();
        expect(screen.getByRole('button', { name: 'Buka kembali' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Verifikasi' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Sahkan' })).toBeNull();
        unmount();

        renderReview({ can: { ...disahkan.can, buka_kembali: false } });
        expect(screen.queryByRole('button', { name: 'Buka kembali' })).toBeNull();
    });

    it('alasan bisnis buka-kembali dari server tetap terbaca', () => {
        renderReview({ can: { ...disahkan.can, buka_kembali: false },
            businessErrors: { ...disahkan.businessErrors, buka_kembali: ['Tahun jadwal telah ditutup; review memerlukan sesi koreksi resmi untuk indikator ini.'] } });
        expect(screen.getByText('Buka kembali tidak tersedia')).toBeTruthy();
        expect(screen.getByText('Tahun jadwal telah ditutup; review memerlukan sesi koreksi resmi untuk indikator ini.')).toBeTruthy();
    });

    it('banner dibuka-kembali menegaskan pengukuran ditahan dan versi lama tersimpan sebagai histori', () => {
        renderReview({ status_alur: 'dikembalikan', dibuka_kembali: true,
            can: { ...disahkan.can, buka_kembali: false } });
        expect(screen.getByText('Dibuka kembali setelah pengesahan')).toBeTruthy();
        expect(screen.getByText(/Pengajuan pengukuran indikator ini ditahan sampai versi baru disahkan/)).toBeTruthy();
        expect(screen.getByText(/tetap tersimpan dan dapat dibaca sebagai histori/)).toBeTruthy();
    });

    it('banner tidak tampil untuk dikembalikan biasa tanpa riwayat buka-kembali', () => {
        renderReview({ status_alur: 'dikembalikan', dibuka_kembali: false,
            can: { ...disahkan.can, buka_kembali: false } });
        expect(screen.queryByText('Dibuka kembali setelah pengesahan')).toBeNull();
    });

    it('modal wajib Q43: tombol nonaktif tanpa alasan dan mengirim token ke endpoint buka-kembali', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Buka kembali' }));
        const dialog = screen.getByRole('dialog', { name: 'Buka kembali rencana aksi' });
        expect((within(dialog).getByRole('button', { name: 'Konfirmasi buka kembali' }) as HTMLButtonElement).disabled).toBe(true);
        expect(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ }).getAttribute('placeholder')).toMatch(/koreksi target periode/);
        await user.type(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ }), 'Koreksi resmi target periode');
        expect((within(dialog).getByRole('button', { name: 'Konfirmasi buka kembali' }) as HTMLButtonElement).disabled).toBe(false);
        await user.click(within(dialog).getByRole('button', { name: 'Konfirmasi buka kembali' }));
        const [, data] = vi.mocked(router.post).mock.calls[0];
        expect(data).toEqual({ expected_versi: 9, versi_pengajuan_id: 'versi-3', alasan: 'Koreksi resmi target periode' });
        expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/rencana-aksi/ra-1/buka-kembali');
    });

    it('modal wajib menandai alasan dengan bintang dan mengirim alasan bila diisi', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Buka kembali' }));
        const dialog = screen.getByRole('dialog', { name: 'Buka kembali rencana aksi' });
        expect(within(dialog).getByText('*')).toBeTruthy();
        await user.type(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ }), 'Koreksi resmi target periode kedua');
        await user.click(within(dialog).getByRole('button', { name: 'Konfirmasi buka kembali' }));
        const [, data] = vi.mocked(router.post).mock.calls[0];
        expect(data).toEqual({ expected_versi: 9, versi_pengajuan_id: 'versi-3', alasan: 'Koreksi resmi target periode kedua' });
        expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/rencana-aksi/ra-1/buka-kembali');
    });

    it('validasi server mempertahankan modal, alasan, dan binding galat', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Buka kembali' }));
        await user.type(screen.getByRole('textbox'), 'Koreksi resmi');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi buka kembali' }));
        act(() => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ alasan: 'Alasan mengandung karakter yang tidak diizinkan.' }); });
        expect((screen.getByRole('textbox') as HTMLTextAreaElement).value).toBe('Koreksi resmi');
        expect(screen.getByText('Alasan mengandung karakter yang tidak diizinkan.')).toBeTruthy();
        expect(screen.getByRole('dialog')).toBeTruthy();
    });
});
