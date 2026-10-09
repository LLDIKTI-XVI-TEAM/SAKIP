import type { RegulasiJenis } from '@/types/regulasi';

export const jenisRegulasiLabel: Record<RegulasiJenis, string> = {
    kepmen: 'Keputusan Menteri',
    permen: 'Peraturan Menteri',
    perpres: 'Peraturan Presiden',
    keputusan_lainnya: 'Keputusan lainnya',
};

const jenisRingkas: Record<string, string> = {
    kepmen: 'Kepmen',
    permen: 'Permen',
    perpres: 'Perpres',
    keputusan_lainnya: 'Keputusan Lainnya',
};

/** Identitas ringkas seperti "Kepmen 358/M/KEP/2025"; tahun ditambahkan bila belum tercantum pada nomor. */
export function formatRegulasiRingkas(regulasi: { jenis: string; nomor: string; tahun: number | null }): string {
    const jenis = jenisRingkas[regulasi.jenis.toLowerCase()] ?? regulasi.jenis.charAt(0).toUpperCase() + regulasi.jenis.slice(1);
    const nomor = regulasi.nomor.trim();

    return regulasi.tahun && !nomor.includes(String(regulasi.tahun)) ? `${jenis} ${nomor}/${regulasi.tahun}` : `${jenis} ${nomor}`;
}
