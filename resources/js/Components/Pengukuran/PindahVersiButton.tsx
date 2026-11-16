import { useId, useState, type ComponentProps } from 'react';
import { useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';

interface PindahVersiButtonProps {
    pengukuranId: string;
    versi: number;
    versiBerlaku: { id: string; nomor: number };
    labelAksesibel: string;
    size?: ComponentProps<typeof Button>['size'];
    disabledReason?: string;
}

/**
 * Server menentukan versi tujuan; token tujuan mendeteksi perubahan sejak halaman dibuka.
 * Kirim ulang setelah hasil belum pasti aman karena token header mencegah pemindahan ganda.
 */
export function PindahVersiButton({ pengukuranId, versi, versiBerlaku, labelAksesibel, size, disabledReason }: PindahVersiButtonProps) {
    const disabledReasonId = useId();
    const [open, setOpen] = useState(false);
    const [notice, setNotice] = useState('');
    const recovery = useAuthRecovery();
    const dataAwal = { versi, expected_snapshot_id: versiBerlaku.id, expected_snapshot_versi: versiBerlaku.nomor, alasan: '' };
    const form = useForm(dataAwal);
    const serverErrors = Object.entries(form.errors).filter(([field]) => field !== 'alasan');
    const path = `/pengukuran/${pengukuranId}/pindah-snapshot`;
    const belumPasti = 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.';
    const buka = () => {
        form.setData(dataAwal);
        form.clearErrors();
        setNotice('');
        setOpen(true);
    };
    const confirm = () => {
        if (form.processing || recovery.recovery) return;
        setNotice('');
        form.post(path, {
            preserveScroll: true,
            preserveState: (page) => !page.flash.success,
            onSuccess: (page) => { if (page.flash.success) setOpen(false); else setNotice(belumPasti); },
            onCancel: () => setNotice(belumPasti),
            onNetworkError: () => { setNotice(belumPasti); return false; },
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) {
                    setNotice(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : belumPasti);
                }
                return false;
            },
        });
    };

    return <div className="flex flex-wrap items-center gap-2">
        <Button type="button" size={size} variant="outline" aria-label={labelAksesibel} aria-describedby={disabledReason ? disabledReasonId : undefined} disabled={Boolean(disabledReason)} onClick={buka}>Pindahkan</Button>
        {disabledReason && <span id={disabledReasonId} className="text-xs text-muted">{disabledReason}</span>}
        <AuditReasonModal open={open} title={`Pindahkan ke versi ${form.data.expected_snapshot_versi}?`}
            description={`Nilai yang tidak sesuai dengan versi ${form.data.expected_snapshot_versi} akan dikosongkan.`}
            reason={form.data.alasan} reasonRequired={false} error={form.errors.alasan} busy={form.processing}
            submitDisabled={Boolean(recovery.recovery)} confirmLabel="Pindahkan"
            onReasonChange={(value) => form.setData('alasan', value)} onClose={() => !form.processing && setOpen(false)} onConfirm={confirm}
            notice={<>
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                {(notice || serverErrors.length > 0) && <div role="alert" className="mb-3 space-y-1 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                    {notice && <p>{notice}</p>}
                    {serverErrors.map(([field, message]) => <p key={field}>{message}</p>)}
                </div>}
            </>}
        />
    </div>;
}

