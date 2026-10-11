import type { PaginationLinkItem } from '@/Components/Pagination';

export interface KegiatanOption { id: string; nama: string }
export interface RencanaKegiatan {
    id: string;
    unit_id: string;
    tahun: number;
    periode_id: string;
    nama: string;
    tujuan: string;
    sasaran_peserta: number | null;
    keterangan_peserta: string | null;
    lokasi: string | null;
    tanggal_rencana: string | null;
    status: string;
    version_token: string;
}
export interface KegiatanFormProps {
    kegiatan: RencanaKegiatan | null;
    tahunDefault: number;
    unitOptions: KegiatanOption[];
    periodeOptions: KegiatanOption[];
}
export interface KegiatanIndexProps {
    kegiatan: {
        data: { id: string; nama: string; tujuan: string; tahun: number; status: string; tanggal_rencana: string | null; unit: KegiatanOption; periode: KegiatanOption; can: { update: boolean } }[];
        links: PaginationLinkItem[]; from: number | null; to: number | null; total: number;
    };
    filters: { tahun: string | number; unit_id: string; periode_id: string; status: string };
    unitOptions: KegiatanOption[];
    periodeOptions: KegiatanOption[];
    can: { create: boolean };
}
export const statusKegiatan = [
    { value: 'rencana', label: 'Rencana' }, { value: 'terlaksana', label: 'Terlaksana' },
    { value: 'tidak_terlaksana', label: 'Tidak terlaksana' }, { value: 'ditunda', label: 'Ditunda' }, { value: 'batal', label: 'Batal' },
];
