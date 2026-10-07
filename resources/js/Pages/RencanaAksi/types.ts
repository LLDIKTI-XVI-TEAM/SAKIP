export interface RencanaAksiSahkan {
    id: string;
    versi: number;
    status: string;
    nomor_pengajuan: number;
    can: {
        ratify: boolean;
    };
}

export interface RencanaAksiBukti {
    id: string;
    jenis_berkas_id: string | null;
    menggantikan_id: string | null;
    alasan_koreksi: string | null;
    mode: 'file' | 'tautan' | 'teks';
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
}

export interface RencanaAksiRingkas {
    id: string;
    versi: number;
    status: 'draft' | 'diajukan' | 'dikembalikan' | 'diverifikasi' | 'disahkan' | string;
    tahun: number;
    nomor_pengajuan: number;
    jalur_pengajuan: 'pic' | 'perencanaan' | null;
    diajukan_pada: string | null;
    uraian: string | null;
    indikator: { kode: string; nama: string };
    unit_kerja: { id: string; nama: string };
    pic: { id: string; nama: string } | null;
    bukti_count: number;
    bukti_dukungs: RencanaAksiBukti[];
    target_periode: { periode_id: string | null; nilai: string | number | null; status_perhitungan: string | null; komponen: { komponen_id: string; nilai: string | number | null }[] }[];
    can: { view: boolean; ratify: boolean };
}

export interface RencanaAksiDetail extends RencanaAksiRingkas {
    diajukan_oleh: { id: string; nama: string } | null;
    disahkan_pada: string | null;
    target_periode: {
        periode_id: string | null;
        periode_nama: string | null;
        periode_urutan: number | null;
        nilai: string | number | null;
        status_perhitungan: string | null;
        komponen: { komponen_id: string; nilai: string | number | null }[];
    }[];
    bukti_dukungs: RencanaAksiBukti[];
    can: { view: boolean; ratify: boolean };
}

export interface RencanaAksiPagination {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
