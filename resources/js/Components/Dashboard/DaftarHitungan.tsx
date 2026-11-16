// Kelas ditulis utuh agar terbaca Tailwind; kunci mengikuti token design-system.md.
const WARNA: Record<string, string> = {
    secondary: 'bg-secondary',
    muted: 'bg-muted',
    warning: 'bg-warning',
    info: 'bg-info',
    danger: 'bg-danger',
    success: 'bg-success',
};

export interface ItemHitungan {
    key: string;
    label: string;
    nilai: number;
    token: string;
}

/** Jumlah per status pada panel dashboard (Q66): label dan angka sebagai isi, warna dan bar proporsi hanya penanda tambahan. */
export function DaftarHitungan({ items, satuan }: { items: ItemHitungan[]; satuan: string }) {
    const total = items.reduce((jumlah, item) => jumlah + item.nilai, 0);

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-3">
                <div data-proporsi aria-hidden="true" className="flex h-2 flex-1 overflow-hidden rounded-full bg-soft">
                    {items
                        .filter((item) => item.nilai > 0)
                        .map((item) => (
                            <span key={item.key} className={WARNA[item.token]} style={{ width: `${(item.nilai * 100) / total}%` }} />
                        ))}
                </div>
                <p className="shrink-0 text-xs font-medium tabular-nums text-muted">
                    {total} {satuan}
                </p>
            </div>
            <ul aria-label={`Jumlah ${satuan} per status`} className="divide-y divide-border">
                {items.map(({ key, label, nilai, token }) => (
                    <li key={key} data-status={key} className="flex items-center justify-between gap-3 py-2 text-sm">
                        <span className="flex min-w-0 items-center gap-2 text-ink">
                            <span aria-hidden="true" className={`h-2 w-2 shrink-0 rounded-full ${WARNA[token]}`} />
                            {label}
                        </span>
                        <span className="font-semibold tabular-nums text-ink">{nilai}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
