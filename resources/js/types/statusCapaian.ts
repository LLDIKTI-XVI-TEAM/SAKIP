/**
 * Kontrak props halaman penetapan status capaian per versi pengukuran yang telah disahkan.
 * Seluruh angka berasal dari snapshot versi beku server; React hanya menampilkan.
 */

export type StatusCapaianNilai = 'tercapai' | 'belum_tercapai';

/** Status capaian yang boleh ditampilkan badge, termasuk kondisi Q47 yang bukan nilai tersimpan. */
export type StatusCapaianKode =
    | StatusCapaianNilai
    | 'belum_ditetapkan'
    | 'tanpa_target_periode'
    | 'belum_dapat_diukur'
    | 'tidak_dapat_dihitung';

export interface RiwayatStatusCapaian {
    id: string;
    status: StatusCapaianNilai;
    sumber: string;
    versi_id: string;
    versi_nomor: number | null;
    penetap: string | null;
    ditetapkan_pada: string | null;
}

export interface StatusCapaianBerlaku {
    id: string;
    status: StatusCapaianNilai;
    sumber: string;
    ditetapkan_pada: string | null;
    penetap: string | null;
    versi_id: string;
    versi_nomor: number | null;
}

export interface BarisStatusCapaian {
    id: string;
    /** Token konkurensi header pengukuran; dikirim kembali saat menyimpan agar versi basi ditolak 409. */
    versi: number;
    tahun: number;
    versi_pengukuran: { id: string; nomor: number; disahkan_pada: string | null } | null;
    indikator: {
        kode: string | null;
        nama: string | null;
        satuan: string | null;
        presisi: number | null;
        desimal_tampilan: number | null;
    };
    unit: { id: string; nama: string | null };
    periode: { id: string; nama: string | null; urutan: number | null };
    nilai: string | null;
    status_perhitungan: string | null;
    target: string | null;
    target_pk: string | null;
    capaian_persen: string | null;
    selisih_target_pk: string | null;
    riwayat: RiwayatStatusCapaian[];
    can: { tetapkan: boolean; revisi: boolean };
    status_berlaku?: StatusCapaianBerlaku;
}

export interface PaginationStatusCapaian {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface StatusCapaianProps {
    /** Pengukuran disahkan yang versi sah terakhirnya belum memiliki status berlaku. */
    kandidat: BarisStatusCapaian[];
    /** Pengukuran disahkan yang versi sah terakhirnya sudah memiliki status berlaku (pintu masuk revisi). */
    sudah_ditetapkan: BarisStatusCapaian[];
    pagination: PaginationStatusCapaian;
    /** Paginasi terpisah untuk koleksi revisi; parameter halamannya `status_page`. */
    pagination_status: PaginationStatusCapaian;
    can: { tetapkan: boolean };
}

export interface FormStatusCapaian {
    status: StatusCapaianNilai | '';
    pengukuran_versi_id: string;
    versi: number;
    alasan: string;
}

export type ModeStatusCapaian = 'tetapkan' | 'revisi';
