import React, { useEffect, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Pencil, Save } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { RegulasiFormFields } from '@/Components/RegulasiFormFields';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { RegulasiFailureNotice } from '@/Components/RegulasiFailureNotice';
import type { RegulasiFormData, RegulasiSummary } from '@/types/regulasi';

interface RegulasiEditModalProps {
    isOpen: boolean;
    onClose: () => void;
    regulasi: RegulasiSummary | null;
}

export function RegulasiEditModal({
    isOpen,
    onClose,
    regulasi,
}: RegulasiEditModalProps) {
    const recovery = useAuthRecovery();
    const [recoveryUnknown, setRecoveryUnknown] = useState(false);
    const [recoveryMessage, setRecoveryMessage] = useState('');

    const form = useForm<RegulasiFormData>({
        jenis: regulasi?.jenis ?? 'kepmen',
        nomor: regulasi?.nomor ?? '',
        tahun: regulasi?.tahun ? String(regulasi.tahun) : String(new Date().getFullYear()),
        tentang: regulasi?.tentang ?? '',
        tanggal: regulasi?.tanggal ?? '',
        tautan_sumber: regulasi?.tautan_sumber ?? '',
        catatan: regulasi?.catatan ?? '',
        aktif: regulasi?.aktif ?? true,
        versi: regulasi?.versi ?? 1,
        alasan: '',
        lampiran: [],
        _method: 'put',
    });

    const prevIsOpenRef = useRef(false);
    const lastLoadedRegulasiIdRef = useRef<number | null>(null);

    useEffect(() => {
        if (!isOpen) {
            prevIsOpenRef.current = false;
            return;
        }

        const justOpened = !prevIsOpenRef.current && isOpen;
        const switchedRegulasi = regulasi !== null && regulasi.id !== lastLoadedRegulasiIdRef.current;

        if (regulasi && (justOpened || switchedRegulasi)) {
            form.setData({
                jenis: regulasi.jenis,
                nomor: regulasi.nomor,
                tahun: String(regulasi.tahun),
                tentang: regulasi.tentang,
                tanggal: regulasi.tanggal ?? '',
                tautan_sumber: regulasi.tautan_sumber ?? '',
                catatan: regulasi.catatan ?? '',
                aktif: regulasi.aktif,
                versi: regulasi.versi ?? 1,
                alasan: '',
                lampiran: [],
                _method: 'put',
            });
            form.clearErrors();
            setRecoveryMessage('');
            setRecoveryUnknown(false);
            lastLoadedRegulasiIdRef.current = regulasi.id;
        }

        prevIsOpenRef.current = isOpen;
    }, [regulasi, isOpen]);

    if (!isOpen || !regulasi) return null;

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

        form.post(`/regulasi/${regulasi.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'put', path: `/regulasi/${regulasi.id}`, mutation: true })) return false;
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

    const isAlasanValid = (form.data.alasan ?? '').trim().length >= 10;

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleSafeClose}
            showCloseButton={!form.processing}
            size="3xl"
            bodyClassName="p-4 sm:p-6"
            title={
                <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary shrink-0">
                        <Pencil className="h-4 w-4" />
                    </div>
                    <span>Edit Dasar Aturan</span>
                </div>
            }
        >
            <form onSubmit={submit} noValidate className="space-y-6">
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                {!recovery.recovery && <RegulasiFailureNotice message={recoveryMessage} />}

                {(form.errors as Record<string, string | undefined>).konflik && (
                    <div className="rounded-xl border border-danger/30 bg-danger/10 p-4 text-ink flex items-start gap-3" role="alert">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-danger mt-0.5" aria-hidden="true" />
                        <div>
                            <h3 className="text-sm font-bold text-danger">Konflik Pembaruan Data</h3>
                            <p className="mt-1 text-xs leading-relaxed text-ink">{(form.errors as Record<string, string | undefined>).konflik}</p>
                        </div>
                    </div>
                )}

                <RegulasiFormFields
                    data={form.data}
                    errors={form.errors as Record<string, string | undefined>}
                    disabled={form.processing}
                    isEdit={true}
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
                        disabled={form.processing || !isAlasanValid || Boolean(recovery.recovery) || recoveryUnknown}
                    >
                        <Save className="h-4 w-4" aria-hidden="true" />
                        Simpan Perubahan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
