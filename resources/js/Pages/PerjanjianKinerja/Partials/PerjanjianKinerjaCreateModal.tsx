import React from 'react';
import { useForm } from '@inertiajs/react';
import { FileText, Save } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { PerjanjianKinerjaFormFields } from '@/Components/PerjanjianKinerjaFormFields';
import type {
    PerjanjianKinerjaFormData,
    RenstraSummary,
    StorageSettings,
} from '@/types/perjanjian-kinerja';

interface PerjanjianKinerjaCreateModalProps {
    isOpen: boolean;
    onClose: () => void;
    renstras: RenstraSummary[];
    storageSettings?: StorageSettings;
}

export function PerjanjianKinerjaCreateModal({
    isOpen,
    onClose,
    renstras,
    storageSettings,
}: PerjanjianKinerjaCreateModalProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<PerjanjianKinerjaFormData>({
        renstra_id: renstras[0]?.id ?? '',
        tahun: renstras[0]?.tahun_mulai ?? new Date().getFullYear(),
        nomor_pk: '',
        tanggal_pk: new Date().toISOString().split('T')[0],
        lampiran: [],
    });

    const setField = <K extends keyof PerjanjianKinerjaFormData>(
        field: K,
        value: PerjanjianKinerjaFormData[K]
    ) => {
        setData((prev) => ({ ...prev, [field]: value }));
    };

    const handleClose = () => {
        if (processing) return;
        clearErrors();
        reset();
        onClose();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/perjanjian-kinerja', {
            forceFormData: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            showCloseButton={!processing}
            size="3xl"
            hideScrollbar={true}
            bodyClassName="p-4 sm:p-6"
            title={
                <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
                        <FileText className="w-4 h-4" />
                    </div>
                    <span>Formulir Pencatatan Perjanjian Kinerja</span>
                </div>
            }
            description="Masukkan rincian dokumen legal formal komitmen kinerja dan lampirkan naskah pendukung."
            footer={
                <div className="flex items-center justify-end gap-3 w-full">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={handleClose}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="create-pk-modal-form"
                        variant="primary"
                        disabled={processing}
                    >
                        <Save className="h-4 w-4" aria-hidden="true" />
                        {processing ? 'Menyimpan...' : 'Simpan Perjanjian Kinerja'}
                    </Button>
                </div>
            }
        >
            <form id="create-pk-modal-form" onSubmit={handleSubmit}>
                <PerjanjianKinerjaFormFields
                    data={data}
                    errors={errors as Record<string, string | undefined>}
                    renstras={renstras}
                    storageSettings={storageSettings}
                    disabled={processing}
                    setField={setField}
                />
            </form>
        </Modal>
    );
}
