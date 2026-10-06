import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, CheckCircle, ChevronDown, Search, UserCog, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { CustomSelect } from '@/Components/CustomSelect';
import { Pagination } from '@/Components/Pagination';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/Components/Table';
import { EmptyState } from '@/Components/EmptyState';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton, secondaryButton } from '@/Pages/Auth/AuthShell';
import type { SharedPageProps } from '@/types/auth';

type AssignmentState = { id: string; role_id: string; audit_id: string | null };
type RoleOption = { id: string; kode: string; nama: string };
type RoleUser = {
    id: string;
    nama: string;
    email: string;
    status: 'aktif' | 'nonaktif';
    current_role: (RoleOption & { aktif: boolean }) | null;
    assignment: AssignmentState | null;
};

interface RoleAssignmentProps {
    users: {
        data: RoleUser[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        from?: number | null;
        to?: number | null;
        total?: number;
        per_page?: number;
    };
    roles: RoleOption[];
    filters: { q: string; per_page?: number };
    can: { assignRole: boolean };
}

const fieldClass =
    'mt-1.5 w-full rounded-lg border border-border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted/60 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 transition-colors';

function RoleDialog({
    user,
    roles,
    isSelf,
    onClose,
    onSaved,
}: {
    user: RoleUser;
    roles: RoleOption[];
    isSelf: boolean;
    onClose: () => void;
    onSaved: () => void;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const roleInput = useRef<HTMLSelectElement>(null);
    const reasonInput = useRef<HTMLTextAreaElement>(null);
    const alert = useRef<HTMLParagraphElement>(null);
    const [message, setMessage] = useState('');
    const recovery = useAuthRecovery();
    const form = useForm<{
        role_id: string;
        alasan: string;
        expected_assignment: AssignmentState | null;
    }>({
        role_id: roles.some((role) => role.id === user.current_role?.id)
            ? user.current_role!.id
            : '',
        alasan: '',
        expected_assignment: user.assignment,
    });
    const conflict = form.errors.expected_assignment;
    const needsReload = Boolean(conflict || message);

    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        return () => element?.close();
    }, []);

    useEffect(() => {
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
                if (
                    page.component === 'Access/RoleAssignmentIndex' &&
                    ['assigned', 'changed', 'unchanged'].includes(
                        String(page.flash.roleAssignmentStatus)
                    )
                ) {
                    onSaved();
                } else if (
                    page.component !== 'Access/RoleAssignmentResult' ||
                    !['assigned', 'changed', 'unchanged'].includes(String(page.props.status))
                ) {
                    setMessage(
                        'Hasil penetapan peran belum diketahui. Muat ulang data sebelum mencoba kembali.'
                    );
                }
            },
            onCancel: () => {
                setMessage(
                    'Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'
                );
            },
            onNetworkError: () => {
                setMessage(
                    'Koneksi terputus. Hasil penetapan peran belum diketahui. Muat ulang data sebelum mencoba kembali.'
                );
                return false;
            },
            onHttpException: (response) => {
                if (
                    recovery.handleHttpException(response, {
                        effectiveMethod: 'post',
                        path: `/akses/peran/${user.id}`,
                        mutation: true,
                    })
                )
                    return false;
                setMessage(
                    response.status === 403
                        ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.'
                        : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'
                );
                return false;
            },
        });
    };

    return (
        <dialog
            ref={dialog}
            aria-labelledby="role-title"
            aria-describedby={
                isSelf ? 'role-description self-role-warning' : 'role-description'
            }
            onCancel={(event) => {
                if (form.processing) event.preventDefault();
            }}
            onClose={onClose}
            className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-border bg-surface p-6 text-ink shadow-2xl backdrop:bg-ink/50"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3.5">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UserCog className="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 id="role-title" className="text-base font-bold text-ink">
                            {user.current_role ? 'Ubah peran' : 'Tetapkan peran'}
                        </h2>
                        <p id="role-description" className="mt-1 break-words text-xs text-muted leading-relaxed">
                            {user.nama} ({user.email}). Peran saat ini:{' '}
                            {user.current_role?.nama ?? 'Belum ditetapkan'}
                            {user.current_role && !user.current_role.aktif ? ' (nonaktif)' : ''}.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    disabled={form.processing}
                    aria-label="Tutup dialog"
                    className="-mr-1.5 -mt-1.5 rounded-lg p-2 text-muted hover:bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors cursor-pointer"
                >
                    <X className="h-4 w-4" aria-hidden="true" />
                </button>
            </div>

            {isSelf && (
                <div
                    id="self-role-warning"
                    className="mt-4 rounded-xl border border-warning/40 bg-warning/10 p-3.5 text-xs text-ink leading-relaxed"
                >
                    <p className="font-semibold text-warning-dark">
                        Anda sedang mengubah peran akun sendiri.
                    </p>
                    <p className="mt-1 text-muted">
                        Menu dan akses pengelolaan dapat hilang setelah disimpan. Anda mungkin tidak dapat mengembalikannya sendiri. Pastikan peran tujuan sudah benar.
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="mt-5 space-y-4" aria-busy={form.processing}>
                <CustomSelect
                    id="role-choice"
                    label="Peran tujuan"
                    labelClassName="text-xs font-semibold text-ink"
                    required
                    nativeSelect={true}
                    placeholder="Pilih peran"
                    emptyOptionLabel="Pilih peran"
                    options={roles.map((r) => ({ value: r.id, label: r.nama }))}
                    value={form.data.role_id}
                    onChange={(val) => form.setData('role_id', String(val))}
                    disabled={form.processing}
                    error={form.errors.role_id}
                    className="text-xs h-10"
                />

                <div>
                    <label
                        htmlFor="role-reason"
                        className="block text-xs font-semibold text-ink"
                    >
                        Alasan penetapan <span className="text-danger">*</span>
                    </label>
                    <textarea
                        ref={reasonInput}
                        id="role-reason"
                        required
                        maxLength={2000}
                        rows={3}
                        value={form.data.alasan}
                        onChange={(event) => form.setData('alasan', event.target.value)}
                        disabled={form.processing}
                        aria-invalid={Boolean(form.errors.alasan)}
                        aria-describedby={form.errors.alasan ? 'reason-error' : 'reason-help'}
                        placeholder="Tuliskan alasan penetapan peran untuk audit log..."
                        className={fieldClass}
                    />
                    {form.errors.alasan ? (
                        <p id="reason-error" role="alert" className="mt-1.5 text-xs font-medium text-danger">
                            {form.errors.alasan}
                        </p>
                    ) : (
                        <p id="reason-help" className="mt-1.5 text-[11px] text-muted">
                            Wajib diisi, maksimal 2.000 karakter. Dicatat dalam jejak audit.
                        </p>
                    )}
                </div>

                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                {needsReload && !recovery.recovery && (
                    <div className="space-y-2 rounded-xl bg-danger/10 border border-danger/20 p-3.5">
                        <p
                            ref={alert}
                            tabIndex={-1}
                            role="alert"
                            className="text-xs font-medium text-danger"
                        >
                            {conflict || message}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            className={secondaryButton}
                            disabled={form.processing}
                            onClick={() =>
                                router.get(
                                    '/akses/peran',
                                    {},
                                    { replace: true, preserveState: false }
                                )
                            }
                        >
                            Muat ulang data
                        </Button>
                    </div>
                )}

                <div className="flex flex-wrap justify-end gap-2.5 pt-2 border-t border-border">
                    <Button
                        type="button"
                        variant="outline"
                        className={secondaryButton}
                        disabled={form.processing}
                        onClick={onClose}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        className={primaryButton}
                        isLoading={form.processing}
                        disabled={
                            Boolean(recovery.recovery) || needsReload || roles.length === 0
                        }
                    >
                        Simpan peran
                    </Button>
                </div>
            </form>
        </dialog>
    );
}

function FilterDropdown({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const selectedOption = options.find((opt) => opt.value === value) ?? options[0];

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }
        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [open]);

    return (
        <div ref={dropdownRef} className="relative inline-block text-left">
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors shadow-2xs cursor-pointer"
            >
                <span className="text-muted">{label}:</span>
                <span className="font-semibold text-ink">{selectedOption.label}</span>
                <ChevronDown className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
            </button>
            {open && (
                <div className="absolute right-0 top-full mt-1.5 z-50 min-w-[180px] rounded-xl border border-border bg-surface p-1 shadow-xl text-xs animate-in fade-in zoom-in-95">
                    {options.map((opt) => (
                        <button
                            key={opt.value}
                            type="button"
                            onClick={() => {
                                onChange(opt.value);
                                setOpen(false);
                            }}
                            className={`w-full rounded-lg px-2.5 py-1.5 text-left text-xs transition-colors flex items-center justify-between cursor-pointer ${
                                opt.value === value
                                    ? 'bg-primary/10 font-semibold text-primary'
                                    : 'text-ink hover:bg-soft'
                            }`}
                        >
                            <span>{opt.label}</span>
                            {opt.value === value && <Check className="h-3.5 w-3.5 shrink-0" />}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function RoleAssignmentIndex({
    users,
    roles,
    filters,
    can,
}: RoleAssignmentProps) {
    const {
        props: { auth },
        flash,
    } = usePage<SharedPageProps>();
    const [dismissedFlash, setDismissedFlash] = useState<typeof flash | null>(null);
    const outcome = flash.roleAssignmentStatus;
    const successMessage =
        outcome === 'assigned'
            ? 'Peran berhasil ditetapkan.'
            : outcome === 'changed'
            ? 'Peran berhasil diubah.'
            : outcome === 'unchanged'
            ? 'Peran tidak berubah.'
            : null;
    const [selected, setSelected] = useState<RoleUser | null>(null);
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [roleFilter, setRoleFilter] = useState<string>('all');
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    const search = useForm({ q: filters.q });
    const close = () => setSelected(null);

    useEffect(() => {
        if (selected || !trigger.current) return;
        if (trigger.current?.isConnected) trigger.current.focus();
        else title.current?.focus();
    }, [selected]);

    const filteredUsers = useMemo(() => {
        return users.data.filter((user) => {
            if (statusFilter !== 'all' && user.status !== statusFilter) return false;
            if (roleFilter !== 'all') {
                if (roleFilter === 'none') {
                    if (user.current_role !== null) return false;
                } else if (user.current_role?.id !== roleFilter) {
                    return false;
                }
            }
            return true;
        });
    }, [users.data, statusFilter, roleFilter]);

    return (
        <AuthenticatedLayout
            title="Penetapan Peran"
            breadcrumbs={[
                { label: 'Manajemen Akses' },
                { label: 'Penetapan Peran' }
            ]}
        >
            <Head title="Penetapan Peran" />
            <h2
                ref={title}
                tabIndex={-1}
                className="sr-only"
            >
                Peran utama pengguna
            </h2>

            {successMessage && dismissedFlash !== flash && (
                <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-xl">
                    <CheckCircle aria-hidden="true" className="h-5 w-5 shrink-0 text-success" />
                    <p role="status" className="flex-1 text-sm font-medium text-ink">
                        {successMessage}
                    </p>
                    <button
                        type="button"
                        aria-label="Tutup notifikasi"
                        onClick={() => setDismissedFlash(flash)}
                        className="-m-1 rounded-lg p-2 text-muted hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <X aria-hidden="true" className="h-4 w-4" />
                    </button>
                </div>
            )}

            <div className="space-y-6">
                {/* Filter & Search Bar (Gambar 4 Style) */}
                <Card className="overflow-visible relative z-20">
                    <CardContent className="p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 overflow-visible">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (!search.processing)
                                    search.get('/akses/peran', { preserveState: false });
                            }}
                            className="relative flex-1"
                            role="search"
                        >
                            <Search
                                className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                                aria-hidden="true"
                            />
                            <input
                                id="user-search"
                                type="search"
                                maxLength={100}
                                value={search.data.q}
                                onChange={(event) => search.setData('q', event.target.value)}
                                placeholder="Cari nama atau email pengguna..."
                                aria-label="Cari nama atau email pengguna"
                                className="w-full pl-9 pr-4 py-2 text-xs rounded-lg border border-border bg-surface text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                            />
                        </form>

                        <div className="flex flex-wrap items-center gap-2.5 self-end md:self-auto">
                            <FilterDropdown
                                label="Status"
                                value={statusFilter}
                                options={[
                                    { value: 'all', label: 'Semua Status' },
                                    { value: 'aktif', label: 'Akun aktif' },
                                    { value: 'nonaktif', label: 'Menunggu aktivasi' },
                                ]}
                                onChange={setStatusFilter}
                            />
                            <FilterDropdown
                                label="Peran"
                                value={roleFilter}
                                options={[
                                    { value: 'all', label: 'Semua Peran' },
                                    { value: 'none', label: 'Belum Memiliki Peran' },
                                    ...roles.map((r) => ({ value: r.id, label: r.nama })),
                                ]}
                                onChange={setRoleFilter}
                            />
                            {(filters.q || statusFilter !== 'all' || roleFilter !== 'all') && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setStatusFilter('all');
                                        setRoleFilter('all');
                                        if (filters.q) {
                                            search.setData('q', '');
                                            router.get('/akses/peran', {}, { preserveState: false });
                                        }
                                    }}
                                    className="text-xs text-muted hover:text-ink h-8 px-2.5"
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Main Data Table */}
                <Card className="overflow-hidden border-border bg-surface shadow-xs">
                    {filteredUsers.length === 0 ? (
                        <div className="p-8">
                            <EmptyState
                                icon={UserCog}
                                title="Tidak ada pengguna yang sesuai"
                                description="Coba gunakan kata kunci pencarian yang lain atau reset filter pencarian."
                                variant="inline"
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center whitespace-nowrap">NO</TableHead>
                                        <TableHead className="min-w-[240px] whitespace-nowrap">PENGGUNA</TableHead>
                                        <TableHead className="text-center whitespace-nowrap">STATUS AKUN</TableHead>
                                        <TableHead className="min-w-[200px] whitespace-nowrap">PERAN SAAT INI</TableHead>
                                        {can.assignRole && (
                                            <TableHead className="text-right w-36 whitespace-nowrap">AKSI</TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredUsers.map((user, index) => {
                                        const rowNumber = (users.current_page - 1) * 20 + index + 1;
                                        const initials = user.nama
                                            ? user.nama.slice(0, 2).toUpperCase()
                                            : 'U';
                                        return (
                                            <TableRow
                                                key={user.id}
                                                className="transition-colors hover:bg-soft/40"
                                            >
                                                <TableCell className="text-center font-mono text-xs text-muted">
                                                    {rowNumber}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-start gap-3">
                                                        <div
                                                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold tracking-wider text-primary border border-primary/20"
                                                            aria-hidden="true"
                                                        >
                                                            {initials}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <h3 className="break-words text-sm font-semibold text-ink">
                                                                {user.nama}
                                                            </h3>
                                                            <p className="mt-0.5 text-xs text-muted break-all">
                                                                {user.email}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center whitespace-nowrap">
                                                    <Badge
                                                        variant={user.status === 'aktif' ? 'success' : 'muted'}
                                                        size="sm"
                                                        dot
                                                    >
                                                        {user.status === 'aktif'
                                                            ? 'Akun aktif'
                                                            : 'Menunggu aktivasi'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    {user.current_role ? (
                                                        <Badge
                                                            variant={user.current_role.aktif ? 'primary' : 'warning'}
                                                            size="sm"
                                                        >
                                                            {user.current_role.nama}
                                                            {!user.current_role.aktif ? ' (nonaktif)' : ''}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-xs font-medium text-muted">
                                                            Belum memiliki peran
                                                        </span>
                                                    )}
                                                </TableCell>
                                                {can.assignRole && (
                                                    <TableCell className="text-right whitespace-nowrap">
                                                        <Button
                                                            type="button"
                                                            variant="primary"
                                                            size="sm"
                                                            className="gap-1.5 shadow-xs"
                                                            aria-label={`${user.current_role ? 'Ubah' : 'Tetapkan'} peran ${user.nama}`}
                                                            onClick={(event) => {
                                                                trigger.current = event.currentTarget;
                                                                setSelected(user);
                                                            }}
                                                        >
                                                            <UserCog className="h-3.5 w-3.5" aria-hidden="true" />
                                                            {user.current_role ? 'Ubah peran' : 'Tetapkan peran'}
                                                        </Button>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <Pagination
                        from={users.from ?? (filteredUsers.length > 0 ? (users.current_page - 1) * (users.per_page ?? 20) + 1 : 0)}
                        to={users.to ?? (filteredUsers.length > 0 ? (users.current_page - 1) * (users.per_page ?? 20) + filteredUsers.length : 0)}
                        total={users.total ?? filteredUsers.length}
                        perPage={users.per_page ?? 20}
                        currentPage={users.current_page}
                        lastPage={users.last_page}
                        prevUrl={users.prev_page_url}
                        nextUrl={users.next_page_url}
                        resourceName="pengguna"
                        showPerPage={!selected}
                        onPageChange={(page) => {
                            router.get(
                                '/akses/peran',
                                { ...filters, page, per_page: users.per_page ?? 20 },
                                { preserveState: false, preserveScroll: true }
                            );
                        }}
                        onPerPageChange={(newPerPage) => {
                            router.get(
                                '/akses/peran',
                                { ...filters, page: 1, per_page: newPerPage },
                                { preserveState: false, preserveScroll: true }
                            );
                        }}
                    />
                </Card>
            </div>

            {selected && (
                <RoleDialog
                    key={selected.id}
                    user={selected}
                    roles={roles}
                    isSelf={selected.id === auth.user?.id}
                    onClose={close}
                    onSaved={() => {
                        search.setData('q', '');
                        close();
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
