import React, { SelectHTMLAttributes, useId } from 'react';
import { ChevronDown } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export interface SelectOption {
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

export function Select({
    label,
    error,
    helperText,
    options,
    className,
    id,
    children,
    ...props
}: SelectProps) {
    const fallbackId = useId();
    const selectId = id ?? props.name ?? fallbackId;

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={selectId} className="mb-1.5 block text-sm font-medium text-ink">
                    {label}
                    {props.required && <span className="ml-1 text-danger font-normal" aria-hidden="true">*</span>}
                </label>
            )}
            <div className="relative">
                <select
                    id={selectId}
                    className={twMerge(
                        clsx(
                            'w-full h-[42px] appearance-none rounded-lg border bg-surface pl-3.5 pr-10 py-2 text-sm text-ink transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-soft disabled:text-muted cursor-pointer',
                            error ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-border',
                            className,
                        ),
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
                <ChevronDown
                    className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                    aria-hidden="true"
                />
            </div>
            {error && <p className="mt-1.5 text-xs font-medium text-danger">{error}</p>}
            {helperText && !error && <p className="mt-1.5 text-xs text-muted">{helperText}</p>}
        </div>
    );
}
