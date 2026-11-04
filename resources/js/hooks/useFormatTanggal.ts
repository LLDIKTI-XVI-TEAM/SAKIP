import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@/types/auth';

export interface FormatTanggalOptions {
    withDay?: boolean;
    withTime?: boolean;
}

/**
 * Pola tanggal murni `YYYY-MM-DD` tanpa komponen zona waktu. Nilai seperti ini tidak boleh
 * diparse lewat `new Date(string)` karena akan dianggap instan UTC dan bergeser hari saat
 * diformat pada zona tampilan pengguna.
 */
const POLA_TANGGAL_MURNI = /^\d{4}-\d{2}-\d{2}$/;

/** Mengubah tanggal murni menjadi instan UTC pukul 00:00; `null` bila tanggal tidak ada di kalender. */
function tanggalMurniKeUtc(nilai: string): Date | null {
    const [tahun, bulan, hari] = nilai.split('-').map(Number);
    const anchor = new Date(Date.UTC(tahun, bulan - 1, hari));

    // Date.UTC menormalkan tanggal mustahil (2026-02-31 menjadi 3 Maret); tolak hasil normalisasi.
    if (anchor.getUTCFullYear() !== tahun || anchor.getUTCMonth() !== bulan - 1 || anchor.getUTCDate() !== hari) {
        return null;
    }

    return anchor;
}

/**
 * Format tanggal sesuai zona waktu dan preferensi format tanggal yang aktif.
 */
export function formatTanggal(
    dateInput: Date | string | number | null | undefined,
    options: FormatTanggalOptions = {},
    timeZone: string = 'Asia/Makassar',
    formatTanggalKey: string = 'd F Y',
    locale: string = 'id-ID'
): string {
    if (!dateInput) return '—';

    const tanggalMurni = typeof dateInput === 'string' && POLA_TANGGAL_MURNI.test(dateInput)
        ? tanggalMurniKeUtc(dateInput)
        : undefined;

    if (tanggalMurni === null) return '—';

    const date = tanggalMurni
        ?? (typeof dateInput === 'string' || typeof dateInput === 'number' ? new Date(dateInput) : dateInput);

    if (isNaN(date.getTime())) return '—';

    const dateLocale = locale || 'id-ID';
    // Tanggal murni diformat pada UTC agar hari kalender dari server tidak bergeser oleh zona tampilan.
    const effectiveTimeZone = tanggalMurni ? 'UTC' : (timeZone || 'Asia/Makassar');

    // Gunakan formatToParts untuk merakit komponen tanggal sesuai zona waktu dan format
    const isFullMonth = formatTanggalKey === 'd F Y';
    const formatter = new Intl.DateTimeFormat(dateLocale, {
        timeZone: effectiveTimeZone,
        weekday: options.withDay ? 'long' : undefined,
        day: '2-digit',
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

    const paddedDay = day.padStart(2, '0');
    const paddedMonth = month.padStart(2, '0');

    let dateStr = '';
    if (formatTanggalKey === 'Y-m-d') {
        dateStr = `${year}-${paddedMonth}-${paddedDay}`;
    } else if (formatTanggalKey === 'd/m/Y') {
        dateStr = `${paddedDay}/${paddedMonth}/${year}`;
    } else {
        // Default: d F Y (misal 01 September 2026)
        dateStr = `${paddedDay} ${month} ${year}`;
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

    const { props } = usePage<SharedPageProps>();
    const pengaturan = props.pengaturan;
    if (pengaturan) {
        if (typeof pengaturan['tampilan.zona_waktu'] === 'string') {
            timeZone = pengaturan['tampilan.zona_waktu'];
        }
        if (typeof pengaturan['tampilan.format_tanggal'] === 'string') {
            formatTanggalKey = pengaturan['tampilan.format_tanggal'];
        }
    }

    return (date, options) => {
        return formatTanggal(date, options, timeZone, formatTanggalKey, 'id-ID');
    };
}
