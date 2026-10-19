import type { Pengukuran } from '@/Pages/Pengukuran/types';

export type StatusAlur = 'draft' | 'diajukan' | 'diverifikasi' | 'dikembalikan' | 'disahkan';

export type StatusTampilan =
    | 'belum_dibuka'
    | 'tidak_berlaku'
    | 'belum_mengisi'
    | 'tidak_mengisi'
    | 'dalam_proses'
    | 'belum_dapat_diukur'
    | 'tanpa_target_periode'
    | 'tidak_dapat_dihitung'
    | 'belum_ditetapkan';

export type StatusKegiatan = 'rencana' | 'terlaksana' | 'tidak_terlaksana' | 'ditunda' | 'batal';

export type ArahIndikator = 'naik_baik' | 'turun_baik';

export type DashboardStats = Record<StatusAlur | 'total' | 'belum_mengisi', number>;

export interface ProgresPeriode extends DashboardStats {
    periode_id: string;
    nama: string;
    dibuka: boolean;
}

export interface DashboardFilterValue {
    renstra_id: string | null;
    tahun: number | null;
    periode_id: string | null;
    sasaran_id: string | null;
    unit_id: string | null;
}

export interface DashboardPilihan {
    renstra: { id: string; nama: string }[];
    tahun: number[];
    periode: { id: string; nama: string }[];
    sasaran: { id: string; kode: string; deskripsi: string }[];
    unit: { id: string; nama: string }[];
}

export interface BarisStatusIndikator {
    indikator: { id: string; kode: string; nama: string; satuan: string; desimal_tampilan: number; arah: ArahIndikator };
    unit: { nama: string };
    pic: { nama: string } | null;
    status: StatusTampilan;
    status_alur: StatusAlur | null;
    self_approval: boolean;
    reviu_terlambat: boolean;
    realisasi: string | null;
    status_perhitungan: Pengukuran['status_perhitungan'] | null;
    target: string | null;
    tanpa_target_periode: boolean;
    target_pk: string | null;
    selisih: string | null;
    action: { href: string; label: string } | null;
}

export interface TitikGrafik {
    periode_id: string;
    nama: string;
    target: string | null;
    tanpa_target: boolean;
    realisasi: string | null;
    status: StatusTampilan;
}

export interface DashboardKegiatan {
    per_status: Record<StatusKegiatan, number>;
    terlambat_jumlah: number;
    per_iku: { kode: string; nama: string; jumlah: Record<StatusKegiatan, number> }[];
    terlambat: { id: string; nama: string; unit: string; tanggal_rencana: string | null }[] | null;
}

export interface DashboardProps {
    tahun: number;
    filter: DashboardFilterValue;
    pilihan: DashboardPilihan;
    activeRenstra: { id: string; nama: string; tahun_mulai: number; tahun_selesai: number } | null;
    activeJadwal: { tahun: number; status: string } | null;
    activePeriode: { id: string; nama_periode: string; status: 'aktif' | 'terakhir' } | null;
    stats: DashboardStats;
    progresPeriode: ProgresPeriode[];
    ringkasanStatus: Record<StatusTampilan, number>;
    statusIndikator: BarisStatusIndikator[];
    grafik: {
        indikator: { id: string; kode: string; nama: string; satuan: string; desimal_tampilan: number }[];
        seri: Record<string, TitikGrafik[]>;
    };
    progresRencanaAksi: Record<'belum_disusun' | StatusAlur, number>;
    kegiatan: DashboardKegiatan;
}
