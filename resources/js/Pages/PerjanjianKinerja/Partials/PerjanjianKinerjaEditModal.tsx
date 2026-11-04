import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Input } from '@/Components/Input';
import { PerjanjianKinerjaFormFields } from '@/Components/PerjanjianKinerjaFormFields';
import { PkLockNotice } from './StatusJadwal';
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
    isJadwalTerkunci?: boolean;
    jadwalStatus?: string | null;
    alasanWajib?: boolean;
    storageSettings?: StorageSettings;
    canUploadBerkas?: boolean;
}

export function PerjanjianKinerjaEditModal({
    isOpen,
    onClose,
    pk,
    isJadwalAktif = false,
    isJadwalTerkunci,
    jadwalStatus,
    alasanWajib = false,
    storageSettings,
    canUploadBerkas = true,
}: PerjanjianKinerjaEditModalProps) {
    const isTerkunci = isJadwalTerkunci ?? isJadwalAktif;
    const [confirmationOpen, setConfirmationOpen] = React.useState(false);
    const submitInFlight = React.useRef(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<PerjanjianKinerjaFormData>({
        renstra_id: pk?.renstra_id ?? '',
        tahun: pk?.tahun ?? new Date().getFullYear(),
        nomor_pk: pk?.nomor_pk ?? '',
        tanggal_pk: pk?.tanggal_pk ? pk.tanggal_pk.split('T')[0] : '',
        expected_updated_at: pk?.updated_at ?? '',
        alasan: '',
        rujukan: '',
        lampiran: [],
        _method: 'put',
    });

    const prevIsOpenRef = React.useRef(false);
    const lastLoadedPkIdRef = React.useRef<string | null>(null);

    useEffect(() => {
        if (!isOpen) {
            prevIsOpenRef.current = false;
            setConfirmationOpen(false);
            return;
        }

        const justOpened = !prevIsOpenRef.current && isOpen;
        const switchedPk = pk !== null && pk.id !== lastLoadedPkIdRef.current;

        if (pk && (justOpened || switchedPk)) {
            setData({
                renstra_id: pk.renstra_id,
                tahun: pk.tahun,
                nomor_pk: pk.nomor_pk ?? '',
                tanggal_pk: pk.tanggal_pk ? pk.tanggal_pk.split('T')[0] : '',
                expected_updated_at: pk.updated_at ?? '',
                alasan: '',
                rujukan: '',
                lampiran: [],
                _method: 'put',
            });
            clearErrors();
            setConfirmationOpen(false);
            lastLoadedPkIdRef.current = pk.id;
        }

        prevIsOpenRef.current = isOpen;
    }, [pk, isOpen, setData, clearErrors]);

    const setField = <K extends keyof PerjanjianKinerjaFormData>(
        field: K,
        value: PerjanjianKinerjaFormData[K]
    ) => {
        setData((prev) => ({ ...prev, [field]: value }));
    };

    const handleClose = () => {
        if (processing || submitInFlight.current) return;
        clearErrors();
        reset();
        onClose();
    };

    const save = () => {
        if (!pk || processing || submitInFlight.current) return;
        submitInFlight.current = true;

        post(`/perjanjian-kinerja/${pk.id}`, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setConfirmationOpen(false);
                onClose();
            },
            onFinish: () => { submitInFlight.current = false; },
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (processing || submitInFlight.current) return;
        if (alasanWajib) setConfirmationOpen(true);
        else save();
    };

    if (!pk) return null;

    // Ini hanya petunjuk pengisian; server membandingkan ulang metadata pada baris PK yang terkunci.
    const rujukanWajib = data.nomor_pk.trim() !== (pk.nomor_pk ?? '').trim()
        || data.tanggal_pk !== (pk.tanggal_pk?.split('T')[0] ?? '');
    const confirmationErrors = Object.entries(errors).filter(([field]) => !['alasan', 'rujukan'].includes(field));

    // Satu dialog aktif menjaga fokus; data dan File tetap dimiliki useForm saat editor dilepas sementara.
    return (
        <>
            <Modal
                isOpen={isOpen && !confirmationOpen}
                onClose={handleClose}
                showCloseButton={!processing}
                autoFocus={false}
                size="3xl"
                hideScrollbar={true}
                bodyClassName="p-4 sm:p-6"
                title="Edit Perjanjian Kinerja"
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
                            isLoading={processing}
                        >
                            Perbarui Perjanjian Kinerja
                        </Button>
                    </div>
                }
            >
                <form id="edit-pk-modal-form" onSubmit={handleSubmit}>
                    {errors.konflik && (
                        <div className="mb-6 rounded-xl border border-danger/30 bg-danger/10 p-4 text-ink flex items-start gap-3" role="alert">
                            <AlertTriangle className="h-5 w-5 shrink-0 text-danger mt-0.5" aria-hidden="true" />
                            <div>
                                <h3 className="text-sm font-bold text-danger">Konflik Pembaruan Data</h3>
                                <p className="mt-1 text-xs leading-relaxed text-ink">{errors.konflik}</p>
                            </div>
                        </div>
                    )}

                    {isTerkunci && <div className="mb-6"><PkLockNotice status={jadwalStatus} /></div>}

                    <PerjanjianKinerjaFormFields
                        data={data}
                        errors={errors as Record<string, string | undefined>}
                        renstras={[pk.renstra]}
                        storageSettings={storageSettings}
                        isEdit={true}
                        disabled={processing}
                        alasanWajib={alasanWajib}
                        canUploadBerkas={canUploadBerkas}
                        setField={setField}
                    />
                </form>
            </Modal>
            <AuditReasonModal
                open={isOpen && confirmationOpen}
                title="Konfirmasi perubahan Perjanjian Kinerja"
                description="Isi dasar perubahan sebelum menyimpan. Batal mengembalikan Anda ke formulir tanpa membuang isian."
                reason={data.alasan ?? ''}
                error={errors.alasan}
                busy={processing}
                submitDisabled={!data.alasan?.trim() || (rujukanWajib && !data.rujukan?.trim())}
                confirmLabel="Simpan perubahan"
                onReasonChange={(value) => setField('alasan', value)}
                onClose={() => { if (!processing && !submitInFlight.current) setConfirmationOpen(false); }}
                onConfirm={save}
                notice={
                    <div className="mb-4 space-y-4">
                        {confirmationErrors.length > 0 && (
                            <div role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-ink">
                                <h2 tabIndex={-1} className="mb-1 font-semibold">Perubahan belum tersimpan</h2>
                                {confirmationErrors.map(([field, message]) => <p key={field} className="break-words">{message}</p>)}
                            </div>
                        )}
                        <Input
                            name="rujukan"
                            label={`Rujukan dokumen resmi (mis. nomor surat PK)${rujukanWajib ? '' : ' (opsional)'}`}
                            required={rujukanWajib}
                            value={data.rujukan ?? ''}
                            onChange={(event) => setField('rujukan', event.target.value)}
                            error={errors.rujukan}
                            helperText={rujukanWajib
                                ? 'Rujukan wajib karena nomor atau tanggal PK dikoreksi.'
                                : 'Nomor dan tanggal PK tidak berubah; rujukan opsional.'}
                            maxLength={255}
                            disabled={processing}
                        />
                    </div>
                }
            />
        </>
    );
}
