import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type StatusKinerja = 'draft' | 'diajukan' | 'dikembalikan' | 'diverifikasi' | 'disahkan' | string;

interface BadgeProps {
    status: StatusKinerja;
    className?: string;
    children?: React.ReactNode;
}

export const Badge: React.FC<BadgeProps> = ({ status, className, children }) => {
    const statusMap: Record<string, { label: string; style: string; dotColor: string }> = {
        draft: {
            label: 'Draft',
            style: 'bg-soft text-muted border-border',
            dotColor: 'bg-muted',
        },
        diajukan: {
            label: 'Diajukan',
            style: 'bg-warning/15 text-warning-dark border-warning/30',
            dotColor: 'bg-warning',
        },
        diverifikasi: {
            label: 'Diverifikasi',
            style: 'bg-info/15 text-info-dark border-info/30',
            dotColor: 'bg-info',
        },
        dikembalikan: {
            label: 'Dikembalikan',
            style: 'bg-danger/15 text-danger border-danger/30',
            dotColor: 'bg-danger',
        },
        disahkan: {
            label: 'Disahkan',
            style: 'bg-success/15 text-success border-success/30',
            dotColor: 'bg-success',
        },
    };

    const current = statusMap[status?.toLowerCase?.()] || {
        label: status,
        style: 'bg-soft text-muted border-border',
        dotColor: 'bg-muted',
    };

    return (
        <span
            className={twMerge(
                clsx(
                    'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border tracking-wide transition-colors',
                    current.style,
                    className
                )
            )}
        >
            <span className={clsx('w-1.5 h-1.5 rounded-full shrink-0', current.dotColor)} aria-hidden="true" />
            {children || current.label}
        </span>
    );
};
