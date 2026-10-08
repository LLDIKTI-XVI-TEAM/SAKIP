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
    can: { create: boolean; update: boolean; activate: boolean };
    read_only_reason: string | null;
}

/** Pratinjau server yang advisory; hanya agregat, tanpa nama/nilai indikator atau identitas lampiran. */
export interface JadwalActivationReadiness {
    jadwal_id: string;
    checked_revisi: number;
    checked_at: string;
    allowed: boolean;
    blockers: { code: string; message: string }[];
    gates: { key: 'G1' | 'G2' | 'G3' | 'G4'; status: 'lolos' | 'gagal' | 'pengecualian'; message: string; count: number | null }[];
    counts: {
        indikator_berlaku: number;
        target_belum_terisi: number;
        snapshot_existing: number;
        snapshot_baru: number;
        komponen_baru: number;
    };
    periode_lampau_ids: string[];
}

/** Hasil mutasi yang dikirim lewat flash jadwal_aktivasi; hanya sah bila cocok dengan operasi dan objek request. */
export interface JadwalActivationOutcome {
    operation_id: string;
    jadwal_id: string;
    status: 'aktif';
    activated_at: string;
    revisi: number;
    changed: boolean;
    snapshot_created_count: number;
}
