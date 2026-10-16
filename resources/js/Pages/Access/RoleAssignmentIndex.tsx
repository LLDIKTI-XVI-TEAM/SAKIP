import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { GrantUserAutocomplete, type UserOption } from '@/Components/Access/GrantUserAutocomplete';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Power, UserCog, Users, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardHeader } from '@/Components/Card';
import { Modal } from '@/Components/Modal';
import { Pagination, type PaginationLinkItem } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Textarea } from '@/Components/Textarea';
import { Tooltip } from '@/Components/Tooltip';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { classifyRecovery } from '@/lib/authRecovery';
import type { SharedPageProps } from '@/types/auth';
import { readRoleAssignmentOutcome, RoleAssignmentPjNotice, roleAssignmentMessages, roleAssignmentUnknown, roleAssignmentCheck } from './RoleAssignmentFeedback';

type AssignmentState = { id: string; role_id: string; audit_id: string | null };
type RoleOption = { id: string; kode: string; nama: string };
type RoleUser = { id: string; nama: string; email: string; status: 'aktif' | 'nonaktif'; current_role: (RoleOption & { aktif: boolean }) | null; assignment: AssignmentState | null; has_active_pj: boolean };
interface RoleAssignmentProps {
    users: { data: RoleUser[]; links: PaginationLinkItem[]; from: number | null; to: number | null; total: number };
    roles: RoleOption[];
    filters: { q: string; user?: string | null };
    can: { assignRole: boolean };
    confirmationUnavailable: boolean;
}
const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';
const filterFailureText = {
    network: 'Daftar pengguna belum diperbarui. Periksa koneksi, lalu coba lagi.',
    denied: 'Akses ke daftar pengguna ditolak. Periksa akses sebelum mencoba kembali.',
    other: 'Daftar pengguna belum diperbarui.',
};

function RoleDialog({ user, roles, isSelf, onClose, onSaved }: { user: RoleUser; roles: RoleOption[]; isSelf: boolean; onClose: () => void; onSaved: () => void }) {
    const alert = useRef<HTMLParagraphElement>(null);
    const [message, setMessage] = useState('');
    const recovery = useAuthRecovery();
    const form = useForm<{ role_id: string; alasan: string; expected_assignment: AssignmentState | null }>({
        role_id: roles.some((role) => role.id === user.current_role?.id) ? user.current_role!.id : '',
        alasan: '', expected_assignment: user.assignment,
    });
    const conflict = form.errors.expected_assignment;
    const legacyRole = user.current_role && !['superadmin', 'admin', 'perencanaan', 'pimpinan', 'pegawai'].includes(user.current_role.kode);
    const needsReload = Boolean(conflict || message);
    useEffect(() => {
        // Tunggu field kembali enabled setelah respons Inertia sebelum memindahkan fokus.
        if (form.processing) return;
        if (needsReload) alert.current?.focus();
        else if (form.errors.role_id) document.getElementById('role-choice')?.focus();
        else if (form.errors.alasan) document.getElementById('role-reason')?.focus();
    }, [form.processing, form.errors, needsReload, message]);
    const close = () => { if (!form.processing) onClose(); };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || recovery.recovery || needsReload) return;
        form.post(`/akses/peran/${user.id}`, {
            // Notifikasi hasil tampil di atas daftar, jadi gulir ke atas kecuali saat validasi gagal.
            preserveScroll: 'errors',
            onSuccess: (page) => {
                const outcome = readRoleAssignmentOutcome(page, page.component === 'Access/RoleAssignmentResult' ? 'Access/RoleAssignmentResult' : 'Access/RoleAssignmentIndex');
                if (!outcome) {
                    setMessage(roleAssignmentUnknown);
                } else if (page.component === 'Access/RoleAssignmentIndex') {
                    onSaved();
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
    return <Modal
        isOpen
        onClose={close}
        size="lg"
        title={user.current_role ? 'Ubah peran' : 'Tetapkan peran'}
        description={<>{user.nama} (<span className="break-all">{user.email}</span>). Peran saat ini: {user.current_role?.nama ?? 'Belum ditetapkan'}{user.current_role && !user.current_role.aktif ? ' (nonaktif)' : ''}.</>}
        footer={<>
            <Button type="button" variant="outline" disabled={form.processing} onClick={close}>Batal</Button>
            <Button type="submit" form="role-assignment-form" isLoading={form.processing} disabled={Boolean(recovery.recovery) || needsReload || roles.length === 0}>Simpan peran</Button>
        </>}
    >
        <form id="role-assignment-form" onSubmit={submit} className="space-y-4" aria-busy={form.processing}>
            {legacyRole && <p className="text-sm text-warning-dark">Peran lama ini perlu ditetapkan ulang ke peran resmi.</p>}
            {user.has_active_pj && <p role="status" className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-ink">Pengguna ini masih menjadi Penanggung Jawab. Perubahan peran tidak mengakhiri penugasan atau mengubah grant izin.</p>}
            {isSelf && <div id="self-role-warning" className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-ink">
                <p className="font-semibold">Anda sedang mengubah peran akun sendiri.</p>
                <p className="mt-1">Menu dan akses pengelolaan dapat hilang setelah disimpan. Anda mungkin tidak dapat mengembalikannya sendiri. Pastikan peran tujuan sudah benar.</p>
            </div>}
            <Select
                id="role-choice"
                label="Peran tujuan"
                required
                value={form.data.role_id}
                onChange={(event) => form.setData('role_id', event.target.value)}
                disabled={form.processing}
                error={form.errors.role_id}
                aria-describedby={[isSelf && 'self-role-warning', form.errors.role_id && 'role-choice-error'].filter(Boolean).join(' ') || undefined}
            >
                <option value="" disabled>Pilih peran</option>
                {roles.map((role) => <option key={role.id} value={role.id}>{role.nama}</option>)}
            </Select>
            <Textarea
                id="role-reason"
                label="Alasan penetapan"
                required
                maxLength={2000}
                rows={3}
                value={form.data.alasan}
                onChange={(event) => form.setData('alasan', event.target.value)}
                disabled={form.processing}
                error={form.errors.alasan}
                helperText="Maksimal 2.000 karakter."
            />
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {needsReload && !recovery.recovery && <div className="space-y-2 rounded-lg bg-soft p-3">
                <p ref={alert} tabIndex={-1} role="alert" className="text-sm text-danger focus:outline-none">{conflict || message}</p>
                {!conflict && message === roleAssignmentUnknown && <p className="text-sm text-ink">{roleAssignmentCheck}</p>}
                <Button type="button" variant="outline" size="sm" disabled={form.processing} onClick={() => router.get('/akses/peran', {}, { replace: true, preserveState: false })}>{!conflict && message === roleAssignmentUnknown ? 'Kembali ke daftar pengguna' : 'Muat ulang data'}</Button>
            </div>}
        </form>
    </Modal>;
}

export default function RoleAssignmentIndex({ users, roles, filters, can, confirmationUnavailable }: RoleAssignmentProps) {
    const page = usePage<SharedPageProps>();
    const { props: { auth } } = page;
    const [dismissedReceipt, setDismissedReceipt] = useState<string | null>(null);
    const outcome = readRoleAssignmentOutcome(page, 'Access/RoleAssignmentIndex');
    const successMessage = outcome ? roleAssignmentMessages[outcome.status] : null;
    const [selected, setSelected] = useState<RoleUser | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    const close = () => setSelected(null);
    const recovery = useAuthRecovery();
    const [statusTarget, setStatusTarget] = useState<RoleUser | null>(null);
    const [statusReason, setStatusReason] = useState('');
    const [statusError, setStatusError] = useState('');
    const [statusBusy, setStatusBusy] = useState(false);
    const deactivating = statusTarget?.status === 'aktif';
    const openStatus = (user: RoleUser) => { setStatusTarget(user); setStatusReason(''); setStatusError(''); };
    // Filter id pengguna ditampilkan sebagai pengguna terpilih pada autocomplete.
    const filtered = useMemo<UserOption | null>(() => {
        const match = filters.user ? users.data.find((user) => user.id === filters.user) : undefined;
        return match ? { id: match.id, nama: match.nama, email: match.email, status: match.status, roles: match.current_role ? [`${match.current_role.nama}${match.current_role.aktif ? '' : ' (nonaktif)'}`] : [] } : null;
    }, [users.data, filters.user]);
    const [filtering, setFiltering] = useState(false);
    // Pilihan yang sedang atau gagal dimuat; nilai dari server dipakai lagi setelah daftar berhasil diperbarui.
    const [pendingFilter, setPendingFilter] = useState<{ id: string; user: UserOption | null } | null>(null);
    const [filterFailure, setFilterFailure] = useState<keyof typeof filterFailureText | null>(null);
    const filterInterrupted = useRef(false);
    const filterBy = useCallback((id: string, user: UserOption | null = null) => {
        let loaded = false;
        let kind: keyof typeof filterFailureText = 'other';
        let sessionDialog = false;
        // Dipasang sebelum respons tiba agar respons lama tidak mengosongkan kata cari yang baru diketik.
        setPendingFilter({ id, user });
        router.get('/akses/peran', id ? { user: id } : {}, {
            preserveState: true, preserveScroll: true, replace: true,
            onStart: () => { setFiltering(true); setFilterFailure(null); },
            onSuccess: () => { loaded = true; filterInterrupted.current = false; setPendingFilter(null); },
            onNetworkError: () => { kind = 'network'; return false; },
            // Sesi kedaluwarsa diteruskan ke dialog sesi global; kegagalan lain cukup ditampilkan di halaman tanpa dialog galat bawaan.
            onHttpException: (response) => {
                sessionDialog = classifyRecovery(response) !== null;
                if (sessionDialog) return;
                if (response.status === 403) kind = 'denied';
                return false;
            },
            // onFinish juga terpanggil saat koneksi/server gagal; hanya onSuccess yang membuktikan daftar sudah diperbarui.
            onFinish: (visit) => {
                setFiltering(false);
                // Dipotong kunjungan lain: dikirim ulang sekali bila navigasi itu gagal dan halaman tetap terbuka.
                if (visit.interrupted) filterInterrupted.current = true;
                else if (!loaded && !visit.cancelled && !sessionDialog) setFilterFailure(kind);
            },
        });
    }, []);
    useEffect(() => router.on('finish', (event) => {
        const visit = event.detail.visit;
        if (visit.prefetch || visit.url.pathname === '/akses/peran' || !filterInterrupted.current || !pendingFilter) return;
        filterInterrupted.current = false;
        // Navigasi yang berhasil sudah mengganti URL; hanya navigasi gagal yang menyisakan halaman ini terbuka.
        if (!visit.cancelled && !visit.interrupted && window.location.pathname === '/akses/peran') filterBy(pendingFilter.id, pendingFilter.user);
    }), [pendingFilter, filterBy]);
    // Simpan memuat daftar baru dari server; pilihan dan pesan filter yang gagal tidak berlaku lagi.
    const saved = () => { close(); setStatusTarget(null); setPendingFilter(null); setFilterFailure(null); };
    const submitStatus = () => {
        if (!statusTarget || statusBusy || recovery.recovery) return;
        const path = `/akses/peran/${statusTarget.id}/status`;
        setStatusBusy(true);
        setStatusError('');
        router.post(path, { status: deactivating ? 'nonaktif' : 'aktif', alasan: statusReason, expected_status: statusTarget.status }, {
            preserveScroll: true,
            onSuccess: saved,
            onError: (errors) => setStatusError(Object.values(errors)[0] ?? 'Gagal mengubah status akun.'),
            onNetworkError: () => {
                setStatusError('Koneksi terputus. Hasil perubahan status belum diketahui. Muat ulang data sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) return false;
                setStatusError(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
            onFinish: () => setStatusBusy(false),
        });
    };
    const hasFilter = Boolean(filters.q || filters.user);
    useEffect(() => {
        // Fokus daftar baru dapat dipulihkan setelah dialog modal tidak lagi membuatnya inert.
        if (selected || !trigger.current) return;
        if (trigger.current?.isConnected) trigger.current.focus();
        else title.current?.focus();
    }, [selected]);
    return <AuthenticatedLayout title="Penetapan Peran" breadcrumbs={[{ label: 'Penetapan Peran' }]}>
        <Head title="Penetapan Peran" />
        <div className="space-y-5">
            {confirmationUnavailable && !selected && <section role="status" aria-labelledby="role-confirmation-unavailable" className="flex items-start gap-2.5 rounded-xl border border-warning/30 bg-warning/10 p-3.5 text-sm text-ink shadow-2xs">
                <AlertCircle aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0 text-warning-dark" />
                <div className="min-w-0">
                    <h2 id="role-confirmation-unavailable" className="font-semibold">{roleAssignmentUnknown}</h2>
                    <p className="mt-1">{roleAssignmentCheck}</p>
                    <Link href="/akses/peran" className="mt-3 inline-flex h-9 items-center rounded-lg border border-border bg-surface px-3 text-xs font-medium text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25">Kembali ke daftar pengguna</Link>
                </div>
            </section>}
            {successMessage && outcome && dismissedReceipt !== outcome.receipt_id && <div className="flex items-start gap-2.5 rounded-xl border border-success/30 bg-success/10 p-3.5 text-sm text-ink shadow-2xs">
                <CheckCircle aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                <p role="status" className="min-w-0 flex-1">{successMessage}</p>
                <button type="button" aria-label="Tutup notifikasi" onClick={() => setDismissedReceipt(outcome.receipt_id)} className="-m-2 rounded-lg p-2.5 text-muted transition-colors hover:bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/25"><X aria-hidden="true" className="h-4 w-4" /></button>
            </div>}
            <RoleAssignmentPjNotice outcome={outcome} />
            <Card className="overflow-visible">
                <div className="space-y-2 p-4" role="search">
                    <GrantUserAutocomplete id="user-search" label="Cari pengguna" required={false} endpoint="/akses/peran/opsi/pengguna" placeholder="Ketik minimal 2 huruf nama atau email…" value={pendingFilter ? pendingFilter.id : (filtered?.id ?? '')} initialUser={pendingFilter ? pendingFilter.user : filtered} onChange={(id, user) => filterBy(id, user ?? null)} />
                    {hasFilter && !filtered && <p className="text-xs text-muted">{filters.q ? `Menampilkan hasil untuk “${filters.q}”.` : 'Pengguna terpilih tidak ditemukan.'} <Link href="/akses/peran" className="font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/25">Tampilkan semua</Link></p>}
                    {filterFailure && pendingFilter && <p role="alert" className="text-xs text-danger">{filterFailureText[filterFailure]} <button type="button" onClick={() => {
                        // Tombol ini hilang setelah diklik; fokus dipindah ke judul daftar agar keyboard tidak jatuh ke awal halaman.
                        title.current?.focus();
                        filterBy(pendingFilter.id, pendingFilter.user);
                    }} className="font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/25">Coba lagi</button></p>}
                </div>
            </Card>
            <Card>
                <CardHeader className="px-4 py-4">
                    <div className="min-w-0">
                        <h2 ref={title} tabIndex={-1} className="text-base font-semibold tracking-tight text-ink focus:outline-none">Peran utama pengguna</h2>
                        <p className="mt-0.5 text-xs text-muted">Setiap pengguna memiliki paling banyak satu peran utama. Perubahan peran wajib disertai alasan dan tercatat di jejak audit.</p>
                    </div>
                </CardHeader>
                {/* Grid mobile: setiap pengguna tampil sebagai kartu ringkas tanpa scroll horizontal. */}
                {/* Aksi baris daftar lama tidak boleh membuka dialog untuk pengguna yang akan hilang setelah filter dimuat. */}
                <Table inert={filtering} wrapperClassName="overflow-x-visible" className={`block transition-opacity md:table ${filtering ? 'opacity-60' : ''}`}>
                    <caption className="sr-only">Daftar pengguna dan peran utama</caption>
                    <TableHeader className="max-md:sr-only">
                        <TableRow>
                            <TableHead className="whitespace-normal">Pengguna</TableHead>
                            <TableHead>Peran</TableHead>
                            <TableHead>Status akun</TableHead>
                            {can.assignRole && <TableHead className="text-right">Aksi</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody className="block md:table-row-group">
                        {users.data.length === 0 && <TableRow className="block md:table-row">
                            <TableCell colSpan={can.assignRole ? 4 : 3} className="block py-12 text-center md:table-cell">
                                <Users className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                {/* Halaman kosong padahal total > 0 berarti nomor halaman melampaui halaman terakhir, mis. bookmark lama. */}
                                {users.total > 0 ? <>
                                    <p className="mt-2 text-sm font-semibold text-ink">Tidak ada pengguna di halaman ini.</p>
                                    <Button type="button" variant="outline" size="sm" className="mt-4" onClick={() => router.get('/akses/peran', { ...(filters.q ? { q: filters.q } : {}), ...(filters.user ? { user: filters.user } : {}) })}>Ke halaman pertama</Button>
                                </> : <>
                                    <p className="mt-2 text-sm font-semibold text-ink">{hasFilter ? 'Tidak ada pengguna yang sesuai' : 'Belum ada pengguna'}</p>
                                    <p className="mt-1 text-xs text-muted">{hasFilter ? 'Coba kata kunci nama atau email lain.' : 'Pengguna tampil di sini setelah login SSO pertama kali.'}</p>
                                </>}
                            </TableCell>
                        </TableRow>}
                        {users.data.map((user) => {
                            const action = user.current_role ? 'Ubah peran' : 'Tetapkan peran';
                            const statusAction = user.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan kembali';
                            return <TableRow key={user.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                <TableCell className={`col-span-2 ${cellClass}`}>
                                    <p className="break-words text-sm font-semibold text-ink">{user.nama}</p>
                                    <p className="break-all text-xs text-muted">{user.email}</p>
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <span className="mr-2 text-muted md:hidden">Peran</span>
                                    {user.current_role
                                        ? <span className="inline-flex flex-wrap items-center gap-1.5"><span className="text-sm font-medium text-ink">{user.current_role.nama}</span>{!user.current_role.aktif && <Badge variant="muted">Peran nonaktif</Badge>}</span>
                                        : <Badge variant="warning">Belum memiliki peran</Badge>}
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <span className="mr-2 text-muted md:hidden">Status akun</span>
                                    <Badge variant={user.status === 'aktif' ? 'success' : 'muted'}>{user.status === 'aktif' ? 'Aktif' : 'Nonaktif'}</Badge>
                                </TableCell>
                                {can.assignRole && <TableCell className={`col-span-2 ${cellClass} md:text-right`}>
                                    <div className="flex flex-wrap justify-end gap-1.5">
                                        <Tooltip content={action} align="right">
                                            <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0" aria-label={`${action} ${user.nama}`} onClick={(event) => { trigger.current = event.currentTarget; setSelected(user); }}>
                                                <UserCog className="h-4 w-4" aria-hidden="true" />
                                            </Button>
                                        </Tooltip>
                                        {user.id !== auth.user?.id && <Tooltip content={statusAction} align="right">
                                            <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0" aria-label={`${statusAction} ${user.nama}`} onClick={() => openStatus(user)}>
                                                <Power className="h-4 w-4" aria-hidden="true" />
                                            </Button>
                                        </Tooltip>}
                                    </div>
                                </TableCell>}
                            </TableRow>;
                        })}
                    </TableBody>
                </Table>
            </Card>
            {/* Tautan halaman lama tidak boleh membatalkan filter pengguna yang sedang dimuat. */}
            <div inert={filtering} className={`transition-opacity ${filtering ? 'opacity-60' : ''}`}>
                <Pagination links={users.links} from={users.from} to={users.to} total={users.total} ariaLabel="Halaman pengguna" />
            </div>
        </div>
        {selected && <RoleDialog key={selected.id} user={selected} roles={roles} isSelf={selected.id === auth.user?.id} onClose={close} onSaved={saved} />}
        {/* Tetap terpasang agar penutupan dialog native mengembalikan fokus ke tombol pemicu. */}
        <AuditReasonModal
            open={statusTarget !== null}
            title={`${deactivating ? 'Nonaktifkan' : 'Aktifkan kembali'} ${statusTarget?.nama ?? 'akun'}?`}
            description={deactivating ? 'Sesi akun terblokir pada permintaan berikutnya. Peran dan grant izin tidak dihapus.' : 'Akun dapat masuk kembali dengan peran dan grant izin yang sama.'}
            reason={statusReason}
            error={statusError || undefined}
            busy={statusBusy}
            submitDisabled={statusReason.trim() === '' || Boolean(recovery.recovery)}
            notice={<>
                {deactivating && statusTarget?.has_active_pj && <p role="status" className="mb-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-ink">Pengguna ini masih menjadi Penanggung Jawab aktif. Penonaktifan tidak mengakhiri penugasan; ganti Penanggung Jawab bila diperlukan.</p>}
                <AuthRecoveryNotice recovery={recovery.recovery} pending={statusBusy} />
            </>}
            confirmLabel={deactivating ? 'Nonaktifkan' : 'Aktifkan kembali'}
            destructive={deactivating}
            onReasonChange={setStatusReason}
            onClose={() => { if (!statusBusy) setStatusTarget(null); }}
            onConfirm={submitStatus}
        />
    </AuthenticatedLayout>;
}
