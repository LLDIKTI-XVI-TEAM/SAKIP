import { expect, it } from 'vitest';
import { formatRentangPeriode } from '@/lib/rentangPeriode';

const TW1 = { bulan_mulai: 1, hari_mulai: 1, bulan_selesai: 3, hari_selesai: 31 };
const KOSONG = { bulan_mulai: null, hari_mulai: null, bulan_selesai: null, hari_selesai: null };

it('rentang pada tahun terkait memakai bulan singkat dan satu tahun', () => {
    expect(formatRentangPeriode(TW1, 2027)).toBe('1 Jan–31 Mar 2027');
    expect(formatRentangPeriode({ bulan_mulai: 10, hari_mulai: 1, bulan_selesai: 12, hari_selesai: 31 }, 2026)).toBe('1 Okt–31 Des 2026');
});

it('rentang master tanpa tahun dan kosong bila tanggal belum diatur', () => {
    expect(formatRentangPeriode({ bulan_mulai: 5, hari_mulai: 15, bulan_selesai: 8, hari_selesai: 14 })).toBe('15 Mei–14 Agu');
    expect(formatRentangPeriode(KOSONG, 2026)).toBeNull();
    expect(formatRentangPeriode(null, 2026)).toBeNull();
});
