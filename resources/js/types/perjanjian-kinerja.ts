export type LampiranMode = 'file' | 'tautan' | 'teks';

export interface LampiranDraft {
    clientId: string;
    mode: LampiranMode;
    file: File | null;
    tautan: string;
    isi_teks: string;
    nama_asli?: string;
}

export interface BerkasPk {
    id: string;
    mode: LampiranMode;
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    download_url?: string;
    created_at?: string;
    pengunggah?: {
        id: string;
        nama: string;
    } | null;
}

export interface RenstraSummary {
    id: string;
    kode: string;
    nama: string;
    tahun_mulai: number;
    tahun_selesai: number;
    is_aktif?: boolean;
}

export interface RenstraPkSummary {
    id: string;
    renstra_id: string;
    tahun: number;
    nomor_pk: string;
    tanggal_pk: string | null;
    created_at: string;
    updated_at: string;
    renstra: RenstraSummary;
    creator?: {
        id: string;
        nama: string;
    } | null;
    berkas?: BerkasPk[];
    berkas_count?: number;
    jadwal_tahunan?: {
        id: string;
        status: string;
        is_terkunci?: boolean;
        activated_at?: string | null;
    } | null;
}

export interface StorageSettings {
    unggahan_aktif: boolean;
    ukuran_maks_kb: number;
    format_diizinkan: string;
}

export interface PerjanjianKinerjaFormData {
    renstra_id: string;
    tahun: number | string;
    nomor_pk: string;
    tanggal_pk: string;
    alasan?: string;
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
