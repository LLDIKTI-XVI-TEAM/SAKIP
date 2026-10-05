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
    /** Jika true, ratakan teks ke tengah ketika tidak terjadi overflow (default: false) */
    centerWhenNoOverflow?: boolean;
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
    centerWhenNoOverflow = false,
}) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const textRef = useRef<HTMLSpanElement>(null);
    const [overflow, setOverflow] = useState(0);
    const [isSelfHovered, setIsSelfHovered] = useState(false);
    const [isTouched, setIsTouched] = useState(false);
    const [isResetting, setIsResetting] = useState(false);

    const updateOverflow = () => {
        if (containerRef.current && textRef.current) {
            const diff = textRef.current.scrollWidth - containerRef.current.clientWidth;
            setOverflow(diff > 0 ? diff : 0);
        }
    };

    useEffect(() => {
        setIsResetting(true);
        setIsSelfHovered(false);
        setIsTouched(false);
        updateOverflow();
        const timer1 = setTimeout(() => {
            updateOverflow();
            setIsResetting(false);
        }, 50);
        const timer2 = setTimeout(updateOverflow, 200);
        const timer3 = setTimeout(updateOverflow, 600);
        window.addEventListener('resize', updateOverflow);
        return () => {
            clearTimeout(timer1);
            clearTimeout(timer2);
            clearTimeout(timer3);
            window.removeEventListener('resize', updateOverflow);
        };
    }, [text]);

    useEffect(() => {
        if (!isParentHovered && !isSelfHovered) {
            setIsTouched(false);
        }
    }, [isParentHovered, isSelfHovered]);

    const activeHover = !isResetting && (isParentHovered || isSelfHovered || isTouched);
    const isScrolling = activeHover && overflow > 0;
    const duration = Math.max(2, Math.round(overflow / scrollSpeed));

    useEffect(() => {
        if (isTouched && overflow > 0) {
            const timer = setTimeout(() => {
                setIsTouched(false);
            }, (duration + startDelay + 1.5) * 1000);
            return () => clearTimeout(timer);
        }
    }, [isTouched, overflow, duration, startDelay]);

    return (
        <div
            className={twMerge(
                clsx(
                    'flex items-center gap-1.5 text-sm font-semibold text-ink overflow-hidden max-w-full min-w-0',
                    overflow > 0 && 'cursor-pointer',
                    className
                )
            )}
            onMouseEnter={() => {
                updateOverflow();
                setIsSelfHovered(true);
            }}
            onMouseLeave={() => {
                setIsSelfHovered(false);
                setIsTouched(false);
            }}
            onPointerDown={(e) => {
                if (e.pointerType === 'touch' && overflow > 0) {
                    updateOverflow();
                    setIsTouched((prev) => !prev);
                }
            }}
        >
            {icon && (
                <span className="shrink-0 z-10 bg-surface pr-0.5 select-none flex items-center pointer-events-none">
                    {icon}
                </span>
            )}
            <div
                ref={containerRef}
                className={clsx(
                    'overflow-hidden relative w-full flex items-center pointer-events-none',
                    centerWhenNoOverflow && overflow === 0 && 'justify-center'
                )}
            >
                <span
                    ref={textRef}
                    className={twMerge(
                        clsx('inline-block whitespace-nowrap select-none', textClassName)
                    )}
                    style={{
                        transform: isScrolling ? `translateX(-${overflow + 6}px)` : 'translateX(0)',
                        transitionProperty: 'transform',
                        transitionDuration: isResetting ? '0s' : isScrolling ? `${duration}s` : '0.35s',
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
