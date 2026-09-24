import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type BadgeVariant = 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info' | 'muted';
export type StatusKinerja = 'draft' | 'diajukan' | 'dikembalikan' | 'diverifikasi' | 'disahkan' | string;

export interface BadgeProps {
    variant?: BadgeVariant;
    status?: StatusKinerja;
    size?: 'sm' | 'md';
    dot?: boolean;
    className?: string;
    children?: React.ReactNode;
}

export const Badge: React.FC<BadgeProps> = ({
    variant,
    status,
    size = 'md',
    dot,
    className,
    children,
}) => {
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
            style: 'bg-danger/15 text-danger-dark border-danger/30',
            dotColor: 'bg-danger',
        },
        disahkan: {
            label: 'Disahkan',
            style: 'bg-success/15 text-success-dark border-success/30',
            dotColor: 'bg-success',
        },
    };

    const variantMap: Record<BadgeVariant, { style: string; dotColor: string }> = {
        primary: {
            style: 'bg-primary/10 text-primary border-primary/20',
            dotColor: 'bg-primary',
        },
        secondary: {
            style: 'bg-secondary/15 text-ink border-secondary/30',
            dotColor: 'bg-secondary',
        },
        success: {
            style: 'bg-success/10 text-success border-success/20',
            dotColor: 'bg-success',
        },
        warning: {
            style: 'bg-warning/10 text-warning-dark border-warning/20',
            dotColor: 'bg-warning',
        },
        danger: {
            style: 'bg-danger/10 text-danger border-danger/20',
            dotColor: 'bg-danger',
        },
        info: {
            style: 'bg-info/10 text-info-dark border-info/20',
            dotColor: 'bg-info',
        },
        muted: {
            style: 'bg-soft text-muted border-border',
            dotColor: 'bg-muted',
        },
    };

    let style = 'bg-soft text-muted border-border';
    let dotColor = 'bg-muted';
    let label = '';
    const showDot = dot !== undefined ? dot : Boolean(status);

    if (variant && variantMap[variant]) {
        style = variantMap[variant].style;
        dotColor = variantMap[variant].dotColor;
    } else if (status) {
        const current = statusMap[status.toLowerCase?.()] || {
            label: status,
            style: 'bg-soft text-muted border-border',
            dotColor: 'bg-muted',
        };
        style = current.style;
        dotColor = current.dotColor;
        label = current.label;
    }

    const sizeClasses = {
        sm: 'px-2 py-0.5 text-[11px]',
        md: 'px-2.5 py-0.5 text-xs',
    };

    return (
        <span
            className={twMerge(
                clsx(
                    'inline-flex items-center gap-1.5 rounded-md border font-semibold tracking-wide transition-colors leading-none',
                    sizeClasses[size],
                    style,
                    className
                )
            )}
        >
            {showDot && (
                <span className={clsx('w-1.5 h-1.5 rounded-full shrink-0', dotColor)} aria-hidden="true" />
            )}
            {children || label}
        </span>
    );
};

