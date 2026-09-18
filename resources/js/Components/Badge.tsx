import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type StatusKinerja = 'draft' | 'diajukan' | 'dikembalikan' | 'diverifikasi' | 'disahkan' | string;

interface BadgeProps {
    status: StatusKinerja;
    className?: string;
}

export const Badge: React.FC<BadgeProps> = ({ status, className }) => {
    const statusMap: Record<string, { label: string; style: string }> = {
        draft: {
            label: 'Draft',
            style: 'bg-slate-100 text-slate-700 border-slate-200',
        },
        diajukan: {
            label: 'Diajukan',
            style: 'bg-blue-50 text-blue-700 border-blue-200',
        },
        dikembalikan: {
            label: 'Dikembalikan',
            style: 'bg-rose-50 text-rose-700 border-rose-200',
        },
        diverifikasi: {
            label: 'Diverifikasi',
            style: 'bg-amber-50 text-amber-700 border-amber-200',
        },
        disahkan: {
            label: 'Disahkan',
            style: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        },
    };

    const current = statusMap[status.toLowerCase()] || {
        label: status,
        style: 'bg-slate-100 text-slate-700 border-slate-200',
    };

    return (
        <span
            className={twMerge(
                clsx(
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border tracking-wide uppercase',
                    current.style,
                    className
                )
            )}
        >
            <span className="w-1.5 h-1.5 mr-1.5 rounded-full bg-current opacity-75" />
            {current.label}
        </span>
    );
};
