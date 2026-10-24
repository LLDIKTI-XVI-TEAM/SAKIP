import React, { SelectHTMLAttributes, useId } from 'react';
import { ChevronDown } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { HoverScrollText } from './HoverScrollText';

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    labelClassName?: string;
    error?: string;
    helperText?: string;
    options?: SelectOption[];
    hoverScroll?: boolean;
    hoverScrollDuration?: string;
}

export function Select({
    label,
    labelClassName,
    error,
    helperText,
    options,
    hoverScroll = false,
    hoverScrollDuration,
    className,
    id,
    children,
    ...props
}: SelectProps) {
    const fallbackId = useId();
    const selectId = id ?? props.name ?? fallbackId;

    const selectedOption = options?.find((opt) => String(opt.value) === String(props.value));
    const showHoverScroll = Boolean(hoverScroll && selectedOption && selectedOption.value !== '');

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={selectId} className={twMerge(clsx('mb-1.5 block text-sm font-medium text-ink', labelClassName))}>
                    {label}
                    {props.required && <span className="ml-1 text-danger font-normal" aria-hidden="true">*</span>}
                </label>
            )}
            <div
                className={clsx('relative', showHoverScroll && 'hover-scroll-select')}
                style={hoverScrollDuration ? ({ '--hover-scroll-duration': hoverScrollDuration } as React.CSSProperties) : undefined}
            >
                <select
                    id={selectId}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${selectId}-error` : helperText ? `${selectId}-help` : undefined}
                    className={twMerge(
                        clsx(
                            'peer w-full h-[42px] appearance-none rounded-lg border bg-surface pl-3.5 pr-10 py-2 text-sm text-ink transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-soft disabled:text-muted cursor-pointer',
                            showHoverScroll && 'text-transparent',
                            error ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-border',
                            className,
                        ),
                    )}
                    {...props}
                >
                    {options
                        ? options.map((opt) => (
                              <option key={opt.value} value={opt.value} disabled={opt.disabled} className="text-ink bg-surface">
                                  {opt.label}
                              </option>
                          ))
                        : children}
                </select>
                {showHoverScroll && selectedOption && (
                    // Warna ikut `:disabled` select, sehingga fieldset nonaktif (form terkunci) ikut meredupkan label overlay.
                    <div className="pointer-events-none absolute inset-y-0 left-0 right-10 flex items-center pl-3.5 overflow-hidden text-ink peer-disabled:text-muted">
                        <span className="flex min-w-0 flex-1 items-center" aria-hidden="true">
                            <HoverScrollText
                                key={String(selectedOption.value)}
                                className="text-sm font-normal"
                            >
                                {selectedOption.label}
                            </HoverScrollText>
                        </span>
                    </div>
                )}
                <ChevronDown
                    className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                    aria-hidden="true"
                />
            </div>
            {error && <p id={`${selectId}-error`} role="alert" className="mt-1.5 text-xs font-medium text-danger">{error}</p>}
            {helperText && !error && <p id={`${selectId}-help`} className="mt-1.5 text-xs text-muted">{helperText}</p>}
        </div>
    );
}
