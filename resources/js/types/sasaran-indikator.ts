export interface RenstraOption {
    id: string;
    kode: string;
    nama: string;
    tahun_mulai: number;
    tahun_selesai: number;
    is_aktif: boolean;
}

export interface UnitOption {
    id: string;
    nama: string;
}

export interface RegulasiOption {
    id: string;
    jenis: string;
    nomor: string;
    tahun: number;
    tentang: string;
}

export type IndikatorArah = 'naik_baik' | 'turun_baik';
export type IndikatorTipePerhitungan = 'manual' | 'rasio_persen' | 'penjumlahan';

export interface IndikatorKinerjaItem {
    id: string;
    sasaran_strategis_id: string;
    regulasi_id: string | null;
    kode: string;
    nama: string;
    definisi_operasional: string | null;
    satuan: string;
    unit_id: string;
    unit_nama?: string | null;
    arah: IndikatorArah;
    tipe_perhitungan: IndikatorTipePerhitungan;
    presisi: number;
    desimal_tampilan?: number;
    wajib_catatan: boolean;
    jenis_agregasi?: string;
    is_aktif: boolean;
    created_by_role?: string | null;
    regulasi?: RegulasiOption | null;
}

export interface SasaranStrategisItem {
    id: string;
    renstra_id: string;
    kode: string;
    deskripsi: string;
    urutan: number;
    indikator_kinerjas: IndikatorKinerjaItem[];
}

export interface SasaranIndikatorCapabilities {
    sasaran_create: boolean;
    sasaran_update: boolean;
    sasaran_delete: boolean;
    indikator_create: boolean;
    indikator_read: boolean;
    indikator_update: boolean;
    indikator_delete: boolean;
    regulasi_read?: boolean;
}
