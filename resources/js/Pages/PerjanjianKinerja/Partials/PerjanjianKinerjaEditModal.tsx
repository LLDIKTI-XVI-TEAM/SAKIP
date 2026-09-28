import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Save, ShieldCheck } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { PerjanjianKinerjaFormFields } from '@/Components/PerjanjianKinerjaFormFields';
import type {
    PerjanjianKinerjaFormData,
    RenstraPkSummary,
    StorageSettings,
} from '@/types/perjanjian-kinerja';

interface PerjanjianKinerjaEditModalProps {
    isOpen: boolean;
    onClose: () => void;
    pk: RenstraPkSummary | null;
    isJadwalAktif?: boolean;
    storageSettings?: StorageSettings;
    canUploadBerkas?: boolean;
}

export function PerjanjianKinerjaEditModal({
    isOpen,
    onClose,
    pk,
    isJadwalAktif = false,
    storageSettings,
    canUploadBerkas = true,
}: PerjanjianKinerjaEditModalProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<PerjanjianKinerjaFormData>({
        renstra_id: pk?.renstra_id ?? '',
        tahun: pk?.tahun ?? new Date().getFullYear(),
        nomor_pk: pk?.nomor_pk ?? '',
        tanggal_pk: pk?.tanggal_pk ? pk.tanggal_pk.split('T')[0] : '',
        alasan: '',
        lampiran: [],
        _method: 'put',
    });

    useEffect(() => {
        if (pk && isOpen) {
            setData({
                renstra_id: pk.renstra_id,
                tahun: pk.tahun,
                nomor_pk: pk.nomor_pk,
                tanggal_pk: pk.tanggal_pk ? pk.tanggal_pk.split('T')[0] : '',
                alasan: '',
                lampiran: [],
                _method: 'put',
            });
            clearErrors();
        }
    }, [pk, isOpen, setData, clearErrors]);

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
        if (!pk) return;

        post(`/perjanjian-kinerja/${pk.id}`, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    if (!pk) return null;

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            showCloseButton={!processing}
            autoFocus={false}
            size="3xl"
            hideScrollbar={true}
            bodyClassName="p-4 sm:p-6"
            title={
                <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
                        <Pencil className="w-4 h-4" />
                    </div>
                    <span>Edit Data Perjanjian Kinerja</span>
                </div>
            }
            description="Perbarui nomor surat, tanggal penandatanganan, atau tambahkan naskah lampiran baru. Seluruh perubahan dicatat dalam jejak audit beralasan."
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
                        form="edit-pk-modal-form"
                        variant="primary"
                        disabled={processing || !data.alasan?.trim()}
                    >
                        <Save className="h-4 w-4" aria-hidden="true" />
                        {processing ? 'Menyimpan...' : 'Perbarui Perjanjian Kinerja'}
                    </Button>
                </div>
            }
        >
            <form id="edit-pk-modal-form" onSubmit={handleSubmit}>
                {isJadwalAktif && (
                    <div className="mb-6 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-950 dark:text-emerald-200">
                        <div className="flex items-start gap-3">
                            <ShieldCheck className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" aria-hidden="true" />
                            <div>
                                <h3 className="text-sm font-bold">Jadwal Tahunan Telah Aktif</h3>
                                <p className="mt-1 text-xs leading-relaxed text-emerald-900/80 dark:text-emerald-300">
                                    Pembaruan metadata atau penambahan lampiran baru tetap diizinkan. Namun, lampiran yang telah ada tidak dapat dihapus demi kepatuhan audit legal formal.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <PerjanjianKinerjaFormFields
                    data={data}
                    errors={errors as Record<string, string | undefined>}
                    renstras={[pk.renstra]}
                    storageSettings={storageSettings}
                    isEdit={true}
                    disabled={processing}
                    canUploadBerkas={canUploadBerkas}
                    setField={setField}
                />
            </form>
        </Modal>
    );
}
