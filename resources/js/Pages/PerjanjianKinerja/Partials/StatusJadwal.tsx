import React from 'react';
import { Lock, ShieldCheck } from 'lucide-react';
import { Badge } from '@/Components/Badge';

/** Status Jadwal Tahunan untuk tahun PK; selain aktif/ditutup dianggap belum aktif. */
export function StatusJadwalBadge({ status, className }: { status?: string | null; className?: string }) {
    if (status === 'aktif') {
        return <Badge variant="success" className={className}><ShieldCheck className="mr-1 h-3 w-3" aria-hidden="true" />Aktif</Badge>;
    }
    if (status === 'ditutup') {
        return <Badge variant="secondary" className={className}><Lock className="mr-1 h-3 w-3" aria-hidden="true" />Ditutup</Badge>;
    }

    return <Badge variant="muted" className={className}>Belum Aktif</Badge>;
}

/** Penanda singkat bahwa lampiran PK terkunci karena Jadwal Tahunan sudah aktif/ditutup. */
export function PkLockNotice({ status }: { status?: string | null }) {
    return (
        <p role="status" className="flex items-start gap-2 rounded-lg border border-border bg-soft p-3 text-sm text-ink">
            <Lock className="mt-0.5 h-4 w-4 shrink-0 text-muted" aria-hidden="true" />
            <span>Jadwal tahunan {status === 'ditutup' ? 'ditutup' : 'aktif'}: lampiran yang ada tidak dapat dihapus. Perubahan data wajib disertai alasan.</span>
        </p>
    );
}
