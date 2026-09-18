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
                <label htmlFor={textareaId} className="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    {label}
                    {props.required && <span className="text-rose-500 ml-1">*</span>}
                </label>
            )}
            <textarea
                id={textareaId}
                rows={props.rows || 3}
                className={twMerge(
                    clsx(
                        'w-full px-3.5 py-2 text-sm bg-white border rounded-lg transition-colors placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#122E92]/20 focus:border-[#122E92]',
                        error ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-300',
                        className
                    )
                )}
                {...props}
            />
            {error && <p className="mt-1 text-xs text-rose-600 font-medium">{error}</p>}
            {helperText && !error && <p className="mt-1 text-xs text-slate-500">{helperText}</p>}
        </div>
    );
};
