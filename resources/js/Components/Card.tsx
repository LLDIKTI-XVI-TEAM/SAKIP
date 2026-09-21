import React, { HTMLAttributes } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export const Card: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className, children, ...props }) => {
    return (
        <div
            className={twMerge(
                clsx('overflow-hidden rounded-xl border border-border bg-surface', className)
            )}
            {...props}
        >
            {children}
        </div>
    );
};

export const CardHeader: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className, children, ...props }) => {
    return (
        <div className={twMerge(clsx('flex items-center justify-between border-b border-border px-6 py-4', className))} {...props}>
            {children}
        </div>
    );
};

export const CardTitle: React.FC<HTMLAttributes<HTMLHeadingElement>> = ({ className, children, ...props }) => {
    return (
        <h3 className={twMerge(clsx('text-base font-semibold text-ink', className))} {...props}>
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
