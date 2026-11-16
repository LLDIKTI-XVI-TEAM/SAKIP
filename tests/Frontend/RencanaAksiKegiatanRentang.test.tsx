import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, expect, it } from 'vitest';
import KegiatanPanel from '@/Pages/RencanaAksi/KegiatanPanel';
import type { RencanaAksiPeriode } from '@/Pages/RencanaAksi/types';

const TOTAL_KOSONG = { total: '0', belum_diisi: 0, per_periode: {} };

const periode = (id: string, nama: string, tanggal: RencanaAksiPeriode['tanggal']): RencanaAksiPeriode => ({
    id, nama, urutan: 1, efektif: true, status: 'efektif', nilai: [], skor: { nilai: null, status_perhitungan: 'belum_diisi' }, peringatan_turun: false, komponen_turun: [], tanggal, batas_terkunci_pic: null, boleh_tanpa_target: false,
});

const kegiatan = { daftar: [], total: TOTAL_KOSONG, can: { tambah: true, kelola: false } };

afterEach(cleanup);

/** Buka modal tambah kegiatan periode pertama lalu kembalikan dialognya. */
const bukaModal = (namaPeriode: string): ReturnType<typeof within> => {
    fireEvent.click(screen.getByRole('button', { name: `Tambah kegiatan ${namaPeriode}` }));
    return within(screen.getByRole('dialog'));
};

it('modal tambah kegiatan menampilkan rentang tanggal periode beserta tahun RA', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" tahun={2027}
        periode={[periode('tw1', 'Triwulan I', { bulan_mulai: 1, hari_mulai: 1, bulan_selesai: 3, hari_selesai: 31 })]}
        komponen={[]} terkunci={[]} kegiatan={kegiatan} />);

    const dialog = bukaModal('Triwulan I');
    expect(dialog.getByRole('heading', { name: /^Tambah kegiatan — Triwulan I/ })).toBeTruthy();
    expect(dialog.getByText('1 Jan–31 Mar 2027')).toBeTruthy();
});

it('modal tambah kegiatan tetap tampil tanpa keterangan rentang saat tanggal periode belum diatur', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" tahun={2027} periode={[periode('tw1', 'Triwulan I', null)]}
        komponen={[]} terkunci={[]} kegiatan={kegiatan} />);

    const dialog = bukaModal('Triwulan I');
    expect(dialog.getByRole('heading', { name: 'Tambah kegiatan — Triwulan I' })).toBeTruthy();
    expect(dialog.queryByText(/Jan/)).toBeNull();
});
