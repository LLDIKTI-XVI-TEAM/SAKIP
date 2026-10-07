export interface RencanaAksiSahkan {
    id: string;
    versi: number;
    status: string;
    nomor_pengajuan: number;
    can: {
        ratify: boolean;
    };
}
