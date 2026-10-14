import type { PengukuranPagination } from '@/Pages/Pengukuran/types';

export type StatusReview = 'draft' | 'diajukan' | 'diverifikasi' | 'dikembalikan' | 'disahkan';
export type FilterReview = Exclude<StatusReview, 'draft'> | 'semua';

export interface ReviewIndexProps {
    daftar: {
        id: string;
        tahun: number;
        status_alur: StatusReview;
        nomor_pengajuan: number;
        indikator: { kode: string; nama: string };
        unit_nama: string | null;
    }[];
    pagination: PengukuranPagination;
    filters: { status: FilterReview };
}

export interface ReviewRencanaAksi {
    id: string;
    tahun: number;
    status_alur: StatusReview;
    expected_versi: number;
    versi_pengajuan: {
        id: string;
        nomor: number;
        diajukan_at: string;
        jalur_pengajuan: 'pic' | 'perencanaan';
        pengaju: { id: string; nama: string } | null;
    };
    indikator: { kode: string; nama: string; satuan: string; desimal_tampilan: number };
    unit_kerja: { id: string; nama: string } | null;
    pic: { id: string; nama: string } | null;
    uraian: string | null;
    alasan_deviasi_pk: string | null;
    target_periode: {
        periode_id: string;
        periode_nama: string;
        periode_urutan: number;
        nilai: string | null;
        status_perhitungan: string | null;
        keterangan: string | null;
        komponen: { komponen_id: string; kode: string; label: string; nilai: string | null; keterangan: string | null }[];
    }[];
    bukti_dukungs: {
        id: string;
        mode: 'file' | 'tautan' | 'teks';
        nama_asli: string | null;
        tautan: string | null;
        isi_teks: string | null;
        download_url: string | null;
    }[];
    can: { verifikasi: boolean; kembalikan: boolean; sahkan: boolean; buka_kembali: boolean; evidence: boolean };
    businessErrors: { verifikasi: string[]; kembalikan: string[]; sahkan: string[]; buka_kembali: string[] };
    /** Metadata pengesahan versi yang ditampilkan; null bila versi ini belum disahkan. */
    pengesahan: { oleh: string; pada: string | null; self_approval: boolean } | null;
    /** Penanda pernah dibuka kembali pasca-pengesahan (dari audit append-only). */
    dibuka_kembali: boolean;
    histori: { id: string; nomor: number; diajukan_at: string; jalur_pengajuan: 'pic' | 'perencanaan' }[];
    pagination: PengukuranPagination;
}

export interface ReviewProps {
    rencanaAksi: ReviewRencanaAksi;
}
