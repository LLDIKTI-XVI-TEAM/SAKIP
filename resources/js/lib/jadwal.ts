import type { BadgeVariant } from '@/Components/Badge';
import type { JadwalStatus } from '@/types/periode-jadwal';

export const statusJadwal: Record<JadwalStatus, { label: string; variant: BadgeVariant }> = {
    draft: { label: 'Draft', variant: 'muted' },
    aktif: { label: 'Aktif', variant: 'success' },
    ditutup: { label: 'Ditutup', variant: 'secondary' },
};
