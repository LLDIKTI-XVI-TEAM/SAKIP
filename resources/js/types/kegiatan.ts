import type { BadgeVariant } from '@/Components/Badge';
import type { PaginationLinkItem } from '@/Components/Pagination';
import type { TanggalPeriode } from '@/types/periode-jadwal';

export interface KegiatanOption {
    id: string;
    nama: string;
}

export interface PeriodeKegiatanOption extends KegiatanOption {
    tanggal: TanggalPeriode;
}

/** Status tujuan form status pelaksanaan (Q69 §69.1); pilihannya dihitung server per kegiatan. */
export type StatusTujuan = 'ditunda' | 'tidak_terlaksana' | 'batal';

export interface RencanaKegiatan {
    id: string;
    unit_id: string;
    tahun: number;
    periode_id: string;
    nama: string;
    tujuan: string | null;
    sasaran_peserta: number | null;
    keterangan_peserta: string | null;
    lokasi: string | null;
    tanggal_rencana: string | null;
    /** Rupiah bulat; null berarti belum diisi (data lama, data awal, periode lampau). */
    anggaran: string | null;
    status: string;
    version_token: string;
    /** Justifikasi status pelaksanaan; null selama status `rencana`. */
    justifikasi: string | null;
    periode_nama: string;
    /** Nama periode kegiatan lanjutan bila kegiatan ini sudah ditunda. */
    periode_tujuan: string | null;
}

export interface KegiatanFormProps {
    kegiatan: RencanaKegiatan;
    /** Panel klaim tahap RA; hanya ada pada halaman ubah. */
    klaim?: KlaimKegiatanPanel | null;
    /** Panel berkas tahap perencanaan kegiatan (Q42 §42.3). */
    berkasPerencanaan?: BerkasPerencanaanKegiatan | null;
    unitOptions: KegiatanOption[];
    periodeOptions: PeriodeKegiatanOption[];
    opsiPeriodeTujuan: KegiatanOption[];
    /** `ubah_rencana` false berarti data rencana baca-saja (Q69 §69.3); `ubah_status` kosong berarti tanpa aksi status. */
    can: { ubah_rencana: boolean; ubah_status: StatusTujuan[] };
}

export interface KegiatanIndexItem {
    id: string;
    nama: string;
    tujuan: string | null;
    tahun: number;
    status: string;
    tanggal_rencana: string | null;
    unit: KegiatanOption;
    periode: PeriodeKegiatanOption;
    can: { update: boolean; ubah_rencana: boolean; ubah_status: StatusTujuan[] };
    opsi_periode_tujuan: KegiatanOption[];
    unit_id?: string;
    periode_id?: string;
    sasaran_peserta?: number | null;
    keterangan_peserta?: string | null;
    lokasi?: string | null;
    version_token: string;
}

export interface KegiatanIndexProps {
    kegiatan: {
        data: KegiatanIndexItem[];
        links: PaginationLinkItem[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { tahun: string | number; unit_id: string; periode_id: string; status: string };
    unitOptions: KegiatanOption[];
    periodeOptions: KegiatanOption[];
    creatableUnitOptions?: KegiatanOption[];
    activePeriodeOptions?: KegiatanOption[];
    tahunDefault?: number;
    can: { create: boolean };
}

export const statusKegiatan = [
    { value: 'rencana', label: 'Rencana' },
    { value: 'terlaksana', label: 'Terlaksana' },
    { value: 'tidak_terlaksana', label: 'Tidak terlaksana' },
    { value: 'ditunda', label: 'Ditunda' },
    { value: 'batal', label: 'Batal' },
];

export const statusKegiatanConfig: Record<string, { label: string; variant: BadgeVariant }> = {
    rencana: { label: 'Rencana', variant: 'muted' },
    terlaksana: { label: 'Terlaksana', variant: 'success' },
    tidak_terlaksana: { label: 'Tidak terlaksana', variant: 'danger' },
    ditunda: { label: 'Ditunda', variant: 'warning' },
    batal: { label: 'Batal', variant: 'danger' },
};

export type ArahDampak = 'menambah' | 'mengurangi';

export const ARAH_DAMPAK_OPTIONS: { value: ArahDampak; label: string }[] = [
    { value: 'menambah', label: 'Menambah' },
    { value: 'mengurangi', label: 'Mengurangi' },
];

export interface KlaimKegiatanItem {
    id: string;
    rencana_aksi_id: string;
    rencana_aksi: string | null;
    status_alur: string | null;
    komponen_id: string | null;
    komponen: string | null;
    arah_dampak: ArahDampak;
    catatan: string | null;
    can: { hapus: boolean };
}

export interface OpsiRencanaAksi {
    id: string;
    label: string;
    /** Komponen yang belum diklaim untuk RA ini. */
    komponen: KegiatanOption[];
    /** True bila klaim tanpa komponen tertentu ke RA ini belum ada. */
    tanpa_komponen: boolean;
}

export interface KlaimKegiatanPanel {
    daftar: KlaimKegiatanItem[];
    opsiRencanaAksi: OpsiRencanaAksi[];
    can: { tambah: boolean };
}

export type BerkasKegiatanMode = 'file' | 'tautan' | 'teks';

export interface BerkasKegiatanItem {
    id: string;
    mode: BerkasKegiatanMode;
    nama_asli: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    menggantikan_id: string | null;
    created_at: string | null;
}

export interface PersyaratanPerencanaanKegiatan {
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
    hanya_kegiatan_beranggaran: boolean;
    pemenuhan: { terpenuhi: boolean; mode_kurang: BerkasKegiatanMode[] };
    berkas: BerkasKegiatanItem[];
    contoh: { id: string; nama_asli: string | null }[];
}

export interface BerkasPerencanaanKegiatan {
    persyaratan: PersyaratanPerencanaanKegiatan[];
    alasan_tolak_unggah: string | null;
    alasan_tolak_ganti: string | null;
    alasan_tolak_hapus: string | null;
}
