import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@/types/auth';

export interface FormatTanggalOptions {
    withDay?: boolean;
    withTime?: boolean;
}

/**
 * Format tanggal sesuai zona waktu dan preferensi format tanggal yang aktif.
 */
export function formatTanggal(
    dateInput: Date | string | number | null | undefined,
    options: FormatTanggalOptions = {},
    timeZone: string = 'Asia/Makassar',
    formatTanggalKey: string = 'd F Y',
    formatAngkaKey: string = 'id_ID'
): string {
    if (!dateInput) return '—';

    const date = typeof dateInput === 'string' || typeof dateInput === 'number'
        ? new Date(dateInput)
        : dateInput;

    if (isNaN(date.getTime())) return '—';

    const locale = (formatAngkaKey || 'id_ID').replace('_', '-');
    const effectiveTimeZone = timeZone || 'Asia/Makassar';

    // Gunakan formatToParts untuk merakit komponen tanggal sesuai zona waktu dan format
    const isFullMonth = formatTanggalKey === 'd F Y';
    const formatter = new Intl.DateTimeFormat(locale, {
        timeZone: effectiveTimeZone,
        weekday: options.withDay ? 'long' : undefined,
        day: 'numeric',
        month: isFullMonth ? 'long' : '2-digit',
        year: 'numeric',
        hour: options.withTime ? '2-digit' : undefined,
        minute: options.withTime ? '2-digit' : undefined,
        hour12: false,
    });

    const parts = formatter.formatToParts(date);
    const getPart = (type: string) => parts.find((p) => p.type === type)?.value || '';

    const day = getPart('day');
    const month = getPart('month');
    const year = getPart('year');
    const weekday = getPart('weekday');
    const hour = getPart('hour');
    const minute = getPart('minute');

    let dateStr = '';
    if (formatTanggalKey === 'Y-m-d') {
        const paddedDay = day.padStart(2, '0');
        const paddedMonth = month.padStart(2, '0');
        dateStr = `${year}-${paddedMonth}-${paddedDay}`;
    } else if (formatTanggalKey === 'd/m/Y') {
        const paddedDay = day.padStart(2, '0');
        const paddedMonth = month.padStart(2, '0');
        dateStr = `${paddedDay}/${paddedMonth}/${year}`;
    } else {
        // Default: d F Y (misal 24 September 2026)
        dateStr = `${day} ${month} ${year}`;
    }

    if (options.withDay && weekday) {
        dateStr = `${weekday}, ${dateStr}`;
    }

    if (options.withTime && hour && minute) {
        dateStr = `${dateStr} ${hour}:${minute}`;
    }

    return dateStr;
}

/** Hook untuk memformat tanggal sesuai preferensi tampilan yang aktif. */
export function useFormatTanggal(): (
    date: Date | string | number | null | undefined,
    options?: FormatTanggalOptions
) => string {
    let timeZone = 'Asia/Makassar';
    let formatTanggalKey = 'd F Y';
    let formatAngkaKey = 'id_ID';

    try {
        const { props } = usePage<SharedPageProps>();
        const pengaturan = props?.pengaturan;
        if (pengaturan) {
            if (typeof pengaturan['tampilan.zona_waktu'] === 'string') {
                timeZone = pengaturan['tampilan.zona_waktu'];
            }
            if (typeof pengaturan['tampilan.format_tanggal'] === 'string') {
                formatTanggalKey = pengaturan['tampilan.format_tanggal'];
            }
            if (typeof pengaturan['tampilan.format_angka'] === 'string') {
                formatAngkaKey = pengaturan['tampilan.format_angka'];
            }
        }
    } catch {
        // Fallback jika di luar context Inertia
    }

    return (date, options) => {
        return formatTanggal(date, options, timeZone, formatTanggalKey, formatAngkaKey);
    };
}
