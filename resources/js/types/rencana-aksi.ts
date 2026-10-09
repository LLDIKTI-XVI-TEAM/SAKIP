export type BuktiMode = 'file' | 'tautan' | 'teks';

export interface PersyaratanBuktiPemenuhan {
    terpenuhi: boolean;
    mode_terpenuhi: BuktiMode[];
    mode_kurang: BuktiMode[];
    mode_dikecualikan: string[];
    tidak_dapat_dipenuhi: boolean;
    alasan_pengecualian: string | null;
}

export interface PersyaratanBuktiRencanaAksi {
    id: string;
    nama: string;
    keterangan: string | null;
    wajib: boolean;
    semua_mode_wajib: boolean;
    izinkan_file: boolean;
    izinkan_tautan: boolean;
    izinkan_teks: boolean;
    format_diizinkan: string;
    ukuran_maks_kb: number;
    pemenuhan: PersyaratanBuktiPemenuhan;
}

export interface ItemBuktiRencanaAksi {
    id: string;
    jenis_berkas_id: string | null;
    nama_persyaratan?: string | null;
    mode: BuktiMode;
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    menggantikan_id: string | null;
    alasan_koreksi: string | null;
    uploaded_by: string;
    pengunggah_nama?: string | null;
    created_at: string | null;
}

export interface RencanaAksiSummary {
    id: string;
    tahun: number;
    uraian: string | null;
    status_alur: string;
    versi: number;
    is_disahkan: boolean;
    indikator: {
        id: string;
        kode: string;
        nama: string;
        satuan: string;
    } | null;
    unit: {
        id: string;
        nama: string;
    } | null;
    penanggungJawab: {
        id: string;
        nama: string;
    } | null;
}

export interface BuktiRencanaAksiStorageSettings {
    unggahan_aktif: boolean;
    ukuran_maks_kb: number;
    format_diizinkan: string;
}

export interface BuktiRencanaAksiCapabilities {
    upload: boolean;
    delete: boolean;
}

export interface BuktiRencanaAksiPageProps {
    rencanaAksi: RencanaAksiSummary;
    persyaratan: PersyaratanBuktiRencanaAksi[];
    summary: {
        lengkap: boolean;
        total_wajib: number;
        terpenuhi_wajib: number;
        persyaratan: PersyaratanBuktiRencanaAksi[];
    };
    daftarBukti: ItemBuktiRencanaAksi[];
    storageSettings: BuktiRencanaAksiStorageSettings;
    can: BuktiRencanaAksiCapabilities;
}
