import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import VerifikasiIndex from '@/Pages/Verifikasi/Index';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...await importOriginal<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const pengukuran: Pengukuran = {
    id: 'pengukuran-uji', versi: 2, nomor_pengajuan: 1, status: 'diajukan', jalur_pengajuan: 'pic',
    versi_berlaku: null,
    self_approval: false, reviu_terlambat: false, nilai: '80', target: '100', status_perhitungan: 'terhitung', sumber_nilai: 'manual',
    catatan: null, alasan_tidak_dapat_dihitung: null, komponen: [], bukti_dukungs: [], bukti_count: 3,
    prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [],
    can: { view: true, evidence: true, uploadEvidence: false, update: false, submit: false, verify: true, ratify: false, return: true },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'unit-uji', nama: 'Unit Uji' }, pic: null,
    },
    periode_jadwal: { id: 'periode-uji', urutan: 2, nama_periode: 'Triwulan II', tahun: 2026, is_nilai_akhir: false, tanggal: { bulan_mulai: 4, hari_mulai: 1, bulan_selesai: 6, hari_selesai: 30 } },
};
const pagination = { current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null };
const filters = { status: 'diajukan' as const, renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const selected_filters = { renstra_id: null, periode_id: null, unit_id: null };
const jumlah = { diajukan: 12, diverifikasi: 4 };

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe('Tata letak antrean verifikasi', () => {
    it('menampilkan dua antrean sebagai tab berjumlah dan menandai antrean aktif', () => {
        render(<VerifikasiIndex {...{ pengukurans: [pengukuran], pagination, filters: { ...filters, tahun: '2026' }, selected_filters, jumlah }} />);
        const tab = within(screen.getByRole('navigation', { name: 'Status antrean' }));
        const menunggu = tab.getByRole('link', { name: 'Menunggu verifikasi, 12 pengukuran' });
        const siap = tab.getByRole('link', { name: 'Siap disahkan, 4 pengukuran' });

        expect(menunggu.getAttribute('aria-current')).toBe('page');
        expect(siap.getAttribute('aria-current')).toBeNull();
        // Pindah antrean membawa filter yang sedang diterapkan, tanpa nomor halaman lama.
        expect(siap.getAttribute('href')).toBe('/verifikasi?status=diverifikasi&tahun=2026');
        expect(menunggu.getAttribute('href')).toBe('/verifikasi?status=diajukan&tahun=2026');
        expect(screen.queryByRole('combobox', { name: 'Status antrean' })).toBeNull();
    });

    it('menandai tab Siap disahkan saat status diverifikasi', () => {
        render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters: { ...filters, status: 'diverifikasi' as const }, selected_filters, jumlah: { diajukan: 0, diverifikasi: 4 } }} />);
        const tab = within(screen.getByRole('navigation', { name: 'Status antrean' }));
        const menunggu = tab.getByRole('link', { name: 'Menunggu verifikasi, 0 pengukuran' });

        expect(tab.getByRole('link', { name: 'Siap disahkan, 4 pengukuran' }).getAttribute('aria-current')).toBe('page');
        expect(menunggu.getAttribute('aria-current')).toBeNull();
        // Antrean kosong tetap menampilkan angka nol, bukan lencana tanpa isi.
        expect(menunggu.textContent).toBe('Menunggu verifikasi0');
    });

    it('memakai enam kolom ringkas dan pola kartu di layar kecil tanpa lebar minimum', () => {
        render(<VerifikasiIndex {...{ pengukurans: [pengukuran], pagination, filters, selected_filters, jumlah }} />);
        const tabel = screen.getByRole('table', { name: 'Antrean verifikasi pengukuran' });

        expect(tabel.className).toContain('md:table');
        expect(tabel.className).not.toContain('min-w-max');
        expect(tabel.className).not.toContain('whitespace-nowrap');
        expect(within(tabel).getAllByRole('columnheader').map((kolom) => kolom.textContent)).toEqual([
            'KODE & INDIKATOR', 'UNIT KERJA', 'PERIODE', 'REALISASI', 'STATUS', 'AKSI',
        ]);
        expect(within(tabel).getByText('Triwulan II 2026')).toBeTruthy();
        expect(within(tabel).getByText('80,00 %')).toBeTruthy();
        // Rincian dibaca di halaman detail, bukan di baris antrean.
        expect(within(tabel).queryByText(/PIC/)).toBeNull();
        expect(within(tabel).queryByText(/Target/)).toBeNull();
        expect(within(tabel).queryByText(/dokumen|Tanpa bukti/)).toBeNull();
        expect(within(tabel).queryByText(/Jalur/)).toBeNull();
        expect(within(tabel).getByRole('link', { name: 'Lihat pengajuan IKU-UJI' }).getAttribute('href')).toBe('/verifikasi/pengukuran-uji');
    });

    it('hanya menandai hasil perhitungan yang belum terhitung', () => {
        const { rerender } = render(<VerifikasiIndex {...{ pengukurans: [pengukuran], pagination, filters, selected_filters, jumlah }} />);
        expect(screen.queryByText('Terhitung')).toBeNull();
        expect(screen.queryByText('Belum Dapat Diukur')).toBeNull();

        rerender(<VerifikasiIndex {...{ pengukurans: [{ ...pengukuran, nilai: null, status_perhitungan: 'belum_dapat_diukur' as const }], pagination, filters, selected_filters, jumlah }} />);
        expect(screen.getByText('Belum Dapat Diukur')).toBeTruthy();
    });

    it('melipat filter di layar kecil kecuali ada filter aktif', async () => {
        const user = userEvent.setup();
        const { unmount } = render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters, selected_filters, jumlah }} />);
        const tombol = screen.getByRole('button', { name: 'Filter' });
        const panel = document.getElementById(tombol.getAttribute('aria-controls') ?? '');

        expect(tombol.getAttribute('aria-expanded')).toBe('false');
        expect(panel?.className).toContain('hidden');
        await user.click(tombol);
        expect(tombol.getAttribute('aria-expanded')).toBe('true');
        expect(panel?.className).not.toContain('hidden');
        unmount();

        render(<VerifikasiIndex {...{ pengukurans: [], pagination, filters: { ...filters, tahun: '2026' }, selected_filters, jumlah }} />);
        expect(screen.getByRole('button', { name: 'Filter' }).getAttribute('aria-expanded')).toBe('true');
    });
});
