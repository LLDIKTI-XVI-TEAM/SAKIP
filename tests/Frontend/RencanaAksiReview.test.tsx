import type { ReactNode } from 'react';
import type { Page, PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import Review from '@/Pages/RencanaAksi/Review';
import ReviewIndex from '@/Pages/RencanaAksi/ReviewIndex';
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
const rencanaAksi: ReviewRencanaAksi = {
    id: 'ra-1', tahun: 2026, status_alur: 'diajukan', expected_versi: 7,
    versi_pengajuan: { id: 'versi-2', nomor: 2, diajukan_at: '2026-10-01T01:00:00Z', jalur_pengajuan: 'pic', pengaju: { id: 'user-1', nama: 'Pengaju Lama' } },
    indikator: { kode: 'IKU-1', nama: 'Label indikator dibekukan' },
    unit_kerja: { id: 'unit-1', nama: 'Unit versi pengajuan' }, pic: { id: 'user-1', nama: 'PJ saat pengajuan' },
    uraian: 'Substansi versi pengajuan', alasan_deviasi_pk: null,
    target_periode: [{ periode_id: 'p-1', periode_nama: 'Triwulan I', periode_urutan: 1, nilai: '10.25', status_perhitungan: null, keterangan: null, komponen: [] }],
    bukti_dukungs: [{ id: 'bukti-1', mode: 'file', nama_asli: 'versi-dua.pdf', tautan: null, isi_teks: null, download_url: '/rencana-aksi/ra-1/versi/versi-2/bukti/bukti-1/unduh' }],
    can: { verifikasi: true, kembalikan: true, evidence: true }, businessErrors: { verifikasi: [], kembalikan: [] },
    histori: [{ id: 'versi-1', nomor: 1, diajukan_at: '2026-09-01T01:00:00Z', jalur_pengajuan: 'pic' }, { id: 'versi-2', nomor: 2, diajukan_at: '2026-10-01T01:00:00Z', jalur_pengajuan: 'pic' }], pagination,
};
const renderReview = (override: Partial<ReviewRencanaAksi> = {}) => render(<Review rencanaAksi={{ ...rencanaAksi, ...override }} />);
const page = (props: Record<string, unknown> = {}): Page => ({ component: 'RencanaAksi/Review', url: '/rencana-aksi/ra-1/review', version: null, clearHistory: false, encryptHistory: false, rescuedProps: [], rememberedState: {}, flash: {}, props: { errors: {}, ...props } });

describe('Review Rencana Aksi', () => {
    it('menampilkan keterangan target dan komponen serta menjelaskan berkas yang tidak dapat diunduh', () => {
        renderReview({
            target_periode: [{ ...rencanaAksi.target_periode[0], keterangan: 'Penjelasan target manual.', komponen: [{ komponen_id: 'k-1', kode: 'n', label: 'Pembilang', nilai: '1', keterangan: 'Penjelasan komponen beku.' }] }],
            bukti_dukungs: [{ ...rencanaAksi.bukti_dukungs[0], download_url: null }],
        });
        expect(screen.getByText('Penjelasan target manual.')).toBeTruthy();
        expect(screen.getByText('Penjelasan komponen beku.')).toBeTruthy();
        expect(screen.getByText('Berkas ini sudah tidak berlaku pada rencana aksi; unduhan tidak tersedia.')).toBeTruthy();
        expect(screen.queryByRole('link', { name: 'Unduh file' })).toBeNull();
    });
    it('menampilkan substansi beku, provenance, histori, dan unduhan versi yang dikirim server', () => {
        renderReview();
        expect(screen.getByText('Substansi versi pengajuan')).toBeTruthy();
        expect(screen.getByText('Unit versi pengajuan')).toBeTruthy();
        expect(screen.getByText('Pengaju Lama')).toBeTruthy();
        expect(screen.getByText('10.25')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Lihat versi' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/review?versi=versi-1');
        expect(screen.getByRole('link', { name: 'Unduh file' }).getAttribute('href')).toBe(rencanaAksi.bukti_dukungs[0].download_url);
        expect(screen.queryByRole('textbox')).toBeNull();
    });

    it('capability server menyembunyikan aksi/bukti dan alasan bisnis tetap terbaca', () => {
        renderReview({ can: { verifikasi: false, kembalikan: false, evidence: false }, businessErrors: { verifikasi: ['Indikator telah diarsipkan.'], kembalikan: ['Unit konteks rencana aksi nonaktif.'] } });
        expect(screen.queryByRole('button', { name: 'Verifikasi' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Kembalikan untuk revisi' })).toBeNull();
        expect(screen.queryByRole('link', { name: 'Unduh file' })).toBeNull();
        expect(screen.getByText('Indikator telah diarsipkan.')).toBeTruthy();
        expect(screen.getByText('Unit konteks rencana aksi nonaktif.')).toBeTruthy();
        expect(screen.getByText('Substansi versi pengajuan')).toBeTruthy();
    });

    it('verifikasi memakai konfirmasi tanpa alasan dan mengirim token header serta versi pengajuan', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Verifikasi' }));
        const dialog = screen.getByRole('dialog', { name: 'Verifikasi rencana aksi' });
        expect(within(dialog).queryByRole('textbox')).toBeNull();
        await user.click(within(dialog).getByRole('button', { name: 'Konfirmasi verifikasi' }));
        expect(router.post).toHaveBeenCalledTimes(1);
        expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/rencana-aksi/ra-1/verifikasi', { expected_versi: 7, versi_pengajuan_id: 'versi-2' }]);
    });

    it('submit keyboard tetap mengirim intent verifikasi', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Verifikasi' }));
        screen.getByRole('button', { name: 'Konfirmasi verifikasi' }).focus();
        await user.keyboard('{Enter}');
        expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/rencana-aksi/ra-1/verifikasi');
    });

    it('pengembalian wajib alasan; validasi server mempertahankan modal, alasan, dan binding galat', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Kembalikan untuk revisi' }));
        const dialog = screen.getByRole('dialog', { name: 'Kembalikan rencana aksi' });
        expect((within(dialog).getByRole('button', { name: 'Konfirmasi pengembalian' }) as HTMLButtonElement).disabled).toBe(true);
        await user.type(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ }), 'A');
        expect((within(dialog).getByRole('button', { name: 'Konfirmasi pengembalian' }) as HTMLButtonElement).disabled).toBe(false);
        await user.clear(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ }));
        await user.type(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ }), 'Perbaiki target periode kedua');
        await user.click(within(dialog).getByRole('button', { name: 'Konfirmasi pengembalian' }));
        const [, data, options] = vi.mocked(router.post).mock.calls[0];
        expect(data).toEqual({ expected_versi: 7, versi_pengajuan_id: 'versi-2', alasan: 'Perbaiki target periode kedua' });
        act(() => { options?.onError?.({ alasan: 'Alasan mengandung karakter yang tidak diizinkan.' }); });
        expect((screen.getByRole('textbox') as HTMLTextAreaElement).value).toBe('Perbaiki target periode kedua');
        expect(screen.getByRole('textbox').getAttribute('aria-invalid')).toBe('true');
        expect(screen.getByText('Alasan mengandung karakter yang tidak diizinkan.')).toBeTruthy();
        expect(screen.getByRole('dialog')).toBeTruthy();
    });

    it('pending menonaktifkan kedua aksi dan melindungi modal dari dismissal', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Verifikasi' }));
        await user.click(screen.getByRole('button', { name: 'Konfirmasi verifikasi' }));
        const options = vi.mocked(router.post).mock.calls[0][2];
        act(() => { options?.onStart?.({} as PendingVisit); });
        expect((screen.getByRole('button', { name: 'Verifikasi' }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Kembalikan untuk revisi' }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Batal' }) as HTMLButtonElement).disabled).toBe(true);
        await user.keyboard('{Escape}');
        expect(screen.getByRole('dialog')).toBeTruthy();
    });

    it('konflik versi dari validation props tetap menampilkan alasan dan token yang ditinjau', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Kembalikan untuk revisi' }));
        await user.type(screen.getByRole('textbox'), 'Perbaiki uraian');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi pengembalian' }));
        act(() => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ expected_versi: 'Versi sudah berubah. Muat ulang halaman.' }); });
        expect(screen.getByText('Versi sudah berubah. Muat ulang halaman.')).toBeTruthy();
        expect((screen.getByRole('textbox') as HTMLTextAreaElement).value).toBe('Perbaiki uraian');
        expect((screen.getByRole('button', { name: 'Konfirmasi pengembalian' }) as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByRole('button', { name: 'Periksa status terbaru' })).toBeTruthy();
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ expected_versi: 7, versi_pengajuan_id: 'versi-2' });
    });

    it.each([403, 409, 500])('galat HTTP %s mempertahankan alasan dan meminta pembacaan ulang sebelum retry', async (status) => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Kembalikan untuk revisi' }));
        await user.type(screen.getByRole('textbox'), 'Perbaiki uraian');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi pengembalian' }));
        const options = vi.mocked(router.post).mock.calls[0][2];
        act(() => { options?.onHttpException?.({ status, data: {}, headers: {} }); });
        expect((screen.getByRole('textbox') as HTMLTextAreaElement).value).toBe('Perbaiki uraian');
        expect((screen.getByRole('button', { name: 'Konfirmasi pengembalian' }) as HTMLButtonElement).disabled).toBe(true);
        await user.click(screen.getByRole('button', { name: 'Batal' }));
        expect(screen.queryByRole('dialog')).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Periksa status terbaru' }));
        expect(router.get).toHaveBeenCalledWith('/rencana-aksi/ra-1/review', {}, { preserveState: false, preserveScroll: true });
        expect(router.post).toHaveBeenCalledTimes(1);
    });

    it('sesi kedaluwarsa tidak mengulang mutasi dan mempertahankan input', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Kembalikan untuk revisi' }));
        await user.type(screen.getByRole('textbox'), 'Perbaiki uraian');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi pengembalian' }));
        const options = vi.mocked(router.post).mock.calls[0][2];
        act(() => { options?.onHttpException?.({ status: 419, data: {}, headers: {} }); });
        expect(screen.getByText('Verifikasi keamanan diperlukan')).toBeTruthy();
        expect((screen.getByRole('textbox') as HTMLTextAreaElement).value).toBe('Perbaiki uraian');
        expect((screen.getByRole('button', { name: 'Konfirmasi pengembalian' }) as HTMLButtonElement).disabled).toBe(true);
        expect(router.post).toHaveBeenCalledTimes(1);
    });

    it('redirect sukses tanpa status otoritatif tidak menutup modal', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Verifikasi' }));
        await user.click(screen.getByRole('button', { name: 'Konfirmasi verifikasi' }));
        act(() => { vi.mocked(router.post).mock.calls[0][2]?.onSuccess?.(page()); });
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByText(/Hasil tindakan belum terkonfirmasi/)).toBeTruthy();
    });

    it('hasil server yang memastikan status dan versi baru menutup modal', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Verifikasi' }));
        await user.click(screen.getByRole('button', { name: 'Konfirmasi verifikasi' }));
        act(() => { vi.mocked(router.post).mock.calls[0][2]?.onSuccess?.(page({ rencanaAksi: { ...rencanaAksi, status_alur: 'diverifikasi', expected_versi: 8 } })); });
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('putus koneksi mempertahankan modal dan tidak mengirim ulang otomatis', async () => {
        const user = userEvent.setup();
        renderReview();
        await user.click(screen.getByRole('button', { name: 'Verifikasi' }));
        await user.click(screen.getByRole('button', { name: 'Konfirmasi verifikasi' }));
        act(() => { vi.mocked(router.post).mock.calls[0][2]?.onNetworkError?.(new Error('Koneksi terputus')); });
        expect(screen.getByText(/Koneksi terputus/)).toBeTruthy();
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(router.post).toHaveBeenCalledTimes(1);
    });
});

describe('Antrean review rencana aksi', () => {
    it('filter status berupa GET server dan tautan menuju versi review', async () => {
        const user = userEvent.setup();
        render(<ReviewIndex daftar={[{ id: 'ra-1', tahun: 2026, status_alur: 'diajukan', nomor_pengajuan: 2, indikator: rencanaAksi.indikator, unit_nama: 'Unit A' }]} pagination={pagination} filters={{ status: 'diajukan' }} />);
        expect(screen.getByRole('link', { name: 'Review IKU-1 2026' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/review');
        await user.selectOptions(screen.getByLabelText('Status rencana aksi'), 'dikembalikan');
        expect(router.get).toHaveBeenCalledWith('/rencana-aksi/review', { status: 'dikembalikan' }, { preserveScroll: true });
        expect(router.post).not.toHaveBeenCalled();
    });

    it('antrean kosong menyajikan pesan yang jelas', () => {
        render(<ReviewIndex daftar={[]} pagination={{ ...pagination, total: 0 }} filters={{ status: 'diajukan' }} />);
        expect(screen.getByText('Belum ada rencana aksi pada status yang dipilih.')).toBeTruthy();
    });
});
