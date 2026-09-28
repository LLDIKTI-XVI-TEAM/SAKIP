import React, { ReactNode } from 'react';

export interface TooltipProps {
    content: ReactNode;
    children: ReactNode;
    position?: 'top' | 'bottom';
    className?: string;
}

export function Tooltip({
    content,
    children,
    position = 'top',
    className = '',
}: TooltipProps) {
    return (
        <div className={`relative inline-flex group ${className}`}>
            {children}
            <div
                role="tooltip"
                className={`pointer-events-none absolute left-1/2 -translate-x-1/2 z-30 whitespace-nowrap rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-medium text-ink shadow-md opacity-0 transition-opacity duration-150 group-hover:opacity-100 ${
                    position === 'top'
                        ? 'bottom-full mb-1.5'
                        : 'top-full mt-1.5'
                }`}
            >
                {content}
                <div
                    className={`absolute left-1/2 -translate-x-1/2 h-1.5 w-1.5 rotate-45 border-border bg-surface ${
                        position === 'top'
                            ? '-bottom-1 border-r border-b'
                            : '-top-1 border-l border-t'
                    }`}
                    aria-hidden="true"
                />
            </div>
        </div>
    );
}
