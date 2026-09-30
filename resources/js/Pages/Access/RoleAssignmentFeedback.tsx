import type { Page } from '@inertiajs/core';
import { X } from 'lucide-react';
import { useState } from 'react';
import type { RoleAssignmentOutcome } from '@/types/auth';

export const roleAssignmentMessages = {
    assigned: 'Peran berhasil ditetapkan.',
    changed: 'Peran berhasil diubah.',
    unchanged: 'Peran tidak berubah.',
};
export const roleAssignmentUnknown = 'Konfirmasi perubahan tidak tersedia';
export const roleAssignmentCheck = 'Periksa peran terbaru pengguna di daftar sebelum mengulangi perubahan.';

export function readRoleAssignmentOutcome(page: Pick<Page, 'component' | 'url' | 'flash'>, component: 'Access/RoleAssignmentIndex' | 'Access/RoleAssignmentResult'): RoleAssignmentOutcome | null {
    if (page.component !== component) return null;
    const outcome: unknown = page.flash?.roleAssignmentOutcome;
    if (typeof outcome !== 'object' || outcome === null || !('receipt_id' in outcome) || !('status' in outcome) || !('has_active_pj' in outcome)) return null;
    if (typeof outcome.receipt_id !== 'string' || typeof outcome.has_active_pj !== 'boolean' || (outcome.status !== 'assigned' && outcome.status !== 'changed' && outcome.status !== 'unchanged')) return null;
    let references: string[];
    try {
        references = new URL(page.url, 'http://localhost').searchParams.getAll('receipt');
    } catch {
        return null;
    }
    if (references.length !== 1 || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(references[0]) || outcome.receipt_id !== references[0]) return null;
    return { receipt_id: outcome.receipt_id, status: outcome.status, has_active_pj: outcome.has_active_pj };
}

export function RoleAssignmentPjNotice({ outcome }: { outcome: RoleAssignmentOutcome | null }) {
    const [dismissedReceipt, setDismissedReceipt] = useState<string | null>(null);
    if (!outcome?.has_active_pj || dismissedReceipt === outcome.receipt_id) return null;
    return <div className="mb-4 flex items-start gap-3 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-ink">
        <p role="status" className="min-w-0 flex-1">Pengguna masih menjadi Penanggung Jawab. Penugasan dan grant izin tidak berubah.</p>
        <button type="button" aria-label="Tutup pemberitahuan Penanggung Jawab" onClick={() => setDismissedReceipt(outcome.receipt_id)} className="-m-2 rounded-lg p-3 text-muted hover:bg-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"><X aria-hidden="true" className="h-4 w-4" /></button>
    </div>;
}
