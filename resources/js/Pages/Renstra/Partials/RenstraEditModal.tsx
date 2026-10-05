import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Save } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import type {
    RegulasiOption,
    RenstraDetail,
    RenstraFormData,
    RenstraSummary,
} from '@/types/renstra';

interface RenstraEditModalProps {
    isOpen: boolean;
    onClose: () => void;
    renstra: RenstraDetail | RenstraSummary | null;
    regulasiOptions?: RegulasiOption[];
    canReadRegulasi?: boolean;
    canUploadAttachment?: boolean;
    onSuccess?: () => void;
}

export function RenstraEditModal({
    isOpen,
    onClose,
    renstra,
    regulasiOptions = [],
    canReadRegulasi = false,
    canUploadAttachment = false,
    onSuccess,
}: RenstraEditModalProps) {
    const editForm = useForm<RenstraFormData>({
        nama: '',
        kode: '',
        tahun_mulai: String(new Date().getFullYear()),
        tahun_selesai: String(new Date().getFullYear() + 4),
        deskripsi: '',
        dasar_hukum: '',
        regulasi_id: '',
        alasan: '',
        lampiran: [],
        _method: 'put',
    });

    const { setData, clearErrors } = editForm;

    useEffect(() => {
        if (!isOpen || !renstra) return;

        setData({
            nama: renstra.nama,
            kode: renstra.kode,
            tahun_mulai: String(renstra.tahun_mulai),
            tahun_selesai: String(renstra.tahun_selesai),
            deskripsi: renstra.deskripsi ?? '',
            dasar_hukum: renstra.dasar_hukum ?? '',
            regulasi_id: renstra.regulasi_id
                ? String(renstra.regulasi_id)
                : ('regulasi' in renstra && renstra.regulasi?.id ? String(renstra.regulasi.id) : ''),
            alasan: '',
            lampiran: [],
            _method: 'put',
        });
        clearErrors();
    }, [isOpen, renstra, setData, clearErrors]);

    const handleClose = () => {
        if (editForm.processing) return;
        editForm.clearErrors();
        editForm.reset();
        onClose();
    };

    const handleEditSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!renstra || editForm.processing) return;

        editForm.transform((data) => {
            if (canReadRegulasi === true) return data;

            const payload: Partial<RenstraFormData> = { ...data };
            delete payload.regulasi_id;

            return payload;
        });

        editForm.post(`/renstra/${renstra.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                editForm.reset();
                onClose();
                onSuccess?.();
            },
        });
    };

    if (!renstra) return null;

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            size="3xl"
            title={
                <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary shrink-0">
                        <Pencil className="h-4 w-4" />
                    </div>
                    <span>Edit Rencana Strategis</span>
                </div>
            }
            footer={
                <div className="flex items-center justify-end gap-3 w-full">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleClose}
                        disabled={editForm.processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="form-edit-renstra-modal"
                        variant="primary"
                        isLoading={editForm.processing}
                        disabled={editForm.processing}
                    >
                        <Save className="h-4 w-4" aria-hidden="true" />
                        Simpan Perubahan
                    </Button>
                </div>
            }
        >
            <form id="form-edit-renstra-modal" onSubmit={handleEditSubmit} noValidate>
                <RenstraFormFields
                    data={editForm.data}
                    errors={editForm.errors as Record<string, string | undefined>}
                    regulasiOptions={regulasiOptions}
                    disabled={editForm.processing}
                    isEdit={true}
                    requireReason={renstra.status === 'aktif'}
                    canReadRegulasi={canReadRegulasi === true}
                    canUploadAttachment={canUploadAttachment}
                    attachmentLocked={renstra.status !== 'draft'}
                    setField={(field, value) => editForm.setData((prev) => ({ ...prev, [field]: value }))}
                    setLampiran={(updater) => editForm.setData((prev) => ({ ...prev, lampiran: updater(prev.lampiran) }))}
                />
            </form>
        </Modal>
    );
}
