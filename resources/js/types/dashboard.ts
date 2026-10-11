import type { Pengukuran } from '@/Pages/Pengukuran/types';

export type StatusAlur = 'draft' | 'diajukan' | 'diverifikasi' | 'dikembalikan' | 'disahkan';

export type DashboardStats = Record<StatusAlur | 'total', number>;

export interface ProgresPeriode extends DashboardStats {
    periode_id: string;
    nama: string;
}

export interface DashboardProps {
    tahun: number;
    activeRenstra: { id: string; nama: string; tahun_mulai: number; tahun_selesai: number } | null;
    activeJadwal: { tahun: number; status: string } | null;
    activePeriode: { id: string; nama_periode: string; status: 'aktif' | 'terakhir' } | null;
    stats: DashboardStats;
    progresPeriode: ProgresPeriode[];
    pengukurans: {
        id: string;
        status: Pengukuran['status'];
        self_approval: boolean;
        reviu_terlambat: boolean;
        nilai: Pengukuran['nilai'];
        status_perhitungan: Pengukuran['status_perhitungan'];
        satuan: string;
        desimal_tampilan: number;
        indikator: { kode: string; nama: string };
        unit: { nama: string };
        pic: { nama: string } | null;
        action: { href: string; label: string } | null;
    }[];
}
