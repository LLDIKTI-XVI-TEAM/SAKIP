import type { ReactNode } from 'react';
import { Badge, type BadgeProps, type BadgeVariant } from '@/Components/Badge';
import { statusPerhitungan, type Pengukuran } from '@/Pages/Pengukuran/types';

type StatusPerhitungan = Pengukuran['status_perhitungan'];

export const statusPerhitunganBadgeMap: Record<Exclude<StatusPerhitungan, 'terhitung'>, { variant: BadgeVariant; label: string }> = {
    belum_diisi: { variant: 'muted', label: 'Belum Diisi' },
    tidak_dapat_dihitung: { variant: 'secondary', label: 'Tidak Dapat Dihitung' },
    belum_dapat_diukur: { variant: 'warning', label: 'Belum Dapat Diukur' },
};

/**
 * Badge hanya menandai status tanpa nilai. `terhitung` selalu memiliki nilai, sehingga tampil
 * sebagai teks polos dan sel status cukup satu pemanggilan untuk semua status.
 */
export function StatusPerhitunganBadge({ status, size }: { status: StatusPerhitungan } & Pick<BadgeProps, 'size'>): ReactNode {
    if (status === 'terhitung') return statusPerhitungan.terhitung;
    const { variant, label } = statusPerhitunganBadgeMap[status];

    return <Badge variant={variant} size={size}>{label}</Badge>;
}
