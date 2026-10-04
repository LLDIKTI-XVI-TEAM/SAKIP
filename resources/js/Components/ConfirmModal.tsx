import React, { ReactNode } from 'react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type ConfirmVariant = 'primary' | 'danger' | 'warning' | 'info' | 'success';

export interface ConfirmModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: ConfirmVariant;
    isLoading?: boolean;
    disabled?: boolean;
    notice?: ReactNode;
    children?: ReactNode;
}

const variantConfig: Record<
    ConfirmVariant,
    { icon: typeof Info; iconBg: string; iconColor: string; buttonVariant: 'primary' | 'danger-solid' }
> = {
    primary: {
        icon: CheckCircle2,
        iconBg: 'bg-primary/10',
        iconColor: 'text-primary',
        buttonVariant: 'primary',
    },
    danger: {
        icon: AlertCircle,
        iconBg: 'bg-danger/10',
        iconColor: 'text-danger',
        buttonVariant: 'danger-solid',
    },
    warning: {
        icon: AlertTriangle,
        iconBg: 'bg-warning/10',
        iconColor: 'text-warning-dark',
        buttonVariant: 'primary',
    },
    info: {
        icon: Info,
        iconBg: 'bg-info/10',
        iconColor: 'text-info-dark',
        buttonVariant: 'primary',
    },
    success: {
        icon: CheckCircle2,
        iconBg: 'bg-success/10',
        iconColor: 'text-success',
        buttonVariant: 'primary',
    },
};

export const ConfirmModal: React.FC<ConfirmModalProps> = ({
    isOpen,
    onClose,
    onConfirm,
    title,
    description,
    confirmLabel = 'Konfirmasi',
    cancelLabel = 'Batal',
    variant = 'primary',
    isLoading = false,
    disabled = false,
    notice,
    children,
}) => {
    const config = variantConfig[variant];
    const Icon = config.icon;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!isLoading && !disabled) {
            onConfirm();
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            size="md"
            showCloseButton={!isLoading}
            title={
                <div className="flex items-center gap-3">
                    <span
                        className={twMerge(
                            clsx('flex h-9 w-9 shrink-0 items-center justify-center rounded-full', config.iconBg)
                        )}
                        aria-hidden="true"
                    >
                        <Icon className={twMerge(clsx('h-5 w-5', config.iconColor))} />
                    </span>
                    <span className="font-semibold text-ink">{title}</span>
                </div>
            }
            description={description}
            footer={
                <div className="flex w-full items-center justify-end gap-2.5">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={isLoading}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={config.buttonVariant}
                        onClick={onConfirm}
                        isLoading={isLoading}
                        disabled={disabled || isLoading}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {notice && <div>{notice}</div>}
                {children}
            </form>
        </Modal>
    );
};

export default ConfirmModal;
