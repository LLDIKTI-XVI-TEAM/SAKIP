import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@/types/auth';

/** Pembulatan hanya untuk tampilan; nilai formulir dan hasil server tetap utuh. */
export function formatNilai(
    nilai: string | number,
    desimalTampilan: number,
    formatAngka: string = 'id_ID'
): string {
    // Normalisasi format id_ID -> id-ID untuk Intl.NumberFormat
    const locale = formatAngka.replace('_', '-');
    const format = new Intl.NumberFormat(locale, {
        minimumFractionDigits: desimalTampilan,
        maximumFractionDigits: desimalTampilan,
    }).format as (value: string | number) => string;

    return format(nilai);
}

/** Hook untuk memformat nilai angka sesuai preferensi tampilan yang aktif. */
export function useFormatNilai(): (nilai: string | number, desimalTampilan: number) => string {
    const { props } = usePage<SharedPageProps>();
    const formatAngka = (props.pengaturan?.['tampilan.format_angka'] as string) || 'id_ID';

    return (nilai: string | number, desimalTampilan: number) => {
        return formatNilai(nilai, desimalTampilan, formatAngka);
    };
}
