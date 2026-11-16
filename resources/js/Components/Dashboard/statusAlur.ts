import type { StatusAlur } from '@/types/dashboard';

/** Urutan alur pengukuran untuk kartu, legenda, dan grafik dashboard. Label mengikuti Badge; token warna mengikuti design-system.md. */
export const STATUS_ALUR: { key: StatusAlur; label: string; token: string }[] = [
    { key: 'draft', label: 'Draft', token: 'muted' },
    { key: 'diajukan', label: 'Diajukan', token: 'warning' },
    { key: 'diverifikasi', label: 'Diverifikasi', token: 'info' },
    { key: 'dikembalikan', label: 'Dikembalikan', token: 'danger' },
    { key: 'disahkan', label: 'Disahkan', token: 'success' },
];

// ApexCharts butuh warna konkret (diolah lalu ditulis ke atribut SVG), jadi token @theme di app.css dibaca saat render.
export const warnaToken = (token: string) => getComputedStyle(document.documentElement).getPropertyValue(`--color-${token}`).trim();

/** Persentase dibulatkan ke bawah agar 100% hanya tampil bila semua pengukuran benar-benar disahkan. */
export const persenDisahkan = (disahkan: number, total: number) => (total > 0 ? Math.floor((disahkan * 100) / total) : 0);
