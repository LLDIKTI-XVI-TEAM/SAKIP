import React, { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Check, ChevronDown } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { HoverScrollText } from '@/Components/HoverScrollText';

export interface CustomSelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface CustomSelectProps {
    id?: string;
    name?: string;
    label?: string;
    labelClassName?: string;
    error?: string;
    helperText?: string;
    placeholder?: string;
    options: CustomSelectOption[];
    value?: string | number | null;
    onChange?: (value: string | number) => void;
    disabled?: boolean;
    required?: boolean;
    className?: string;
    useHoverScroll?: boolean;
    emptyOptionLabel?: string;
    showEmptyOption?: boolean;
}

export function CustomSelect({
    id,
    name,
    label,
    labelClassName,
    error,
    helperText,
    placeholder = '-- Pilih --',
    options,
    value,
    onChange,
    disabled = false,
    required = false,
    className,
    useHoverScroll = true,
    emptyOptionLabel,
    showEmptyOption = true,
}: CustomSelectProps) {
    const generatedId = useId();
    const selectId = id || name || generatedId;
    const [isOpen, setIsOpen] = useState(false);
    const [isHovered, setIsHovered] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);

    const selectedOption = useMemo(() => {
        if (value === null || value === undefined || value === '') return null;
        return options.find((opt) => String(opt.value) === String(value)) || null;
    }, [options, value]);

    useEffect(() => {
        if (!isOpen) return;

        const handleClickOutside = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setIsOpen(false);
                triggerRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen]);

    const handleSelect = (val: string | number) => {
        if (disabled) return;
        onChange?.(val);
        setIsOpen(false);
        triggerRef.current?.focus();
    };

    return (
        <div className="w-full min-w-0 max-w-full">
            {label && (
                <label
                    htmlFor={selectId}
                    className={twMerge(clsx('mb-1.5 block text-sm font-medium text-ink', labelClassName))}
                >
                    {label}
                    {required && <span className="ml-1 text-danger font-normal" aria-hidden="true">*</span>}
                </label>
            )}

            <div ref={containerRef} className="relative w-full max-w-full min-w-0">
                {name && (
                    <input
                        type="hidden"
                        name={name}
                        value={value ?? ''}
                        disabled={disabled}
                    />
                )}

                <button
                    ref={triggerRef}
                    type="button"
                    id={selectId}
                    aria-haspopup="listbox"
                    aria-expanded={isOpen}
                    aria-label={label || placeholder}
                    disabled={disabled}
                    onClick={() => {
                        if (!disabled) {
                            setIsOpen((prev) => !prev);
                        }
                    }}
                    onMouseEnter={() => setIsHovered(true)}
                    onMouseLeave={() => setIsHovered(false)}
                    className={twMerge(
                        clsx(
                            'relative w-full max-w-full min-w-0 h-[42px] rounded-lg border bg-surface px-3.5 py-2 text-sm transition-colors flex items-center justify-between text-left focus:outline-none',
                            error
                                ? 'border-danger focus:border-danger focus:ring-2 focus:ring-danger/20'
                                : isOpen
                                    ? 'border-primary ring-2 ring-primary/20'
                                    : 'border-border focus:border-primary focus:ring-2 focus:ring-primary/20',
                            disabled
                                ? 'cursor-not-allowed bg-soft text-muted'
                                : 'cursor-pointer hover:border-border-dark',
                            className
                        )
                    )}
                >
                    <div className="min-w-0 flex-1 pr-6 overflow-hidden">
                        {selectedOption ? (
                            useHoverScroll ? (
                                <HoverScrollText
                                    text={selectedOption.label}
                                    isParentHovered={isHovered}
                                    className="text-sm font-normal text-ink w-full max-w-full"
                                    textClassName="font-normal"
                                />
                            ) : (
                                <span className="text-sm font-normal text-ink truncate block">
                                    {selectedOption.label}
                                </span>
                            )
                        ) : (
                            <span className="text-muted text-sm select-none truncate block">
                                {placeholder}
                            </span>
                        )}
                    </div>

                    <div className="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-muted">
                        <ChevronDown
                            className={clsx(
                                'h-4 w-4 transition-transform duration-200',
                                isOpen ? 'rotate-180 text-primary' : 'text-muted'
                            )}
                            aria-hidden="true"
                        />
                    </div>
                </button>

                {isOpen && !disabled && (
                    <div
                        role="listbox"
                        aria-label={label || 'Daftar pilihan'}
                        className="absolute left-0 right-0 top-full mt-1.5 z-40 w-full max-w-full rounded-xl border border-border bg-surface shadow-lg overflow-hidden py-1 max-h-60 overflow-y-auto"
                    >
                        {showEmptyOption && (
                            <button
                                type="button"
                                role="option"
                                aria-selected={!selectedOption}
                                onClick={() => handleSelect('')}
                                className={clsx(
                                    'w-full text-left px-3.5 py-2.5 text-sm transition-colors flex items-center justify-between',
                                    !selectedOption
                                        ? 'bg-primary/10 text-primary font-semibold'
                                        : 'text-muted hover:bg-soft'
                                )}
                            >
                                <span className="truncate">{emptyOptionLabel || placeholder}</span>
                                {!selectedOption && <Check className="h-4 w-4 text-primary shrink-0" aria-hidden="true" />}
                            </button>
                        )}

                        {options.map((opt) => {
                            const isSelected = selectedOption && String(selectedOption.value) === String(opt.value);
                            return (
                                <button
                                    type="button"
                                    key={opt.value}
                                    role="option"
                                    aria-selected={Boolean(isSelected)}
                                    disabled={opt.disabled}
                                    onClick={() => handleSelect(opt.value)}
                                    className={clsx(
                                        'w-full text-left px-3.5 py-2.5 text-sm transition-colors flex items-center justify-between gap-2',
                                        opt.disabled
                                            ? 'cursor-not-allowed opacity-50 text-muted'
                                            : isSelected
                                                ? 'bg-primary/10 text-primary font-semibold'
                                                : 'text-ink hover:bg-soft'
                                    )}
                                >
                                    <span className="truncate min-w-0 flex-1">{opt.label}</span>
                                    {isSelected && <Check className="h-4 w-4 text-primary shrink-0" aria-hidden="true" />}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            {error && <p className="mt-1.5 text-xs font-medium text-danger">{error}</p>}
            {helperText && !error && <p className="mt-1.5 text-xs text-muted">{helperText}</p>}
        </div>
    );
}

export default CustomSelect;
