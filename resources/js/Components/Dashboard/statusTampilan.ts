import type { BadgeVariant } from '@/Components/Badge';
import type { StatusTampilan } from '@/types/dashboard';

/**
 * Status tampilan indikator × periode pada dashboard. Varian Q47, "Tidak dapat dihitung", dan status capaian
 * mengikuti design-system.md.
 */
export const STATUS_TAMPILAN: Record<StatusTampilan, { label: string; variant: BadgeVariant }> = {
    belum_mengisi: { label: 'Belum mengisi', variant: 'muted' },
    tidak_mengisi: { label: 'Tidak mengisi', variant: 'danger' },
    dalam_proses: { label: 'Dalam proses', variant: 'info' },
    tercapai: { label: 'Tercapai', variant: 'success' },
    belum_tercapai: { label: 'Belum Tercapai', variant: 'danger' },
    belum_ditetapkan: { label: 'Belum ditetapkan', variant: 'muted' },
    tanpa_target_periode: { label: 'Tanpa target periode', variant: 'info' },
    belum_dapat_diukur: { label: 'Belum dapat diukur', variant: 'warning' },
    tidak_dapat_dihitung: { label: 'Tidak dapat dihitung', variant: 'secondary' },
    tidak_berlaku: { label: 'Tidak berlaku', variant: 'muted' },
    belum_dibuka: { label: 'Belum dibuka', variant: 'muted' },
};
