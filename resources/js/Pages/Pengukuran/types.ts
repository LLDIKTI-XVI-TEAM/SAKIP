export interface PeriodePengukuran {
    id: string;
    nama_periode: string;
    urutan: number;
}

export interface BuktiPengukuran {
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
    download_url: string | null;
}

export interface PersyaratanBukti {
    id: string;
    nama: string;
    wajib: boolean;
    semua_mode_wajib: boolean;
    izinkan_file: boolean;
    izinkan_tautan: boolean;
    izinkan_teks: boolean;
    format_diizinkan: string;
    ukuran_maks_kb: number;
    pemenuhan: { terpenuhi: boolean; mode_terpenuhi: string[]; mode_kurang: string[]; mode_dikecualikan: string[]; alasan_pengecualian: string | null };
}

export const statusPerhitungan = { belum_diisi: 'Belum diisi', terhitung: 'Terhitung', tidak_dapat_dihitung: 'Tidak dapat dihitung' };

export interface Pengukuran {
    id: string;
    versi: number;
    nomor_pengajuan: number;
    jalur_pengajuan: 'pic' | 'perencanaan' | null;
    self_approval: boolean;
    status: 'draft' | 'diajukan' | 'dikembalikan' | 'diverifikasi' | 'disahkan';
    target: number | null;
    target_pk?: number | null;
    klaim?: KlaimPengukuran[];
    nilai: number | null;
    status_perhitungan: 'belum_diisi' | 'terhitung' | 'tidak_dapat_dihitung';
    sumber_nilai: 'manual' | 'komponen' | 'historis';
    catatan: string | null;
    alasan_tidak_dapat_dihitung: string | null;
    komponen: { komponen_id: string; kode: string; label: string; peran: string; bobot: number | null; nilai: number | null }[];
    prasyarat: { siap: boolean; alasan: string[] };
    persyaratan_bukti: PersyaratanBukti[];
    unggahan_aktif: boolean;
    can: PengukuranCapabilities;
    penugasan_indikator: {
        indikator_kinerja: { kode: string; nama: string; definisi_operasional?: string | null; tipe_perhitungan: 'manual' | 'rasio_persen' | 'penjumlahan'; arah: 'naik_baik' | 'turun_baik'; presisi: number; desimal_tampilan: number; satuan: string };
        unit_kerja: { id: string; nama: string };
        pic: { id: string; nama: string } | null;
    };
    periode_jadwal: PeriodePengukuran;
    bukti_dukungs: BuktiPengukuran[];
    bukti_count: number;
    riwayats?: { id: string; status_ke: string; catatan: string | null; created_at: string; user: { id: string; nama: string } | null }[];
    snapshot?: { snapshot_hash: string; disahkan_pada: string; nomor_pengajuan: number } | null;
}

export interface PengukuranCapabilities {
    viewClaims?: boolean;
    claimEvidence?: boolean;
    view: boolean;
    evidence: boolean;
    uploadEvidence: boolean;
    update: boolean;
    submit: boolean;
    verify: boolean;
    ratify: boolean;
    return: boolean;
}

export interface KlaimPengukuran {
    id: string;
    komponen_id: string | null;
    arah_dampak: 'menambah' | 'mengurangi';
    catatan: string | null;
    sumber_klaim: 'rencana_aksi' | 'pengukuran';
    kegiatan: {
        id: string;
        nama: string;
        tujuan: string;
        status: 'rencana' | 'terlaksana' | 'tidak_terlaksana' | 'ditunda' | 'batal';
        tanggal_rencana: string | null;
        tanggal_realisasi: string | null;
        sasaran_peserta: number | null;
        realisasi_peserta: number | null;
        justifikasi: string | null;
        uraian_pelaksanaan: string | null;
        kendala: string | null;
        strategi_tindaklanjut: string | null;
        bukti_dukungs: Pick<BuktiPengukuran, 'id' | 'mode' | 'nama_asli' | 'tautan' | 'isi_teks' | 'download_url'>[];
    };
}

export interface PengukuranPagination {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
