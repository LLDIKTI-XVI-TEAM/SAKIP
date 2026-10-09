import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { RegulasiFormFields } from '@/Components/RegulasiFormFields';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { RegulasiFailureNotice } from '@/Components/RegulasiFailureNotice';
import type { RegulasiFormData } from '@/types/regulasi';

interface RegulasiCreateModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const defaultFormData: RegulasiFormData = {
    jenis: 'kepmen',
    nomor: '',
    tahun: String(new Date().getFullYear()),
    tentang: '',
    tanggal: '',
    tautan_sumber: '',
    catatan: '',
    aktif: true,
    alasan: '',
    lampiran: [],
};

export const RegulasiCreateModal: React.FC<RegulasiCreateModalProps> = ({
    isOpen,
    onClose,
}) => {
    const recovery = useAuthRecovery();
    const [recoveryUnknown, setRecoveryUnknown] = useState(false);
    const [recoveryMessage, setRecoveryMessage] = useState('');

    const form = useForm<RegulasiFormData>({ ...defaultFormData });

    if (!isOpen) return null;

    const isRecoveryActive = Boolean(recovery.recovery) || recoveryUnknown;

    const handleSafeClose = () => {
        if (form.processing) return;
        if (!isRecoveryActive) {
            form.clearErrors();
            form.reset();
            setRecoveryMessage('');
        }
        onClose();
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (form.processing || recovery.recovery || recoveryUnknown) return;

        form.post('/regulasi', {
            preserveState: 'errors',
            forceFormData: true,
            preserveScroll: true,
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: '/regulasi', mutation: true })) return false;
                setRecoveryMessage(
                    response.status === 403
                        ? 'Akses ditolak. Hasil tindakan sebelumnya belum dapat dipastikan. Periksa akses dan data terbaru.'
                        : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'
                );
                setRecoveryUnknown(true);
                return false;
            },
            onCancel: () => {
                setRecoveryUnknown(true);
                setRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
            },
            onNetworkError: () => {
                setRecoveryUnknown(true);
                setRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
            onSuccess: () => {
                form.reset();
                form.clearErrors();
                setRecoveryUnknown(false);
                setRecoveryMessage('');
                onClose();
            },
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleSafeClose}
            showCloseButton={!form.processing}
            size="3xl"
            bodyClassName="p-4 sm:p-6"
            title="Tambah Dasar Aturan"
        >
            <form onSubmit={submit} noValidate className="space-y-6">
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                {!recovery.recovery && <RegulasiFailureNotice message={recoveryMessage} />}

                <RegulasiFormFields
                    data={form.data}
                    errors={form.errors as Record<string, string | undefined>}
                    disabled={form.processing}
                    setField={(field, value) => form.setData((prev) => ({ ...prev, [field]: value }))}
                />

                <div className="flex items-center justify-end gap-3 pt-5 border-t border-border">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={handleSafeClose}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        isLoading={form.processing}
                        disabled={form.processing || Boolean(recovery.recovery) || recoveryUnknown}
                    >
                        Simpan Dasar Aturan
                    </Button>
                </div>
            </form>
        </Modal>
    );
};
