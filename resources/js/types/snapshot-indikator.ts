import type { JadwalStatus } from '@/types/periode-jadwal';

/** Kolom snapshot yang dibandingkan dengan data master; `unit_id` dikirim server sebagai nama unit. */
export type KolomSnapshot =
    | 'nama'
    | 'definisi'
    | 'satuan'
    | 'unit_id'
    | 'arah'
    | 'presisi'
    | 'desimal_tampilan'
    | 'dinilai_tahunan'
    | 'tipe_perhitungan'
    | 'target'
    | 'baseline';

export interface SelisihKolom {
    kolom: KolomSnapshot;
    label: string;
    /** Desimal dikirim sebagai string ternormalisasi; presisi sebagai angka; penanda sebagai boolean. */
    lama: string | number | boolean | null;
    baru: string | number | boolean | null;
}

export interface KomponenSnapshot {
    komponen_id: string;
    kode: string;
    label: string;
    peran: string;
    bobot: string;
    urutan: number;
}

export interface RiwayatVersiSnapshot {
    snapshot_id: string;
    nomor_versi: number;
    periode_mulai: string | null;
    alasan_koreksi: string | null;
    rujukan_koreksi: string | null;
    diterbitkan_pada: string | null;
}

export interface SnapshotPeriode {
    id: string;
    nama: string;
    urutan: number;
}

export interface SnapshotIndikatorRow {
    indikator_id: string;
    kode: string | null;
    nama: string;
    satuan: string;
    /** Desimal tampilan versi terbaru; nilai mentah tetap dikirim sebagai string desimal. */
    desimal_tampilan: number;
    unit: string | null;
    target: string | null;
    snapshot_id: string;
    nomor_versi: number;
    periode_mulai: SnapshotPeriode | null;
    selisih: {
        kolom: SelisihKolom[];
        komponen: { lama: KomponenSnapshot[]; baru: KomponenSnapshot[] } | null;
    };
    riwayat: RiwayatVersiSnapshot[];
}

export interface SnapshotIndikatorProps {
    jadwal: { id: string; tahun: number; status: JadwalStatus; renstra: string | null };
    periode: SnapshotPeriode[];
    indikator: SnapshotIndikatorRow[];
    /** `baca_indikator` false berarti server tidak mengirim isi indikator (sama dengan detail jadwal). */
    can: { koreksi: boolean; baca_indikator: boolean };
}
