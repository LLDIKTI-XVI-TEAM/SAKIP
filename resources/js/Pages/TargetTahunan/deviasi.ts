/** Teks pembanding target Renstra (Q39 §39.2, Q65 §65.2); status deviasi selalu berasal dari server. */
// Tabel halaman PK memuat indikator dengan arah berbeda, jadi anjurannya menyebut kedua arah.
export const ANJURAN_TARGET_RENSTRA = 'Anjuran: target PK minimal sama atau naik satu poin dari target Renstra; untuk indikator yang membaik bila nilainya turun, minimal sama atau turun satu poin. Anjuran ini panduan, bukan aturan sistem.';

/** Anjuran untuk satu indikator, mengikuti arah yang sama dengan penilaian deviasi server (Q65 §65.2 butir 13). */
export function anjuranTargetRenstra(arah: string): string {
    return `Anjuran: target PK minimal sama atau ${arah === 'turun_baik' ? 'turun' : 'naik'} satu poin dari target Renstra. Anjuran ini panduan, bukan aturan sistem.`;
}

export function labelDeviasi(arah: string): string {
    return arah === 'turun_baik' ? 'Di atas target Renstra' : 'Di bawah target Renstra';
}
