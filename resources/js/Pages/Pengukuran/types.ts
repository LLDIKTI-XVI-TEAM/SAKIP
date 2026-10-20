export interface PeriodePengukuran {
    id: string;
    nama_periode: string;
    urutan: number;
    tahun: number;
    is_nilai_akhir: boolean;
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

export const statusPerhitungan = { belum_diisi: 'Belum diisi', terhitung: 'Terhitung', tidak_dapat_dihitung: 'Tidak dapat dihitung', belum_dapat_diukur: 'Belum dapat diukur' };

export interface Pengukuran {
    id: string;
    versi: number;
    nomor_pengajuan: number;
    jalur_pengajuan: 'pic' | 'perencanaan' | null;
    self_approval: boolean;
    reviu_terlambat: boolean;
    peringatan_verifikasi?: {
        periode_pembanding: string | null;
        realisasi_sama: boolean;
        narasi_sama: boolean;
        keterangan: string | null;
    };
    status: 'draft' | 'diajukan' | 'dikembalikan' | 'diverifikasi' | 'disahkan';
    target: string | number | null;
    tanpa_target_periode?: boolean;
    target_pk?: string | number | null;
    selisih_target_pk?: string | null;
    klaim?: KlaimPengukuran[];
    nilai: string | number | null;
    status_perhitungan: keyof typeof statusPerhitungan;
    sumber_nilai: 'manual' | 'komponen' | 'historis';
    catatan: string | null;
    alasan_tidak_dapat_dihitung: string | null;
    /** Narasi hanya dikirim pada detail; versi beku tanpa key narasi bernilai null. */
    progres_kegiatan?: string | null;
    kendala_masalah?: string | null;
    strategi_tindak_lanjut?: string | null;
    tindak_lanjut_rekomendasi?: string | null;
    /** Diomit sampai reader rekomendasi tersedia; null berarti terbukti tidak ada rekomendasi approved. */
    rekomendasi_sebelumnya?: null | { id: string; isi: string; tahun: number; periode_id: string; disetujui_pada: string };
    komponen: { komponen_id: string; kode: string; label: string; peran: string; bobot: string | number | null; nilai: string | number | null }[];
    prasyarat: { siap: boolean; alasan: string[] };
    persyaratan_bukti: PersyaratanBukti[];
    can: PengukuranCapabilities;
    businessErrors?: PengukuranBusinessErrors;
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

export interface PengukuranBusinessErrors {
    verifikasi: string[];
    sahkan: string[];
    kembalikan: string[];
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
        tujuan: string | null;
        status: 'rencana' | 'terlaksana' | 'tidak_terlaksana' | 'ditunda' | 'batal';
        tanggal_rencana: string | null;
        tanggal_realisasi: string | null;
        sasaran_peserta: number | null;
        realisasi_peserta: number | null;
        justifikasi: string | null;
        uraian_pelaksanaan: string | null;
        kendala: string | null;
        strategi_tindaklanjut: string | null;
        bukti_dukungs: Pick<BuktiPengukuran, 'id' | 'mode' | 'nama_asli' | 'tautan' | 'isi_teks' | 'download_url' | 'menggantikan_id' | 'alasan_koreksi'>[];
    };
}

export interface PengukuranPagination {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export type RingkasanPengukuran = Pick<Pengukuran, 'id' | 'status' | 'nilai' | 'status_perhitungan' | 'target' | 'tanpa_target_periode' | 'bukti_count' | 'self_approval' | 'reviu_terlambat'>;

export interface KonteksBarisPengukuran {
    indikator_id: string;
    jadwal_id: string;
    jadwal_snapshot_id: string;
    tahun: number;
    periode: PeriodePengukuran;
    indikator: { kode: string; nama: string; satuan: string; desimal_tampilan: number };
    unit: { id: string; nama: string };
    pic: { id: string; nama: string } | null;
}

/** Baris kewajiban: header existing, belum diisi (berlaku), atau sebelum efektif ("Tidak berlaku"). */
export type BarisPengukuran = KonteksBarisPengukuran & (
    | { jenis: 'existing'; pengukuran: RingkasanPengukuran; can: { create: false } }
    | { jenis: 'absent'; berlaku: true; pengukuran: null; target: string | number | null; tanpa_target_periode?: boolean; can: { create: boolean } }
    | { jenis: 'absent'; berlaku: false; pengukuran: null; target: null; tanpa_target_periode?: boolean; can: { create: false } }
);

export interface KonteksPengukuran {
    jadwal_id: string;
    tahun: number;
    periode: PeriodePengukuran | null;
}

export interface PilihanJadwalPengukuran {
    id: string;
    tahun: number;
    status: 'aktif' | 'ditutup';
    periode: PeriodePengukuran[];
}
