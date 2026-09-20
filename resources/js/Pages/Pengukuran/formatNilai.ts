/** Pembulatan hanya untuk tampilan; nilai formulir dan hasil server tetap utuh. */
export function formatNilai(nilai: number, desimalTampilan: number): string {
    return nilai.toLocaleString('id-ID', { minimumFractionDigits: desimalTampilan, maximumFractionDigits: desimalTampilan });
}
