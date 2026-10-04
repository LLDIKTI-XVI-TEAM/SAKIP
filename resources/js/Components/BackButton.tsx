import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type BackButtonSize = 'sm' | 'md';
export type BackButtonVariant = 'primary' | 'subtle';

export interface BackButtonProps {
    href?: string;
    onClick?: (e: React.MouseEvent<any>) => void;
    children?: React.ReactNode;
    label?: string;
    size?: BackButtonSize;
    variant?: BackButtonVariant;
    className?: string;
}

const variantStyles: Record<BackButtonVariant, string> = {
    primary: 'text-primary hover:text-primary/80 font-medium',
    subtle: 'text-muted hover:text-primary font-medium',
};

const sizeStyles: Record<BackButtonSize, { text: string; icon: string }> = {
    sm: { text: 'text-xs gap-1.5', icon: 'h-3.5 w-3.5' },
    md: { text: 'text-sm gap-2', icon: 'h-4 w-4' },
};

export const BackButton: React.FC<BackButtonProps> = ({
    href,
    onClick,
    children,
    label,
    size = 'md',
    variant = 'primary',
    className,
}) => {
    const displayText = children ?? label ?? 'Kembali';
    const sizeConfig = sizeStyles[size];
    const baseClasses =
        'inline-flex items-center rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20 select-none cursor-pointer';

    const classes = twMerge(
        clsx(baseClasses, variantStyles[variant], sizeConfig.text, className)
    );

    const content = (
        <>
            <ArrowLeft className={clsx(sizeConfig.icon, 'shrink-0')} aria-hidden="true" />
            <span>{displayText}</span>
        </>
    );

    if (href) {
        return (
            <Link href={href} className={classes} onClick={onClick}>
                {content}
            </Link>
        );
    }

    return (
        <button
            type="button"
            className={classes}
            onClick={onClick || (() => window.history.back())}
        >
            {content}
        </button>
    );
};

export default BackButton;
