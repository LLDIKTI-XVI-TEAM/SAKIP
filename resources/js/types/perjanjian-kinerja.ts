import type { LampiranDraft, LampiranMode } from './lampiran';
import type { PengukuranBelumDisahkan } from './snapshot-indikator';

export type { LampiranDraft, LampiranMode };

export interface BerkasPk {
    id: string;
    mode: LampiranMode;
    nama_asli: string | null;
    keterangan?: string | null;
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

/** Baris target PK pada halaman PK; nilai berupa string kanonis dari server. */
export interface TargetPkIndikator {
    id: string;
    kode: string;
    nama: string;
    satuan: string;
    desimal_tampilan: number;
    arah: string;
    baseline: string | null;
    target_tahunan: string | null;
    /** `null` berarti target Renstra belum ada. */
    target_renstra: string | null;
    status_deviasi: string;
    read_only_reason: string | null;
}

export interface TargetPkSasaran {
    id: string;
    kode: string;
    deskripsi: string;
    indikator: TargetPkIndikator[];
}

export interface RenstraPkSummary {
    id: string;
    renstra_id: string;
    tahun: number;
    nomor_pk: string | null;
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
    alasan_wajib?: boolean;
    jadwal_tahunan?: {
        id: string;
        status: string;
        is_terkunci?: boolean;
        activated_at?: string | null;
    } | null;
}

/** Selisih target antara versi PK dan versi sebelumnya; `null` berarti indikator tidak ada di salah satu versi. */
export interface PerubahanTargetVersi {
    indikator_id: string;
    kode: string;
    lama: string | null;
    baru: string | null;
    desimal_tampilan: number;
}

/** Satu baris linimasa versi PK; versi 1 (PK awal) tidak membawa surat dan alasan. */
export interface VersiPk {
    id: string;
    nomor_versi: number;
    nomor_surat: string | null;
    tanggal_surat: string | null;
    periode_mulai: { id: string; nama: string } | null;
    alasan: string | null;
    disetujui_oleh: string | null;
    disetujui_pada: string | null;
    berkas: BerkasPk[];
    perubahan_target: PerubahanTargetVersi[];
}

/** Indikator aktif PK dengan nilai dasar revisi (target snapshot terbaru). */
export interface RevisiTargetIndikator {
    indikator_id: string;
    kode: string;
    nama: string;
    satuan: string;
    nilai: string;
    desimal_tampilan: number;
}

/** Data form revisi dari server; periode mulai hanya pratinjau, nilai final ditetapkan saat simpan. */
export interface RevisiPk {
    expected_nomor_versi: number;
    expected_dasar: string;
    periode_mulai: { id: string; nama: string } | null;
    dapat_ubah_target: boolean;
    alasan_tidak_dapat_ubah_target: string | null;
    target: RevisiTargetIndikator[];
    pengukuran_belum_disahkan: Record<string, (PengukuranBelumDisahkan & { periode_nama: string })[]>;
}

export interface RevisiPkFormData {
    nomor_surat: string;
    tanggal_surat: string;
    alasan: string;
    target: { indikator_id: string; nilai: string }[];
    lampiran: LampiranDraft[];
    konfirmasi_pengukuran_sebelumnya: boolean;
}

export interface StorageSettings {
    ukuran_maks_kb: number;
    format_diizinkan: string;
}

export interface PerjanjianKinerjaFormData {
    renstra_id: string;
    tahun: number | string;
    nomor_pk: string;
    tanggal_pk: string;
    expected_updated_at?: string;
    alasan?: string;
    rujukan?: string;
    lampiran: LampiranDraft[];
    konflik?: string;
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
