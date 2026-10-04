import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useState, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import { ConfirmModal } from '@/Components/ConfirmModal';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

export type ReviewDecision = 'verifikasi' | 'sahkan' | 'kembalikan';
const decisions = {
    verifikasi: { title: 'Verifikasi pengukuran', description: 'Pastikan data pengajuan sudah diperiksa. Verifikasi akan dicatat dalam jejak audit.', status: 'diverifikasi' },
    sahkan: { title: 'Sahkan kinerja resmi', description: 'Pengesahan menyimpan snapshot resmi pengukuran. Pastikan pengajuan telah diverifikasi.', status: 'disahkan' },
    kembalikan: { title: 'Kembalikan untuk revisi', description: 'Tuliskan koreksi yang harus ditindaklanjuti sebelum pengajuan berikutnya.', status: 'dikembalikan' },
};

export default function DecisionDialog({ pengukuran, decision, onClose }: { pengukuran: Pengukuran; decision: ReviewDecision; onClose: () => void }) {
    const [message, setMessage] = useState('');
    const reasonRef = useRef<HTMLTextAreaElement>(null);
    const recovery = useAuthRecovery();
    const { data, setData, transform, post, processing, errors } = useForm({ versi: pengukuran.versi, catatan: '' });
    const action = decisions[decision];

    const submit = () => {
        if (processing || recovery.recovery || message) return;
        setMessage('');
        transform((values) => decision === 'kembalikan' ? values : { versi: values.versi });
        post(`/verifikasi/${pengukuran.id}/${decision}`, {
            preserveScroll: true,
            onError: () => reasonRef.current?.focus(),
            onSuccess: (page) => {
                const updated = page.props.pengukuran;
                if (page.component === 'Verifikasi/Index' || (updated && typeof updated === 'object'
                    && 'id' in updated && updated.id === pengukuran.id && 'status' in updated && updated.status === action.status
                    && 'versi' in updated && typeof updated.versi === 'number' && updated.versi > data.versi)) {
                    onClose();
                } else {
                    setMessage('Hasil tindakan belum terkonfirmasi. Periksa status pengukuran sebelum mencoba kembali.');
                }
            },
            onCancel: () => { setMessage('Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => {
                setMessage('Koneksi terputus. Hasil tindakan belum diketahui; periksa status pengukuran sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/verifikasi/${pengukuran.id}/${decision}`, mutation: true })) return false;
                setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };

    if (decision === 'kembalikan') {
        return (
            <AuditReasonModal
                open={true}
                title={action.title}
                description={action.description}
                reason={data.catatan}
                reasonLabel="Catatan perbaikan"
                reasonPlaceholder="Tuliskan koreksi atau catatan perbaikan yang wajib ditindaklanjuti…"
                reasonDescription={
                    <p className="mt-2 text-xs leading-5 text-muted">
                        Catatan revisi akan dikirim ke PIC unit dan tersimpan dalam jejak audit.
                    </p>
                }
                reasonRef={reasonRef}
                error={errors.catatan}
                busy={processing}
                destructive={true}
                confirmLabel="Konfirmasi"
                notice={
                    <>
                        <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />
                        {message && !recovery.recovery && <p role="alert" className="mb-3 text-sm text-danger">{message}</p>}
                    </>
                }
                onReasonChange={(val) => setData('catatan', val)}
                onClose={onClose}
                onConfirm={submit}
            />
        );
    }

    return (
        <ConfirmModal
            isOpen={true}
            title={action.title}
            description={action.description}
            confirmLabel="Konfirmasi"
            cancelLabel="Batal"
            variant="primary"
            isLoading={processing}
            disabled={Boolean(recovery.recovery || message)}
            notice={
                <>
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />
                    {message && !recovery.recovery && <p role="alert" className="mb-3 text-sm text-danger">{message}</p>}
                </>
            }
            onClose={onClose}
            onConfirm={submit}
        />
    );
}
