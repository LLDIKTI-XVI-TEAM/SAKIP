import React from 'react';
import { Link, type InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { LucideIcon } from 'lucide-react';
import { twMerge } from 'tailwind-merge';
import { Tooltip } from '@/Components/Tooltip';

export interface TableProps extends React.HTMLAttributes<HTMLTableElement> {
    wrapperClassName?: string;
}

export const Table = React.forwardRef<HTMLTableElement, TableProps>(
    ({ className, wrapperClassName, ...props }, ref) => (
        <div className={twMerge(clsx('relative w-full overflow-x-auto', wrapperClassName))}>
            <table
                ref={ref}
                className={twMerge(clsx('w-full caption-bottom text-left text-xs text-ink', className))}
                {...props}
            />
        </div>
    )
);
Table.displayName = 'Table';

export const TableHeader = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
    ({ className, ...props }, ref) => (
        <thead
            ref={ref}
            className={twMerge(clsx('border-b border-border bg-soft text-xs font-semibold uppercase tracking-wider text-ink', className))}
            {...props}
        />
    )
);
TableHeader.displayName = 'TableHeader';

export const TableBody = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
    ({ className, ...props }, ref) => (
        <tbody
            ref={ref}
            className={twMerge(clsx('divide-y divide-border bg-surface', className))}
            {...props}
        />
    )
);
TableBody.displayName = 'TableBody';

export const TableFooter = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
    ({ className, ...props }, ref) => (
        <tfoot
            ref={ref}
            className={twMerge(clsx('border-t border-border bg-soft/60 font-medium text-ink', className))}
            {...props}
        />
    )
);
TableFooter.displayName = 'TableFooter';

export const TableRow = React.forwardRef<HTMLTableRowElement, React.HTMLAttributes<HTMLTableRowElement>>(
    ({ className, ...props }, ref) => (
        <tr
            ref={ref}
            className={twMerge(clsx('transition-colors hover:bg-soft/70 data-[state=selected]:bg-soft', className))}
            {...props}
        />
    )
);
TableRow.displayName = 'TableRow';

export const TableHead = React.forwardRef<HTMLTableCellElement, React.ThHTMLAttributes<HTMLTableCellElement>>(
    ({ className, ...props }, ref) => (
        <th
            ref={ref}
            scope="col"
            className={twMerge(clsx('h-11 px-4 py-3.5 text-left align-middle text-xs font-semibold uppercase tracking-wider text-ink whitespace-nowrap [&:has([role=checkbox])]:pr-0', className))}
            {...props}
        />
    )
);
TableHead.displayName = 'TableHead';

export const TableCell = React.forwardRef<HTMLTableCellElement, React.TdHTMLAttributes<HTMLTableCellElement>>(
    ({ className, ...props }, ref) => (
        <td
            ref={ref}
            className={twMerge(clsx('px-4 py-3.5 align-middle text-xs text-ink [&:has([role=checkbox])]:pr-0', className))}
            {...props}
        />
    )
);
TableCell.displayName = 'TableCell';

export const TableCaption = React.forwardRef<HTMLTableCaptionElement, React.HTMLAttributes<HTMLTableCaptionElement>>(
    ({ className, ...props }, ref) => (
        <caption
            ref={ref}
            className={twMerge(clsx('mt-4 text-xs text-muted', className))}
            {...props}
        />
    )
);
TableCaption.displayName = 'TableCaption';

/** Sel tabel yang menjadi kartu di mobile (`block md:table`): padding hanya di desktop. */
export const tableCellResponsive = 'min-w-0 p-0 md:px-4 md:py-3.5';

export function TableCellLabel({ className, ...props }: React.HTMLAttributes<HTMLSpanElement>) {
    return <span className={twMerge(clsx('mr-2 text-muted md:hidden', className))} {...props} />;
}

export interface TableEmptyRowProps {
    colSpan: number;
    icon: LucideIcon;
    title: React.ReactNode;
    description?: React.ReactNode;
    action?: React.ReactNode;
}

export function TableEmptyRow({ colSpan, icon: Icon, title, description, action }: TableEmptyRowProps) {
    return (
        <TableRow className="block md:table-row">
            <TableCell colSpan={colSpan} className="block py-12 text-center md:table-cell">
                <Icon className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                <p className="mt-2 text-sm font-semibold text-ink">{title}</p>
                {description && <p className="mt-1 text-xs text-muted">{description}</p>}
                {action && <div className="mt-4">{action}</div>}
            </TableCell>
        </TableRow>
    );
}

export type TableActionLinkProps = Omit<InertiaLinkProps, 'children' | 'className'> & {
    label: string;
    tooltip: string;
    icon: LucideIcon;
};

export function TableActionLink({ label, tooltip, icon: Icon, ...props }: TableActionLinkProps) {
    return (
        <Tooltip content={tooltip} align="right">
            <Link
                {...props}
                aria-label={label}
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
            >
                <Icon className="h-4 w-4" aria-hidden="true" />
            </Link>
        </Tooltip>
    );
}

