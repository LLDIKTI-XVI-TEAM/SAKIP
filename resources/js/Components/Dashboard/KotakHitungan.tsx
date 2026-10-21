/** Kotak jumlah per status pada panel dashboard (Rencana Aksi dan kegiatan). */
export function KotakHitungan({ status, label, nilai }: { status: string; label: string; nilai: number }) {
    return (
        <div data-status={status} className="rounded-lg border border-border bg-soft/40 px-3 py-2.5">
            <p className="text-xs text-muted">{label}</p>
            <p className="mt-1 text-xl font-bold tabular-nums text-ink">{nilai}</p>
        </div>
    );
}
