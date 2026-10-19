const formatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

/** Rupiah bulat (Q42 §42.2); kosong berarti "belum diisi", bukan 0. */
export function formatRupiah(nilai: string | number | null | undefined): string {
    if (nilai === null || nilai === undefined || nilai === '') return 'Belum diisi';
    // Total dapat melewati Number.MAX_SAFE_INTEGER; rupiah bulat dari server diformat lewat BigInt agar tidak dibulatkan.
    return formatter.format(typeof nilai === 'string' && /^\d+$/.test(nilai) ? BigInt(nilai) : Number(nilai));
}

/** Teks bantuan isian anggaran: pratinjau rupiah atau petunjuk pengisian. */
export function bantuanAnggaran(nilai: string): string {
    return nilai === '' ? 'Wajib diisi dalam rupiah; isi 0 bila kegiatan tanpa anggaran.' : formatRupiah(nilai);
}
