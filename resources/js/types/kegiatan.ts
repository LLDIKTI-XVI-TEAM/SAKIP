import type { BadgeVariant } from '@/Components/Badge';
import type { PaginationLinkItem } from '@/Components/Pagination';

export interface KegiatanOption {
    id: string;
    nama: string;
}

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
    status: string;
    version_token: string;
}

export interface KegiatanFormProps {
    kegiatan: RencanaKegiatan;
    /** Panel klaim tahap RA; hanya ada pada halaman ubah. */
    klaim?: KlaimKegiatanPanel | null;
    unitOptions: KegiatanOption[];
    periodeOptions: KegiatanOption[];
}

export interface KegiatanIndexItem {
    id: string;
    nama: string;
    tujuan: string | null;
    tahun: number;
    status: string;
    tanggal_rencana: string | null;
    unit: KegiatanOption;
    periode: KegiatanOption;
    can: { update: boolean };
    unit_id?: string;
    periode_id?: string;
    sasaran_peserta?: number | null;
    keterangan_peserta?: string | null;
    lokasi?: string | null;
    version_token?: string;
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
