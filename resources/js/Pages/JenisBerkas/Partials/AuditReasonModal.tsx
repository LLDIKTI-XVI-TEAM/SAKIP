import React, { useState, useEffect } from 'react';
import { AlertTriangle } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { Textarea } from '@/Components/Textarea';

interface AuditReasonModalProps {
    isOpen: boolean;
    title: string;
    description: string;
    confirmText?: string;
    confirmVariant?: 'primary' | 'danger';
    isLoading?: boolean;
    serverError?: string;
    onClose: () => void;
    onConfirm: (alasan: string) => void;
}

export const AuditReasonModal: React.FC<AuditReasonModalProps> = ({
    isOpen,
    title,
    description,
    confirmText = 'Simpan Perubahan',
    confirmVariant = 'primary',
    isLoading = false,
    serverError,
    onClose,
    onConfirm,
}) => {
    const [alasan, setAlasan] = useState('');
    const [clientError, setClientError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen) {
            setAlasan('');
            setClientError(null);
        }
    }, [isOpen]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const trimmed = alasan.trim();
        if (trimmed.length < 5) {
            setClientError('Alasan perubahan wajib diisi minimal 5 karakter.');
            return;
        }
        if (trimmed.length > 1000) {
            setClientError('Alasan perubahan maksimal 1.000 karakter.');
            return;
        }
        setClientError(null);
        onConfirm(trimmed);
    };

    const displayError = clientError || serverError;

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            size="md"
            title={
                <div className="flex items-center gap-2 text-slate-900 font-bold text-sm">
                    <AlertTriangle className="w-4 h-4 text-amber-600" />
                    <span>{title}</span>
                </div>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <p className="text-xs text-slate-600 leading-relaxed">
                    {description}
                </p>

                <div className="space-y-1">
                    <Textarea
                        id="audit-alasan-input"
                        label="Alasan Perubahan"
                        required
                        rows={3}
                        maxLength={1000}
                        value={alasan}
                        onChange={(e) => {
                            setAlasan(e.target.value);
                            if (clientError) setClientError(null);
                        }}
                        placeholder="Tuliskan justifikasi atau dasar perubahan katalog persyaratan..."
                        disabled={isLoading}
                        error={displayError || undefined}
                        helperText="Pencatatan jejak audit bersifat permanen dan tidak dapat dihapus."
                    />
                    <div className="flex justify-end">
                        <span className={`text-[11px] ${alasan.length > 900 ? 'text-amber-600 font-medium' : 'text-slate-400'}`}>
                            {alasan.length}/1000 karakter
                        </span>
                    </div>
                </div>

                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={isLoading}
                        onClick={onClose}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant={confirmVariant}
                        size="sm"
                        isLoading={isLoading}
                    >
                        {confirmText}
                    </Button>
                </div>
            </form>
        </Modal>
    );
};
