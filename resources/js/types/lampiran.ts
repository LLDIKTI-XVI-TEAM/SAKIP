export type LampiranMode = 'file' | 'tautan' | 'teks';

/** Draf lampiran pada form master (Regulasi, Renstra, PK) sebelum dikirim ke server. */
export interface LampiranDraft {
    clientId: string;
    mode: LampiranMode;
    file: File | null;
    tautan: string;
    isi_teks: string;
    /** Judul opsional untuk tautan/teks; saat ini hanya dipakai PK. */
    nama_asli?: string;
}
