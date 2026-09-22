import React, { useEffect, useId, useRef, type ReactNode } from 'react';
import { AlertTriangle, X } from 'lucide-react';
import { Button } from '@/Components/Button';

interface AuditReasonModalProps {
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
    onReasonChange,
    onClose,
    onConfirm,
}: AuditReasonModalProps) {
    const dialog = useRef<HTMLDialogElement>(null);
    const id = useId();
    const reasonInput = useRef<HTMLTextAreaElement>(null);
    useEffect(() => {
        const element = dialog.current;
        if (open) {
            element?.showModal();
            (element?.querySelector<HTMLElement>('[role=alert] h2') ?? reasonInput.current)?.focus();
        }
        else element?.close();
        return () => element?.close();
    }, [open]);

    return (
        <dialog ref={dialog} aria-labelledby={`${id}-title`} aria-describedby={`${id}-description`} onCancel={(event) => { event.preventDefault(); if (!busy) onClose(); }} className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-border bg-surface p-0 shadow-xl backdrop:bg-ink/55">
            {open && <div
                className="w-full max-w-lg rounded-xl bg-surface"
            >
                <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                    <div className="flex gap-3">
                        <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-warning/10 text-warning-dark">
                            <AlertTriangle className="h-5 w-5" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <h2 id={`${id}-title`} className="text-base font-semibold text-ink">{title}</h2>
                            <p id={`${id}-description`} className="mt-1 break-words text-sm leading-6 text-muted">{description}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={busy}
                        className="rounded-lg p-1.5 text-muted transition-colors hover:bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/25 disabled:opacity-50"
                        aria-label="Tutup modal alasan audit"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="px-5 py-4">
                    {open && notice}
                    <label htmlFor={`${id}-reason`} className="mb-1.5 block text-sm font-semibold text-ink">
                        Alasan perubahan <span className="text-danger" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id={`${id}-reason`}
                        ref={reasonInput}
                        rows={4}
                        value={reason}
                        onChange={(event) => onReasonChange(event.target.value)}
                        placeholder="Jelaskan alasan yang dapat dipahami saat audit…"
                        className="w-full rounded-lg border border-border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                    {error && <p className="mt-1 text-xs font-medium text-danger">{error}</p>}
                    <p className="mt-2 text-xs leading-5 text-muted">
                        Alasan, pengguna, waktu, serta nilai sebelum dan sesudah akan dicatat pada audit log.
                    </p>
                </div>

                <div className="flex flex-col-reverse gap-2 border-t border-border px-5 py-4 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" onClick={onClose} disabled={busy}>Batal</Button>
                    <Button
                        type="button"
                        variant={destructive ? 'danger' : 'primary'}
                        onClick={() => { if (!busy && !submitDisabled) onConfirm(); }}
                        disabled={submitDisabled}
                        isLoading={busy}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            </div>}
        </dialog>
    );
}
