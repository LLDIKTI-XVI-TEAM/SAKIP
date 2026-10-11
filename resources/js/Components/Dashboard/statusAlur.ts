import type { StatusAlur } from '@/types/dashboard';

/** Urutan alur pengukuran untuk kartu, legenda, dan grafik dashboard. Label mengikuti Badge. */
export const STATUS_ALUR: { key: StatusAlur; label: string; color: string }[] = [
    // Warna = token @theme di app.css (muted, warning, info, danger, success); ApexCharts butuh nilai warna langsung.
    { key: 'draft', label: 'Draft', color: '#64748B' },
    { key: 'diajukan', label: 'Diajukan', color: '#EAB308' },
    { key: 'diverifikasi', label: 'Diverifikasi', color: '#2563EB' },
    { key: 'dikembalikan', label: 'Dikembalikan', color: '#DC2626' },
    { key: 'disahkan', label: 'Disahkan', color: '#16A34A' },
];

/** Persentase dibulatkan ke bawah agar 100% hanya tampil bila semua pengukuran benar-benar disahkan. */
export const persenDisahkan = (disahkan: number, total: number) => (total > 0 ? Math.floor((disahkan * 100) / total) : 0);
