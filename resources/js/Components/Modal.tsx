import React, { useEffect, useRef } from 'react';
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
}

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
}) => {
    const modalRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };

        if (isOpen) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', handleKeyDown);
        }

        return () => {
            document.body.style.overflow = 'unset';
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen, onClose]);

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
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div
                ref={modalRef}
                className={twMerge(
                    clsx(
                        'w-full bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh] transition-all transform duration-200',
                        sizeClasses[size],
                        className
                    )
                )}
            >
                {(title || showCloseButton) && (
                    <div className="flex items-start justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <div>
                            {title && (
                                <h3 className="text-base font-bold text-slate-900 leading-tight">
                                    {title}
                                </h3>
                            )}
                            {description && (
                                <p className="text-xs text-slate-500 mt-0.5">
                                    {description}
                                </p>
                            )}
                        </div>
                        {showCloseButton && (
                            <button
                                type="button"
                                onClick={onClose}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer ml-auto"
                                aria-label="Tutup dialog"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        )}
                    </div>
                )}

                <div className="p-6 overflow-y-auto flex-1">
                    {children}
                </div>

                {footer && (
                    <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
};
