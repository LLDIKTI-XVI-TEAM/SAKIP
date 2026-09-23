import React, { HTMLAttributes } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export const Card: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className, children, ...props }) => {
    return (
        <div
            className={twMerge(
                clsx('overflow-hidden rounded-xl border border-border bg-surface shadow-xs transition-shadow', className)
            )}
            {...props}
        >
            {children}
        </div>
    );
};

export const CardHeader: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className, children, ...props }) => {
    return (
        <div className={twMerge(clsx('flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface px-6 py-4.5', className))} {...props}>
            {children}
        </div>
    );
};

export const CardTitle: React.FC<HTMLAttributes<HTMLHeadingElement>> = ({ className, children, ...props }) => {
    return (
        <h3 className={twMerge(clsx('text-base font-semibold text-ink tracking-tight', className))} {...props}>
            {children}
        </h3>
    );
};

export const CardContent: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className, children, ...props }) => {
    return (
        <div className={twMerge(clsx('p-6', className))} {...props}>
            {children}
        </div>
    );
};
