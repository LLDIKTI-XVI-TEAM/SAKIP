export type RenstraStatus = 'draft' | 'aktif' | 'nonaktif' | 'diarsipkan';
export type LampiranMode = 'file' | 'tautan' | 'teks';

export interface LampiranDraft {
    clientId: string;
    mode: LampiranMode;
    file: File | null;
    tautan: string;
    isi_teks: string;
}

export interface BerkasRenstra {
    id: number;
    mode: LampiranMode;
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    download_url: string | null;
}

export interface RegulasiOption {
    id: number;
    nomor: string;
    tentang: string;
    tahun: number;
}

export interface RenstraSummary {
    id: number;
    nama: string;
    kode: string;
    tahun_mulai: number;
    tahun_selesai: number;
    status: RenstraStatus;
    is_aktif: boolean;
    regulasi_id: number | null;
    regulasi_nomor: string | null;
    berkas_count: number;
    pembuat: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface RenstraDetail {
    id: string | number;
    nama: string;
    kode: string;
    tahun_mulai: number;
    tahun_selesai: number;
    status: RenstraStatus;
    is_aktif: boolean;
    deskripsi: string | null;
    dasar_hukum: string | null;
    regulasi_id: string | number | null;
    regulasi: RegulasiOption | null;
    pembuat: { id: string | number; nama?: string; name?: string } | string | null;
    versi?: number | null;
    berkas?: BerkasRenstra[];
    created_at: string | null;
    updated_at: string | null;
}

export interface RenstraFormData {
    nama: string;
    kode: string;
    tahun_mulai: string;
    tahun_selesai: string;
    deskripsi: string;
    dasar_hukum: string;
    regulasi_id: string;
    alasan: string;
    lampiran: LampiranDraft[];
    _method?: 'put';
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}
