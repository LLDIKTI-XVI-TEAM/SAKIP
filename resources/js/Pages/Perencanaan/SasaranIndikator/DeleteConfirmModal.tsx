import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import type { IndikatorKinerjaItem, SasaranStrategisItem } from '@/types/sasaran-indikator';

export type DeleteTarget =
    | { type: 'sasaran'; item: SasaranStrategisItem }
    | { type: 'indikator'; item: IndikatorKinerjaItem };

interface DeleteConfirmModalProps {
    target: DeleteTarget | null;
    onClose: () => void;
}

export const DeleteConfirmModal: React.FC<DeleteConfirmModalProps> = ({
    target,
    onClose,
}) => {
    const [reasonError, setReasonError] = useState<string | undefined>();
    const { data, setData, delete: destroy, processing, reset, errors } = useForm({
        alasan: '',
    });

    if (!target) return null;

    const isSasaran = target.type === 'sasaran';
    const title = isSasaran
        ? `Hapus Sasaran Strategis [${(target.item as SasaranStrategisItem).kode}]?`
        : `Arsipkan Indikator [${(target.item as IndikatorKinerjaItem).kode}]?`;

    const description = isSasaran
        ? `Tindakan ini akan menghapus sasaran strategis "${(target.item as SasaranStrategisItem).kode}". Perhatian: Sasaran yang memiliki indikator kinerja tidak dapat dihapus sebelum indikatornya dipindahkan atau dihapus.`
        : `Tindakan ini akan mengarsipkan indikator kinerja "${(target.item as IndikatorKinerjaItem).nama}". Indikator yang diarsipkan tetap tersimpan sebagai riwayat dan tidak lagi menerima target pengisian baru.`;

    const handleConfirm = () => {
        if (data.alasan.trim().length < 10) {
            setReasonError('Alasan pengarsipan minimal 10 karakter.');
            return;
        }

        const endpoint = isSasaran
            ? `/perencanaan/sasaran/${target.item.id}`
            : `/perencanaan/indikator/${target.item.id}`;

        destroy(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setReasonError(undefined);
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        setReasonError(undefined);
        onClose();
    };

    return (
        <AuditReasonModal
            open={Boolean(target)}
            title={title}
            description={description}
            reason={data.alasan}
            error={reasonError || errors.alasan}
            busy={processing}
            confirmLabel={isSasaran ? 'Hapus Sasaran' : 'Arsipkan Indikator'}
            destructive
            onReasonChange={(reason) => {
                setData('alasan', reason);
                if (reasonError && reason.trim().length >= 10) {
                    setReasonError(undefined);
                }
            }}
            onClose={handleClose}
            onConfirm={handleConfirm}
        />
    );
};
