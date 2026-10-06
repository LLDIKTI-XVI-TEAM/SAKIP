import React, { useEffect, useId, useRef, type ReactNode } from 'react';
import { AlertTriangle, X } from 'lucide-react';
import { Button } from '@/Components/Button';

export interface AuditReasonModalProps {
    open: boolean;
    title: string;
    description: string;
    reason: string;
    error?: string;
    busy?: boolean;
    submitDisabled?: boolean;
    notice?: ReactNode;
    confirmLabel: string;
    destructive?: boolean;
    confirmVariant?: 'primary' | 'secondary' | 'danger' | 'danger-solid';
    reasonLabel?: string;
    reasonPlaceholder?: string;
    reasonDescription?: ReactNode;
    reasonRef?: React.RefObject<HTMLTextAreaElement | null>;
    onReasonChange: (reason: string) => void;
    onClose: () => void;
    onConfirm: () => void;
}

export function AuditReasonModal({
    open,
    title,
    description,
    reason,
    error,
    busy = false,
    submitDisabled = false,
    notice,
    confirmLabel,
    destructive = false,
    confirmVariant,
    reasonLabel = 'Alasan perubahan',
    reasonPlaceholder = 'Jelaskan alasan yang dapat dipahami saat audit...',
    reasonDescription,
    reasonRef,
    onReasonChange,
    onClose,
    onConfirm,
}: AuditReasonModalProps) {
    const dialog = useRef<HTMLDialogElement>(null);
    const id = useId();
    const reasonInput = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        if (open && error) {
            reasonInput.current?.focus();
        }
    }, [open, error]);

    useEffect(() => {
        const element = dialog.current;
        if (open) {
            element?.showModal();
            (element?.querySelector<HTMLElement>('[role=alert] h2') ?? reasonInput.current)?.focus();
        } else {
            element?.close();
        }
        return () => element?.close();
    }, [open]);

    return (
        <dialog
            ref={dialog}
            aria-labelledby={`${id}-title`}
            aria-describedby={`${id}-description`}
            onCancel={(event) => {
                event.preventDefault();
                if (!busy) onClose();
            }}
            className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-border bg-surface p-6 text-ink shadow-2xl backdrop:bg-ink/50"
        >
            {open && (
                <div>
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex items-start gap-3.5 min-w-0 flex-1">
                            <span
                                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border ${
                                    destructive
                                        ? 'bg-danger/10 text-danger border-danger/20'
                                        : 'bg-warning/10 text-warning-dark border-warning/20'
                                }`}
                                aria-hidden="true"
                            >
                                <AlertTriangle className="h-5 w-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h2 id={`${id}-title`} className="text-base font-bold text-ink leading-tight">
                                    {title}
                                </h2>
                                <p id={`${id}-description`} className="mt-1 break-words text-xs text-muted leading-relaxed">
                                    {description}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={busy}
                            aria-label="Tutup modal alasan audit"
                            className="-mr-1.5 -mt-1.5 rounded-lg p-2 text-muted hover:bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors cursor-pointer disabled:opacity-50"
                        >
                            <X className="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>

                    {notice && <div className="mt-4">{notice}</div>}

                    <div className="mt-5 space-y-4">
                        <div>
                            <label htmlFor={`${id}-reason`} className="block text-xs font-semibold text-ink">
                                {reasonLabel} <span className="text-danger" aria-hidden="true">*</span>
                            </label>
                            <textarea
                                id={`${id}-reason`}
                                ref={(el) => {
                                    reasonInput.current = el;
                                    if (reasonRef) {
                                        (reasonRef as React.MutableRefObject<HTMLTextAreaElement | null>).current = el;
                                    }
                                }}
                                rows={4}
                                value={reason}
                                onChange={(event) => onReasonChange(event.target.value)}
                                placeholder={reasonPlaceholder}
                                aria-invalid={Boolean(error)}
                                aria-describedby={error ? `${id}-error` : undefined}
                                className="mt-1.5 w-full rounded-lg border border-border bg-surface px-3.5 py-2.5 text-xs text-ink placeholder:text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors"
                            />
                            {error && (
                                <p id={`${id}-error`} role="alert" className="mt-1 text-xs font-medium text-danger">
                                    {error}
                                </p>
                            )}
                            {reasonDescription}
                        </div>
                    </div>

                    <div className="mt-6 flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 pt-4 border-t border-border/80">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onClose}
                            disabled={busy}
                            className="w-full sm:w-auto h-9 text-xs"
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant={confirmVariant ?? (destructive ? 'danger' : 'primary')}
                            size="sm"
                            onClick={() => {
                                if (!busy && !submitDisabled) onConfirm();
                            }}
                            disabled={submitDisabled}
                            isLoading={busy}
                            className="w-full sm:w-auto h-9 text-xs shadow-xs"
                        >
                            {confirmLabel}
                        </Button>
                    </div>
                </div>
            )}
        </dialog>
    );
}

export default AuditReasonModal;
