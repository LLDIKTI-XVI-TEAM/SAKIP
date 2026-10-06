import React from 'react';
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationData {
    links?: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
    current_page?: number;
    last_page?: number;
    per_page?: number;
}

export interface PaginationProps {
    pagination?: PaginationData;
    links?: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
    currentPage?: number;
    lastPage?: number;
    perPage?: number;
    perPageOptions?: number[];
    onPerPageChange?: (perPage: number) => void;
    onPageChange?: (page: number, url?: string | null) => void;
    resourceName?: string;
    showPerPage?: boolean;
    preserveScroll?: boolean;
    preserveState?: boolean;
    className?: string;
    prevUrl?: string | null;
    nextUrl?: string | null;
    prevLabel?: string;
    nextLabel?: string;
}

export function Pagination({
    pagination,
    links: propLinks,
    from: propFrom,
    to: propTo,
    total: propTotal,
    currentPage: propCurrentPage,
    lastPage: propLastPage,
    perPage: propPerPage,
    perPageOptions = [10, 25, 50, 100],
    onPerPageChange,
    onPageChange,
    resourceName = 'data',
    showPerPage = true,
    preserveScroll = true,
    preserveState = true,
    className,
    prevUrl: propPrevUrl,
    nextUrl: propNextUrl,
    prevLabel = 'Halaman sebelumnya',
    nextLabel = 'Halaman berikutnya',
}: PaginationProps) {
    const links = pagination?.links ?? propLinks ?? [];
    const total = pagination?.total ?? propTotal ?? 0;

    // Resolve current page
    let activePage = pagination?.current_page ?? propCurrentPage;
    if (!activePage) {
        const found = links.find((l) => l.active);
        if (found) {
            const parsed = parseInt(found.label, 10);
            if (!isNaN(parsed)) activePage = parsed;
        }
    }
    const current = activePage ?? 1;

    // Resolve perPage
    const currentPerPage = pagination?.per_page ?? propPerPage ?? 10;

    // Fallback calculation for from and to when not explicitly provided
    const calculatedFrom = total > 0 ? Math.min((current - 1) * currentPerPage + 1, total) : 0;
    const calculatedTo = total > 0 ? Math.min(current * currentPerPage, total) : 0;

    const rawFrom = pagination?.from ?? propFrom ?? calculatedFrom;
    const rawTo = pagination?.to ?? propTo ?? calculatedTo;
    const from = total > 0 ? Math.min(rawFrom, total) : 0;
    const to = total > 0 ? Math.min(Math.max(rawTo, from), total) : 0;

    // Resolve last page
    let resolvedLastPage = pagination?.last_page ?? propLastPage;
    if (!resolvedLastPage) {
        // Fallback from total and perPage if available
        if (total > 0 && currentPerPage > 0) {
            resolvedLastPage = Math.max(1, Math.ceil(total / currentPerPage));
        } else {
            // Find highest numeric label in links
            const numericLabels = links
                .map((l) => parseInt(l.label, 10))
                .filter((n) => !isNaN(n));
            resolvedLastPage = numericLabels.length > 0 ? Math.max(...numericLabels) : 1;
        }
    }
    const totalPages = Math.max(1, resolvedLastPage);

    const handlePerPageSelect = (newPerPage: number) => {
        if (onPerPageChange) {
            onPerPageChange(newPerPage);
            return;
        }

        // Automatic Inertia navigation if no handler provided
        if (typeof window !== 'undefined') {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('per_page', String(newPerPage));
            searchParams.set('page', '1');
            router.get(
                `${window.location.pathname}?${searchParams.toString()}`,
                {},
                {
                    preserveState,
                    preserveScroll,
                }
            );
        }
    };

    // Helper to find URL for a specific page number
    const getPageUrl = (pageNumber: number): string | null => {
        const link = links.find((l) => l.label === String(pageNumber));
        if (link?.url) {
            return normalizeUrl(link.url);
        }
        if (typeof window !== 'undefined') {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('page', String(pageNumber));
            if (currentPerPage) {
                searchParams.set('per_page', String(currentPerPage));
            }
            return `${window.location.pathname}?${searchParams.toString()}`;
        }
        return null;
    };

    const normalizeUrl = (url: string | null | undefined): string | null => {
        if (!url) return null;
        if (typeof window !== 'undefined') {
            try {
                const parsed = new URL(url, window.location.origin);
                return `${parsed.pathname}${parsed.search}`;
            } catch {
                return url;
            }
        }
        return url;
    };

    const rawPrevUrl = propPrevUrl !== undefined ? propPrevUrl : (current > 1 ? getPageUrl(current - 1) : null);
    const rawNextUrl = propNextUrl !== undefined ? propNextUrl : (current < totalPages ? getPageUrl(current + 1) : null);
    const prevUrl = normalizeUrl(rawPrevUrl);
    const nextUrl = normalizeUrl(rawNextUrl);

    // Build visible page numbers list (compact window: max 3 numbers + ellipsis)
    const pagesToRender: (number | 'ellipsis')[] = [];
    if (totalPages <= 3) {
        for (let i = 1; i <= totalPages; i++) {
            pagesToRender.push(i);
        }
    } else {
        if (current <= 2) {
            pagesToRender.push(1, 2, 'ellipsis', totalPages);
        } else if (current >= totalPages - 1) {
            pagesToRender.push(1, 'ellipsis', totalPages - 1, totalPages);
        } else {
            pagesToRender.push(1, 'ellipsis', current, 'ellipsis', totalPages);
        }
    }

    // If total items is 0 and no manual navigation URLs provided, don't show footer at all
    if (total === 0 && !propPrevUrl && !propNextUrl) {
        return null;
    }

    return (
        <div
            className={twMerge(
                clsx(
                    'flex flex-col sm:flex-row items-center justify-between gap-2.5 sm:gap-4 border-t border-border px-4 sm:px-6 py-2 sm:py-2.5 bg-soft/20 text-xs text-muted',
                    className
                )
            )}
        >
            {/* Left side: Tampilkan per page + Meta info (ringkas & jelas) */}
            <div className="flex items-center gap-2.5 text-xs text-muted">
                {showPerPage && (
                    <div className="flex items-center gap-1.5">
                        <span className="whitespace-nowrap">Tampilkan</span>
                        <select
                            aria-label="Pilih jumlah data per halaman"
                            value={currentPerPage}
                            onChange={(e) => handlePerPageSelect(Number(e.target.value))}
                            className="h-7 rounded-md border border-border bg-surface px-2 text-xs font-medium text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary font-sans cursor-pointer text-center shadow-2xs"
                        >
                            {perPageOptions.map((opt) => (
                                <option key={opt} value={opt}>
                                    {opt}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {/* Divider */}
                {showPerPage && <span className="h-3.5 w-px bg-border/80" aria-hidden="true" />}

                {/* Meta Info */}
                <div data-testid="pagination-meta" className="whitespace-nowrap text-xs text-muted">
                    <span className="font-semibold text-ink">{from ?? 0}</span>
                    {' - '}
                    <span className="font-semibold text-ink">{to ?? 0}</span>
                    {' dari '}
                    <span className="font-semibold text-ink">{total ?? 0}</span>
                    {' '}{resourceName}
                </div>
            </div>

            {/* Right side: Navigasi Halaman Buttons */}
            {(totalPages > 1 || Boolean(propPrevUrl || propNextUrl)) && (
                <nav aria-label="Navigasi halaman" className="w-full max-w-full overflow-x-auto scrollbar-hide sm:w-auto">
                    <div className="flex w-max min-w-full items-center justify-start gap-1 py-0.5 sm:min-w-0 sm:justify-end">
                        {/* Prev Button */}
                        {prevUrl && (current > 1 || Boolean(propPrevUrl)) ? (
                            <Link
                                href={prevUrl}
                                preserveScroll={preserveScroll}
                                preserveState={preserveState}
                                onClick={(e) => {
                                    if (onPageChange) {
                                        e.preventDefault();
                                        onPageChange(current - 1, prevUrl);
                                    }
                                }}
                                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-ink hover:bg-soft hover:text-ink shadow-2xs transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                                aria-label={prevLabel}
                            >
                                <ChevronLeft className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            </Link>
                        ) : (
                            <button
                                type="button"
                                disabled
                                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-muted opacity-40 shadow-2xs cursor-not-allowed"
                                aria-label={prevLabel}
                            >
                                <ChevronLeft className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            </button>
                        )}

                        {/* Page Numbers & Ellipsis */}
                        {pagesToRender.map((item, index) => {
                            if (item === 'ellipsis') {
                                return (
                                    <span
                                        key={`ellipsis-${index}`}
                                        className="flex items-end justify-center px-1 text-muted text-xs font-medium select-none"
                                        aria-hidden="true"
                                    >
                                        ...
                                    </span>
                                );
                            }

                            const pageNumber = item;
                            const isActive = pageNumber === current;
                            const pageUrl = getPageUrl(pageNumber);

                            if (isActive) {
                                return (
                                    <span
                                        key={pageNumber}
                                        aria-current="page"
                                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-primary bg-primary text-white text-xs font-semibold shadow-xs select-none"
                                    >
                                        {pageNumber}
                                    </span>
                                );
                            }

                            if (pageUrl) {
                                return (
                                    <Link
                                        key={pageNumber}
                                        href={pageUrl}
                                        preserveScroll={preserveScroll}
                                        preserveState={preserveState}
                                        onClick={(e) => {
                                            if (onPageChange) {
                                                e.preventDefault();
                                                onPageChange(pageNumber, pageUrl);
                                            }
                                        }}
                                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-muted hover:bg-soft hover:text-ink text-xs font-medium shadow-2xs transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                                    >
                                        {pageNumber}
                                    </Link>
                                );
                            }

                            return (
                                <button
                                    key={pageNumber}
                                    type="button"
                                    onClick={() => onPageChange?.(pageNumber)}
                                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-muted hover:bg-soft hover:text-ink text-xs font-medium shadow-2xs transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                                >
                                    {pageNumber}
                                </button>
                            );
                        })}

                        {/* Next Button */}
                        {nextUrl && (current < totalPages || Boolean(propNextUrl)) ? (
                            <Link
                                href={nextUrl}
                                preserveScroll={preserveScroll}
                                preserveState={preserveState}
                                onClick={(e) => {
                                    if (onPageChange) {
                                        e.preventDefault();
                                        onPageChange(current + 1, nextUrl);
                                    }
                                }}
                                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-ink hover:bg-soft hover:text-ink shadow-2xs transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                                aria-label={nextLabel}
                            >
                                <ChevronRight className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            </Link>
                        ) : (
                            <button
                                type="button"
                                disabled
                                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-muted opacity-40 shadow-2xs cursor-not-allowed"
                                aria-label={nextLabel}
                            >
                                <ChevronRight className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            </button>
                        )}
                    </div>
                </nav>
            )}
        </div>
    );
}

export default Pagination;
