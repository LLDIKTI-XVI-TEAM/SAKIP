import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle, X } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/Components/Button';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton, secondaryButton } from '@/Pages/Auth/AuthShell';
import type { SharedPageProps } from '@/types/auth';

type AssignmentState = { id: string; role_id: string; audit_id: string | null };
type RoleOption = { id: string; kode: string; nama: string };
type RoleUser = { id: string; nama: string; email: string; is_active: boolean; current_role: (RoleOption & { aktif: boolean }) | null; assignment: AssignmentState | null };
interface RoleAssignmentProps {
    users: { data: RoleUser[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
    roles: RoleOption[];
    filters: { q: string };
    can: { assignRole: boolean };
}
const fieldClass = 'mt-2 w-full rounded-lg border border-border bg-surface p-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50';
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
            onHttpException: (response) => { if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/akses/peran/${user.id}`, mutation: true })) return false;
                setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };
    return <dialog ref={dialog} aria-labelledby="role-title" aria-describedby={isSelf ? 'role-description self-role-warning' : 'role-description'} onCancel={(event) => {
        if (form.processing) event.preventDefault();
    }} onClose={onClose} className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-border bg-surface p-6 text-ink shadow-xl backdrop:bg-ink/50">
        <h2 id="role-title" className="text-lg font-semibold">{user.current_role ? 'Ubah peran' : 'Tetapkan peran'}</h2>
        <p id="role-description" className="mt-2 break-words text-sm text-muted">{user.nama} ({user.email}). Peran saat ini: {user.current_role?.nama ?? 'Belum ditetapkan'}{user.current_role && !user.current_role.aktif ? ' (nonaktif)' : ''}.</p>
        <p className="mt-2 text-sm text-muted">{explanation}</p>
        {isSelf && <div id="self-role-warning" className="mt-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
            <p className="font-semibold">Anda sedang mengubah peran akun sendiri.</p>
            <p className="mt-1">Menu dan akses pengelolaan dapat hilang setelah disimpan. Anda mungkin tidak dapat mengembalikannya sendiri. Pastikan peran tujuan sudah benar.</p>
        </div>}
        <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={form.processing}>
            <div>
                <label htmlFor="role-choice" className="block text-sm font-medium">Peran tujuan <span className="text-danger">*</span></label>
                <select ref={roleInput} id="role-choice" autoFocus required value={form.data.role_id} onChange={(event) => form.setData('role_id', event.target.value)} disabled={form.processing} aria-invalid={Boolean(form.errors.role_id)} aria-describedby={form.errors.role_id ? 'role-error' : undefined} className={fieldClass}>
                    <option value="" disabled>Pilih peran</option>
                    {roles.map((role) => <option key={role.id} value={role.id}>{role.nama}</option>)}
                </select>
                {form.errors.role_id && <p id="role-error" role="alert" className="mt-1 text-sm text-danger">{form.errors.role_id}</p>}
            </div>
            <div>
                <label htmlFor="role-reason" className="block text-sm font-medium">Alasan penetapan <span className="text-danger">*</span></label>
                <textarea ref={reasonInput} id="role-reason" required maxLength={2000} rows={3} value={form.data.alasan} onChange={(event) => form.setData('alasan', event.target.value)} disabled={form.processing} aria-invalid={Boolean(form.errors.alasan)} aria-describedby={form.errors.alasan ? 'reason-error' : 'reason-help'} className={fieldClass} />
                {form.errors.alasan ? <p id="reason-error" role="alert" className="mt-1 text-sm text-danger">{form.errors.alasan}</p> : <p id="reason-help" className="mt-1 text-xs text-muted">Wajib diisi, maksimal 2.000 karakter. Dicatat dalam jejak audit.</p>}
            </div>
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {needsReload && !recovery.recovery && <div className="space-y-2 rounded-lg bg-soft p-3">
                <p ref={alert} tabIndex={-1} role="alert" className="text-sm text-danger">{conflict || message}</p>
                <Button type="button" variant="outline" className={secondaryButton} disabled={form.processing} onClick={() => router.get('/akses/peran', {}, { replace: true, preserveState: false })}>Muat ulang data</Button>
            </div>}
            <div className="flex flex-wrap justify-end gap-3">
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
    return <AuthenticatedLayout title="Penetapan Peran" hasCustomHeading>
        <Head title="Penetapan Peran" />
        {successMessage && dismissedFlash !== flash && <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-lg">
            <CheckCircle aria-hidden="true" className="h-5 w-5 shrink-0 text-success" />
            <p role="status" className="flex-1 text-sm font-medium">{successMessage}</p>
            <button type="button" aria-label="Tutup notifikasi" onClick={() => setDismissedFlash(flash)} className="-m-2 rounded-lg p-3 text-muted hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary"><X aria-hidden="true" className="h-4 w-4" /></button>
        </div>}
        <section className="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h1 ref={title} tabIndex={-1} className="text-lg font-semibold">Peran utama pengguna</h1>
            <p className="mt-2 text-sm text-muted">Setiap pengguna memiliki satu peran utama. {explanation}</p>
            <form onSubmit={(event) => { event.preventDefault(); if (!search.processing) search.get('/akses/peran', { preserveState: false }); }} className="my-6 flex flex-wrap items-end gap-3" role="search">
                <div className="min-w-0 flex-1"><label htmlFor="user-search" className="block text-sm font-medium">Cari nama atau email</label><input id="user-search" type="search" maxLength={100} value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} className={fieldClass} /></div>
                <Button type="submit" className={secondaryButton} isLoading={search.processing}>Cari</Button>
                {filters.q && <Link href="/akses/peran" className="rounded py-3 text-sm text-primary underline focus:ring-2 focus:ring-primary">Reset</Link>}
            </form>
            {users.data.length === 0 ? <p className="rounded-lg bg-soft p-4 text-sm text-muted">Tidak ada pengguna yang sesuai.</p> : <ul className="divide-y divide-border">
                {users.data.map((user) => <li key={user.id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0"><h2 className="break-words text-sm font-semibold">{user.nama}</h2><p className="break-all text-sm text-muted">{user.email}</p><p className="mt-2 text-sm"><span className="font-medium">{user.current_role?.nama ?? 'Belum memiliki peran'}{user.current_role && !user.current_role.aktif ? ' (nonaktif)' : ''}</span><span className="text-muted"> · {user.is_active ? 'Akun aktif' : 'Menunggu aktivasi'}</span></p></div>
                    {can.assignRole && <Button type="button" className={`${primaryButton} self-start sm:shrink-0 sm:self-auto`} aria-label={`${user.current_role ? 'Ubah' : 'Tetapkan'} peran ${user.nama}`} onClick={(event) => { trigger.current = event.currentTarget; setSelected(user); }}>{user.current_role ? 'Ubah peran' : 'Tetapkan peran'}</Button>}
                </li>)}
            </ul>}
            {users.last_page > 1 && <nav aria-label="Halaman pengguna" className="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
                {users.prev_page_url ? <Link href={users.prev_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Sebelumnya</Link> : <span className="text-muted">Sebelumnya</span>}
                <span>Halaman {users.current_page} dari {users.last_page}</span>
                {users.next_page_url ? <Link href={users.next_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Berikutnya</Link> : <span className="text-muted">Berikutnya</span>}
            </nav>}
        </section>
        {selected && <RoleDialog key={selected.id} user={selected} roles={roles} isSelf={selected.id === auth.user?.id} onClose={close} onSaved={() => {
            // Respons sukses kembali ke daftar tanpa filter; error tetap mempertahankan input.
            search.setData('q', '');
            close();
        }} />}
    </AuthenticatedLayout>;
}
