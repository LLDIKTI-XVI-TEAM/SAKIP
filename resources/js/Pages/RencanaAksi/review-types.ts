import type { PengukuranPagination } from '@/Pages/Pengukuran/types';
import type { TotalAnggaran } from './types';

export type StatusReview = 'draft' | 'diajukan' | 'diverifikasi' | 'dikembalikan' | 'disahkan';

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
    /** Klaim tahap RA yang dibekukan pada versi; null untuk versi sebelum klaim dibekukan atau tanpa izin baca kegiatan. */
    klaim: {
        id: string;
        periode_nama: string;
        nama: string;
        status: string | null;
        komponen: string | null;
        arah_dampak: 'menambah' | 'mengurangi' | null;
        catatan: string | null;
        /** Rupiah bulat beku; null berarti belum diisi. */
        anggaran: string | null;
    }[] | null;
    /** Total anggaran yang dibekukan saat pengajuan; null untuk versi lama atau tanpa izin baca kegiatan. */
    anggaran_kegiatan: (TotalAnggaran & { per_periode: Record<string, TotalAnggaran> }) | null;
    bukti_dukungs: {
        id: string;
        mode: 'file' | 'tautan' | 'teks';
        nama_asli: string | null;
        tautan: string | null;
        isi_teks: string | null;
        download_url: string | null;
    }[];
    can: { verifikasi: boolean; kembalikan: boolean; sahkan: boolean; buka_kembali: boolean; evidence: boolean; kegiatan: boolean };
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
