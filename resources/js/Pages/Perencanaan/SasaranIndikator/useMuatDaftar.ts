import { useEffect, useState } from 'react';
import { classifyRecovery, type RecoveryState } from '@/lib/authRecovery';

/** Memuat daftar baca-saja `{ items, terpotong }` saat modal dibuka; dipakai riwayat per indikator dan histori revisi. */
export function useMuatDaftar<T>(path: string, gagal: string) {
    const [data, setData] = useState<{ items: T[]; terpotong: boolean } | null>(null);
    const [error, setError] = useState('');
    const [recovery, setRecovery] = useState<RecoveryState | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        const muat = async () => {
            try {
                const response = await fetch(path, {
                    credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal,
                });
                if (controller.signal.aborted) return;
                if (!response.ok) {
                    setRecovery(classifyRecovery({ status: response.status, data: {}, headers: {} }, { effectiveMethod: 'get', path, mutation: false }));
                    setError(response.status === 403 ? 'Anda tidak memiliki akses membaca indikator ini.'
                        : response.status === 401 ? 'Sesi berakhir. Masuk ulang untuk melihat data.' : gagal);
                    return;
                }
                const value: unknown = await response.json();
                if (controller.signal.aborted) return;
                // Bentuk entri dijamin Action; yang dijaga hanya wadahnya agar payload rusak tidak memecah render.
                if (typeof value !== 'object' || value === null || !('items' in value) || !Array.isArray(value.items)
                    || !('terpotong' in value) || typeof value.terpotong !== 'boolean') {
                    setError(gagal);
                    return;
                }
                setData({ items: value.items as T[], terpotong: value.terpotong });
            } catch {
                if (!controller.signal.aborted) setError(gagal);
            }
        };
        void muat();
        return () => controller.abort();
    }, [path, gagal]);

    return { data, error, recovery };
}
