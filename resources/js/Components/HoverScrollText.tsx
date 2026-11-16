import type { CSSProperties } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface HoverScrollTextProps {
    children?: string | null;
    className?: string;
    duration?: string;
    style?: CSSProperties;
    title?: string | false;
    showTooltip?: boolean;
}

/**
 * Teks satu baris yang terpotong elipsis. Saat link/tombol induknya di-hover atau difokus keyboard,
 * atau saat teks itu sendiri di-hover, teks bergeser untuk menampilkan sisanya; teks yang muat tidak
 * bergeser. Perilaku sepenuhnya CSS (`.hover-scroll-text` di app.css) dan mengikuti
 * `prefers-reduced-motion`; teks lengkap juga tersedia sebagai tooltip `title` kecuali bila dimatikan
 * melalui `showTooltip={false}` atau `title={false}`.
 * Di dalam baris flex, komponen mengisi sisa ruang; pembungkus flex-nya juga harus dapat melebar.
 * Hanya untuk wadah yang lebarnya ditentukan dari luar (baris menu selebar penuh, sel tabel, kartu).
 * Jangan dipakai di Button atau badge yang lebarnya mengikuti teks: isi dianggap selebar 0 sehingga teks hilang.
 */
export function HoverScrollText({
    children,
    className,
    duration,
    style,
    title,
    showTooltip = true,
}: HoverScrollTextProps) {
    const text = children ?? '';
    const combinedStyle = duration
        ? ({ ...style, '--hover-scroll-duration': duration } as CSSProperties)
        : style;
    const resolvedTitle = showTooltip === false || title === false ? undefined : (title ?? text);

    return (
        <span
            className={twMerge(clsx('hover-scroll-text', className))}
            title={resolvedTitle}
            style={combinedStyle}
        >
            <span>{text}</span>
        </span>
    );
}
