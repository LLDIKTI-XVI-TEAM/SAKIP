import type { TanggalPeriode } from '@/types/periode-jadwal';

export const NAMA_BULAN = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const hariBulan = (hari: number, bulan: number): string => `${hari} ${NAMA_BULAN[bulan - 1].slice(0, 3)}`;

/**
 * Rentang periode, mis. "1 Jan–31 Mar 2027"; tanpa tahun untuk Master Periode atau bila tahun belum diketahui.
 * Disusun dari angka hari dan bulan, bukan `Date`, sehingga tidak bergeser zona waktu. Null bila tanggal belum diatur.
 */
export function formatRentangPeriode(tanggal: TanggalPeriode | null, tahun?: number): string | null {
    if (!tanggal) return null;
    const { bulan_mulai, hari_mulai, bulan_selesai, hari_selesai } = tanggal;
    if (bulan_mulai === null || hari_mulai === null || bulan_selesai === null || hari_selesai === null) return null;
    const rentang = `${hariBulan(hari_mulai, bulan_mulai)}–${hariBulan(hari_selesai, bulan_selesai)}`;

    return tahun ? `${rentang} ${tahun}` : rentang;
}
