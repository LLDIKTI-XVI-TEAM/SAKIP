import React, { useState, useRef, useEffect } from 'react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export interface HoverScrollTextProps {
    /** Teks yang akan ditampilkan dan digeser jika panjangnya melebihi container */
    text: string;
    /** Icon opsional yang diletakkan di sebelah kiri teks */
    icon?: React.ReactNode;
    /** Trigger hover dari card/container luar jika diinginkan */
    isParentHovered?: boolean;
    /** ClassName untuk wrapper container utama */
    className?: string;
    /** ClassName khusus untuk elemen teks */
    textClassName?: string;
    /** Kecepatan scroll dalam pixel per detik (default: 35) */
    scrollSpeed?: number;
    /** Jeda delay sebelum teks mulai bergeser dalam detik (default: 0.35) */
    startDelay?: number;
    /** Class warna background untuk efek fade gradien di ujung kanan (default: 'from-surface') */
    fadeFromColor?: string;
}

export const HoverScrollText: React.FC<HoverScrollTextProps> = ({
    text,
    icon,
    isParentHovered = false,
    className,
    textClassName,
    scrollSpeed = 35,
    startDelay = 0.35,
    fadeFromColor = 'from-surface',
}) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const textRef = useRef<HTMLSpanElement>(null);
    const [overflow, setOverflow] = useState(0);
    const [isSelfHovered, setIsSelfHovered] = useState(false);

    const updateOverflow = () => {
        if (containerRef.current && textRef.current) {
            const diff = textRef.current.scrollWidth - containerRef.current.clientWidth;
            setOverflow(diff > 0 ? diff : 0);
        }
    };

    useEffect(() => {
        updateOverflow();
        const timer1 = setTimeout(updateOverflow, 150);
        const timer2 = setTimeout(updateOverflow, 600);
        window.addEventListener('resize', updateOverflow);
        return () => {
            clearTimeout(timer1);
            clearTimeout(timer2);
            window.removeEventListener('resize', updateOverflow);
        };
    }, [text]);

    const activeHover = isParentHovered || isSelfHovered;
    const isScrolling = activeHover && overflow > 0;
    const duration = Math.max(2, Math.round(overflow / scrollSpeed));

    return (
        <div
            className={twMerge(
                clsx(
                    'flex items-center gap-1.5 text-sm font-semibold text-ink overflow-hidden',
                    className
                )
            )}
            onMouseEnter={() => {
                updateOverflow();
                setIsSelfHovered(true);
            }}
            onMouseLeave={() => setIsSelfHovered(false)}
        >
            {icon && (
                <span className="shrink-0 z-10 bg-surface pr-0.5 select-none flex items-center">
                    {icon}
                </span>
            )}
            <div ref={containerRef} className="overflow-hidden relative w-full flex items-center">
                <span
                    ref={textRef}
                    className={twMerge(
                        clsx('inline-block whitespace-nowrap select-none', textClassName)
                    )}
                    style={{
                        transform: isScrolling ? `translateX(-${overflow + 6}px)` : 'translateX(0)',
                        transitionProperty: 'transform',
                        transitionDuration: isScrolling ? `${duration}s` : '0.35s',
                        transitionTimingFunction: isScrolling
                            ? 'linear'
                            : 'cubic-bezier(0.25, 1, 0.5, 1)',
                        transitionDelay: isScrolling ? `${startDelay}s` : '0s',
                    }}
                >
                    {text}
                </span>
                <div
                    className={clsx(
                        'pointer-events-none absolute right-0 top-0 bottom-0 w-6 bg-gradient-to-l to-transparent z-10 transition-opacity duration-300',
                        fadeFromColor,
                        isScrolling ? 'opacity-0' : overflow > 0 ? 'opacity-100' : 'opacity-0'
                    )}
                />
            </div>
        </div>
    );
};

export default HoverScrollText;
