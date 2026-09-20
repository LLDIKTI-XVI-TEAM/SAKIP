export type RegulasiJenis = 'kepmen' | 'permen' | 'perpres' | 'keputusan_lainnya';
export type LampiranMode = 'file' | 'tautan' | 'teks';

export interface LampiranDraft {
    clientId: string;
    mode: LampiranMode;
    file: File | null;
    tautan: string;
    isi_teks: string;
}

export interface BerkasRegulasi {
    id: number;
    mode: LampiranMode;
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    download_url: string | null;
}

export interface RegulasiSummary {
    id: number;
    jenis: RegulasiJenis;
    nomor: string;
    tahun: number;
    tentang: string;
    tanggal: string | null;
    tautan_sumber: string | null;
    aktif: boolean;
    berkas_count: number;
    pembuat: string | null;
    updated_at: string | null;
}

export interface RegulasiDetail {
    id: number;
    jenis: RegulasiJenis;
    nomor: string;
    tahun: number;
    tentang: string;
    tanggal: string | null;
    tautan_sumber: string | null;
    aktif: boolean;
    catatan: string | null;
    berkas: BerkasRegulasi[];
}

export interface RegulasiFormData {
    jenis: RegulasiJenis;
    nomor: string;
    tahun: string;
    tentang: string;
    tanggal: string;
    tautan_sumber: string;
    catatan: string;
    aktif: boolean;
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
