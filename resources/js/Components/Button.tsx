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
    const baseStyles = 'inline-flex items-center justify-center font-medium rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-xs cursor-pointer';

    const variants = {
        primary: 'bg-[#122E92] hover:bg-[#0d226b] text-white focus:ring-[#122E92]',
        secondary: 'bg-[#D6AC48] hover:bg-[#be9031] text-slate-900 font-semibold focus:ring-[#D6AC48]',
        danger: 'bg-rose-600 hover:bg-rose-700 text-white focus:ring-rose-500',
        outline: 'border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 focus:ring-[#122E92]',
        ghost: 'bg-transparent hover:bg-slate-100 text-slate-600 focus:ring-slate-300 shadow-none',
    };

    const sizes = {
        sm: 'px-3 py-1.5 text-xs gap-1.5',
        md: 'px-4 py-2 text-sm gap-2',
        lg: 'px-5 py-2.5 text-base gap-2.5',
    };

    return (
        <button
            disabled={disabled || isLoading}
            className={twMerge(clsx(baseStyles, variants[variant], sizes[size], className))}
            {...props}
        >
            {isLoading && <Loader2 className="w-4 h-4 animate-spin" />}
            {children}
        </button>
    );
};
