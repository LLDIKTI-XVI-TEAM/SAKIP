import React, { useState, useMemo } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
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

interface UnitItem {
    id: string;
    nama: string;
    status: 'aktif' | 'nonaktif';
    is_active: boolean;
    indikators_count: number;
    rencana_aksis_count: number;
    kegiatans_count: number;
    grants_count: number;
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
    const [search, setSearch] = useState('');
    const [filterStatus, setFilterStatus] = useState<'all' | 'active' | 'inactive'>('all');

    // Modals state
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingUnit, setEditingUnit] = useState<UnitItem | null>(null);
    const [deletingUnit, setDeletingUnit] = useState<UnitItem | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    // Form Tambah Unit
    const createForm = useForm({
        nama: '',
        status: 'aktif' as 'aktif' | 'nonaktif',
    });

    // Form Edit Unit
    const editForm = useForm({
        nama: '',
        status: 'aktif' as 'aktif' | 'nonaktif',
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
        setIsCreateOpen(true);
    };

    const handleOpenEdit = (unit: UnitItem) => {
        setEditingUnit(unit);
        editForm.setData({
            nama: unit.nama,
            status: unit.status,
        });
        editForm.clearErrors();
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/unit', {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUnit) return;

        editForm.post(`/unit/${editingUnit.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingUnit(null);
            },
        });
    };

    const handleToggleStatus = (unit: UnitItem) => {
        const nextStatus = unit.status === 'aktif' ? 'nonaktif' : 'aktif';
        if (confirm(`Ubah status unit '${unit.nama}' menjadi ${nextStatus === 'aktif' ? 'Aktif' : 'Nonaktif'}?`)) {
            router.post(`/unit/${unit.id}`, {
                nama: unit.nama,
                status: nextStatus,
            }, {
                preserveScroll: true,
            });
        }
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!deletingUnit) return;

        setIsDeleting(true);
        router.delete(`/unit/${deletingUnit.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setDeletingUnit(null);
            },
        });
    };

    return (
        <AuthenticatedLayout
            title="Master Unit Organisasi"
            breadcrumbs={[
                { label: 'Pengaturan Master' },
                { label: 'Unit Organisasi' },
            ]}
        >
            <Head title="Master Unit Organisasi" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Title & Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-[#122E92] flex items-center gap-2">
                            <Building2 className="w-6 h-6 text-[#D6AC48]" />
                            Pengelolaan Master Unit Organisasi
                        </h1>
                        <p className="text-xs text-slate-500 mt-1">
                            Kelola unit kerja pemilik indikator kinerja, rencana aksi, dan kewenangan operasional SAKIP.
                        </p>
                    </div>

                    {can.create && (
                        <Button 
                            onClick={handleOpenCreate}
                            className="inline-flex items-center gap-2 bg-[#122E92] hover:bg-[#0a1b5c] text-white shadow-xs self-start sm:self-auto"
                        >
                            <Plus className="w-4 h-4 text-[#D6AC48]" />
                            Tambah Unit Baru
                        </Button>
                    )}
                </div>

                {/* Filter & Search Bar */}
                <Card>
                    <CardContent className="p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama unit organisasi..."
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
                                    <th className="py-3.5 px-4">Nama Unit Organisasi</th>
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
                                            Tidak ada data unit organisasi yang cocok dengan kriteria pencarian.
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
                                                            onClick={() => setDeletingUnit(unit)}
                                                            title="Hapus Unit Kosong (Superadmin)"
                                                            className="h-8 w-8 p-0 text-rose-600 hover:bg-rose-50 hover:border-rose-200"
                                                        >
                                                            <Trash2 className="w-3.5 h-3.5" />
                                                        </Button>
                                                    ) : (
                                                        !unit.is_deletable && (
                                                            <span 
                                                                title="Tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator, rencana aksi, kegiatan, atau izin."
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
            {isCreateOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
                                <Plus className="w-4 h-4 text-[#D6AC48]" />
                                Tambah Unit Organisasi Baru
                            </h3>
                            <button
                                onClick={() => setIsCreateOpen(false)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-md"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="p-5 space-y-4 text-xs">
                            <div className="space-y-1.5">
                                <label className="font-semibold text-slate-700">Nama Unit Organisasi <span className="text-rose-500">*</span></label>
                                <Input
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

                            <div className="flex items-center gap-2 pt-1">
                                <input
                                    type="checkbox"
                                    id="create_is_active"
                                    checked={createForm.data.status === 'aktif'}
                                    onChange={(e) => createForm.setData('status', e.target.checked ? 'aktif' : 'nonaktif')}
                                    className="w-4 h-4 rounded text-[#122E92] border-slate-300 focus:ring-[#122E92]"
                                />
                                <label htmlFor="create_is_active" className="font-semibold text-slate-700 cursor-pointer">
                                    Status Langsung Aktif
                                </label>
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsCreateOpen(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={createForm.processing}
                                    className="bg-[#122E92] hover:bg-[#0a1b5c] text-white"
                                >
                                    {createForm.processing ? 'Menyimpan...' : 'Simpan Unit'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Edit Unit */}
            {editingUnit && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-xl shadow-xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
                                <Edit2 className="w-4 h-4 text-[#122E92]" />
                                Edit Unit: {editingUnit.nama}
                            </h3>
                            <button
                                onClick={() => setEditingUnit(null)}
                                className="text-slate-400 hover:text-slate-600 p-1 rounded-md"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={handleEditSubmit} className="p-5 space-y-4 text-xs">
                            <div className="space-y-1.5">
                                <label className="font-semibold text-slate-700">Nama Unit Organisasi <span className="text-rose-500">*</span></label>
                                <Input
                                    type="text"
                                    value={editForm.data.nama}
                                    onChange={(e) => editForm.setData('nama', e.target.value)}
                                    required
                                />
                                {editForm.errors.nama && (
                                    <p className="text-[11px] text-rose-600">{editForm.errors.nama}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-2 pt-1">
                                <input
                                    type="checkbox"
                                    id="edit_is_active"
                                    checked={editForm.data.status === 'aktif'}
                                    onChange={(e) => editForm.setData('status', e.target.checked ? 'aktif' : 'nonaktif')}
                                    className="w-4 h-4 rounded text-[#122E92] border-slate-300 focus:ring-[#122E92]"
                                />
                                <label htmlFor="edit_is_active" className="font-semibold text-slate-700 cursor-pointer">
                                    Unit Aktif
                                </label>
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEditingUnit(null)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="bg-[#122E92] hover:bg-[#0a1b5c] text-white"
                                >
                                    {editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Konfirmasi Hapus Unit Kosong (Superadmin) */}
            {deletingUnit && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border border-rose-200 animate-in fade-in zoom-in-95 duration-150">
                        <div className="p-5 flex items-start gap-3 bg-rose-50 border-b border-rose-100">
                            <AlertTriangle className="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                            <div>
                                <h3 className="font-bold text-rose-900 text-sm">
                                    Hapus Unit: {deletingUnit.nama}?
                                </h3>
                                <p className="text-xs text-rose-700 mt-1">
                                    Aksi ini hanya dapat dilakukan oleh Superadmin untuk unit yang tidak memiliki keterkaitan data. Tindakan ini bersifat permanen.
                                </p>
                            </div>
                        </div>

                        <form onSubmit={handleDeleteSubmit} className="p-5 space-y-4 text-xs">
                            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDeletingUnit(null)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={isDeleting}
                                    className="bg-rose-600 hover:bg-rose-700 text-white"
                                >
                                    {isDeleting ? 'Menghapus...' : 'Hapus Permanen'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
