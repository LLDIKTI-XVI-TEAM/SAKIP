import React, { useState, useRef, useEffect, type ReactNode } from 'react';
import { Info } from 'lucide-react';

export interface InfoTooltipProps {
    content: ReactNode;
    title?: string;
    position?: 'top' | 'bottom' | 'left' | 'right';
    label?: string;
    className?: string;
    iconClassName?: string;
}

export function InfoTooltip({
    content,
    title,
    position = 'bottom',
    label = 'Informasi',
    className = '',
    iconClassName = 'h-3.5 w-3.5',
}: InfoTooltipProps) {
    const [isVisible, setIsVisible] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const tooltipId = React.useId();

    useEffect(() => {
        if (!isVisible) return;

        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsVisible(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsVisible(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isVisible]);

    const positionClasses = {
        top: 'bottom-full left-0 sm:left-1/2 sm:-translate-x-1/2 mb-1.5',
        bottom: 'top-full left-0 sm:left-1/2 sm:-translate-x-1/2 mt-1.5',
        left: 'right-full top-1/2 -translate-y-1/2 mr-1.5',
        right: 'left-full top-1/2 -translate-y-1/2 ml-1.5',
    }[position];

    return (
        <div
            ref={containerRef}
            className={`relative inline-flex items-center ${className}`}
            onMouseEnter={() => setIsVisible(true)}
            onMouseLeave={() => setIsVisible(false)}
        >
            <button
                type="button"
                aria-label={label}
                aria-describedby={isVisible ? tooltipId : undefined}
                aria-expanded={isVisible}
                onClick={() => setIsVisible((prev) => !prev)}
                onFocus={() => setIsVisible(true)}
                onBlur={() => setIsVisible(false)}
                className="inline-flex items-center justify-center rounded-full p-0.5 text-muted transition-colors hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer"
            >
                <Info className={iconClassName} aria-hidden="true" />
            </button>

            {isVisible && (
                <div
                    id={tooltipId}
                    role="tooltip"
                    className={`absolute ${positionClasses} z-50 w-64 sm:w-72 max-w-[calc(100vw-2.5rem)] rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs shadow-lg animate-in fade-in zoom-in-95 duration-100 text-left`}
                >
                    {/* Diamond Pointer Arrow matching design-system surface and border */}
                    {position === 'bottom' && (
                        <div
                            className="absolute -top-1.5 left-2.5 sm:left-1/2 sm:-translate-x-1/2 h-3 w-3 rotate-45 border-l border-t border-border bg-surface"
                            aria-hidden="true"
                        />
                    )}
                    {position === 'top' && (
                        <div
                            className="absolute -bottom-1.5 left-2.5 sm:left-1/2 sm:-translate-x-1/2 h-3 w-3 rotate-45 border-r border-b border-border bg-surface"
                            aria-hidden="true"
                        />
                    )}

                    <div className="relative z-10">
                        {title && (
                            <p className="font-bold text-ink mb-1 text-xs leading-tight">
                                {title}
                            </p>
                        )}
                        <div className="text-[11px] leading-relaxed text-muted font-normal">
                            {content}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
