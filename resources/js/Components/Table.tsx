import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export const Table = React.forwardRef<HTMLTableElement, React.HTMLAttributes<HTMLTableElement>>(
    ({ className, ...props }, ref) => (
        <div className="relative w-full overflow-x-auto">
            <table
                ref={ref}
                className={twMerge(clsx('w-full caption-bottom text-left text-xs text-slate-700', className))}
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
            className={twMerge(clsx('border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-800', className))}
            {...props}
        />
    )
);
TableHeader.displayName = 'TableHeader';

export const TableBody = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
    ({ className, ...props }, ref) => (
        <tbody
            ref={ref}
            className={twMerge(clsx('divide-y divide-slate-100 bg-surface', className))}
            {...props}
        />
    )
);
TableBody.displayName = 'TableBody';

export const TableFooter = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
    ({ className, ...props }, ref) => (
        <tfoot
            ref={ref}
            className={twMerge(clsx('border-t border-slate-200 bg-slate-50/50 font-medium text-slate-800', className))}
            {...props}
        />
    )
);
TableFooter.displayName = 'TableFooter';

export const TableRow = React.forwardRef<HTMLTableRowElement, React.HTMLAttributes<HTMLTableRowElement>>(
    ({ className, ...props }, ref) => (
        <tr
            ref={ref}
            className={twMerge(clsx('transition-colors hover:bg-slate-50/60 data-[state=selected]:bg-slate-50', className))}
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
            className={twMerge(clsx('h-11 px-4 py-3.5 text-left align-middle text-xs font-bold uppercase tracking-wider text-slate-800 [&:has([role=checkbox])]:pr-0', className))}
            {...props}
        />
    )
);
TableHead.displayName = 'TableHead';

export const TableCell = React.forwardRef<HTMLTableCellElement, React.TdHTMLAttributes<HTMLTableCellElement>>(
    ({ className, ...props }, ref) => (
        <td
            ref={ref}
            className={twMerge(clsx('px-4 py-3.5 align-middle text-xs text-slate-700 [&:has([role=checkbox])]:pr-0', className))}
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
