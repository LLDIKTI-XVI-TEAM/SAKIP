import React, { useState, useMemo } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { 
    Building2, 
    Plus, 
    Edit2, 
    Trash2, 
    Search, 
    Power, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle,
    X
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useLabelUnit } from '@/hooks/useLabelUnit';

interface UnitItem {
    id: string;
    nama: string;
    status: 'aktif' | 'nonaktif';
    is_active: boolean;
    version_token?: string;
    snapshot?: {
        nama: string;
        status: string;
    };
    expected_nama?: string;
    expected_status?: string;
    indikators_count: number;
    rencana_aksis_count: number;
    kegiatans_count: number;
    grants_count: number;
    denies_count?: number;
    jadwal_snapshots_count?: number;
    is_deletable: boolean;
    can: {
        update: boolean;
        delete: boolean;
    };
}

interface UnitIndexProps {
    units: UnitItem[];
    can: {
        create: boolean;
    };
}

export default function UnitIndex({ units, can }: UnitIndexProps) {
    const page = usePage();
    const pageErrors = (page.props as { errors?: Record<string, string> }).errors;
    const recovery = useAuthRecovery();
    const labelUnit = useLabelUnit();

    const [search, setSearch] = useState('');
    const [filterStatus, setFilterStatus] = useState<'all' | 'active' | 'inactive'>('all');
    const [statusError, setStatusError] = useState<string | null>(null);

    // Modals state
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingUnit, setEditingUnit] = useState<UnitItem | null>(null);
    const [deletingUnit, setDeletingUnit] = useState<UnitItem | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteError, setDeleteError] = useState('');

    // Form Tambah Unit
    const createForm = useForm({
        nama: '',
        status: 'aktif' as 'aktif' | 'nonaktif',
    });

    // Form Edit Unit
    const editForm = useForm<{
        nama: string;
        status: 'aktif' | 'nonaktif';
        version_token: string;
        expected_nama: string;
        expected_status: string;
        snapshot: { nama: string; status: string } | null;
    }>({
        nama: '',
        status: 'aktif',
        version_token: '',
        expected_nama: '',
        expected_status: '',
        snapshot: null,
    });

    const filteredUnits = useMemo(() => {
        return units.filter((u) => {
            const matchesSearch = u.nama.toLowerCase().includes(search.toLowerCase());

            const matchesStatus = 
                filterStatus === 'all' ||
                (filterStatus === 'active' && u.is_active) ||
                (filterStatus === 'inactive' && !u.is_active);

            return matchesSearch && matchesStatus;
        });
    }, [units, search, filterStatus]);

    const handleOpenCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setStatusError(null);
        setIsCreateOpen(true);
    };

    const handleOpenEdit = (unit: UnitItem) => {
        setEditingUnit(unit);
        editForm.setData({
            nama: unit.nama,
            status: unit.status,
            version_token: unit.version_token ?? '',
            expected_nama: unit.nama,
            expected_status: unit.status,
            snapshot: {
                nama: unit.nama,
                status: unit.status,
            },
        });
        editForm.clearErrors();
        setStatusError(null);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (createForm.processing || Boolean(recovery.recovery)) return;

        createForm.post('/unit', {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                setStatusError(null);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: '/unit', mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Izin tindakan ditolak. Anda tidak memiliki wewenang untuk menambahkan unit organisasi.'
                    : 'Gagal menambahkan unit organisasi.');
                createForm.setError('nama', message);
                return false;
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUnit || editForm.processing || Boolean(recovery.recovery)) return;

        editForm.post(`/unit/${editingUnit.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingUnit(null);
                setStatusError(null);
            },
            onError: (errors: Record<string, string>) => {
                const message = errors.version_token || errors.konflik || errors.snapshot || errors.expected_state || errors.status || errors.nama || Object.values(errors)[0] || 'Gagal menyimpan perubahan unit organisasi.';
                setStatusError(message);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/unit/${editingUnit.id}`, mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Izin tindakan ditolak. Anda tidak memiliki wewenang untuk mengubah unit organisasi.'
                    : 'Gagal menyimpan perubahan unit organisasi.');
                setStatusError(message);
                return false;
            },
        });
    };

    const handleToggleStatus = (unit: UnitItem) => {
        if (Boolean(recovery.recovery)) return;
        const nextStatus = unit.status === 'aktif' ? 'nonaktif' : 'aktif';
        if (confirm(`Ubah status unit '${unit.nama}' menjadi ${nextStatus === 'aktif' ? 'Aktif' : 'Nonaktif'}?`)) {
            setStatusError(null);
            router.post(`/unit/${unit.id}`, {
                nama: unit.nama,
                status: nextStatus,
                version_token: unit.version_token ?? '',
                expected_nama: unit.nama,
                expected_status: unit.status,
                snapshot: {
                    nama: unit.nama,
                    status: unit.status,
                },
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setStatusError(null);
                },
                onError: (errors: Record<string, string>) => {
                    const message = errors.version_token || errors.konflik || errors.snapshot || errors.expected_state || errors.status || errors.nama || Object.values(errors)[0] || 'Gagal mengubah status unit organisasi.';
                    setStatusError(message);
                },
                onHttpException: (response) => {
                    if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/unit/${unit.id}`, mutation: true })) {
                        return false;
                    }
                    const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                        ? response.data.message
                        : null;
                    const message = serverMsg || (response.status === 403
                        ? 'Izin tindakan ditolak. Anda tidak memiliki wewenang untuk mengubah status unit organisasi.'
                        : 'Gagal mengubah status unit organisasi.');
                    setStatusError(message);
                    return false;
                },
            });
        }
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!deletingUnit || isDeleting || Boolean(recovery.recovery)) return;

        if (deleteReason.trim().length < 5) {
            setDeleteError('Alasan penghapusan unit wajib diisi minimal 5 karakter.');
            return;
        }

        setIsDeleting(true);
        router.delete(`/unit/${deletingUnit.id}`, {
            data: {
                alasan: deleteReason.trim(),
            },
            preserveScroll: true,
            onSuccess: () => {
                setDeletingUnit(null);
                setDeleteReason('');
                setDeleteError('');
            },
            onError: (errors: Record<string, string>) => {
                const message = errors.alasan || errors.error || Object.values(errors)[0] || 'Gagal menghapus unit organisasi.';
                setDeleteError(message);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'delete', path: `/unit/${deletingUnit.id}`, mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, snapshot jadwal, atau izin terkait.'
                    : 'Gagal menghapus unit organisasi. Silakan coba kembali.');
                setDeleteError(message);
                return false;
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    return (
        <AuthenticatedLayout
            title={`Master ${labelUnit}`}
            breadcrumbs={[
                { label: 'Pengaturan Master' },
                { label: labelUnit },
            ]}
        >
            <Head title={`Master ${labelUnit}`} />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Title & Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-[#122E92] flex items-center gap-2">
                            <Building2 className="w-6 h-6 text-[#D6AC48]" />
                            Pengelolaan Master {labelUnit}
                        </h1>
                        <p className="text-xs text-slate-500 mt-1">
                            Kelola {labelUnit.toLowerCase()} pemilik indikator kinerja, rencana aksi, dan kewenangan operasional SAKIP.
                        </p>
                    </div>

                    {can.create && (
                        <Button 
                            onClick={handleOpenCreate}
                            className="inline-flex items-center gap-2 bg-[#122E92] hover:bg-[#0a1b5c] text-white shadow-xs self-start sm:self-auto"
                        >
                            <Plus className="w-4 h-4 text-[#D6AC48]" />
                            Tambah {labelUnit} Baru
                        </Button>
                    )}
                </div>

                {/* Status / Global Error Notification */}
                {(statusError || pageErrors?.status) && (
                    <div
                        role="alert"
                        className="flex items-start gap-2.5 rounded-lg border border-rose-300 bg-rose-50 p-3.5 text-xs text-rose-800 shadow-2xs"
                    >
                        <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                        <div className="flex-1 font-medium">
                            {statusError || pageErrors?.status}
                        </div>
                        <button
                            type="button"
                            onClick={() => setStatusError(null)}
                            className="text-rose-500 hover:text-rose-700 p-0.5 rounded transition-colors"
                            aria-label="Tutup pesan kesalahan"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                )}

                {/* Filter & Search Bar */}
                <Card>
                    <CardContent className="p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={`Cari nama ${labelUnit.toLowerCase()}...`}
                                className="w-full pl-9 pr-4 py-2 text-xs rounded-lg border border-slate-200 focus:outline-hidden focus:ring-2 focus:ring-[#122E92]/30 focus:border-[#122E92] transition-colors"
                            />
                        </div>

                        <div className="flex items-center gap-2 self-end md:self-auto">
                            <span className="text-xs text-slate-500 font-medium">Status:</span>
                            <div className="inline-flex p-0.5 bg-slate-100 rounded-lg text-xs">
                                <button
                                    onClick={() => setFilterStatus('all')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        filterStatus === 'all' ? 'bg-white shadow-xs text-[#122E92] font-semibold' : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    Semua ({units.length})
                                </button>
                                <button
                                    onClick={() => setFilterStatus('active')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        filterStatus === 'active' ? 'bg-white shadow-xs text-emerald-700 font-semibold' : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    Aktif ({units.filter((u) => u.is_active).length})
                                </button>
                                <button
                                    onClick={() => setFilterStatus('inactive')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        filterStatus === 'inactive' ? 'bg-white shadow-xs text-rose-700 font-semibold' : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    Nonaktif ({units.filter((u) => !u.is_active).length})
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Table Unit */}
                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[11px]">
                                    <th className="py-3.5 px-4 w-12 text-center">No</th>
                                    <th className="py-3.5 px-4">Nama {labelUnit}</th>
                                    <th className="py-3.5 px-4 text-center">Indikator</th>
                                    <th className="py-3.5 px-4 text-center">Rencana Aksi</th>
                                    <th className="py-3.5 px-4 text-center">Kegiatan</th>
                                    <th className="py-3.5 px-4 text-center">Grant Izin</th>
                                    <th className="py-3.5 px-4 text-center">Status</th>
                                    <th className="py-3.5 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredUnits.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-slate-400">
                                            <Building2 className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                            Tidak ada data {labelUnit.toLowerCase()} yang cocok dengan kriteria pencarian.
                                        </td>
                                    </tr>
                                ) : (
                                    filteredUnits.map((unit, index) => (
                                        <tr 
                                            key={unit.id}
                                            className="hover:bg-slate-50/70 transition-colors group"
                                        >
                                            <td className="py-3.5 px-4 text-center font-medium text-slate-400">
                                                {index + 1}
                                            </td>
                                            <td className="py-3.5 px-4 font-semibold text-slate-900">
                                                {unit.nama}
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-flex items-center justify-center px-2.5 py-0.5 rounded-full font-bold text-[11px] ${
                                                    unit.indikators_count > 0 
                                                        ? 'bg-blue-50 text-blue-700' 
                                                        : 'bg-slate-100 text-slate-400'
                                                }`}>
                                                    {unit.indikators_count}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-flex items-center justify-center px-2.5 py-0.5 rounded-full font-bold text-[11px] ${
                                                    unit.rencana_aksis_count > 0 
                                                        ? 'bg-indigo-50 text-indigo-700' 
                                                        : 'bg-slate-100 text-slate-400'
                                                }`}>
                                                    {unit.rencana_aksis_count}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-flex items-center justify-center px-2.5 py-0.5 rounded-full font-bold text-[11px] ${
                                                    unit.kegiatans_count > 0 
                                                        ? 'bg-amber-50 text-amber-700' 
                                                        : 'bg-slate-100 text-slate-400'
                                                }`}>
                                                    {unit.kegiatans_count}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-flex items-center justify-center px-2.5 py-0.5 rounded-full font-bold text-[11px] ${
                                                    unit.grants_count > 0 
                                                        ? 'bg-purple-50 text-purple-700' 
                                                        : 'bg-slate-100 text-slate-400'
                                                }`}>
                                                    {unit.grants_count}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                {unit.is_active ? (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                                        <CheckCircle2 className="w-3 h-3" />
                                                        Aktif
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                                        <XCircle className="w-3 h-3" />
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {unit.can.update && (
                                                        <>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => handleToggleStatus(unit)}
                                                                title={unit.is_active ? 'Nonaktifkan Unit' : 'Aktifkan Unit'}
                                                                className={`h-8 w-8 p-0 ${
                                                                    unit.is_active 
                                                                        ? 'text-amber-600 hover:bg-amber-50 hover:border-amber-200' 
                                                                        : 'text-emerald-600 hover:bg-emerald-50 hover:border-emerald-200'
                                                                }`}
                                                            >
                                                                <Power className="w-3.5 h-3.5" />
                                                            </Button>

                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => handleOpenEdit(unit)}
                                                                title="Edit Data Unit"
                                                                className="h-8 w-8 p-0 text-[#122E92] hover:bg-blue-50 hover:border-blue-200"
                                                            >
                                                                <Edit2 className="w-3.5 h-3.5" />
                                                            </Button>
                                                        </>
                                                    )}

                                                    {unit.can.delete ? (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => {
                                                                setDeletingUnit(unit);
                                                                setDeleteReason('');
                                                                setDeleteError('');
                                                            }}
                                                            title="Hapus Unit Kosong (Superadmin)"
                                                            className="h-8 w-8 p-0 text-rose-600 hover:bg-rose-50 hover:border-rose-200"
                                                        >
                                                            <Trash2 className="w-3.5 h-3.5" />
                                                        </Button>
                                                    ) : (
                                                        !unit.is_deletable && (
                                                            <span 
                                                                title="Tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator, rencana aksi, kegiatan, snapshot jadwal, atau izin."
                                                                className="inline-flex items-center justify-center h-8 w-8 text-slate-300 cursor-not-allowed"
                                                            >
                                                                <Trash2 className="w-3.5 h-3.5 opacity-30" />
                                                            </span>
                                                        )
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            {/* Modal Tambah Unit */}
            <Modal
                isOpen={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                size="lg"
                title={
                    <div className="flex items-center gap-2 text-slate-900 font-semibold text-base">
                        <Plus className="w-4 h-4 text-[#D6AC48]" />
                        <span>Tambah {labelUnit} Baru</span>
                    </div>
                }
                description={`Tambahkan ${labelUnit.toLowerCase()} baru ke dalam master data SAKIP.`}
            >
                <form onSubmit={handleCreateSubmit} className="space-y-4 text-xs">
                    <div className="space-y-1.5">
                        <label htmlFor="create_unit_nama" className="font-semibold text-slate-700">
                            Nama {labelUnit} <span className="text-rose-500">*</span>
                        </label>
                        <Input
                            id="create_unit_nama"
                            type="text"
                            value={createForm.data.nama}
                            onChange={(e) => createForm.setData('nama', e.target.value)}
                            placeholder="Contoh: Bagian Umum / Pokja Kelembagaan"
                            required
                        />
                        {createForm.errors.nama && (
                            <p className="text-[11px] text-rose-600">{createForm.errors.nama}</p>
                        )}
                    </div>

                    <div className="space-y-1.5 pt-1">
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="create_is_active"
                                checked={createForm.data.status === 'aktif'}
                                onChange={(e) => {
                                    createForm.setData('status', e.target.checked ? 'aktif' : 'nonaktif');
                                    if (createForm.errors.status) {
                                        createForm.clearErrors('status');
                                    }
                                }}
                                className="w-4 h-4 rounded text-[#122E92] border-slate-300 focus:ring-[#122E92]"
                            />
                            <label htmlFor="create_is_active" className="font-semibold text-slate-700 cursor-pointer">
                                Status Langsung Aktif
                            </label>
                        </div>
                        {createForm.errors.status && (
                            <p role="alert" className="text-[11px] font-medium text-rose-600">{createForm.errors.status}</p>
                        )}
                    </div>

                    <AuthRecoveryNotice recovery={recovery.recovery} pending={createForm.processing} />

                    <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsCreateOpen(false)}
                            disabled={createForm.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={createForm.processing || Boolean(recovery.recovery)}
                            className="bg-[#122E92] hover:bg-[#0a1b5c] text-white"
                        >
                            {createForm.processing ? 'Menyimpan...' : 'Simpan Unit'}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal Edit Unit */}
            <Modal
                isOpen={!!editingUnit}
                onClose={() => setEditingUnit(null)}
                size="lg"
                title={
                    <div className="flex items-center gap-2 text-slate-900 font-semibold text-base">
                        <Edit2 className="w-4 h-4 text-[#122E92]" />
                        <span>Edit {labelUnit}: {editingUnit?.nama}</span>
                    </div>
                }
                description={`Perbarui informasi nama atau status keaktifan ${labelUnit.toLowerCase()}.`}
            >
                <form onSubmit={handleEditSubmit} className="space-y-4 text-xs">
                    {(editForm.errors.version_token || (editForm.errors as Record<string, string>).konflik || (editForm.errors as Record<string, string>).snapshot || (editForm.errors as Record<string, string>).expected_state) && (
                        <div
                            role="alert"
                            className="flex items-start gap-2.5 rounded-lg border border-rose-300 bg-rose-50 p-3 text-xs text-rose-800 shadow-2xs"
                        >
                            <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                            <div className="flex-1 font-medium">
                                {editForm.errors.version_token || (editForm.errors as Record<string, string>).konflik || (editForm.errors as Record<string, string>).snapshot || (editForm.errors as Record<string, string>).expected_state}
                            </div>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <label htmlFor="edit_unit_nama" className="font-semibold text-slate-700">
                            Nama {labelUnit} <span className="text-rose-500">*</span>
                        </label>
                        <Input
                            id="edit_unit_nama"
                            type="text"
                            value={editForm.data.nama}
                            onChange={(e) => editForm.setData('nama', e.target.value)}
                            required
                        />
                        {editForm.errors.nama && (
                            <p className="text-[11px] text-rose-600">{editForm.errors.nama}</p>
                        )}
                    </div>

                    <div className="space-y-1.5 pt-1">
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="edit_is_active"
                                checked={editForm.data.status === 'aktif'}
                                onChange={(e) => {
                                    editForm.setData('status', e.target.checked ? 'aktif' : 'nonaktif');
                                    if (editForm.errors.status) {
                                        editForm.clearErrors('status');
                                    }
                                }}
                                className="w-4 h-4 rounded text-[#122E92] border-slate-300 focus:ring-[#122E92]"
                            />
                            <label htmlFor="edit_is_active" className="font-semibold text-slate-700 cursor-pointer">
                                Unit Aktif
                            </label>
                        </div>
                        {editForm.errors.status && (
                            <p role="alert" className="text-[11px] font-medium text-rose-600">{editForm.errors.status}</p>
                        )}
                    </div>

                    <AuthRecoveryNotice recovery={recovery.recovery} pending={editForm.processing} />

                    <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditingUnit(null)}
                            disabled={editForm.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={editForm.processing || Boolean(recovery.recovery)}
                            className="bg-[#122E92] hover:bg-[#0a1b5c] text-white"
                        >
                            {editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal Konfirmasi Hapus Unit Kosong (Superadmin) */}
            <Modal
                isOpen={!!deletingUnit}
                onClose={() => {
                    setDeletingUnit(null);
                    setDeleteReason('');
                    setDeleteError('');
                }}
                size="md"
                title={
                    <div className="flex items-center gap-2 text-rose-900 font-semibold text-base">
                        <AlertTriangle className="w-5 h-5 text-rose-600 shrink-0" />
                        <span>Hapus Unit: {deletingUnit?.nama}?</span>
                    </div>
                }
                description="Aksi ini hanya dapat dilakukan oleh Superadmin untuk unit yang tidak memiliki keterkaitan data. Tindakan ini bersifat permanen."
            >
                <form onSubmit={handleDeleteSubmit} className="space-y-4 text-xs">
                    {deleteError && (
                        <div
                            role="alert"
                            className="flex items-start gap-2.5 rounded-lg border border-rose-300 bg-rose-50 p-3 text-xs text-rose-800 shadow-2xs"
                        >
                            <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                            <div className="flex-1 font-medium">
                                {deleteError}
                            </div>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <label htmlFor="delete_alasan" className="block font-semibold text-slate-700">
                            Alasan Penghapusan <span className="text-rose-600">*</span>
                        </label>
                        <textarea
                            id="delete_alasan"
                            rows={3}
                            value={deleteReason}
                            onChange={(e) => {
                                setDeleteReason(e.target.value);
                                if (e.target.value.trim().length >= 5) {
                                    setDeleteError('');
                                }
                            }}
                            placeholder="Masukkan dasar administratif penghapusan unit (minimal 5 karakter)..."
                            className="w-full text-xs rounded-lg border border-slate-300 p-2.5 focus:outline-hidden focus:ring-2 focus:ring-rose-500/30 focus:border-rose-500 transition-colors"
                            required
                            minLength={5}
                            disabled={isDeleting}
                        />
                        {deleteError && (
                            <p className="text-[11px] text-rose-600">{deleteError}</p>
                        )}
                        <p className="text-[11px] text-slate-500">
                            Alasan tertulis diwajibkan sebagai rekaman permanen pada audit trail SAKIP.
                        </p>
                    </div>

                    <AuthRecoveryNotice recovery={recovery.recovery} pending={isDeleting} />

                    <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setDeletingUnit(null);
                                setDeleteReason('');
                                setDeleteError('');
                            }}
                            disabled={isDeleting}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={isDeleting || deleteReason.trim().length < 5 || Boolean(recovery.recovery)}
                            className="bg-rose-600 hover:bg-rose-700 text-white"
                        >
                            {isDeleting ? 'Menghapus...' : 'Hapus Permanen'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
