import React, { HTMLAttributes } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export const Card: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className, children, ...props }) => {
    return (
        <div
            className={twMerge(
                clsx('bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden transition-all', className)
            )}
            {...props}
        >
            {children}
        </div>
    );
};

export const CardHeader: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className, children, ...props }) => {
    return (
        <div className={twMerge(clsx('px-6 py-4 border-b border-slate-100 flex items-center justify-between', className))} {...props}>
            {children}
        </div>
    );
};

export const CardTitle: React.FC<HTMLAttributes<HTMLHeadingElement>> = ({ className, children, ...props }) => {
    return (
        <h3 className={twMerge(clsx('text-base font-semibold text-slate-900', className))} {...props}>
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
