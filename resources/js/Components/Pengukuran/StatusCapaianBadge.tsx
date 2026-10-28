import type { ReactNode } from 'react';
import { Badge, type BadgeProps, type BadgeVariant } from '@/Components/Badge';
import type { StatusCapaianKode } from '@/types/statusCapaian';

/**
 * Badge status capaian mengikuti design-system.md §2. `kode` adalah nilai kontrak server (snake_case);
 * `label` adalah teks rekap yang sudah dinarasikan server dan tidak boleh ditebak variannya.
 */
export const statusCapaianBadgeMap: Record<StatusCapaianKode, { variant: BadgeVariant; label: string }> = {
    tercapai: { variant: 'success', label: 'Tercapai' },
    belum_tercapai: { variant: 'danger', label: 'Belum Tercapai' },
    belum_ditetapkan: { variant: 'muted', label: 'Belum Ditetapkan' },
    tanpa_target_periode: { variant: 'info', label: 'Tanpa Target Periode' },
    belum_dapat_diukur: { variant: 'warning', label: 'Belum Dapat Diukur' },
    tidak_dapat_dihitung: { variant: 'secondary', label: 'Tidak Dapat Dihitung' },
};

/** Teks rekap yang sudah dikenal; label lain ditampilkan apa adanya dengan varian muted. */
const labelVariantMap: Record<string, BadgeVariant> = {
    'tercapai': 'success',
    'belum tercapai': 'danger',
    'belum ditetapkan': 'muted',
    'tidak dinilai': 'info',
    'tidak dapat dihitung': 'secondary',
};

interface StatusCapaianBadgeProps extends Pick<BadgeProps, 'size' | 'className'> {
    kode?: string | null;
    label?: string | null;
}

export function StatusCapaianBadge({ kode, label, size, className }: StatusCapaianBadgeProps): ReactNode {
    const entri = kode !== null && kode !== undefined && Object.prototype.hasOwnProperty.call(statusCapaianBadgeMap, kode)
        ? statusCapaianBadgeMap[kode as StatusCapaianKode]
        : undefined;
    if (entri) {
        return <Badge variant={entri.variant} size={size} className={className}>{entri.label}</Badge>;
    }

    const teks = label ?? kode;
    if (!teks) return null;

    return (
        <Badge variant={labelVariantMap[teks.trim().toLowerCase()] ?? 'muted'} size={size} className={className}>
            {teks}
        </Badge>
    );
}
