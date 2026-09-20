import React, { SelectHTMLAttributes } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    error?: string;
    helperText?: string;
    options?: SelectOption[];
}

export const Select: React.FC<SelectProps> = ({
    label,
    error,
    helperText,
    options,
    className,
    id,
    children,
    ...props
}) => {
    const selectId = id || props.name;

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={selectId} className="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                    {label}
                    {props.required && <span className="text-rose-500 ml-1">*</span>}
                </label>
            )}
            <select
                id={selectId}
                className={twMerge(
                    clsx(
                        'w-full px-3.5 py-2 text-sm bg-white border rounded-lg transition-colors text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#122E92]/20 focus:border-[#122E92] cursor-pointer',
                        error ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-300',
                        className
                    )
                )}
                {...props}
            >
                {options
                    ? options.map((opt) => (
                          <option key={opt.value} value={opt.value} disabled={opt.disabled}>
                              {opt.label}
                          </option>
                      ))
                    : children}
            </select>
            {error && <p className="mt-1 text-xs text-rose-600 font-medium">{error}</p>}
            {helperText && !error && <p className="mt-1 text-xs text-slate-500">{helperText}</p>}
        </div>
    );
};
