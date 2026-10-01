import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle, Search, UserCog, X } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/Components/Button';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton, secondaryButton } from '@/Pages/Auth/AuthShell';
import type { SharedPageProps } from '@/types/auth';

type AssignmentState = { id: string; role_id: string; audit_id: string | null };
type RoleOption = { id: string; kode: string; nama: string };
type RoleUser = { id: string; nama: string; email: string; status: 'aktif' | 'nonaktif'; current_role: (RoleOption & { aktif: boolean }) | null; assignment: AssignmentState | null };
interface RoleAssignmentProps {
    users: { data: RoleUser[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
    roles: RoleOption[];
    filters: { q: string };
    can: { assignRole: boolean };
}
const fieldClass = 'mt-1.5 w-full rounded-lg border border-border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted/60 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 transition-colors';
const explanation = 'Perubahan peran tidak otomatis mengubah grant izin atau penugasan.';

function RoleDialog({ user, roles, isSelf, onClose, onSaved }: { user: RoleUser; roles: RoleOption[]; isSelf: boolean; onClose: () => void; onSaved: () => void }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const roleInput = useRef<HTMLSelectElement>(null);
    const reasonInput = useRef<HTMLTextAreaElement>(null);
    const alert = useRef<HTMLParagraphElement>(null);
    const [message, setMessage] = useState('');
    const recovery = useAuthRecovery();
    const form = useForm<{ role_id: string; alasan: string; expected_assignment: AssignmentState | null }>({
        role_id: roles.some((role) => role.id === user.current_role?.id) ? user.current_role!.id : '',
        alasan: '', expected_assignment: user.assignment,
    });
    const conflict = form.errors.expected_assignment;
    const needsReload = Boolean(conflict || message);

    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        return () => element?.close();
    }, []);

    useEffect(() => {
        // Tunggu field kembali enabled setelah respons Inertia sebelum memindahkan fokus.
        if (form.processing) return;
        if (needsReload) alert.current?.focus();
        else if (form.errors.role_id) roleInput.current?.focus();
        else if (form.errors.alasan) reasonInput.current?.focus();
    }, [form.processing, form.errors, needsReload, message]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || recovery.recovery || needsReload) return;
        form.post(`/akses/peran/${user.id}`, {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.component === 'Access/RoleAssignmentIndex' && ['assigned', 'changed', 'unchanged'].includes(String(page.flash.roleAssignmentStatus))) {
                    onSaved();
                } else if (page.component !== 'Access/RoleAssignmentResult' || !['assigned', 'changed', 'unchanged'].includes(String(page.props.status))) {
                    setMessage('Hasil penetapan peran belum diketahui. Muat ulang data sebelum mencoba kembali.');
                }
            },
            onCancel: () => { setMessage('Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => {
                setMessage('Koneksi terputus. Hasil penetapan peran belum diketahui. Muat ulang data sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/akses/peran/${user.id}`, mutation: true })) return false;
                setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };

    return <dialog ref={dialog} aria-labelledby="role-title" aria-describedby={isSelf ? 'role-description self-role-warning' : 'role-description'} onCancel={(event) => {
        if (form.processing) event.preventDefault();
    }} onClose={onClose} className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-border bg-surface p-6 text-ink shadow-2xl backdrop:bg-ink/50">
        <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <UserCog className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
                <h2 id="role-title" className="text-lg font-semibold text-ink">{user.current_role ? 'Ubah peran' : 'Tetapkan peran'}</h2>
                <p id="role-description" className="mt-1 break-words text-xs text-muted">
                    {user.nama} ({user.email}). Peran saat ini: {user.current_role?.nama ?? 'Belum ditetapkan'}{user.current_role && !user.current_role.aktif ? ' (nonaktif)' : ''}.
                </p>
            </div>
        </div>

        <p className="mt-3.5 rounded-lg border border-border/60 bg-soft/60 p-2.5 text-xs text-muted leading-relaxed">
            {explanation}
        </p>

        {isSelf && <div id="self-role-warning" className="mt-4 rounded-xl border border-warning/40 bg-warning/10 p-3.5 text-xs text-ink leading-relaxed">
            <p className="font-semibold text-warning-dark">Anda sedang mengubah peran akun sendiri.</p>
            <p className="mt-1 text-muted">Menu dan akses pengelolaan dapat hilang setelah disimpan. Anda mungkin tidak dapat mengembalikannya sendiri. Pastikan peran tujuan sudah benar.</p>
        </div>}

        <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={form.processing}>
            <div>
                <label htmlFor="role-choice" className="block text-xs font-semibold uppercase tracking-wider text-muted">Peran tujuan <span className="text-danger">*</span></label>
                <select ref={roleInput} id="role-choice" autoFocus required value={form.data.role_id} onChange={(event) => form.setData('role_id', event.target.value)} disabled={form.processing} aria-invalid={Boolean(form.errors.role_id)} aria-describedby={form.errors.role_id ? 'role-error' : undefined} className={fieldClass}>
                    <option value="" disabled>Pilih peran</option>
                    {roles.map((role) => <option key={role.id} value={role.id}>{role.nama}</option>)}
                </select>
                {form.errors.role_id && <p id="role-error" role="alert" className="mt-1.5 text-xs font-medium text-danger">{form.errors.role_id}</p>}
            </div>
            <div>
                <label htmlFor="role-reason" className="block text-xs font-semibold uppercase tracking-wider text-muted">Alasan penetapan <span className="text-danger">*</span></label>
                <textarea ref={reasonInput} id="role-reason" required maxLength={2000} rows={3} value={form.data.alasan} onChange={(event) => form.setData('alasan', event.target.value)} disabled={form.processing} aria-invalid={Boolean(form.errors.alasan)} aria-describedby={form.errors.alasan ? 'reason-error' : 'reason-help'} className={fieldClass} />
                {form.errors.alasan ? <p id="reason-error" role="alert" className="mt-1.5 text-xs font-medium text-danger">{form.errors.alasan}</p> : <p id="reason-help" className="mt-1.5 text-xs text-muted">Wajib diisi, maksimal 2.000 karakter. Dicatat dalam jejak audit.</p>}
            </div>
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {needsReload && !recovery.recovery && <div className="space-y-2 rounded-xl bg-danger/10 border border-danger/20 p-3.5">
                <p ref={alert} tabIndex={-1} role="alert" className="text-xs font-medium text-danger">{conflict || message}</p>
                <Button type="button" variant="outline" className={secondaryButton} disabled={form.processing} onClick={() => router.get('/akses/peran', {}, { replace: true, preserveState: false })}>Muat ulang data</Button>
            </div>}
            <div className="flex flex-wrap justify-end gap-2.5 pt-2">
                <Button type="button" variant="outline" className={secondaryButton} disabled={form.processing} onClick={onClose}>Batal</Button>
                <Button type="submit" className={primaryButton} isLoading={form.processing} disabled={Boolean(recovery.recovery) || needsReload || roles.length === 0}>Simpan peran</Button>
            </div>
        </form>
    </dialog>;
}

export default function RoleAssignmentIndex({ users, roles, filters, can }: RoleAssignmentProps) {
    const { props: { auth }, flash } = usePage<SharedPageProps>();
    const [dismissedFlash, setDismissedFlash] = useState<typeof flash | null>(null);
    const outcome = flash.roleAssignmentStatus;
    const successMessage = outcome === 'assigned' ? 'Peran berhasil ditetapkan.' : outcome === 'changed' ? 'Peran berhasil diubah.' : outcome === 'unchanged' ? 'Peran tidak berubah.' : null;
    const [selected, setSelected] = useState<RoleUser | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    const search = useForm({ q: filters.q });
    const close = () => setSelected(null);

    useEffect(() => {
        // Fokus daftar baru dapat dipulihkan setelah dialog modal tidak lagi membuatnya inert.
        if (selected || !trigger.current) return;
        if (trigger.current?.isConnected) trigger.current.focus();
        else title.current?.focus();
    }, [selected]);

    return <AuthenticatedLayout title="Penetapan Peran">
        <Head title="Penetapan Peran" />
        {successMessage && dismissedFlash !== flash && <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-xl">
            <CheckCircle aria-hidden="true" className="h-5 w-5 shrink-0 text-success" />
            <p role="status" className="flex-1 text-sm font-medium text-ink">{successMessage}</p>
            <button type="button" aria-label="Tutup notifikasi" onClick={() => setDismissedFlash(flash)} className="-m-1 rounded-lg p-2 text-muted hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary"><X aria-hidden="true" className="h-4 w-4" /></button>
        </div>}

        <div className="space-y-6">
            <div className="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 ref={title} tabIndex={-1} className="text-base font-semibold text-ink">Peran utama pengguna</h2>
                        <p className="mt-1 text-xs text-muted">Setiap pengguna memiliki satu peran utama. {explanation}</p>
                    </div>
                </div>

                <form onSubmit={(event) => { event.preventDefault(); if (!search.processing) search.get('/akses/peran', { preserveState: false }); }} className="mt-5 flex flex-wrap items-end gap-3" role="search">
                    <div className="min-w-0 flex-1">
                        <label htmlFor="user-search" className="block text-xs font-semibold uppercase tracking-wider text-muted">Cari nama atau email</label>
                        <div className="relative mt-1.5">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                            <input id="user-search" type="search" maxLength={100} value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} placeholder="Ketik nama atau alamat email..." className="w-full rounded-lg border border-border bg-surface pl-10 pr-3.5 py-2.5 text-sm text-ink placeholder:text-muted/60 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors" />
                        </div>
                    </div>
                    <Button type="submit" className={secondaryButton} isLoading={search.processing}>Cari</Button>
                    {filters.q && <Link href="/akses/peran" className="inline-flex items-center justify-center rounded-lg border border-border px-3.5 py-2.5 text-sm font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors">Reset</Link>}
                </form>
            </div>

            <section className="rounded-xl border border-border bg-surface overflow-hidden shadow-xs">
                {users.data.length === 0 ? (
                    <div className="p-8 text-center text-sm text-muted">
                        <p className="font-medium text-ink">Tidak ada pengguna yang sesuai.</p>
                        <p className="mt-1 text-xs text-muted">Coba gunakan kata kunci pencarian yang lain atau reset filter pencarian.</p>
                    </div>
                ) : (
                    <div className="divide-y divide-border">
                        {users.data.map((user) => {
                            const initials = user.nama ? user.nama.slice(0, 2).toUpperCase() : 'U';
                            return (
                                <div key={user.id} className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5 hover:bg-soft/40 transition-colors">
                                    <div className="flex items-start gap-3.5 min-w-0">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold tracking-wider text-primary border border-primary/20" aria-hidden="true">
                                            {initials}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="break-words text-sm font-semibold text-ink">{user.nama}</h3>
                                                <Badge variant={user.status === 'aktif' ? 'success' : 'muted'} size="sm" dot>
                                                    {user.status === 'aktif' ? 'Akun aktif' : 'Menunggu aktivasi'}
                                                </Badge>
                                            </div>
                                            <p className="break-all text-xs text-muted mt-0.5">{user.email}</p>
                                            <div className="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                                                <span className="text-muted">Peran:</span>
                                                {user.current_role ? (
                                                    <Badge variant={user.current_role.aktif ? 'primary' : 'warning'} size="sm">
                                                        {user.current_role.nama}{!user.current_role.aktif ? ' (nonaktif)' : ''}
                                                    </Badge>
                                                ) : (
                                                    <span className="font-medium text-muted">Belum memiliki peran</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    {can.assignRole && (
                                        <Button
                                            type="button"
                                            className={`${primaryButton} self-start sm:shrink-0 sm:self-auto`}
                                            aria-label={`${user.current_role ? 'Ubah' : 'Tetapkan'} peran ${user.nama}`}
                                            onClick={(event) => { trigger.current = event.currentTarget; setSelected(user); }}
                                        >
                                            {user.current_role ? 'Ubah peran' : 'Tetapkan peran'}
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {users.last_page > 1 && (
                    <nav aria-label="Halaman pengguna" className="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-soft/30 px-4 py-3 sm:px-6 text-xs text-muted">
                        <div>
                            <span>Halaman <strong className="font-semibold text-ink">{users.current_page}</strong> dari <strong className="font-semibold text-ink">{users.last_page}</strong></span>
                        </div>
                        <div className="flex items-center gap-2">
                            {users.prev_page_url ? (
                                <Link href={users.prev_page_url} className="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors">
                                    Sebelumnya
                                </Link>
                            ) : (
                                <span className="inline-flex items-center rounded-md border border-border/40 bg-surface/50 px-3 py-1.5 text-xs font-medium text-muted opacity-50 cursor-not-allowed">
                                    Sebelumnya
                                </span>
                            )}
                            {users.next_page_url ? (
                                <Link href={users.next_page_url} className="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors">
                                    Berikutnya
                                </Link>
                            ) : (
                                <span className="inline-flex items-center rounded-md border border-border/40 bg-surface/50 px-3 py-1.5 text-xs font-medium text-muted opacity-50 cursor-not-allowed">
                                    Berikutnya
                                </span>
                            )}
                        </div>
                    </nav>
                )}
            </section>
        </div>

        {selected && <RoleDialog key={selected.id} user={selected} roles={roles} isSelf={selected.id === auth.user?.id} onClose={close} onSaved={() => {
            // Respons sukses kembali ke daftar tanpa filter; error tetap mempertahankan input.
            search.setData('q', '');
            close();
        }} />}
    </AuthenticatedLayout>;
}
