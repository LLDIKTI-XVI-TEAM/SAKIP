import { Link } from '@inertiajs/react';
import { clsx } from 'clsx';
import { Badge } from '@/Components/Badge';

export interface TabTautanItem {
    href: string;
    label: string;
    jumlah: number;
    aktif: boolean;
}

/** Tab berupa tautan untuk daftar yang dipilah lewat URL; jumlah tampil sebagai lencana dan ikut dibacakan. */
export function TabTautan({ label, satuan, items }: { label: string; satuan: string; items: TabTautanItem[] }) {
    return (
        <nav aria-label={label} className="flex flex-wrap gap-x-4 border-b border-border px-4 sm:gap-x-6 sm:px-6">
            {items.map((item) => (
                <Link
                    key={item.href}
                    href={item.href}
                    aria-current={item.aktif ? 'page' : undefined}
                    aria-label={`${item.label}, ${item.jumlah} ${satuan}`}
                    className={clsx(
                        '-mb-px inline-flex min-h-11 items-center gap-2 border-b-2 py-3 text-xs font-semibold transition-colors sm:text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/25',
                        item.aktif ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink',
                    )}
                >
                    {item.label}
                    {/* Angka dikirim sebagai teks agar nol tetap tampil. */}
                    <Badge variant={item.aktif ? 'primary' : 'muted'} size="sm">{String(item.jumlah)}</Badge>
                </Link>
            ))}
        </nav>
    );
}
