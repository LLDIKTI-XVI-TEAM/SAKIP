// Pesan server (mis. penolakan wewenang atau grant di luar cakupan unit) lebih spesifik daripada teks baku.
export function pesanGagal(response: { status: number; data: unknown }): string {
    const { data } = response;
    if (typeof data === 'object' && data !== null && 'message' in data && typeof data.message === 'string' && data.message !== '') return data.message;
    return response.status === 403
        ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.'
        : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.';
}

export const belumDiketahui = (aksi: string, sebab: string) => `${sebab} Hasil ${aksi} izin belum diketahui. Periksa daftar terbaru sebelum mencoba kembali.`;

export const failureClass = 'rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-ink';

/** Galat per butir datang sebagai permission_ids.N; tampil di grup checkbox yang sama. */
export function galatIzin(errors: Record<string, string | undefined>): string | undefined {
    return errors.permission_ids ?? Object.entries(errors).find(([key]) => key.startsWith('permission_ids.'))?.[1];
}
