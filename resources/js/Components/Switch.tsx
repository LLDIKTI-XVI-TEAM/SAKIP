import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export interface SwitchProps {
    checked: boolean;
    onChange?: (checked: boolean) => void;
    disabled?: boolean;
    label?: string;
    id?: string;
    'aria-label'?: string;
    className?: string;
}

export const Switch: React.FC<SwitchProps> = ({
    checked,
    onChange,
    disabled = false,
    label,
    id,
    'aria-label': ariaLabel,
    className,
}) => {
    const handleToggle = () => {
        if (!disabled && onChange) {
            onChange(!checked);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            handleToggle();
        }
    };

    return (
        <button
            type="button"
            role="switch"
            id={id}
            aria-checked={checked}
            aria-label={ariaLabel || label}
            disabled={disabled}
            onClick={handleToggle}
            onKeyDown={handleKeyDown}
            className={twMerge(
                clsx(
                    'relative inline-flex h-7 w-12 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2',
                    disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                    checked ? 'bg-primary' : 'bg-border',
                    className
                )
            )}
        >
            <span
                className={twMerge(
                    clsx(
                        'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out',
                        checked ? 'translate-x-5' : 'translate-x-0'
                    )
                )}
            />
        </button>
    );
};
