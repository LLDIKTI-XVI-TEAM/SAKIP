export interface PengaturanItem {
    kunci: string;
    nilai: string | null;
    tipe: string;
    grup: 'instansi' | 'aplikasi' | 'tampilan' | 'laporan';
    label: string;
    updated_at: string | null;
    updated_by: {
        id: string;
        nama: string;
    } | null;
}

export interface PengaturanIndexProps {
    grouped: {
        instansi: PengaturanItem[];
        aplikasi: PengaturanItem[];
        tampilan: PengaturanItem[];
        laporan: PengaturanItem[];
    };
    values: Record<string, string | null>;
}
