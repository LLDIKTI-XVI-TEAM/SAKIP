import type { ReactNode } from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import VerifikasiIndex from '@/Pages/Verifikasi/Index';
import VerifikasiShow from '@/Pages/Verifikasi/Show';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...await importOriginal<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const pengukuran: Pengukuran = {
    id: 'pengukuran-uji', versi: 2, nomor_pengajuan: 1, status: 'diajukan', jalur_pengajuan: 'pic',
    self_approval: false, reviu_terlambat: true, nilai: '80', target: '100', status_perhitungan: 'terhitung', sumber_nilai: 'manual',
    catatan: null, alasan_tidak_dapat_dihitung: null, komponen: [], bukti_dukungs: [], bukti_count: 0,
    prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [],
    can: { view: true, evidence: true, uploadEvidence: false, update: false, submit: false, verify: true, ratify: false, return: true },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'unit-uji', nama: 'Unit Uji' }, pic: null,
    },
    periode_jadwal: { id: 'periode-uji', urutan: 2, nama_periode: 'Triwulan II', tahun: 2026, is_nilai_akhir: false },
};
const pagination = { current_page: 3, last_page: 3, total: 41, prev_page_url: '/verifikasi?status=diajukan&page=2', next_page_url: null };
const filters = { status: 'diajukan' as const, renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const selected_filters = { renstra_id: null, periode_id: null, unit_id: null };

beforeEach(() => { vi.spyOn(router, 'get').mockImplementation(() => undefined); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('Antrean dan peringatan verifikasi pengukuran', () => {
    it.each([
        { realisasi_sama: true, narasi_sama: false, teks: 'Realisasi sama dengan periode pembanding.' },
        { realisasi_sama: false, narasi_sama: true, teks: 'Ketiga narasi utama sama dengan periode pembanding.' },
        { realisasi_sama: true, narasi_sama: true, teks: 'Ketiga narasi utama sama dengan periode pembanding.' },
    ])('peringatan informasional realisasi=$realisasi_sama narasi=$narasi_sama tidak memblokir keputusan', ({ teks, ...peringatan }) => {
        render(<VerifikasiShow pengukuran={{ ...pengukuran, peringatan_verifikasi: { ...peringatan, periode_pembanding: 'Triwulan I 2026', keterangan: null } }} />);
        expect(screen.getByText(teks)).toBeTruthy();
        expect(screen.getByText(/Peringatan ini tidak menghalangi verifikasi/)).toBeTruthy();
        expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Verifikasi pengukuran' }).disabled).toBe(false);
        expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Kembalikan untuk revisi' }).disabled).toBe(false);
    });

    it('menampilkan keterangan pembanding tanpa memberi kesan data identik dan menghormati can server', () => {
        render(<VerifikasiShow pengukuran={{ ...pengukuran, can: { ...pengukuran.can, verify: false }, peringatan_verifikasi: { periode_pembanding: null, realisasi_sama: false, narasi_sama: false, keterangan: 'Pembanding tidak tersedia.' } }} />);
        expect(screen.getByText('Pembanding tidak tersedia.')).toBeTruthy();
        expect(screen.queryByText('Data sama dengan periode sebelumnya')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Verifikasi pengukuran' })).toBeNull();
    });

    it('mengirim status dan filter tahun melalui GET tanpa membawa halaman lama', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<VerifikasiIndex {...{ pengukurans: [pengukuran], pagination, filters, selected_filters }} />);
        await user.selectOptions(screen.getByRole('combobox', { name: 'Status antrean' }), 'diverifikasi');
        await user.type(screen.getByRole('spinbutton', { name: 'Tahun' }), '2026');
        await user.click(screen.getByRole('button', { name: 'Terapkan filter' }));
        expect(router.get).toHaveBeenCalledWith('/verifikasi', { ...filters, status: 'diverifikasi', tahun: '2026' }, expect.any(Object));
        expect(screen.getByText('Jalur PIC')).toBeTruthy();
        expect(screen.getByText('Reviu terlambat')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Reset filter' }).getAttribute('href')).toBe('/verifikasi');
        rerender(<VerifikasiIndex {...{ pengukurans: [], pagination, filters: { ...filters, tahun: '2027' }, selected_filters }} />);
        expect(screen.getByRole<HTMLInputElement>('spinbutton', { name: 'Tahun' }).value).toBe('2027');
        expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'Status antrean' }).value).toBe('diajukan');
    });

    it('membedakan antrean kosong terfilter dari klaim semua pengajuan selesai', () => {
        render(<VerifikasiIndex {...{ pengukurans: [], pagination: { ...pagination, last_page: 1 }, filters: { ...filters, status: 'diverifikasi' as const, tahun: '2026' }, selected_filters }} />);
        expect(screen.getByText('Tidak ada pengukuran sesuai filter')).toBeTruthy();
        expect(screen.queryByText('Semua pengajuan telah diproses')).toBeNull();
    });

    it('menampilkan dan menghapus filter yang labelnya tidak lagi tersedia tanpa mereset filter lain', async () => {
        const user = userEvent.setup();
        render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters: { ...filters, tahun: '2026', unit_id: 'unit-uji' }, selected_filters }} />);
        const unit = screen.getByRole<HTMLInputElement>('textbox', { name: 'Cari Unit' });
        expect(unit.value).toBe('Pilihan tidak tersedia');
        expect(unit.disabled).toBe(true);
        await user.click(screen.getByRole('button', { name: 'Hapus filter Unit' }));
        expect(unit.value).toBe('');
        expect(unit.disabled).toBe(false);
        await user.click(screen.getByRole('button', { name: 'Terapkan filter' }));
        expect(router.get).toHaveBeenCalledWith('/verifikasi', { ...filters, tahun: '2026' }, expect.any(Object));
    });

    it('mencari pilihan terbatas, memilih hasil lalu mengirim ID pilihan', async () => {
        const user = userEvent.setup();
        const fetchMock = vi.fn().mockResolvedValueOnce({ ok: true, json: async () => ({ data: [{ id: 'renstra-lain', label: 'Renstra lainnya' }], page: 1, has_more: true }) })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ data: [{ id: 'renstra-uji', label: 'Renstra 2025–2029' }], page: 2, has_more: false }) });
        vi.stubGlobal('fetch', fetchMock);
        render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters, selected_filters }} />);
        await user.type(screen.getByRole('textbox', { name: 'Cari Renstra' }), 'Renstra');
        await user.click(await screen.findByRole('button', { name: 'Pilihan Renstra berikutnya' }));
        await user.click(await screen.findByRole('button', { name: 'Renstra 2025–2029' }));
        expect(screen.queryByRole('button', { name: 'Renstra lainnya' })).toBeNull();
        expect(fetchMock).toHaveBeenLastCalledWith('/verifikasi/pilihan?jenis=renstra&q=Renstra&page=2', expect.objectContaining({ signal: expect.any(AbortSignal) }));
        expect(fetchMock).toHaveBeenCalledWith('/verifikasi/pilihan?jenis=renstra&q=Renstra&page=1', expect.objectContaining({ signal: expect.any(AbortSignal) }));
        await user.click(screen.getByRole('button', { name: 'Terapkan filter' }));
        expect(router.get).toHaveBeenCalledWith('/verifikasi', { ...filters, renstra_id: 'renstra-uji' }, expect.any(Object));
    });

    it('kegagalan lookup menawarkan pemulihan tanpa menghapus filter terpilih', async () => {
        const user = userEvent.setup();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500 }));
        render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters: { ...filters, unit_id: 'unit-uji' }, selected_filters: { ...selected_filters, unit_id: { id: 'unit-uji', label: 'Unit Uji' } } }} />);
        await user.type(screen.getByRole('textbox', { name: 'Cari Renstra' }), 'Renstra');
        expect(await screen.findByText('Pilihan belum dapat dimuat. Coba lagi.')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Coba lagi Renstra' })).toBeTruthy();
        expect(screen.getByDisplayValue('Unit Uji')).toBeTruthy();
    });

    it('mengabaikan hasil lookup lama sesudah istilah pencarian berubah', async () => {
        let resolveLama: (value: unknown) => void = () => undefined;
        const lama = new Promise((resolve) => { resolveLama = resolve; });
        const fetchMock = vi.fn().mockReturnValueOnce(lama).mockResolvedValue({ ok: true, json: async () => ({ data: [{ id: 'baru', label: 'Renstra baru' }], page: 1, has_more: false }) });
        vi.stubGlobal('fetch', fetchMock);
        render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters, selected_filters }} />);
        const input = screen.getByRole('textbox', { name: 'Cari Renstra' });
        fireEvent.change(input, { target: { value: 'lama' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        fireEvent.change(input, { target: { value: 'baru' } });
        expect(await screen.findByRole('button', { name: 'Renstra baru' })).toBeTruthy();
        resolveLama({ ok: true, json: async () => ({ data: [{ id: 'lama', label: 'Renstra lama' }], page: 1, has_more: false }) });
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Renstra lama' })).toBeNull());
    });

    it('mencari nama periode satu karakter yang sah', async () => {
        const user = userEvent.setup();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: [{ id: 'periode-i', label: 'I' }], page: 1, has_more: false }) }));
        render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters, selected_filters }} />);
        await user.type(screen.getByRole('textbox', { name: 'Cari Periode' }), 'I');
        await user.click(await screen.findByRole('button', { name: 'I' }));
        await user.click(screen.getByRole('button', { name: 'Terapkan filter' }));
        expect(router.get).toHaveBeenCalledWith('/verifikasi', { ...filters, periode_id: 'periode-i' }, expect.any(Object));
    });

});
