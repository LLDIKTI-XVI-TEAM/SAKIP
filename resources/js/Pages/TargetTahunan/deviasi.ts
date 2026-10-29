/** Teks pembanding target Renstra (Q39 §39.2, Q72 §72.1); status deviasi selalu berasal dari server. */
// Tabel halaman PK memuat indikator dengan arah berbeda, jadi anjurannya menyebut kedua arah.
export const ANJURAN_TARGET_RENSTRA = 'Target PK tidak boleh di bawah target Renstra, atau di atasnya untuk indikator yang membaik bila nilainya turun; anjuran naik atau turun satu poin hanya panduan.';

/** Batas ditegakkan server, sedangkan "satu poin" tetap panduan; mengikuti arah penilaian deviasi server (Q72 §72.1 butir 7). */
export function anjuranTargetRenstra(arah: string): string {
    return arah === 'turun_baik'
        ? 'Target PK tidak boleh di atas target Renstra. Anjuran: turun satu poin; anjuran ini panduan.'
        : 'Target PK tidak boleh di bawah target Renstra. Anjuran: naik satu poin; anjuran ini panduan.';
}

export function labelDeviasi(arah: string): string {
    return arah === 'turun_baik' ? 'Di atas target Renstra' : 'Di bawah target Renstra';
}
