import React, { ReactNode } from 'react';

export interface TooltipProps {
    content: ReactNode;
    children: ReactNode;
    position?: 'top' | 'bottom';
    align?: 'center' | 'left' | 'right';
    className?: string;
}

export function Tooltip({
    content,
    children,
    position = 'top',
    align = 'center',
    className = '',
}: TooltipProps) {
    const alignClasses =
        align === 'right'
            ? 'left-auto right-0 translate-x-0'
            : align === 'left'
              ? 'left-0 right-auto translate-x-0'
              : 'left-1/2 -translate-x-1/2';
    const arrowAlignClasses =
        align === 'right'
            ? 'left-auto right-3 translate-x-0'
            : align === 'left'
              ? 'left-3 right-auto translate-x-0'
              : 'left-1/2 -translate-x-1/2';
    return (
        <div className={`relative inline-flex group ${className}`}>
            {children}
            <div
                role="tooltip"
                className={`pointer-events-none absolute z-30 max-w-[min(16rem,calc(100vw-2rem))] break-words rounded-md border border-border bg-surface px-2.5 py-1 text-center text-xs font-medium whitespace-normal text-ink shadow-md opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100 ${alignClasses} ${
                    position === 'top'
                        ? 'bottom-full mb-1.5'
                        : 'top-full mt-1.5'
                }`}
            >
                {content}
                <div
                    className={`absolute h-1.5 w-1.5 rotate-45 border-border bg-surface ${arrowAlignClasses} ${
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
