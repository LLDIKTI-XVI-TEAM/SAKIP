import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import type { RencanaAksiSahkan } from './types';

export default function SahkanDialog({ rencanaAksi, onClose }: { rencanaAksi: RencanaAksiSahkan; onClose: () => void }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const [message, setMessage] = useState('');
    const recovery = useAuthRecovery();
    const { data, post, processing, errors } = useForm({ versi: rencanaAksi.versi });
    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        return () => element?.close();
    }, []);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (processing || recovery.recovery || message) return;
        setMessage('');
        post(`/rencana-aksi/${rencanaAksi.id}/sahkan`, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = page.props.rencanaAksi;
                if (updated && typeof updated === 'object' && 'status' in updated && updated.status === 'disahkan') {
                    onClose();
                } else {
                    setMessage('Hasil tindakan belum terkonfirmasi. Periksa status rencana aksi sebelum mencoba kembali.');
                }
            },
            onCancel: () => { setMessage('Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => {
                setMessage('Koneksi terputus. Hasil tindakan belum diketahui; periksa status rencana aksi sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => { if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/rencana-aksi/${rencanaAksi.id}/sahkan`, mutation: true })) return false;
                setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };
    return <dialog ref={dialog} aria-labelledby="ra-sahkan-title" aria-describedby="ra-sahkan-description" onCancel={(event) => { if (processing) event.preventDefault(); }} onClose={onClose} className="m-auto max-h-[calc(100dvh-2rem)] overflow-y-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border border-border bg-surface p-6 text-ink shadow-xl backdrop:bg-ink/50">
        <h2 id="ra-sahkan-title" className="text-lg font-semibold">Sahkan rencana aksi resmi</h2>
        <p id="ra-sahkan-description" className="mt-2 text-sm text-muted">Pengesahan menyimpan versi pengajuan beku sebagai dokumen resmi. Pengajuan ke-{rencanaAksi.nomor_pengajuan} · versi data {data.versi}.</p>
        <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={processing}>
            {Object.entries(errors).map(([field, error]) => <p key={field} role="alert" className="text-sm text-danger">{error}</p>)}
            <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />
            {message && !recovery.recovery && <p role="alert" className="text-sm text-danger">{message}</p>}
            <div className="flex flex-wrap justify-end gap-3">
                <Button type="button" variant="outline" disabled={processing} className="border-border bg-surface text-ink hover:bg-soft focus:ring-primary" onClick={onClose}>Batal</Button>
                <Button disabled={Boolean(recovery.recovery || message)} type="submit" isLoading={processing} className="bg-primary text-white hover:bg-primary/90 focus:ring-primary">Konfirmasi</Button>
            </div>
        </form>
    </dialog>;
}
