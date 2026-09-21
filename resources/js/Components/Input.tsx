import React, { InputHTMLAttributes } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    helperText?: string;
}

export const Input: React.FC<InputProps> = ({ label, error, helperText, className, id, ...props }) => {
    const inputId = id || props.name;

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-ink">
                    {label}
                    {props.required && <span className="ml-1 text-danger font-normal" aria-hidden="true">*</span>}
                </label>
            )}
            <input
                id={inputId}
                className={twMerge(
                    clsx(
                        'w-full h-[42px] rounded-lg border bg-surface px-3.5 py-2 text-sm text-ink transition-colors placeholder:text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-soft disabled:text-muted',
                        error ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-border',
                        className
                    )
                )}
                {...props}
            />
            {error && <p className="mt-1.5 text-xs font-medium text-danger">{error}</p>}
            {helperText && !error && <p className="mt-1.5 text-xs text-muted">{helperText}</p>}
        </div>
    );
};
