import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
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
import { primaryButton, secondaryButton } from './AuthShell';
import { UserCheck, ShieldCheck, Mail, Calendar, Search, X } from 'lucide-react';

interface PendingUser {
    id: string;
    nama: string;
    email: string;
    created_at: string;
}

interface ActivationProps {
    users: {
        data: PendingUser[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
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
                if (
                    result &&
                    typeof result === 'object' &&
                    'user_id' in result &&
                    'status' in result &&
                    result.user_id === user.id &&
                    (result.status === 'activated' || result.status === 'already_active')
                ) {
                    onClose();
                } else {
                    setMessage('Hasil aktivasi belum terkonfirmasi. Periksa status akun sebelum mencoba kembali.');
                }
            },
            onCancel: () => {
                setMessage('Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
            },
            onNetworkError: () => {
                setMessage('Koneksi terputus. Hasil aktivasi belum diketahui; periksa status akun sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => {
                if (
                    recovery.handleHttpException(response, {
                        effectiveMethod: 'post',
                        path: `/akses/aktivasi/${user.id}`,
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
            aria-labelledby="activation-title"
            aria-describedby="activation-description"
            onCancel={(event) => {
                if (processing) event.preventDefault();
            }}
            onClose={onClose}
            className="m-auto max-h-[calc(100dvh-2rem)] overflow-y-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-border bg-surface p-6 text-ink shadow-2xl backdrop:bg-ink/50"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3.5">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UserCheck className="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 id="activation-title" className="text-base font-bold text-ink">
                            Aktifkan akun
                        </h2>
                        <p id="activation-description" className="mt-1 break-words text-xs text-muted leading-relaxed">
                            Aktifkan {user.nama} ({user.email}). Peran dan izin akun tetap mengikuti pengaturan yang berlaku.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    disabled={processing}
                    aria-label="Tutup dialog"
                    className="text-muted hover:text-ink p-1 rounded-lg hover:bg-soft transition-colors cursor-pointer shrink-0"
                >
                    <X className="w-5 h-5" />
                </button>
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
                        <p role="alert" className="text-xs text-danger font-medium">
                            {message}
                        </p>
                    </div>
                )}

                <div className="flex flex-wrap justify-end gap-2.5 pt-2 border-t border-border">
                    <Button
                        type="button"
                        variant="outline"
                        className={secondaryButton}
                        disabled={processing}
                        onClick={onClose}
                    >
                        Batal
                    </Button>
                    <Button
                        disabled={Boolean(recovery.recovery || message)}
                        type="submit"
                        className={primaryButton}
                        isLoading={processing}
                    >
                        Konfirmasi aktivasi
                    </Button>
                </div>
            </form>
        </dialog>
    );
}

export default function ActivationIndex({ users, canActivate }: ActivationProps) {
    const [selected, setSelected] = useState<PendingUser | null>(null);
    const [search, setSearch] = useState('');
    const [currentPage, setCurrentPage] = useState(users.current_page || 1);
    const [perPage, setPerPage] = useState(20);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);

    const filteredUsers = useMemo(() => {
        return users.data.filter((u) => {
            const matchesSearch =
                u.nama.toLowerCase().includes(search.toLowerCase()) ||
                u.email.toLowerCase().includes(search.toLowerCase());
            return matchesSearch;
        });
    }, [users.data, search]);

    const paginatedUsers = useMemo(() => {
        const start = (currentPage - 1) * perPage;
        return filteredUsers.slice(start, start + perPage);
    }, [filteredUsers, currentPage, perPage]);

    const close = () => {
        setSelected(null);
        if (trigger.current?.isConnected) trigger.current.focus();
        else title.current?.focus();
    };

    return (
        <AuthenticatedLayout
            title="Aktivasi Pengguna"
            breadcrumbs={[
                { label: 'Manajemen Akses' },
                { label: 'Aktivasi Pengguna' }
            ]}
        >
            <Head title="Aktivasi Pengguna" />
            <h2
                ref={title}
                tabIndex={-1}
                className="sr-only"
            >
                Akun menunggu aktivasi
            </h2>

            <div className="space-y-6">
                {/* Filter & Search Bar (Gambar 4 Style) */}
                <Card>
                    <CardContent className="p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-muted" />
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    setCurrentPage(1);
                                }}
                                placeholder="Cari nama atau email pengguna..."
                                aria-label="Cari nama atau email pengguna"
                                className="w-full pl-9 pr-4 py-2 text-xs rounded-lg border border-border bg-surface text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                            />
                        </div>

                        <div className="flex items-center gap-2 self-end md:self-auto">
                            <span className="text-xs text-muted font-medium">Status:</span>
                            <div className="inline-flex p-0.5 bg-soft rounded-lg text-xs">
                                <span className="px-3 py-1.5 rounded-md font-semibold bg-surface shadow-xs text-warning-dark">
                                    Menunggu Aktivasi ({users.data.length})
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Main Data Table */}
                <Card className="overflow-hidden border-border bg-surface shadow-xs">
                    {filteredUsers.length === 0 ? (
                        <div className="p-8">
                            <EmptyState
                                icon={ShieldCheck}
                                title="Tidak ada akun yang menunggu aktivasi"
                                description="Semua akun pengguna yang terdaftar telah diproses atau belum ada akun baru yang memerlukan aktivasi."
                                variant="inline"
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center whitespace-nowrap">NO</TableHead>
                                        <TableHead className="min-w-[260px] whitespace-nowrap">PENGGUNA</TableHead>
                                        <TableHead className="min-w-[180px] whitespace-nowrap">TANGGAL TERDAFTAR</TableHead>
                                        <TableHead className="text-center whitespace-nowrap">STATUS</TableHead>
                                        {canActivate && (
                                            <TableHead className="text-right w-28 whitespace-nowrap">AKSI</TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {paginatedUsers.map((user, index) => {
                                        const rowNumber = (currentPage - 1) * perPage + index + 1;
                                        return (
                                            <TableRow key={user.id} className="transition-colors hover:bg-soft/40">
                                                <TableCell className="text-center font-mono text-xs text-muted">
                                                    {rowNumber}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-start gap-3">
                                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary border border-primary/20">
                                                            {user.nama.charAt(0).toUpperCase()}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <h3 className="break-words text-sm font-semibold text-ink">
                                                                {user.nama}
                                                            </h3>
                                                            <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted break-all">
                                                                <Mail className="h-3 w-3 text-muted/70 shrink-0" aria-hidden="true" />
                                                                {user.email}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {user.created_at ? (
                                                        <span className="flex items-center gap-1.5 text-xs text-muted font-medium whitespace-nowrap">
                                                            <Calendar className="h-3 w-3 text-muted/70 shrink-0" aria-hidden="true" />
                                                            {formatDate(user.created_at)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-center whitespace-nowrap">
                                                    <Badge variant="warning" size="sm" dot>
                                                        Menunggu aktivasi
                                                    </Badge>
                                                </TableCell>
                                                {canActivate && (
                                                    <TableCell className="text-right whitespace-nowrap">
                                                        <Button
                                                            type="button"
                                                            variant="primary"
                                                            size="sm"
                                                            className="gap-1.5 shadow-xs"
                                                            aria-label={`Aktifkan ${user.nama}`}
                                                            onClick={(event) => {
                                                                trigger.current = event.currentTarget;
                                                                setSelected(user);
                                                            }}
                                                        >
                                                            <UserCheck className="h-3.5 w-3.5" aria-hidden="true" />
                                                            Aktifkan
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
                        total={filteredUsers.length}
                        currentPage={currentPage}
                        perPage={perPage}
                        lastPage={Math.max(1, Math.ceil(filteredUsers.length / perPage))}
                        onPageChange={(page) => setCurrentPage(page)}
                        onPerPageChange={(newPerPage) => {
                            setPerPage(newPerPage);
                            setCurrentPage(1);
                        }}
                        resourceName="pengguna"
                    />
                </Card>
            </div>

            {selected && <ActivationDialog key={selected.id} user={selected} onClose={close} />}
        </AuthenticatedLayout>
    );
}
