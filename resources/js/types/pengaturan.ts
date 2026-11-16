export interface PengaturanItem {
    kunci: string;
    nilai: string | boolean | null;
    tipe: string;
    grup: 'instansi' | 'aplikasi' | 'tampilan' | 'laporan' | 'notifikasi';
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
        notifikasi: PengaturanItem[];
    };
    values: PengaturanValues;
    notifikasi: {
        h_minus_pic: number[];
        h_minus_rekap: number[];
        simulasi: { email: boolean; whatsapp: boolean };
    };
}

export interface PengaturanValues {
    'instansi.nama': string | null;
    'instansi.alamat': string | null;
    'instansi.telepon': string | null;
    'instansi.surel': string | null;
    'instansi.laman': string | null;
    'instansi.logo': string | null;
    'aplikasi.nama': string | null;
    'aplikasi.label_unit': string | null;
    'tampilan.zona_waktu': string | null;
    'tampilan.format_tanggal': string | null;
    'tampilan.format_angka': string | null;
    'laporan.header': string | null;
    'laporan.footer': string | null;
    'notifikasi.kanal_email_aktif': boolean;
    'notifikasi.kanal_whatsapp_aktif': boolean;
    'notifikasi.jam_kirim': string | null;
}
