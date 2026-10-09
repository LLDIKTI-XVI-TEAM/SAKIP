import React, { useEffect, useId, useRef } from 'react';
import { X } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title?: React.ReactNode;
    description?: React.ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl';
    children: React.ReactNode;
    footer?: React.ReactNode;
    showCloseButton?: boolean;
    className?: string;
    bodyClassName?: string;
    hideScrollbar?: boolean;
    scrollable?: boolean;
    ariaLabel?: string;
    autoFocus?: boolean;
}

// Global modal stack to ensure only the topmost modal handles Escape
const modalStack: string[] = [];

export const Modal: React.FC<ModalProps> = ({
    isOpen,
    onClose,
    title,
    description,
    size = 'md',
    children,
    footer,
    showCloseButton = true,
    className,
    bodyClassName,
    hideScrollbar = false,
    scrollable = true,
    ariaLabel,
    autoFocus = true,
}) => {
    const modalId = useId();
    const titleId = title ? `${modalId}-title` : undefined;
    const descriptionId = description ? `${modalId}-desc` : undefined;
    const modalRef = useRef<HTMLDivElement>(null);
    const previousFocusedElement = useRef<HTMLElement | null>(null);
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;

    useEffect(() => {
        if (!isOpen) return;

        previousFocusedElement.current = document.activeElement as HTMLElement | null;
        modalStack.push(modalId);
        document.body.style.overflow = 'hidden';

        // Focus the first form field or focusable element once when modal opens
        const focusInitialElement = () => {
            if (!modalRef.current) return;
            if (!autoFocus) {
                modalRef.current.focus();
                return;
            }
            const primaryField = modalRef.current.querySelector<HTMLElement>(
                'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
            );
            if (primaryField) {
                primaryField.focus();
                return;
            }

            const focusable = modalRef.current.querySelectorAll<HTMLElement>(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (focusable.length > 0) {
                focusable[0].focus();
            } else {
                modalRef.current.focus();
            }
        };

        // Fokus field pertama hanya bila pengguna belum berinteraksi dengan elemen
        // di dalam modal; mencuri fokus di tengah pengetikan akan menelan keystroke.
        const timer = setTimeout(() => {
            if (modalRef.current?.contains(document.activeElement)) return;
            focusInitialElement();
        }, 50);

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                // Only the topmost modal in the stack responds to Escape
                if (modalStack[modalStack.length - 1] === modalId) {
                    e.stopPropagation();
                    onCloseRef.current();
                }
                return;
            }

            if (e.key === 'Tab' && modalRef.current) {
                const focusables = Array.from(
                    modalRef.current.querySelectorAll<HTMLElement>(
                        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    )
                ).filter((el) => el.offsetParent !== null);

                if (focusables.length === 0) {
                    e.preventDefault();
                    return;
                }

                const first = focusables[0];
                const last = focusables[focusables.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === first || !modalRef.current.contains(document.activeElement)) {
                        e.preventDefault();
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last || !modalRef.current.contains(document.activeElement)) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            clearTimeout(timer);
            const index = modalStack.indexOf(modalId);
            if (index !== -1) {
                modalStack.splice(index, 1);
            }
            if (modalStack.length === 0) {
                document.body.style.overflow = 'unset';
            }
            window.removeEventListener('keydown', handleKeyDown);
            if (previousFocusedElement.current && typeof previousFocusedElement.current.focus === 'function') {
                previousFocusedElement.current.focus();
            }
        };
    }, [isOpen, modalId, autoFocus]);

    if (!isOpen) return null;

    const sizeClasses = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
        '2xl': 'max-w-2xl',
        '3xl': 'max-w-3xl',
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/60 backdrop-blur-xs transition-opacity duration-200"
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            aria-describedby={descriptionId}
            aria-label={!titleId ? ariaLabel : undefined}
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div
                ref={modalRef}
                tabIndex={-1}
                className={twMerge(
                    clsx(
                        'w-full bg-surface rounded-xl shadow-xl border border-border overflow-hidden flex flex-col max-h-[95vh] transition-all transform duration-200 outline-none',
                        sizeClasses[size],
                        className
                    )
                )}
            >
                {(title || showCloseButton) && (
                    <div className="px-5 py-3 sm:px-6 sm:py-3.5 border-b border-border bg-soft/50">
                        <div className="flex items-center justify-between gap-3">
                            {title && (
                                <h3 id={titleId} className="min-w-0 text-base font-bold text-ink leading-tight">
                                    {title}
                                </h3>
                            )}
                            {showCloseButton && (
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="inline-flex shrink-0 items-center justify-center text-muted hover:text-ink p-1 rounded-lg hover:bg-soft transition-colors cursor-pointer ml-auto"
                                    aria-label="Tutup dialog"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            )}
                        </div>
                        {description && (
                            <p id={descriptionId} className="text-xs text-muted mt-0.5">
                                {description}
                            </p>
                        )}
                    </div>
                )}

                <div
                    className={twMerge(
                        clsx(
                            'p-6 flex-1 overflow-y-auto',
                            (hideScrollbar || !scrollable) && 'no-scrollbar',
                            bodyClassName
                        )
                    )}
                    style={
                        hideScrollbar || !scrollable
                            ? {
                                  scrollbarWidth: 'none',
                                  msOverflowStyle: 'none',
                              }
                            : undefined
                    }
                >
                    {(hideScrollbar || !scrollable) && (
                        <style>{`
                            .no-scrollbar::-webkit-scrollbar {
                                display: none !important;
                                width: 0 !important;
                                height: 0 !important;
                                background: transparent !important;
                            }
                        `}</style>
                    )}
                    {children}
                </div>

                {footer && (
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-soft/50">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
};
