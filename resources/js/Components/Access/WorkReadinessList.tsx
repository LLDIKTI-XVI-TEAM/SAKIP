import { Badge } from '@/Components/Badge';
import type { WorkReadiness } from '@/types/penanggung-jawab';

const reasons: Record<string, string> = {
    explicit_deny: 'Ditolak secara eksplisit', inactive_user: 'Pengguna nonaktif', no_role: 'Belum memiliki role',
    inactive_unit: 'Unit nonaktif', invalid_scope: 'Scope unit tidak berlaku', no_allow: 'Izin belum diberikan',
    unknown_permission: 'Izin tidak dikenali',
};
export function WorkReadinessList({ readiness }: { readiness: WorkReadiness }) {
    return <div className="space-y-3">
        <p className="text-sm text-muted">{readiness.available.length} dari 7 izin kerja tersedia untuk unit ini.</p>
        <ul className="divide-y divide-border" aria-label="Kelengkapan izin kerja">
            {readiness.permissions.map((item) => <li key={item.permission} className="flex flex-wrap items-start justify-between gap-2 py-3">
                <div className="min-w-0"><p className="text-sm font-medium text-ink">{item.label}</p>
                    <p className="break-all text-xs text-muted">{item.permission}</p>
                    {!item.allowed && <p className="mt-1 text-xs text-danger">{reasons[item.reason] ?? 'Izin belum tersedia'}</p>}
                </div>
                <Badge variant={item.allowed ? 'success' : 'warning'} size="sm">{item.allowed ? 'Tersedia' : 'Belum tersedia'}</Badge>
            </li>)}
        </ul>
    </div>;
}
