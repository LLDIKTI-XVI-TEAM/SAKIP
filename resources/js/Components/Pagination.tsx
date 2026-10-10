import React from 'react';
import { Link } from '@inertiajs/react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export interface PaginationLinkItem {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationProps {
    links: PaginationLinkItem[];
    from: number | null;
    to: number | null;
    total: number;
    ariaLabel: string;
    className?: string;
}

/** Label paginator Laravel memuat entitas HTML; diubah menjadi teks agar tidak perlu dangerouslySetInnerHTML. */
function cleanLabel(label: string): string {
    return label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Sebelumnya')
        .replace('Next', 'Berikutnya')
        .trim();
}

/** Navigasi halaman untuk paginator Laravel (`links`, `from`, `to`, `total`) yang dikirim sebagai props Inertia. */
export function Pagination({ links, from, to, total, ariaLabel, className }: PaginationProps) {
    // Satu halaman hanya berisi tautan sebelumnya, halaman 1, dan berikutnya.
    if (links.length <= 3) return null;

    return (
        <nav
            aria-label={ariaLabel}
            className={twMerge(clsx('flex flex-col gap-3 text-xs text-muted sm:flex-row sm:items-center sm:justify-between', className))}
        >
            <p>
                Menampilkan <span className="font-semibold text-ink">{from ?? 0}</span>–<span className="font-semibold text-ink">{to ?? 0}</span> dari{' '}
                <span className="font-semibold text-ink">{total}</span> data
            </p>
            <div className="flex flex-wrap gap-1">
                {links.map((link, index) => (
                    link.url ? (
                        <Link
                            key={`${index}-${link.label}`}
                            href={link.url}
                            preserveScroll
                            aria-current={link.active ? 'page' : undefined}
                            className={clsx(
                                'rounded-lg px-3 py-1.5 font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20',
                                link.active ? 'bg-primary text-white' : 'border border-border bg-surface text-ink hover:bg-soft',
                            )}
                        >
                            {cleanLabel(link.label)}
                        </Link>
                    ) : (
                        <span key={`${index}-${link.label}`} aria-disabled="true" className="rounded-lg border border-border px-3 py-1.5 text-muted opacity-50">
                            {cleanLabel(link.label)}
                        </span>
                    )
                ))}
            </div>
        </nav>
    );
}
