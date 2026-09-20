import { Link } from '@inertiajs/react';
import type { PengukuranPagination } from './types';

export default function Pagination({ pagination }: { pagination: PengukuranPagination }) {
    if (pagination.last_page <= 1) return null;
    return <nav aria-label="Halaman pengukuran" className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 text-sm text-ink">
        {pagination.prev_page_url ? <Link href={pagination.prev_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Sebelumnya</Link> : <span className="text-muted">Sebelumnya</span>}
        <span>Halaman {pagination.current_page} dari {pagination.last_page} · {pagination.total} pengukuran</span>
        {pagination.next_page_url ? <Link href={pagination.next_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Berikutnya</Link> : <span className="text-muted">Berikutnya</span>}
    </nav>;
}
