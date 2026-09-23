import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

export type ReviewDecision = 'verifikasi' | 'sahkan' | 'kembalikan';
const decisions = {
    verifikasi: { title: 'Verifikasi pengukuran', description: 'Pastikan data pengajuan sudah diperiksa. Verifikasi akan dicatat dalam jejak audit.', status: 'diverifikasi' },
    sahkan: { title: 'Sahkan kinerja resmi', description: 'Pengesahan menyimpan snapshot resmi pengukuran. Pastikan pengajuan telah diverifikasi.', status: 'disahkan' },
    kembalikan: { title: 'Kembalikan untuk revisi', description: 'Tuliskan koreksi yang harus ditindaklanjuti sebelum pengajuan berikutnya.', status: 'dikembalikan' },
};

export default function DecisionDialog({ pengukuran, decision, onClose }: { pengukuran: Pengukuran; decision: ReviewDecision; onClose: () => void }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const reason = useRef<HTMLTextAreaElement>(null);
    const [message, setMessage] = useState('');
    const recovery = useAuthRecovery();
    const { data, setData, transform, post, processing, errors } = useForm({ versi: pengukuran.versi, catatan: '' });
    const action = decisions[decision];
    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        return () => element?.close();
    }, []);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (processing || recovery.recovery || message) return;
        setMessage('');
        transform((values) => decision === 'kembalikan' ? values : { versi: values.versi });
        post(`/verifikasi/${pengukuran.id}/${decision}`, {
            preserveScroll: true,
            onError: () => reason.current?.focus(),
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
            onHttpException: (response) => { if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/verifikasi/${pengukuran.id}/${decision}`, mutation: true })) return false;
                setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };
    return <dialog ref={dialog} aria-labelledby="decision-title" aria-describedby="decision-description" onCancel={(event) => { if (processing) event.preventDefault(); }} onClose={onClose} className="m-auto max-h-[calc(100dvh-2rem)] overflow-y-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border border-border bg-surface p-6 text-ink shadow-xl backdrop:bg-ink/50">
        <h2 id="decision-title" className="text-lg font-semibold">{action.title}</h2>
        <p id="decision-description" className="mt-2 text-sm text-muted">{action.description}</p>
        <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={processing}>
            {decision === 'kembalikan' && <div>
                <label htmlFor="review-reason" className="block text-sm font-medium">Catatan perbaikan <span className="text-danger">*</span></label>
                <textarea ref={reason} id="review-reason" name="catatan" required autoFocus rows={4} value={data.catatan} onChange={(event) => setData('catatan', event.target.value)} disabled={processing} aria-invalid={Boolean(errors.catatan)} aria-describedby={errors.catatan ? 'review-reason-error' : undefined} className="mt-2 w-full rounded-lg border border-border bg-surface p-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50" />
                {errors.catatan && <p id="review-reason-error" role="alert" className="mt-1 text-sm text-danger">{errors.catatan}</p>}
            </div>}
            {Object.entries(errors).filter(([field]) => field !== 'catatan').map(([field, error]) => <p key={field} role="alert" className="text-sm text-danger">{error}</p>)}
            <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />
            {message && !recovery.recovery && <p role="alert" className="text-sm text-danger">{message}</p>}
            <div className="flex flex-wrap justify-end gap-3">
                <Button type="button" variant="outline" disabled={processing} className="border-border bg-surface text-ink hover:bg-soft focus:ring-primary" onClick={onClose}>Batal</Button>
                <Button disabled={Boolean(recovery.recovery || message)} type="submit" isLoading={processing} className="bg-primary text-white hover:bg-primary/90 focus:ring-primary">Konfirmasi</Button>
            </div>
        </form>
    </dialog>;
}
