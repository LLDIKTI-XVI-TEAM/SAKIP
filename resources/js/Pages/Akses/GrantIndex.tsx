import React, { useState, useRef, useEffect } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ShieldCheck,
    Plus,
    Trash2,
    Search,
    AlertTriangle,
    Building2,
    KeyRound,
    Info,
    RotateCcw,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';

interface GrantItem {
    id: string;
    user_id: string;
    user_name: string;
    user_email: string;
    user_roles: string[];
    permission_id: string;
    permission_kode: string;
    permission_keterangan: string | null;
    unit_id: string | null;
    unit_nama: string | null;
    alasan: string;
    diberikan_oleh_nama: string;
    created_at: string;
    can_revoke?: boolean;
}

interface UserOption {
    id: string;
    name?: string;
    nama: string;
    email: string;
    roles?: string[];
    is_active?: boolean;
}

interface UserOptionPage {
    items: UserOption[];
    page: number;
    hasMore: boolean;
}

function GrantUserLookup({
    id,
    label,
    value,
    onChange,
    disabled,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (id: string) => void;
    disabled?: boolean;
    error?: string;
}) {
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState({ q: '', page: 1 });
    const [result, setResult] = useState<UserOptionPage>({ items: [], page: 1, hasMore: false });
    const [selectedUser, setSelectedUser] = useState<UserOption | null>(null);
    const [loading, setLoading] = useState(false);
    const [failure, setFailure] = useState('');

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        setLoading(true);
        setFailure('');

        const params = new URLSearchParams({
            q: query.q,
            page: String(query.page),
        });

        void fetch(`/akses/grant/opsi/pengguna?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!current) return;
                if (!response.ok) {
                    throw new Error('Daftar pengguna belum dapat dimuat. Coba cari kembali.');
                }
                const data: UserOptionPage = await response.json();
                if (current) {
                    setResult(data);
                }
            })
            .catch((err: unknown) => {
                if (current && !(err instanceof DOMException && err.name === 'AbortError')) {
                    setFailure(err instanceof Error ? err.message : 'Daftar pengguna belum dapat dimuat.');
                }
            })
            .finally(() => {
                if (current) setLoading(false);
            });

        return () => {
            current = false;
            controller.abort();
        };
    }, [query]);

    const items = Array.isArray(result?.items) ? result.items : [];
    const options = selectedUser && !items.some((item) => item.id === selectedUser.id)
        ? [selectedUser, ...items]
        : items;

    const applySearch = () => {
        setQuery({ q: search, page: 1 });
    };

    return (
        <div className="space-y-2">
            <div className="flex items-end gap-2">
                <div className="min-w-0 flex-1">
                    <label htmlFor={`${id}-search`} className="block text-xs font-semibold text-slate-700 mb-1">
                        Cari Pengguna Target
                    </label>
                    <input
                        id={`${id}-search`}
                        type="search"
                        maxLength={100}
                        placeholder="Ketik nama atau email pengguna..."
                        value={search}
                        disabled={disabled}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                applySearch();
                            }
                        }}
                        className="w-full text-xs rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || loading}
                    aria-label="Cari pengguna target"
                    onClick={applySearch}
                    className="text-xs"
                >
                    Cari
                </Button>
            </div>

            <label htmlFor={id} className="block text-xs font-semibold text-slate-700 mb-1">
                {label} <span className="text-red-500">*</span>
            </label>
            <select
                id={id}
                required
                value={value}
                disabled={disabled || loading || Boolean(failure)}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : `${id}-status`}
                className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                onChange={(e) => {
                    const found = options.find((item) => item.id === e.target.value) ?? null;
                    setSelectedUser(found);
                    onChange(e.target.value);
                }}
            >
                <option value="">-- Pilih Pengguna Target --</option>
                {options.map((user) => (
                    <option key={user.id} value={user.id}>
                        {user.nama} ({user.roles && user.roles.length > 0 ? user.roles.join(', ') : 'Tanpa Role'}) - {user.email}
                    </option>
                ))}
            </select>

            {error && (
                <p id={`${id}-error`} role="alert" className="text-xs text-red-600">
                    {error}
                </p>
            )}

            <div className="flex items-center justify-between text-xs text-slate-500">
                <p id={`${id}-status`} role={failure ? 'alert' : 'status'} className={failure ? 'text-red-600' : 'text-slate-500'}>
                    {failure || (loading ? 'Memuat pilihan pengguna…' : result.items.length === 0 ? 'Tidak ada pengguna yang cocok.' : `Halaman ${result.page}`)}
                </p>
                {(result.page > 1 || result.hasMore) && (
                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={disabled || loading || result.page <= 1}
                            onClick={() => setQuery((prev) => ({ ...prev, page: result.page - 1 }))}
                            className="text-xs text-[#122E92] hover:underline disabled:opacity-40 disabled:no-underline"
                        >
                            Sebelumnya
                        </button>
                        <span>·</span>
                        <button
                            type="button"
                            disabled={disabled || loading || !result.hasMore}
                            onClick={() => setQuery((prev) => ({ ...prev, page: result.page + 1 }))}
                            className="text-xs text-[#122E92] hover:underline disabled:opacity-40 disabled:no-underline"
                        >
                            Berikutnya
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

interface UnitOption {
    id: string;
    nama: string;
}

interface PermissionOption {
    id: string;
    name: string;
    kode: string;
    keterangan: string | null;
}

interface PaginatedGrants {
    data: GrantItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface GrantIndexProps {
    grants: PaginatedGrants;
    filters?: {
        search?: string;
        unit_id?: string;
    };
    users?: UserOption[];
    units: UnitOption[];
    unitPermissions: PermissionOption[];
    is_superadmin?: boolean;
    can: {
        create_grant: boolean;
        revoke_grant: boolean;
    };
}

export default function GrantIndex({
    grants,
    filters,
    users = [],
    units,
    unitPermissions,
    is_superadmin = false,
    can,
}: GrantIndexProps) {
    const [search, setSearch] = useState(filters?.search || '');
    const [selectedUnitFilter, setSelectedUnitFilter] = useState<string>(filters?.unit_id || 'all');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [grantToRevoke, setGrantToRevoke] = useState<GrantItem | null>(null);
    const [createError, setCreateError] = useState<string | null>(null);
    const [revokeError, setRevokeError] = useState<string | null>(null);

    const formatTanggal = useFormatTanggal();

    const recovery = useAuthRecovery();

    const createTriggerRef = useRef<HTMLButtonElement | null>(null);
    const revokeTriggerRefs = useRef<Record<string, HTMLButtonElement | null>>({});

    // Form Tambah Grant
    const createForm = useForm({
        user_id: '',
        permission_id: '',
        unit_id: '',
        alasan: '',
    });

    // Form Revoke Grant (Modal Alasan Audit)
    const revokeForm = useForm({
        alasan: '',
    });

    const executeFilter = (newSearch: string, newUnitId: string) => {
        router.get('/akses/grant', {
            search: newSearch.trim() || undefined,
            unit_id: newUnitId !== 'all' ? newUnitId : undefined,
        }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        executeFilter(search, selectedUnitFilter);
    };

    const handleUnitFilterChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const newUnitId = e.target.value;
        setSelectedUnitFilter(newUnitId);
        executeFilter(search, newUnitId);
    };

    const handleResetFilters = () => {
        setSearch('');
        setSelectedUnitFilter('all');
        router.get('/akses/grant', {}, { preserveState: true, replace: true });
    };

    const handleOpenCreate = (e: React.MouseEvent<HTMLButtonElement>) => {
        createTriggerRef.current = e.currentTarget;
        createForm.clearErrors();
        createForm.reset();
        setCreateError(null);
        setIsCreateOpen(true);
    };

    const handleCloseCreate = () => {
        setIsCreateOpen(false);
        setCreateError(null);
        createForm.clearErrors();
        createTriggerRef.current?.focus();
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (createForm.processing || Boolean(recovery.recovery)) return;
        setCreateError(null);

        createForm.post('/akses/grant', {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCreateError(null);
                setIsCreateOpen(false);
                createTriggerRef.current?.focus();
            },
            onError: (errors: Record<string, string>) => {
                const message = errors.permission_id || errors.user_id || errors.unit_id || errors.alasan || Object.values(errors)[0] || 'Validasi pemberian izin unit gagal.';
                setCreateError(message);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: '/akses/grant', mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Wewenang Anda untuk mengelola pemberian izin unit telah dicabut atau ditolak. Periksa akses sebelum mencoba kembali.'
                    : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                setCreateError(message);
                createForm.setError('alasan', message);
                return false;
            },
        });
    };

    const handleOpenRevoke = (grant: GrantItem, el: HTMLButtonElement | null) => {
        revokeTriggerRefs.current[grant.id] = el;
        revokeForm.clearErrors();
        revokeForm.reset();
        setRevokeError(null);
        setGrantToRevoke(grant);
    };

    const handleCloseRevoke = () => {
        const grantId = grantToRevoke?.id;
        setGrantToRevoke(null);
        setRevokeError(null);
        revokeForm.clearErrors();
        if (grantId && revokeTriggerRefs.current[grantId]) {
            revokeTriggerRefs.current[grantId]?.focus();
        }
    };

    const handleRevokeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!grantToRevoke || revokeForm.processing || Boolean(recovery.recovery)) return;

        const grantId = grantToRevoke.id;
        setRevokeError(null);

        revokeForm.delete(`/akses/grant/${grantId}`, {
            preserveScroll: true,
            onSuccess: () => {
                revokeForm.reset();
                setRevokeError(null);
                setGrantToRevoke(null);
                if (revokeTriggerRefs.current[grantId]) {
                    revokeTriggerRefs.current[grantId]?.focus();
                }
            },
            onError: (errors: Record<string, string>) => {
                const message = errors.alasan || errors.error || Object.values(errors)[0] || 'Validasi pencabutan izin unit gagal.';
                setRevokeError(message);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'delete', path: `/akses/grant/${grantId}`, mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Wewenang Anda untuk mengelola pencabutan izin unit telah dicabut atau ditolak. Periksa akses sebelum mencoba kembali.'
                    : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                setRevokeError(message);
                revokeForm.setError('alasan', message);
                return false;
            },
        });
    };

    return (
        <AuthenticatedLayout
            title="Pemberian Grant Izin Tambahan per Unit"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Manajemen Akses' },
                { label: 'Grant Izin Unit' },
            ]}
        >
            <Head title="Grant Izin Unit - SAKIP LLDIKTI XVI" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Banner */}
                <div className="bg-gradient-to-r from-[#122E92] to-[#1e3fae] rounded-xl p-6 text-white shadow-md flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="w-6 h-6 text-[#D6AC48]" />
                            <h1 className="text-xl font-bold tracking-tight">
                                Grant Izin Tambahan per Unit
                            </h1>
                        </div>
                        <p className="text-sm text-blue-100/90 max-w-2xl">
                            Berikan pengecualian izin operasional berscope unit kepada pengguna tanpa mengubah peran utama. Setiap aksi penambahan dan pencabutan dicatat permanen dalam jejak audit.
                        </p>
                    </div>

                    {can.create_grant && (
                        <Button
                            onClick={handleOpenCreate}
                            className="bg-[#D6AC48] hover:bg-[#c49a37] text-slate-900 font-semibold shadow-sm shrink-0 flex items-center gap-2"
                        >
                            <Plus className="w-4 h-4" />
                            Beri Grant Baru
                        </Button>
                    )}
                </div>

                {/* Filter & Kontrol Server-Side */}
                <Card className="border-slate-200">
                    <CardContent className="p-4">
                        <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
                            <div className="flex-1 relative">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <Input
                                    type="text"
                                    placeholder="Cari nama pegawai, email, kode permission, keterangan, atau nama unit..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 text-sm"
                                />
                            </div>

                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs text-slate-500 font-medium whitespace-nowrap">Filter Unit:</span>
                                <select
                                    value={selectedUnitFilter}
                                    onChange={handleUnitFilterChange}
                                    className="text-xs rounded-md border-slate-200 py-1.5 px-2.5 bg-white text-slate-700 focus:border-[#122E92] focus:ring-[#122E92]"
                                >
                                    <option value="all">Semua Unit</option>
                                    {units.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.nama}
                                        </option>
                                    ))}
                                </select>

                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    className="text-xs"
                                >
                                    Cari
                                </Button>

                                {(search || selectedUnitFilter !== 'all') && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleResetFilters}
                                        className="text-xs text-slate-500 hover:text-slate-700 flex items-center gap-1"
                                    >
                                        <RotateCcw className="w-3.5 h-3.5" />
                                        Reset
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Tabel Daftar Grant Aktif */}
                <Card className="border-slate-200 shadow-xs">
                    <CardHeader className="bg-slate-50/70 border-b border-slate-200/80 px-6 py-4 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-base font-semibold text-slate-800 flex items-center gap-2">
                                <span>Daftar Grant Izin Unit Aktif</span>
                                <span className="text-xs px-2.5 py-0.5 rounded-full bg-blue-50 text-[#122E92] font-semibold border border-blue-200">
                                    {grants.total} data
                                </span>
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200 text-xs">
                                <tr>
                                    <th className="px-6 py-3.5">Pegawai Penerima</th>
                                    <th className="px-6 py-3.5">Permission Diberikan</th>
                                    <th className="px-6 py-3.5">Scope Unit Organisasi</th>
                                    <th className="px-6 py-3.5">Alasan Administratif</th>
                                    <th className="px-6 py-3.5">Diberikan Oleh & Waktu</th>
                                    <th className="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {grants.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center text-slate-400">
                                            <div className="flex flex-col items-center justify-center space-y-2">
                                                <KeyRound className="w-8 h-8 text-slate-300" />
                                                <p className="font-medium text-slate-600">Tidak ada grant izin unit yang ditemukan</p>
                                                <p className="text-xs text-slate-400">
                                                    {search || selectedUnitFilter !== 'all'
                                                        ? 'Coba sesuaikan kata kunci pencarian atau filter unit.'
                                                        : 'Belum ada grant izin unit yang diterbitkan.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    grants.data.map((grant) => (
                                        <tr key={grant.id} className="hover:bg-slate-50/60 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-full bg-blue-50 text-[#122E92] flex items-center justify-center font-bold text-xs shrink-0 border border-blue-100">
                                                        {grant.user_name.slice(0, 2).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <div className="font-medium text-slate-900">{grant.user_name}</div>
                                                        <div className="text-xs text-slate-500">{grant.user_email}</div>
                                                        <div className="flex gap-1 mt-1">
                                                            {grant.user_roles.map((r) => (
                                                                <span
                                                                    key={r}
                                                                    className="inline-block text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 uppercase font-semibold"
                                                                >
                                                                    {r}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-blue-50 text-[#122E92] border border-blue-200">
                                                    {grant.permission_kode}
                                                </span>
                                                {grant.permission_keterangan && (
                                                    <p className="text-xs text-slate-500 mt-1 max-w-xs line-clamp-2">
                                                        {grant.permission_keterangan}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    <Building2 className="w-4 h-4 text-[#D6AC48] shrink-0" />
                                                    <span className="font-medium text-slate-800">
                                                        {grant.unit_nama || 'Semua Unit'}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <p className="text-xs text-slate-700 italic max-w-xs bg-slate-50 p-2 rounded border border-slate-100">
                                                    "{grant.alasan}"
                                                </p>
                                            </td>
                                            <td className="px-6 py-4 text-xs text-slate-500">
                                                <div className="font-medium text-slate-700">{grant.diberikan_oleh_nama}</div>
                                                <time dateTime={grant.created_at} className="text-slate-400 mt-0.5 block">
                                                    {grant.created_at
                                                        ? formatTanggal(grant.created_at, { withTime: true })
                                                        : '-'}
                                                </time>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                {can.revoke_grant && (grant.can_revoke ?? true) ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        title="Cabut Izin Unit"
                                                        aria-label={`Cabut izin unit ${grant.permission_kode} untuk ${grant.user_name}`}
                                                        onClick={(e) => handleOpenRevoke(grant, e.currentTarget)}
                                                        className="text-red-600 hover:text-red-700 hover:bg-red-50 text-xs flex items-center gap-1.5 ml-auto"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                        Cabut
                                                    </Button>
                                                ) : (
                                                    <span className="text-[11px] text-slate-400 italic font-medium px-2 py-0.5 rounded bg-slate-100/80">
                                                        Tidak dapat dicabut
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>

                    {/* Navigasi Paginasi Server-side */}
                    {grants.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
                            <div>
                                Menampilkan <span className="font-semibold text-slate-800">{grants.from ?? 0}</span> sampai{' '}
                                <span className="font-semibold text-slate-800">{grants.to ?? 0}</span> dari{' '}
                                <span className="font-semibold text-slate-800">{grants.total}</span> data
                            </div>
                            <nav aria-label="Navigasi halaman grant izin unit" className="flex items-center gap-2">
                                {grants.prev_page_url ? (
                                    <Link
                                        href={grants.prev_page_url}
                                        preserveScroll
                                        preserveState
                                        className="px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-100 text-slate-700 font-medium transition-colors"
                                    >
                                        Sebelumnya
                                    </Link>
                                ) : (
                                    <span className="px-3 py-1.5 rounded border border-slate-200 text-slate-300 cursor-not-allowed">
                                        Sebelumnya
                                    </span>
                                )}

                                <span className="px-2 font-semibold text-slate-700">
                                    Halaman {grants.current_page} dari {grants.last_page}
                                </span>

                                {grants.next_page_url ? (
                                    <Link
                                        href={grants.next_page_url}
                                        preserveScroll
                                        preserveState
                                        className="px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-100 text-slate-700 font-medium transition-colors"
                                    >
                                        Berikutnya
                                    </Link>
                                ) : (
                                    <span className="px-3 py-1.5 rounded border border-slate-200 text-slate-300 cursor-not-allowed">
                                        Berikutnya
                                    </span>
                                )}
                            </nav>
                        </div>
                    )}
                </Card>
            </div>

            {/* MODAL: Beri Grant Baru (Accessible Dialog with Focus Trap & Escape) */}
            <Modal
                isOpen={isCreateOpen}
                onClose={handleCloseCreate}
                size="lg"
                title={
                    <div className="flex items-center gap-2 text-slate-900 font-semibold text-base">
                        <ShieldCheck className="w-5 h-5 text-[#122E92]" />
                        <span>Beri Izin Tambahan per Unit</span>
                    </div>
                }
                description="Berikan pengecualian izin operasional berscope unit kepada pengguna tanpa mengubah peran utama."
            >
                <form onSubmit={handleCreateSubmit} className="space-y-4">
                    {createError && (
                        <div
                            role="alert"
                            className="flex items-start gap-2.5 rounded-lg border border-rose-300 bg-rose-50 p-3 text-xs text-rose-800 shadow-2xs"
                        >
                            <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                            <div className="flex-1 font-medium">
                                {createError}
                            </div>
                        </div>
                    )}

                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900 flex items-start gap-2">
                        <Info className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p>
                                Form ini khusus untuk permission berscope unit. Grant tidak mengubah role pengguna, dan seluruh batas waktu serta verifikasi alur tetap berlaku.
                            </p>
                        </div>
                    </div>

                    {/* Pilih Pengguna */}
                    <GrantUserLookup
                        id="grant-user-select"
                        label="Pilih Pengguna Target"
                        value={createForm.data.user_id}
                        onChange={(id) => createForm.setData('user_id', id)}
                        disabled={createForm.processing}
                        error={createForm.errors.user_id}
                    />

                    {/* Pilih Permission (Hanya butuh_scope = unit) */}
                    <div>
                        <label htmlFor="grant-permission-select" className="block text-xs font-semibold text-slate-700 mb-1">
                            Permission Unit-Scoped <span className="text-red-500">*</span>
                        </label>
                        <select
                            id="grant-permission-select"
                            value={createForm.data.permission_id}
                            onChange={(e) => createForm.setData('permission_id', e.target.value)}
                            className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                            required
                        >
                            <option value="">-- Pilih Permission Berscope Unit --</option>
                            {unitPermissions.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.kode} - {p.keterangan || p.name}
                                </option>
                            ))}
                        </select>
                        {createForm.errors.permission_id && (
                            <p className="text-xs text-red-600 mt-1">{createForm.errors.permission_id}</p>
                        )}
                    </div>

                    {/* Pilih Unit Target */}
                    <div>
                        <label htmlFor="grant-unit-select" className="block text-xs font-semibold text-slate-700 mb-1">
                            Unit Organisasi Target <span className="text-red-500">*</span>
                        </label>
                        <select
                            id="grant-unit-select"
                            value={createForm.data.unit_id}
                            onChange={(e) => createForm.setData('unit_id', e.target.value)}
                            className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                            required
                        >
                            <option value="">-- Pilih Unit Target --</option>
                            {units.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.nama}
                                </option>
                            ))}
                        </select>
                        {createForm.errors.unit_id && (
                            <p className="text-xs text-red-600 mt-1">{createForm.errors.unit_id}</p>
                        )}
                    </div>

                    {/* Alasan Pemberian (Wajib Audit) */}
                    <div>
                        <label htmlFor="grant-alasan-input" className="block text-xs font-semibold text-slate-700 mb-1">
                            Alasan Pemberian Izin (Audit) <span className="text-red-500">*</span>
                        </label>
                        <textarea
                            id="grant-alasan-input"
                            value={createForm.data.alasan}
                            onChange={(e) => createForm.setData('alasan', e.target.value)}
                            rows={3}
                            placeholder="Contoh: Penugasan koordinasi pengisian indikator lintas pokja sesuai SK penugasan..."
                            className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                            required
                        />
                        <p className="text-[11px] text-slate-400 mt-0.5">
                            Wajib diisi. Catatan ini disimpan permanen pada audit trail SAKIP.
                        </p>
                        {createForm.errors.alasan && (
                            <p className="text-xs text-red-600 mt-1">{createForm.errors.alasan}</p>
                        )}
                    </div>

                    <AuthRecoveryNotice recovery={recovery.recovery} pending={createForm.processing} />

                    <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleCloseCreate}
                            disabled={createForm.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={createForm.processing || Boolean(recovery.recovery)}
                            className="bg-[#122E92] hover:bg-[#0d226b] text-white"
                        >
                            {createForm.processing ? 'Menyimpan...' : 'Simpan & Catat Audit'}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* MODAL: Cabut Grant (Accessible Dialog with Focus Trap & Escape) */}
            <Modal
                isOpen={Boolean(grantToRevoke)}
                onClose={handleCloseRevoke}
                size="md"
                title={
                    <div className="flex items-center gap-2 text-rose-700 font-semibold text-base">
                        <AlertTriangle className="w-5 h-5 text-rose-600" />
                        <span>Konfirmasi Pencabutan Izin</span>
                    </div>
                }
                description="Pencabutan izin unit bersifat permanen dan dicatat dalam audit trail."
            >
                {grantToRevoke && (
                    <form onSubmit={handleRevokeSubmit} className="space-y-4">
                        {revokeError && (
                            <div
                                role="alert"
                                className="flex items-start gap-2.5 rounded-lg border border-rose-300 bg-rose-50 p-3 text-xs text-rose-800 shadow-2xs"
                            >
                                <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                                <div className="flex-1 font-medium">
                                    {revokeError}
                                </div>
                            </div>
                        )}

                        <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-800">
                            <p className="font-semibold mb-1">Perhatian:</p>
                            <p>
                                Anda akan mencabut izin <strong className="font-mono">{grantToRevoke.permission_kode}</strong> pada unit <strong>{grantToRevoke.unit_nama}</strong> dari pegawai <strong>{grantToRevoke.user_name}</strong>.
                            </p>
                        </div>

                        <div>
                            <label htmlFor="revoke-alasan-input" className="block text-xs font-semibold text-slate-700 mb-1">
                                Alasan Pencabutan Izin (Wajib Audit) <span className="text-red-500">*</span>
                            </label>
                            <textarea
                                id="revoke-alasan-input"
                                value={revokeForm.data.alasan}
                                onChange={(e) => revokeForm.setData('alasan', e.target.value)}
                                rows={3}
                                placeholder="Contoh: Penugasan penyusunan laporan telah selesai / pergantian personel..."
                                className="w-full text-sm rounded-md border-slate-300 focus:border-red-600 focus:ring-red-600"
                                required
                            />
                            {revokeForm.errors.alasan && (
                                <p className="text-xs text-red-600 mt-1">{revokeForm.errors.alasan}</p>
                            )}
                        </div>

                        <AuthRecoveryNotice recovery={recovery.recovery} pending={revokeForm.processing} />

                        <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleCloseRevoke}
                                disabled={revokeForm.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={revokeForm.processing || Boolean(recovery.recovery)}
                                className="bg-red-600 hover:bg-red-700 text-white"
                            >
                                {revokeForm.processing ? 'Mencabut...' : 'Cabut Izin Sekarang'}
                            </Button>
                        </div>
                    </form>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
