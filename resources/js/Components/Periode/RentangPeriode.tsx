import { formatRentangPeriode } from '@/lib/rentangPeriode';
import type { TanggalPeriode } from '@/types/periode-jadwal';

interface RentangPeriodeProps {
    tanggal: TanggalPeriode | null;
    tahun?: number;
    className?: string;
}

/** Keterangan rentang tanggal di bawah nama periode; tidak dirender bila tanggal periode belum diatur. */
export function RentangPeriode({ tanggal, tahun, className = 'block text-xs font-normal text-muted' }: RentangPeriodeProps) {
    const rentang = formatRentangPeriode(tanggal, tahun);

    // Spasi menjaga teks aksesibel "Triwulan I 2027 1 Jan–31 Mar 2027" tetap terpisah; tidak tampil karena span berupa blok.
    return rentang ? (
        <>
            {' '}
            <span className={className}>{rentang}</span>
        </>
    ) : null;
}
