import React, { TextareaHTMLAttributes } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    error?: string;
    helperText?: string;
}

export const Textarea: React.FC<TextareaProps> = ({ label, error, helperText, className, id, ...props }) => {
    const textareaId = id || props.name;

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={textareaId} className="mb-1.5 block text-sm font-semibold text-ink">
                    {label}
                    {props.required && <span className="ml-1 text-danger" aria-hidden="true">*</span>}
                </label>
            )}
            <textarea
                id={textareaId}
                rows={props.rows || 3}
                className={twMerge(
                    clsx(
                        'w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink transition-colors placeholder:text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-soft disabled:text-muted',
                        error ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-border',
                        className
                    )
                )}
                {...props}
            />
            {error && <p className="mt-1 text-xs font-medium text-danger">{error}</p>}
            {helperText && !error && <p className="mt-1 text-xs text-muted">{helperText}</p>}
        </div>
    );
};
