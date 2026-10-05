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
    const alignmentClasses = {
        center: 'left-1/2 -translate-x-1/2',
        left: 'left-0',
        right: 'right-0',
    };

    const arrowAlignmentClasses = {
        center: 'left-1/2 -translate-x-1/2',
        left: 'left-3.5',
        right: 'right-3.5',
    };

    return (
        <div className={`relative inline-flex group/tooltip ${className}`}>
            {children}
            <div
                role="tooltip"
                className={`pointer-events-none absolute z-30 whitespace-nowrap rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-medium text-ink shadow-md opacity-0 invisible transition-[opacity,visibility] duration-150 group-hover/tooltip:opacity-100 group-hover/tooltip:visible ${alignmentClasses[align]} ${
                    position === 'top'
                        ? 'bottom-full mb-1.5'
                        : 'top-full mt-1.5'
                }`}
            >
                {content}
                <div
                    className={`absolute h-1.5 w-1.5 rotate-45 border-border bg-surface ${arrowAlignmentClasses[align]} ${
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
