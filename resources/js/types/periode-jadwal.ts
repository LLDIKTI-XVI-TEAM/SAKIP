import type { Paginated, RenstraStatus } from '@/types/renstra';

export interface PeriodeOption {
    id: string;
    nama: string;
    urutan: number;
    aktif: boolean;
    is_nilai_akhir: boolean;
    revisi: number;
}

export interface PeriodeSummary extends PeriodeOption {
    metadata_locked: boolean;
    metadata_locked_reason: string | null;
}

export interface PeriodeIndexProps {
    periode: Paginated<PeriodeSummary>;
    current_final: { id: string; nama: string; revisi: number } | null;
    filters: { q: string; status: string | null; sort: string };
    can: { create: boolean; update: boolean; replaceFinal: boolean };
}

export interface JadwalRenstraOption {
    id: string;
    nama: string;
    status: RenstraStatus;
    tahun_mulai: number;
    tahun_selesai: number;
}

export type JadwalStatus = 'draft' | 'aktif' | 'ditutup';

export interface JadwalSummary {
    id: string;
    renstra: Pick<JadwalRenstraOption, 'id' | 'nama' | 'status'>;
    tahun: number;
    status: JadwalStatus;
    revisi: number;
    penutupan: string | null;
    can_update: boolean;
}

export interface JadwalIndexProps {
    jadwal: Paginated<JadwalSummary>;
    filters: {
        q: string;
        status: string | null;
        renstra_id: string | null;
        tahun: string | number | null;
        sort: string;
    };
    can: { create: boolean };
}

export interface JadwalPeriodeInput {
    periode_id: string;
    periode_revisi: number;
    pengisian_mulai: string;
    pengisian_selesai: string;
    reviu_mulai: string;
    reviu_selesai: string;
}

export interface JadwalPeriode extends JadwalPeriodeInput {
    nama: string;
    urutan: number;
    aktif: boolean;
    is_nilai_akhir: boolean;
}

export interface JadwalDetail {
    id: string;
    renstra_id: string;
    renstra: JadwalRenstraOption;
    tahun: number;
    status: JadwalStatus;
    revisi: number;
    rencana_aksi_mulai: string | null;
    rencana_aksi_selesai: string | null;
    penutupan: string | null;
    periode: JadwalPeriode[];
}

export interface JadwalEditorProps {
    jadwal: JadwalDetail | null;
    can: { create: boolean; update: boolean };
    read_only_reason: string | null;
}
