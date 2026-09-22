import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import { 
    Plus, 
    FileText, 
    Layers, 
    Globe, 
    Target, 
    Edit2, 
    Trash2, 
    Check, 
    AlertCircle, 
    SlidersHorizontal,
    Search,
    ShieldAlert
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { 
    JenisBerkasModal, 
    JenisBerkasFormData, 
    IndikatorOption 
} from './Partials/JenisBerkasModal';
import { AuditReasonModal } from './Partials/AuditReasonModal';

export interface JenisBerkasItem {
    id: string;
    nama: string;
    tahap: 'rencana_aksi' | 'pengukuran' | 'kegiatan';
    indikator_id: string | null;
    wajib: boolean;
    keterangan: string | null;
    izinkan_file: boolean;
    izinkan_tautan: boolean;
    izinkan_teks: boolean;
    semua_mode_wajib: boolean;
    urutan: number;
    format_diizinkan: string | null;
    ukuran_maks_kb: number | null;
    aktif: boolean;
    updated_at?: string;
    indikator?: {
        id: string;
        kode: string;
        nama: string;
        is_aktif?: boolean;
    } | null;
}

interface IndexProps {
    jenisBerkasList: JenisBerkasItem[];
    indikators: IndikatorOption[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
    unggahanAktif?: boolean;
}

const defaultFormData: JenisBerkasFormData = {
    nama: '',
    tahap: 'pengukuran',
    indikator_id: '',
    wajib: false,
    keterangan: '',
    izinkan_file: true,
    izinkan_tautan: false,
    izinkan_teks: false,
    semua_mode_wajib: false,
    urutan: 0,
    format_diizinkan: '',
    ukuran_maks_kb: null,
    aktif: true,
};

export default function JenisBerkasIndex({
    jenisBerkasList = [],
    indikators = [],
    can = { create: false, update: false, delete: false },
    unggahanAktif = true,
}: IndexProps) {
    const [selectedTahap, setSelectedTahap] = useState<string>('semua');
    const [searchQuery, setSearchQuery] = useState<string>('');

    // Modal Form State
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState<JenisBerkasFormData>(defaultFormData);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Modal Audit Reason State
    const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
    const [auditAction, setAuditAction] = useState<'update' | 'delete' | null>(null);
    const [targetItem, setTargetItem] = useState<JenisBerkasItem | null>(null);
    const [auditError, setAuditError] = useState<string | null>(null);

    // Filter list
    const filteredList = useMemo(() => {
        return jenisBerkasList.filter((item) => {
            const matchesTahap = selectedTahap === 'semua' || item.tahap === selectedTahap;
            const matchesSearch = 
                item.nama.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (item.keterangan && item.keterangan.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (item.indikator && (
                    item.indikator.kode.toLowerCase().includes(searchQuery.toLowerCase()) ||
                    item.indikator.nama.toLowerCase().includes(searchQuery.toLowerCase())
                ));
            return matchesTahap && matchesSearch;
        });
    }, [jenisBerkasList, selectedTahap, searchQuery]);

    // Form handlers
    const handleOpenCreate = () => {
        setIsEditing(false);
        setFormData(defaultFormData);
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    const handleOpenEdit = (item: JenisBerkasItem) => {
        setIsEditing(true);
        setFormData({
            id: item.id,
            nama: item.nama,
            tahap: item.tahap,
            indikator_id: item.indikator_id || '',
            wajib: item.wajib,
            keterangan: item.keterangan || '',
            izinkan_file: item.izinkan_file,
            izinkan_tautan: item.izinkan_tautan,
            izinkan_teks: item.izinkan_teks,
            semua_mode_wajib: item.semua_mode_wajib,
            urutan: item.urutan,
            format_diizinkan: item.format_diizinkan || '',
            ukuran_maks_kb: item.ukuran_maks_kb,
            aktif: item.aktif !== false,
            expected_updated_at: item.updated_at || new Date().toISOString(),
        });
        setFormErrors({});
        setAuditError(null);
        setIsFormModalOpen(true);
    };

    const handleFormChange = (field: keyof JenisBerkasFormData, value: any) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        if (formErrors[field]) {
            setFormErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Validasi client-side minimal 1 mode
        if (!formData.izinkan_file && !formData.izinkan_tautan && !formData.izinkan_teks) {
            setFormErrors((prev) => ({
                ...prev,
                modes: 'Minimal satu mode bukti (file, tautan, atau teks) harus diizinkan.',
            }));
            return;
        }

        if (isEditing) {
            // Edit bertanda sensitif: buka modal alasan audit
            setAuditError(null);
            setIsAuditModalOpen(true);
            setAuditAction('update');
        } else {
            // Create langsung simpan
            setIsSubmitting(true);
            router.post('/jenis-berkas', formData as any, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    setIsSubmitting(false);
                },
                onError: (errs) => {
                    setFormErrors(errs);
                    setIsSubmitting(false);
                },
            });
        }
    };

    const handleOpenDelete = (item: JenisBerkasItem) => {
        setTargetItem(item);
        setAuditAction('delete');
        setAuditError(null);
        setIsAuditModalOpen(true);
    };

    const handleConfirmAudit = (alasan: string) => {
        setAuditError(null);
        if (auditAction === 'update' && formData.id) {
            setIsSubmitting(true);
            router.put(`/jenis-berkas/${formData.id}`, {
                ...formData,
                alasan,
            } as any, {
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setIsFormModalOpen(false);
                    setIsSubmitting(false);
                    setAuditError(null);
                },
                onError: (errs) => {
                    setIsSubmitting(false);
                    if (errs.alasan || errs.konflik) {
                        setAuditError(errs.alasan || errs.konflik);
                    } else {
                        setFormErrors(errs);
                        setIsAuditModalOpen(false);
                    }
                },
            });
        } else if (auditAction === 'delete' && targetItem) {
            setIsSubmitting(true);
            router.delete(`/jenis-berkas/${targetItem.id}`, {
                data: { alasan },
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setTargetItem(null);
                    setIsSubmitting(false);
                    setAuditError(null);
                },
                onError: (errs) => {
                    setIsSubmitting(false);
                    if (errs && (errs.alasan || errs.konflik)) {
                        setAuditError(errs.alasan || errs.konflik);
                    } else {
                        setAuditError('Gagal menghapus persyaratan jenis berkas.');
                    }
                },
            });
        }
    };

    const getTahapBadge = (tahap: string) => {
        switch (tahap) {
            case 'rencana_aksi':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-[#122E92] border border-blue-200">
                        Rencana Aksi
                    </span>
                );
            case 'pengukuran':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Pengukuran
                    </span>
                );
            case 'kegiatan':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                        Kegiatan (SPJ)
                    </span>
                );
            default:
                return <span>{tahap}</span>;
        }
    };

    return (
        <AuthenticatedLayout
            title="Konfigurasi Persyaratan Jenis Berkas"
            breadcrumbs={[
                { label: 'Konfigurasi' },
                { label: 'Persyaratan Jenis Berkas' }
            ]}
        >
            <Head title="Konfigurasi Persyaratan Jenis Berkas" />

            {/* Header & Deskripsi */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 className="text-sm font-semibold text-slate-800">
                        Standar Bukti Dukung Kinerja & Kegiatan
                    </h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Kelola persyaratan bukti dukung per tahap kepatuhan, batasan mode, dan verifikasi kelengkapan SAKIP.
                    </p>
                </div>

                {can.create && (
                    <Button
                        variant="primary"
                        size="sm"
                        data-testid="btn-tambah-persyaratan"
                        onClick={handleOpenCreate}
                        className="gap-1.5"
                    >
                        <Plus className="w-4 h-4" />
                        Tambah Persyaratan
                    </Button>
                )}
            </div>

            {/* Filter Tabs & Search */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 bg-white p-2.5 rounded-xl border border-slate-200">
                {/* Tabs Tahap */}
                <div className="flex items-center gap-1.5 overflow-x-auto">
                    {[
                        { id: 'semua', label: 'Semua Tahap' },
                        { id: 'rencana_aksi', label: 'Rencana Aksi' },
                        { id: 'pengukuran', label: 'Pengukuran' },
                        { id: 'kegiatan', label: 'Kegiatan (SPJ)' },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setSelectedTahap(tab.id)}
                            className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer ${
                                selectedTahap === tab.id
                                    ? 'bg-[#122E92] text-white shadow-xs'
                                    : 'text-slate-600 hover:bg-slate-100'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Search */}
                <div className="relative w-full sm:w-64">
                    <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Cari nama atau indikator..."
                        className="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-slate-200 text-slate-800 placeholder:text-slate-400 focus:border-[#122E92] focus:ring-1 focus:ring-[#122E92] focus:outline-none"
                    />
                </div>
            </div>

            {/* Main Data Table */}
            <Card>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700">
                        <thead className="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th className="px-4 py-3.5 w-12 text-center">NO</th>
                                <th className="px-4 py-3.5 min-w-[220px]">NAMA PERSYARATAN</th>
                                <th className="px-4 py-3.5">TAHAP</th>
                                <th className="px-4 py-3.5 min-w-[180px]">LINGKUP</th>
                                <th className="px-4 py-3.5 text-center">MODE DIIZINKAN</th>
                                <th className="px-4 py-3.5 text-center">KEWAJIBAN</th>
                                <th className="px-4 py-3.5 text-center">STATUS</th>
                                <th className="px-4 py-3.5">BATAS TEKNIS</th>
                                {(can.update || can.delete) && (
                                    <th className="px-4 py-3.5 text-center w-28">AKSI</th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {filteredList.length === 0 ? (
                                <tr>
                                    <td colSpan={can.update || can.delete ? 9 : 8} className="px-4 py-12 text-center text-slate-400">
                                        <div className="flex flex-col items-center justify-center gap-2">
                                            <FileText className="w-8 h-8 text-slate-300" />
                                            <span className="font-medium text-slate-600">
                                                Tidak ada persyaratan jenis berkas ditemukan
                                            </span>
                                            <p className="text-[11px] text-slate-400 max-w-sm">
                                                {searchQuery || selectedTahap !== 'semua'
                                                    ? 'Coba sesuaikan filter tahap atau kata kunci pencarian Anda.'
                                                    : 'Mulai dengan menambahkan standar persyaratan bukti dukung untuk instansi.'}
                                            </p>
                                            {can.create && !searchQuery && selectedTahap === 'semua' && (
                                                <Button
                                                    variant="primary"
                                                    size="sm"
                                                    onClick={handleOpenCreate}
                                                    className="mt-2"
                                                >
                                                    <Plus className="w-4 h-4" />
                                                    Tambah Persyaratan Sekarang
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                filteredList.map((item, index) => (
                                    <tr key={item.id} className="hover:bg-slate-50/80 transition-colors">
                                        {/* No */}
                                        <td className="px-4 py-3.5 text-center font-mono text-slate-500">
                                            {index + 1}
                                        </td>

                                        {/* Nama & Keterangan */}
                                        <td className="px-4 py-3.5">
                                            <div className="font-bold text-slate-900 text-xs">
                                                {item.nama}
                                            </div>
                                            {item.keterangan && (
                                                <div className="text-[11px] text-slate-500 mt-0.5 line-clamp-2">
                                                    {item.keterangan}
                                                </div>
                                            )}
                                        </td>

                                        {/* Tahap */}
                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                            {getTahapBadge(item.tahap)}
                                        </td>

                                        {/* Lingkup Indikator */}
                                        <td className="px-4 py-3.5">
                                            {item.indikator ? (
                                                <div>
                                                    <span className="inline-flex items-center gap-1 font-bold text-[#122E92] text-[11px]">
                                                        <Target className="w-3 h-3" />
                                                        {item.indikator.kode}
                                                    </span>
                                                    <div className="text-[11px] text-slate-600 line-clamp-1 mt-0.5">
                                                        {item.indikator.nama}
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500">
                                                    <Globe className="w-3 h-3 text-slate-400" />
                                                    Global (Semua Indikator)
                                                </span>
                                            )}
                                        </td>

                                        {/* Mode yang Diizinkan */}
                                        <td className="px-4 py-3.5 text-center">
                                            <div className="inline-flex items-center gap-1 flex-wrap justify-center">
                                                {item.izinkan_file && (
                                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                        FILE
                                                    </span>
                                                )}
                                                {item.izinkan_tautan && (
                                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                        TAUTAN
                                                    </span>
                                                )}
                                                {item.izinkan_teks && (
                                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                                        TEKS
                                                    </span>
                                                )}
                                            </div>
                                        </td>

                                        {/* Sifat Kewajiban */}
                                        <td className="px-4 py-3.5 text-center">
                                            <div className="flex flex-col items-center gap-1">
                                                {item.wajib ? (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 uppercase">
                                                        Wajib
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium text-slate-500 bg-slate-100">
                                                        Opsional
                                                    </span>
                                                )}
                                                {item.semua_mode_wajib && (
                                                    <span className="text-[10px] text-amber-700 font-semibold">
                                                        Semua Mode
                                                    </span>
                                                )}
                                            </div>
                                        </td>

                                        {/* Status */}
                                        <td className="px-4 py-3.5 text-center whitespace-nowrap">
                                            {item.aktif !== false ? (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    Aktif
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                                    Nonaktif
                                                </span>
                                            )}
                                        </td>

                                        {/* Batas Teknis */}
                                        <td className="px-4 py-3.5">
                                            {item.izinkan_file ? (
                                                <div className="text-[11px] space-y-0.5">
                                                    <div>
                                                        <span className="text-slate-400">Format: </span>
                                                        <span className="font-mono text-slate-700">
                                                            {item.format_diizinkan || 'Default'}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-slate-400">Maks: </span>
                                                        <span className="font-mono text-slate-700">
                                                            {item.ukuran_maks_kb ? `${item.ukuran_maks_kb} KB` : 'Default'}
                                                        </span>
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="text-slate-400 text-[11px]">N/A</span>
                                            )}
                                        </td>

                                        {/* Aksi */}
                                        {(can.update || can.delete) && (
                                            <td className="px-4 py-3.5 text-center">
                                                <div className="inline-flex items-center gap-1 justify-center">
                                                    {can.update && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleOpenEdit(item)}
                                                            className="p-1.5 text-slate-500 hover:text-[#122E92] hover:bg-slate-100 rounded-md transition-colors cursor-pointer"
                                                            title="Ubah Persyaratan"
                                                        >
                                                            <Edit2 className="w-3.5 h-3.5" />
                                                        </button>
                                                    )}
                                                    {can.delete && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleOpenDelete(item)}
                                                            className="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors cursor-pointer"
                                                            title="Hapus Persyaratan"
                                                        >
                                                            <Trash2 className="w-3.5 h-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </Card>

            {/* Modal Tambah / Ubah */}
            <JenisBerkasModal
                isOpen={isFormModalOpen}
                isEditing={isEditing}
                data={formData}
                errors={formErrors}
                indikators={indikators}
                isLoading={isSubmitting}
                unggahanAktif={unggahanAktif}
                onChange={handleFormChange}
                onClose={() => {
                    if (!isSubmitting) setIsFormModalOpen(false);
                }}
                onSubmit={handleFormSubmit}
            />

            {/* Modal Alasan Audit (Sensitif) */}
            <AuditReasonModal
                isOpen={isAuditModalOpen}
                title={auditAction === 'delete' ? 'Konfirmasi Hapus Persyaratan' : 'Konfirmasi Perubahan Substantif'}
                description={
                    auditAction === 'delete'
                        ? 'Penghapusan katalog jenis berkas bersifat sensitif. Persyaratan yang dihapus tidak lagi berlaku untuk pengajuan berikutnya. Masukkan alasan penghapusan untuk rekaman audit.'
                        : 'Perubahan katalog persyaratan bukti dukung bersifat sensitif dan akan dicatat pada audit trail dengan rekaman kondisi sebelum dan sesudah perubahan. Masukkan alasan perubahan.'
                }
                confirmText={auditAction === 'delete' ? 'Hapus Persyaratan' : 'Simpan Perubahan'}
                confirmVariant={auditAction === 'delete' ? 'danger' : 'primary'}
                isLoading={isSubmitting}
                serverError={auditError || undefined}
                onClose={() => {
                    if (!isSubmitting) setIsAuditModalOpen(false);
                }}
                onConfirm={handleConfirmAudit}
            />
        </AuthenticatedLayout>
    );
}
