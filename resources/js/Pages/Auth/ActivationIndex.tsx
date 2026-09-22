import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/Components/Button';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton, secondaryButton } from './AuthShell';

interface PendingUser { id: string; nama: string; email: string; created_at: string }
interface ActivationProps {
    users: { data: PendingUser[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
    canActivate: boolean;
}

function ActivationDialog({ user, onClose }: { user: PendingUser; onClose: () => void }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const reason = useRef<HTMLTextAreaElement>(null);
    const [message, setMessage] = useState('');
    const recovery = useAuthRecovery();
    const { data, setData, post, processing, errors } = useForm({ alasan: '' });

    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        return () => element?.close();
    }, []);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (processing || recovery.recovery || message) return;
        setMessage('');
        post(`/akses/aktivasi/${user.id}`, {
            preserveScroll: true,
            onError: () => reason.current?.focus(),
            onSuccess: (page) => {
                const result = page.props.activationResult;
                if (result && typeof result === 'object' && 'user_id' in result && 'status' in result
                    && result.user_id === user.id && (result.status === 'activated' || result.status === 'already_active')) {
                    onClose();
                } else {
                    setMessage('Hasil aktivasi belum terkonfirmasi. Periksa status akun sebelum mencoba kembali.');
                }
            },
            onCancel: () => { setMessage('Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => {
                setMessage('Koneksi terputus. Hasil aktivasi belum diketahui; periksa status akun sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => { if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/akses/aktivasi/${user.id}`, mutation: true })) return false;
                setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };

    return (
        <dialog ref={dialog} aria-labelledby="activation-title" aria-describedby="activation-description" onCancel={(event) => {
            if (processing) event.preventDefault();
        }} onClose={onClose} className="m-auto max-h-[calc(100dvh-2rem)] overflow-y-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border border-border bg-surface p-6 text-ink shadow-xl backdrop:bg-ink/50">
            <h2 id="activation-title" className="text-lg font-semibold">Aktifkan akun</h2>
            <p id="activation-description" className="mt-2 break-words text-sm text-muted">Aktifkan {user.nama} ({user.email}). Peran dan izin akun tetap mengikuti pengaturan yang berlaku.</p>
            <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={processing}>
                <div>
                    <label htmlFor="activation-reason" className="block text-sm font-medium">Alasan aktivasi <span className="text-danger">*</span></label>
                    <textarea ref={reason} id="activation-reason" name="alasan" required autoFocus rows={4} value={data.alasan} onChange={(event) => setData('alasan', event.target.value)} disabled={processing} aria-invalid={Boolean(errors.alasan)} aria-describedby={errors.alasan ? 'activation-error' : 'activation-help'} className="mt-2 w-full rounded-lg border border-border bg-surface p-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50" />
                    {errors.alasan ? <p id="activation-error" role="alert" className="mt-1 text-sm text-danger">{errors.alasan}</p> : <p id="activation-help" className="mt-1 text-xs text-muted">Alasan dicatat dalam jejak audit.</p>}
                </div>
                <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />
                {message && !recovery.recovery && <p role="alert" className="text-sm text-danger">{message}</p>}
                <div className="flex flex-wrap justify-end gap-3">
                    <Button type="button" variant="outline" className={secondaryButton} disabled={processing} onClick={onClose}>Batal</Button>
                    <Button disabled={Boolean(recovery.recovery || message)} type="submit" className={primaryButton} isLoading={processing}>Konfirmasi aktivasi</Button>
                </div>
            </form>
        </dialog>
    );
}

export default function ActivationIndex({ users, canActivate }: ActivationProps) {
    const [selected, setSelected] = useState<PendingUser | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    const close = () => {
        setSelected(null);
        if (trigger.current?.isConnected) trigger.current.focus();
        else title.current?.focus();
    };

    return (
        <AuthenticatedLayout title="Aktivasi pengguna">
            <Head title="Aktivasi pengguna" />
            <section className="rounded-xl border border-border bg-surface p-4 sm:p-6">
                <h1 ref={title} tabIndex={-1} className="text-lg font-semibold text-ink">Akun menunggu aktivasi</h1>
                <p className="mb-6 mt-2 text-sm text-muted">Tinjau identitas pengguna sebelum mengaktifkan akses SAKIP.</p>
                {users.data.length === 0 ? <p className="rounded-lg bg-soft p-4 text-sm text-muted">Tidak ada akun yang menunggu aktivasi.</p> : (
                    <ul className="divide-y divide-border">
                        {users.data.map((user) => (
                            <li key={user.id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0"><h2 className="break-words text-sm font-semibold text-ink">{user.nama}</h2><p className="break-all text-sm text-muted">{user.email}</p></div>
                                {canActivate && <Button type="button" className={`${primaryButton} self-start sm:self-auto`} aria-label={`Aktifkan ${user.nama}`} onClick={(event) => {
                                    trigger.current = event.currentTarget;
                                    setSelected(user);
                                }}>Aktifkan</Button>}
                            </li>
                        ))}
                    </ul>
                )}
                {users.last_page > 1 && <nav aria-label="Halaman pengguna" className="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
                    {users.prev_page_url ? <Link href={users.prev_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Sebelumnya</Link> : <span className="text-muted">Sebelumnya</span>}
                    <span>Halaman {users.current_page} dari {users.last_page}</span>
                    {users.next_page_url ? <Link href={users.next_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Berikutnya</Link> : <span className="text-muted">Berikutnya</span>}
                </nav>}
            </section>
            {selected && <ActivationDialog key={selected.id} user={selected} onClose={close} />}
        </AuthenticatedLayout>
    );
}
