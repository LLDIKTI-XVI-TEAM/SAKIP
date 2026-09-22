import React, { useState, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { 
    ShieldCheck, 
    Plus, 
    Trash2, 
    Search, 
    AlertTriangle, 
    X, 
    Building2, 
    KeyRound, 
    Info
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';

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
    name: string;
    nama: string;
    email: string;
    roles: string[];
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

interface GrantIndexProps {
    grants: GrantItem[];
    users: UserOption[];
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
    users,
    units,
    unitPermissions,
    is_superadmin = false,
    can,
}: GrantIndexProps) {
    const [search, setSearch] = useState('');
    const [selectedUnitFilter, setSelectedUnitFilter] = useState<string>('all');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [grantToRevoke, setGrantToRevoke] = useState<GrantItem | null>(null);

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

    // Filter daftar grant aktif
    const filteredGrants = useMemo(() => {
        return grants.filter((grant) => {
            const matchSearch =
                grant.user_name.toLowerCase().includes(search.toLowerCase()) ||
                grant.user_email.toLowerCase().includes(search.toLowerCase()) ||
                grant.permission_kode.toLowerCase().includes(search.toLowerCase()) ||
                (grant.unit_nama && grant.unit_nama.toLowerCase().includes(search.toLowerCase()));

            const matchUnit =
                selectedUnitFilter === 'all' ||
                grant.unit_id === selectedUnitFilter;

            return matchSearch && matchUnit;
        });
    }, [grants, search, selectedUnitFilter]);

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/akses/grant', {
            onSuccess: () => {
                createForm.reset();
                setIsCreateOpen(false);
            },
        });
    };

    const handleRevokeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!grantToRevoke) return;

        revokeForm.delete(`/akses/grant/${grantToRevoke.id}`, {
            onSuccess: () => {
                revokeForm.reset();
                setGrantToRevoke(null);
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
                            onClick={() => {
                                createForm.clearErrors();
                                createForm.reset();
                                setIsCreateOpen(true);
                            }}
                            className="bg-[#D6AC48] hover:bg-[#c49a37] text-slate-900 font-semibold shadow-sm shrink-0 flex items-center gap-2"
                        >
                            <Plus className="w-4 h-4" />
                            Beri Grant Baru
                        </Button>
                    )}
                </div>

                {/* Filter & Kontrol */}
                <Card className="border-slate-200">
                    <CardContent className="p-4">
                        <div className="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
                            <div className="flex-1 relative">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <Input
                                    type="text"
                                    placeholder="Cari nama pegawai, email, permission, atau unit..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 text-sm"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <span className="text-xs text-slate-500 font-medium whitespace-nowrap">Filter Unit:</span>
                                <select
                                    value={selectedUnitFilter}
                                    onChange={(e) => setSelectedUnitFilter(e.target.value)}
                                    className="text-xs rounded-md border-slate-200 py-1.5 px-2.5 bg-white text-slate-700 focus:border-[#122E92] focus:ring-[#122E92]"
                                >
                                    <option value="all">Semua Unit</option>
                                    {units.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.nama}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabel Daftar Grant Aktif */}
                <Card className="border-slate-200 shadow-xs">
                    <CardHeader className="bg-slate-50/70 border-b border-slate-200/80 px-6 py-4 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-base font-semibold text-slate-800 flex items-center gap-2">
                                <span>Daftar Grant Izin Unit Aktif</span>
                                <span className="text-xs px-2.5 py-0.5 rounded-full bg-blue-50 text-[#122E92] font-semibold border border-blue-200">
                                    {filteredGrants.length} data
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
                                {filteredGrants.length === 0 ? (
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
                                    filteredGrants.map((grant) => (
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
                                                <div className="text-slate-400 mt-0.5">{grant.created_at}</div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                {can.revoke_grant && (grant.can_revoke ?? true) ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            revokeForm.clearErrors();
                                                            revokeForm.reset();
                                                            setGrantToRevoke(grant);
                                                        }}
                                                        className="text-red-600 hover:text-red-700 hover:bg-red-50 text-xs flex items-center gap-1.5 ml-auto"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                        Cabut
                                                    </Button>
                                                ) : (
                                                    <span className="text-[11px] text-slate-400 italic font-medium px-2 py-0.5 rounded bg-slate-100/80">
                                                        Hanya Superadmin
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

            {/* MODAL: Beri Grant Baru */}
            {isCreateOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-6 py-4 bg-[#122E92] text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="w-5 h-5 text-[#D6AC48]" />
                                <h3 className="font-semibold text-base">Beri Izin Tambahan per Unit</h3>
                            </div>
                            <button
                                onClick={() => setIsCreateOpen(false)}
                                className="text-white/80 hover:text-white"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="p-6 space-y-4">
                            <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900 flex items-start gap-2">
                                <Info className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                <div className="space-y-1">
                                    <p>
                                        Form ini khusus untuk permission berscope unit. Grant tidak mengubah role pengguna, dan seluruh batas waktu serta verifikasi alur tetap berlaku.
                                    </p>
                                    {!is_superadmin && (
                                        <p className="font-semibold text-amber-800">
                                            Catatan: Admin berwenang mengelola izin unit pegawai non-Admin dan non-Superadmin.
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Pilih Pengguna */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Pilih Pengguna Target <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={createForm.data.user_id}
                                    onChange={(e) => createForm.setData('user_id', e.target.value)}
                                    className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                                    required
                                >
                                    <option value="">-- Pilih Pengguna --</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.nama} ({u.roles.join(', ')}) - {u.email}
                                        </option>
                                    ))}
                                </select>
                                {createForm.errors.user_id && (
                                    <p className="text-xs text-red-600 mt-1">{createForm.errors.user_id}</p>
                                )}
                            </div>

                            {/* Pilih Permission (Hanya butuh_scope = unit) */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Permission Unit-Scoped <span className="text-red-500">*</span>
                                </label>
                                <select
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
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Unit Organisasi Target <span className="text-red-500">*</span>
                                </label>
                                <select
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
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Alasan Pemberian Izin (Audit) <span className="text-red-500">*</span>
                                </label>
                                <textarea
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

                            <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
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
                                    disabled={createForm.processing}
                                    className="bg-[#122E92] hover:bg-[#0d226b] text-white"
                                >
                                    {createForm.processing ? 'Menyimpan...' : 'Simpan & Catat Audit'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL: Cabut Grant (Modal Alasan Audit) */}
            {grantToRevoke && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-6 py-4 bg-red-600 text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="w-5 h-5 text-red-100" />
                                <h3 className="font-semibold text-base">Konfirmasi Pencabutan Izin</h3>
                            </div>
                            <button
                                onClick={() => setGrantToRevoke(null)}
                                className="text-white/80 hover:text-white"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleRevokeSubmit} className="p-6 space-y-4">
                            <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-800">
                                <p className="font-semibold mb-1">Perhatian:</p>
                                <p>
                                    Anda akan mencabut izin <strong className="font-mono">{grantToRevoke.permission_kode}</strong> pada unit <strong>{grantToRevoke.unit_nama}</strong> dari pegawai <strong>{grantToRevoke.user_name}</strong>.
                                </p>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Alasan Pencabutan Izin (Wajib Audit) <span className="text-red-500">*</span>
                                </label>
                                <textarea
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

                            <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setGrantToRevoke(null)}
                                    disabled={revokeForm.processing}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={revokeForm.processing}
                                    className="bg-red-600 hover:bg-red-700 text-white"
                                >
                                    {revokeForm.processing ? 'Mencabut...' : 'Cabut Izin Sekarang'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
