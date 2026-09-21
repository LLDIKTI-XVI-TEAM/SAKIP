import React, { ButtonHTMLAttributes } from 'react';
import { Loader2 } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'danger' | 'outline' | 'ghost';
    size?: 'sm' | 'md' | 'lg';
    isLoading?: boolean;
}

export const Button: React.FC<ButtonProps> = ({
    variant = 'primary',
    size = 'md',
    isLoading = false,
    className,
    disabled,
    children,
    ...props
}) => {
    const baseStyles = 'inline-flex items-center justify-center rounded-lg font-semibold transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer select-none';

    const variants = {
        primary: 'bg-primary text-white hover:bg-primary/90 focus:ring-primary/30 shadow-xs',
        secondary: 'bg-secondary text-ink hover:bg-secondary/90 focus:ring-secondary/40 shadow-xs',
        danger: 'bg-danger text-white hover:bg-danger/90 focus:ring-danger/30 shadow-xs',
        outline: 'border border-border bg-surface text-ink hover:bg-soft focus:ring-primary/25',
        ghost: 'bg-transparent text-muted hover:bg-soft hover:text-ink focus:ring-border',
    };

    const sizes = {
        sm: 'h-9 px-3 text-xs gap-1.5',
        md: 'h-10 px-4 py-2 text-sm gap-2',
        lg: 'h-11 px-5 py-2.5 text-base gap-2.5',
    };

    return (
        <button
            disabled={disabled || isLoading}
            className={twMerge(clsx(baseStyles, variants[variant], sizes[size], className))}
            {...props}
        >
            {isLoading && <Loader2 className="w-4 h-4 animate-spin shrink-0" />}
            {children}
        </button>
    );
};
