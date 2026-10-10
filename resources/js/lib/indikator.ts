import type { IndikatorArah, IndikatorTipePerhitungan } from '@/types/sasaran-indikator';

export const tipePerhitunganLabel: Record<IndikatorTipePerhitungan, string> = {
    manual: 'Manual',
    rasio_persen: 'Rasio Persen',
    penjumlahan: 'Penjumlahan',
};

export const arahLabel: Record<IndikatorArah, string> = {
    naik_baik: 'Makin tinggi makin baik',
    turun_baik: 'Makin rendah makin baik',
};
