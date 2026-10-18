/** Kontrak Izin Unit per pengguna × unit (Q60), sama untuk daftar dan halaman detail. */
export interface IzinDalamKelompok {
    grant_id: string;
    permission_id: string;
    label: string;
    aktif: boolean;
    /** Hanya dikirim halaman detail. */
    alasan?: string;
    diberikan_oleh_nama?: string;
    created_at?: string | null;
}

export interface KelompokIzinUnit {
    key: string;
    user_id: string;
    user_name: string;
    user_email: string;
    user_roles: string[];
    unit_id: string;
    unit_nama: string | null;
    /** Alasan seluruh kelompok tidak berlaku menurut kriteria resolver server; kosong berarti berlaku. */
    tidak_berlaku: string[];
    izin: IzinDalamKelompok[];
}

export interface PilihanIzin {
    id: string;
    kode: string;
    label: string;
}
