export interface RenstraOption {
    id: string;
    kode: string;
    nama: string;
    tahun_mulai: number;
    tahun_selesai: number;
    is_aktif: boolean;
    /** Hanya dikirim halaman daftar Sasaran & Indikator; bila absen, target Renstra diperlakukan baca-saja. */
    status?: 'draft' | 'aktif' | 'nonaktif' | 'diarsipkan';
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
export type IndikatorStatus = 'aktif' | 'arsip';

export type KomponenPeran = 'pembilang' | 'penyebut' | 'penjumlah';

export interface FormulaKomponenItem {
    id: string;
    kode: string;
    label: string;
    peran: KomponenPeran;
    bobot: string;
    urutan: number;
    satuan: string | null;
    aktif: boolean;
}

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
    desimal_tampilan: number;
    wajib_catatan: boolean;
    dinilai_tahunan: boolean;
    jenis_agregasi?: string;
    status: IndikatorStatus;
    tahun_mulai_berlaku: number;
    updated_at?: string | null;
    created_by_role?: string | null;
    regulasi?: RegulasiOption | null;
    /** PJ efektif hari ini; hanya dikirim kepada pemegang `penanggung_jawab:update`, selain itu null. */
    penanggung_jawab?: { nama: string; status: string } | null;
    /** Target Renstra per tahun yang sudah punya baris (nilai null = dikosongkan); hanya di halaman daftar. */
    target_renstra?: { tahun: number; nilai: string | null }[];
    target_renstra_state?: string;
}

export interface SasaranStrategisItem {
    id: string;
    renstra_id: string;
    kode: string;
    deskripsi: string;
    urutan: number;
    updated_at?: string | null;
    indikator_kinerjas: IndikatorKinerjaItem[];
}

export interface SasaranIndikatorCapabilities {
    sasaran_create: boolean;
    sasaran_update: boolean;
    sasaran_delete: boolean;
    penanggung_jawab_update?: boolean;
    indikator_create: boolean;
    indikator_read: boolean;
    indikator_update: boolean;
    indikator_delete: boolean;
    regulasi_read?: boolean;
    komponen_read?: boolean;
    komponen_create?: boolean;
    komponen_update?: boolean;
    komponen_delete?: boolean;
    target_update?: boolean;
}
