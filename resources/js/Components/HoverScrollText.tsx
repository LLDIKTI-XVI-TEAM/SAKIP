import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface HoverScrollTextProps {
    children: string;
    className?: string;
}

/**
 * Teks satu baris yang terpotong elipsis. Saat link/tombol induknya di-hover atau difokus keyboard,
 * atau saat teks itu sendiri di-hover, teks bergeser untuk menampilkan sisanya; teks yang muat tidak
 * bergeser. Perilaku sepenuhnya CSS (`.hover-scroll-text` di app.css) dan mengikuti
 * `prefers-reduced-motion`; teks lengkap juga tersedia sebagai tooltip `title`.
 * Di dalam baris flex, komponen mengisi sisa ruang; pembungkus flex-nya juga harus dapat melebar.
 * Hanya untuk wadah yang lebarnya ditentukan dari luar (baris menu selebar penuh, sel tabel, kartu).
 * Jangan dipakai di Button atau badge yang lebarnya mengikuti teks: isi dianggap selebar 0 sehingga teks hilang.
 */
export function HoverScrollText({ children, className }: HoverScrollTextProps) {
    return (
        <span className={twMerge(clsx('hover-scroll-text', className))} title={children}>
            <span>{children}</span>
        </span>
    );
}
