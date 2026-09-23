import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export const Table = React.forwardRef<HTMLTableElement, React.HTMLAttributes<HTMLTableElement>>(
    ({ className, ...props }, ref) => (
        <div className="relative w-full overflow-x-auto">
            <table
                ref={ref}
                className={twMerge(clsx('w-full caption-bottom text-left text-sm text-ink', className))}
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
            className={twMerge(clsx('border-b border-border bg-soft text-xs font-semibold uppercase tracking-wider text-muted', className))}
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
            className={twMerge(clsx('border-t border-border bg-soft/50 font-medium text-ink', className))}
            {...props}
        />
    )
);
TableFooter.displayName = 'TableFooter';

export const TableRow = React.forwardRef<HTMLTableRowElement, React.HTMLAttributes<HTMLTableRowElement>>(
    ({ className, ...props }, ref) => (
        <tr
            ref={ref}
            className={twMerge(clsx('transition-colors hover:bg-soft/50 data-[state=selected]:bg-soft', className))}
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
            className={twMerge(clsx('h-11 px-6 py-3 text-left align-middle font-semibold text-muted [&:has([role=checkbox])]:pr-0', className))}
            {...props}
        />
    )
);
TableHead.displayName = 'TableHead';

export const TableCell = React.forwardRef<HTMLTableCellElement, React.TdHTMLAttributes<HTMLTableCellElement>>(
    ({ className, ...props }, ref) => (
        <td
            ref={ref}
            className={twMerge(clsx('px-6 py-4 align-middle text-ink [&:has([role=checkbox])]:pr-0', className))}
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
