/** Pembulatan hanya untuk tampilan; nilai formulir dan hasil server tetap utuh. */
export function formatNilai(nilai: string | number, desimalTampilan: number): string {
    // Intl menerima string desimal eksak; tipe bawaan TS belum memuat overload string.
    const format = new Intl.NumberFormat('id-ID', { minimumFractionDigits: desimalTampilan, maximumFractionDigits: desimalTampilan }).format as (value: string | number) => string;
    return format(nilai);
}
