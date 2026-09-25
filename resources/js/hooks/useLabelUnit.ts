import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@/types/auth';

/** Hook untuk membaca label nomenklatur unit kerja yang aktif. */
export function useLabelUnit(): string {
    try {
        const { props } = usePage<SharedPageProps>();
        return (props?.pengaturan?.['aplikasi.label_unit'] as string) || 'Unit Kerja';
    } catch {
        return 'Unit Kerja';
    }
}
