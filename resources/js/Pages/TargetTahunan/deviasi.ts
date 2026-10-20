/** Teks pembanding target Renstra (Q39 §39.2, Q65 §65.2); status deviasi selalu berasal dari server. */
export const ANJURAN_TARGET_RENSTRA = 'Anjuran: target PK minimal sama atau naik satu poin dari target Renstra. Anjuran ini panduan, bukan aturan sistem.';

export function labelDeviasi(arah: string): string {
    return arah === 'turun_baik' ? 'Di atas target Renstra' : 'Di bawah target Renstra';
}
