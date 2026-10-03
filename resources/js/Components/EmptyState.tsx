import React from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export interface EmptyStateProps {
    icon?: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean | 'true' | 'false' }>;
    title: string;
    description?: string;
    action?: React.ReactNode;
    variant?: 'inline' | 'dashed' | 'box';
    className?: string;
    iconClassName?: string;
    titleClassName?: string;
    descriptionClassName?: string;
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    variant = 'inline',
    className,
    iconClassName,
    titleClassName,
    descriptionClassName,
}: EmptyStateProps) {
    const variantStyles = {
        inline: 'py-8 px-4 text-center',
        dashed: 'rounded-xl border border-dashed border-border bg-page p-8 text-center',
        box: 'rounded-xl border border-border bg-surface p-8 text-center shadow-xs',
    };

    return (
        <div
            className={twMerge(
                clsx(
                    'flex flex-col items-center justify-center max-w-md mx-auto text-center',
                    variantStyles[variant],
                    className
                )
            )}
        >
            {Icon && (
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-soft text-muted mb-3 border border-border/80 shrink-0">
                    <Icon
                        className={twMerge(clsx('h-6 w-6 text-muted/70', iconClassName))}
                        aria-hidden="true"
                    />
                </div>
            )}

            <h3
                className={twMerge(
                    clsx('text-sm font-semibold text-ink leading-snug', titleClassName)
                )}
            >
                {title}
            </h3>

            {description && (
                <p
                    className={twMerge(
                        clsx('mt-1.5 text-xs text-muted leading-relaxed max-w-sm', descriptionClassName)
                    )}
                >
                    {description}
                </p>
            )}

            {action && <div className="mt-4 flex items-center justify-center">{action}</div>}
        </div>
    );
}

export default EmptyState;
