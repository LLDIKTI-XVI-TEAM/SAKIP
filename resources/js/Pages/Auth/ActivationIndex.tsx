import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton, secondaryButton } from './AuthShell';
import { UserCheck, Clock, ShieldCheck, Mail, Calendar } from 'lucide-react';

interface PendingUser { id: string; nama: string; email: string; created_at: string }
interface ActivationProps {
    users: { data: PendingUser[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
    canActivate: boolean;
}

function formatDate(dateStr: string): string {
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
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
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/akses/aktivasi/${user.id}`, mutation: true })) return false;
                setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };

    return (
        <dialog
            ref={dialog}
            aria-labelledby="activation-title"
            aria-describedby="activation-description"
            onCancel={(event) => {
                if (processing) event.preventDefault();
            }}
            onClose={onClose}
            className="m-auto max-h-[calc(100dvh-2rem)] overflow-y-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-border bg-surface p-6 text-ink shadow-2xl backdrop:bg-ink/50"
        >
            <div className="flex items-start gap-3.5">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <UserCheck className="h-5 w-5" aria-hidden="true" />
                </div>
                <div className="min-w-0 flex-1">
                    <h2 id="activation-title" className="text-base font-bold text-ink">Aktifkan akun</h2>
                    <p id="activation-description" className="mt-1 break-words text-xs text-muted leading-relaxed">
                        Aktifkan {user.nama} ({user.email}). Peran dan izin akun tetap mengikuti pengaturan yang berlaku.
                    </p>
                </div>
            </div>

            <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={processing}>
                <div>
                    <label htmlFor="activation-reason" className="block text-xs font-semibold text-ink">
                        Alasan aktivasi <span className="text-danger">*</span>
                    </label>
                    <textarea
                        ref={reason}
                        id="activation-reason"
                        name="alasan"
                        required
                        autoFocus
                        rows={4}
                        value={data.alasan}
                        onChange={(event) => setData('alasan', event.target.value)}
                        disabled={processing}
                        aria-invalid={Boolean(errors.alasan)}
                        aria-describedby={errors.alasan ? 'activation-error' : 'activation-help'}
                        placeholder="Contoh: Akun telah diverifikasi untuk penugasan pada unit kerja..."
                        className="mt-2 w-full rounded-xl border border-border bg-page p-3 text-xs text-ink placeholder:text-muted/70 focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50"
                    />
                    {errors.alasan ? (
                        <p id="activation-error" role="alert" className="mt-1.5 text-xs text-danger font-medium">
                            {errors.alasan}
                        </p>
                    ) : (
                        <p id="activation-help" className="mt-1.5 text-[11px] text-muted">
                            Alasan dicatat dalam jejak audit.
                        </p>
                    )}
                </div>

                <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />
                {message && !recovery.recovery && (
                    <div className="rounded-xl border border-danger/30 bg-danger/10 p-3">
                        <p role="alert" className="text-xs text-danger font-medium">{message}</p>
                    </div>
                )}

                <div className="flex flex-wrap justify-end gap-2.5 pt-2 border-t border-border">
                    <Button type="button" variant="outline" className={secondaryButton} disabled={processing} onClick={onClose}>
                        Batal
                    </Button>
                    <Button disabled={Boolean(recovery.recovery || message)} type="submit" className={primaryButton} isLoading={processing}>
                        Konfirmasi aktivasi
                    </Button>
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

            <div className="space-y-6">
                {/* Header Information Banner */}
                <div className="rounded-2xl border border-border bg-surface p-5 sm:p-6 shadow-2xs">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div className="max-w-2xl">
                            <h2 ref={title} tabIndex={-1} className="text-base font-bold text-ink leading-tight outline-none">
                                Akun menunggu aktivasi
                            </h2>
                            <p className="mt-1 text-xs text-muted leading-relaxed">
                                Tinjau identitas pengguna sebelum mengaktifkan akses SAKIP. Akun yang diaktifkan akan dapat masuk menggunakan kredensial SSO yang sah.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
                            <Badge variant="warning" size="md">
                                <Clock className="h-3 w-3 mr-1 inline-block" aria-hidden="true" />
                                {users.data.length} Menunggu
                            </Badge>
                        </div>
                    </div>
                </div>

                {/* List Container */}
                <div className="rounded-2xl border border-border bg-surface p-5 sm:p-6 shadow-2xs">
                    {users.data.length === 0 ? (
                        <div className="rounded-xl border border-border/60 bg-soft py-12 px-4 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-surface text-muted shadow-2xs">
                                <ShieldCheck className="h-6 w-6 text-success" aria-hidden="true" />
                            </div>
                            <p className="mt-3 text-sm font-semibold text-ink">
                                Tidak ada akun yang menunggu aktivasi.
                            </p>
                            <p className="mt-1 text-xs text-muted max-w-sm mx-auto">
                                Semua akun pengguna yang terdaftar telah diproses atau belum ada permohonan baru.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-border">
                            {users.data.map((user) => (
                                <div
                                    key={user.id}
                                    className="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between transition-colors first:pt-0 last:pb-0"
                                >
                                    <div className="flex items-start gap-3.5 min-w-0">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-soft text-primary font-bold text-sm border border-border">
                                            {user.nama.charAt(0).toUpperCase()}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="break-words text-sm font-bold text-ink">
                                                    {user.nama}
                                                </h3>
                                                <Badge variant="warning" size="sm">
                                                    Menunggu aktivasi
                                                </Badge>
                                            </div>
                                            <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                                                <span className="flex items-center gap-1.5 break-all">
                                                    <Mail className="h-3.5 w-3.5 text-muted/70 shrink-0" aria-hidden="true" />
                                                    {user.email}
                                                </span>
                                                {user.created_at && (
                                                    <span className="flex items-center gap-1.5 shrink-0">
                                                        <Calendar className="h-3.5 w-3.5 text-muted/70 shrink-0" aria-hidden="true" />
                                                        Terdaftar: {formatDate(user.created_at)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {canActivate && (
                                        <Button
                                            type="button"
                                            className={`${primaryButton} self-start sm:self-auto shrink-0`}
                                            aria-label={`Aktifkan ${user.nama}`}
                                            onClick={(event) => {
                                                trigger.current = event.currentTarget;
                                                setSelected(user);
                                            }}
                                        >
                                            <UserCheck className="h-4 w-4 mr-1.5" aria-hidden="true" />
                                            Aktifkan
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {users.last_page > 1 && (
                        <nav aria-label="Halaman pengguna" className="mt-6 pt-4 border-t border-border flex flex-wrap items-center justify-between gap-3 text-xs">
                            {users.prev_page_url ? (
                                <Link href={users.prev_page_url} className="rounded font-medium text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary">
                                    Sebelumnya
                                </Link>
                            ) : (
                                <span className="text-muted/60">Sebelumnya</span>
                            )}
                            <span className="text-muted font-medium">
                                Halaman {users.current_page} dari {users.last_page}
                            </span>
                            {users.next_page_url ? (
                                <Link href={users.next_page_url} className="rounded font-medium text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary">
                                    Berikutnya
                                </Link>
                            ) : (
                                <span className="text-muted/60">Berikutnya</span>
                            )}
                        </nav>
                    )}
                </div>
            </div>

            {selected && <ActivationDialog key={selected.id} user={selected} onClose={close} />}
        </AuthenticatedLayout>
    );
}
