import type { PaginationLinkItem } from '@/Components/Pagination';

export interface BarisRekap {
    id: string;
    versi: { id: string; nomor: number; disahkan_pada: string } | null;
    periode: { id: string; nama: string; urutan: number };
    indikator: {
        id: string; kode: string | null; nama: string | null; satuan: string | null;
        arah: 'naik_baik' | 'turun_baik'; tipe_perhitungan: 'manual' | 'penjumlahan' | 'rasio_persen';
        presisi: number; desimal_tampilan: number; definisi: string | null;
    };
    sasaran: { id: string; kode: string; deskripsi: string } | null;
    unit: { id: string; nama: string | null };
    pic: { id: string; nama: string } | null;
    pic_saat_ini: { id: string; nama: string } | null;
    baseline: string | null;
    target_pk: string | null;
    target_periode: string | null;
    tanpa_target_periode: boolean;
    nilai: string | null;
    status_perhitungan: string | null;
    status_laporan: 'belum_disahkan' | 'belum_dapat_diukur' | 'tanpa_target_periode' | 'tidak_dapat_dihitung' | 'disahkan' | 'konteks_tidak_lengkap';
    capaian_persen: string | null;
    keterangan_capaian: string | null;
    status_capaian: string;
    ditetapkan_pada?: string | null;
    selisih_target_pk: string | null;
    alasan_tidak_dapat_dihitung: string | null;
    komponen: { komponen_id: string; kode: string; label: string; peran: string; bobot: string; target: string | null; realisasi: string | null }[];
    narasi: { progres_kegiatan: string | null; kendala_masalah: string | null; strategi_tindak_lanjut: string | null; tindak_lanjut_rekomendasi: string | null } | null;
    kegiatan: {
        id: string | null; nama?: string; tujuan?: string | null; status?: string;
        uraian_pelaksanaan?: string | null; kendala?: string | null; strategi_tindaklanjut?: string | null;
        anggaran_tercatat: boolean; anggaran: string | null; kegiatan_bersama: boolean | null; sumber_klaim: string | null;
    }[] | null;
    bukti: { status: 'lengkap' | 'belum_lengkap' | 'tidak_tercatat'; total?: number; terpenuhi?: number; histori_tidak_dapat_dipenuhi: boolean } | null;
    rekomendasi: { id: string; isi: string; disetujui_pada: string; cadangan: boolean } | null;
    rekomendasi_sebelumnya: { isi?: string; tahun?: number; periode_nama?: string } | null;
    can: { lihat_kegiatan: boolean };
}

export interface RekapProps {
    filters: { jadwal_id: string; periode_id: string };
    jadwal: { id: string; tahun: number; status: string; renstra: string } | null;
    pilihan_jadwal: { id: string; label: string }[];
    pilihan_periode: { id: string; nama: string }[];
    baris: BarisRekap[];
    pagination: { links: PaginationLinkItem[]; from: number | null; to: number | null; total: number };
}
