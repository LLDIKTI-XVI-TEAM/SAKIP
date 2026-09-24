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
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/Components/Table';
import { Badge } from '@/Components/Badge';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { 
    JenisBerkasModal, 
    JenisBerkasFormData, 
    IndikatorOption 
} from './Partials/JenisBerkasModal';

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
    created_at?: string;
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
        pengaturan_update?: boolean;
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
    const [isBatasTeknisOnly, setIsBatasTeknisOnly] = useState(false);
    const [formData, setFormData] = useState<JenisBerkasFormData>(defaultFormData);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Modal Audit Reason State
    const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
    const [auditAction, setAuditAction] = useState<'update' | 'delete' | 'update-batas-teknis' | null>(null);
    const [targetItem, setTargetItem] = useState<JenisBerkasItem | null>(null);
    const [auditReason, setAuditReason] = useState<string>('');
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
        setIsBatasTeknisOnly(false);
        setFormData(defaultFormData);
        setFormErrors({});
        setAuditReason('');
        setIsFormModalOpen(true);
    };

    const handleOpenEdit = (item: JenisBerkasItem, batasTeknisOnly: boolean = false) => {
        setIsEditing(true);
        setIsBatasTeknisOnly(batasTeknisOnly);
        setTargetItem(item);
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
            expected_updated_at: item.updated_at || item.created_at || new Date().toISOString(),
        });
        setFormErrors({});
        setAuditReason('');
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

        if (isBatasTeknisOnly) {
            setAuditReason('');
            setAuditError(null);
            setIsAuditModalOpen(true);
            setAuditAction('update-batas-teknis');
            return;
        }

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
            setAuditReason('');
            setAuditError(null);
            setIsAuditModalOpen(true);
            setAuditAction('update');
        } else {
            // Create langsung simpan
            setIsSubmitting(true);
            router.post('/jenis-berkas', formData as any, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                },
                onError: (errs) => {
                    setFormErrors(errs);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    const handleOpenDelete = (item: JenisBerkasItem) => {
        setTargetItem(item);
        setAuditAction('delete');
        setAuditReason('');
        setAuditError(null);
        setIsAuditModalOpen(true);
    };

    const handleConfirmAudit = () => {
        const trimmed = auditReason.trim();
        if (trimmed.length < 5) {
            setAuditError('Alasan perubahan wajib diisi minimal 5 karakter.');
            return;
        }
        if (trimmed.length > 1000) {
            setAuditError('Alasan perubahan maksimal 1.000 karakter.');
            return;
        }
        setAuditError(null);
        if (auditAction === 'update-batas-teknis' && formData.id) {
            setIsSubmitting(true);
            const patchPayload: Record<string, any> = {
                alasan: trimmed,
                expected_updated_at: formData.expected_updated_at,
            };

            const trimmedFormat = (formData.format_diizinkan || '').trim();
            const originalFormat = (targetItem?.format_diizinkan || '').trim();

            if (trimmedFormat !== '') {
                patchPayload.format_diizinkan = trimmedFormat;
            } else if (originalFormat !== '') {
                patchPayload.format_diizinkan = null;
            }

            if (formData.ukuran_maks_kb !== '' && formData.ukuran_maks_kb !== null && formData.ukuran_maks_kb !== undefined) {
                patchPayload.ukuran_maks_kb = Number(formData.ukuran_maks_kb);
            } else if (targetItem && targetItem.ukuran_maks_kb !== null && targetItem.ukuran_maks_kb !== undefined) {
                patchPayload.ukuran_maks_kb = null;
            }

            router.patch(`/jenis-berkas/${formData.id}/batas-teknis`, patchPayload, {
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setIsFormModalOpen(false);
                    setAuditReason('');
                    setAuditError(null);
                },
                onError: (errs) => {
                    if (errs.alasan || errs.konflik || errs.expected_updated_at) {
                        setAuditError(errs.alasan || errs.konflik || errs.expected_updated_at);
                    } else {
                        setFormErrors(errs);
                        setIsAuditModalOpen(false);
                    }
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        } else if (auditAction === 'update' && formData.id) {
            setIsSubmitting(true);
            router.put(`/jenis-berkas/${formData.id}`, {
                ...formData,
                alasan: trimmed,
            } as any, {
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setIsFormModalOpen(false);
                    setAuditReason('');
                    setAuditError(null);
                },
                onError: (errs) => {
                    if (errs.alasan || errs.konflik) {
                        setAuditError(errs.alasan || errs.konflik);
                    } else {
                        setFormErrors(errs);
                        setIsAuditModalOpen(false);
                    }
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        } else if (auditAction === 'delete' && targetItem) {
            setIsSubmitting(true);
            router.delete(`/jenis-berkas/${targetItem.id}`, {
                data: {
                    alasan: trimmed,
                    expected_updated_at: targetItem.updated_at || targetItem.created_at,
                },
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setTargetItem(null);
                    setAuditReason('');
                    setAuditError(null);
                },
                onError: (errs) => {
                    if (errs && (errs.alasan || errs.konflik || errs.expected_updated_at)) {
                        setAuditError(errs.alasan || errs.konflik || errs.expected_updated_at);
                    } else {
                        setAuditError('Gagal menghapus persyaratan jenis berkas.');
                    }
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    const getTahapBadge = (tahap: string) => {
        switch (tahap) {
            case 'rencana_aksi':
                return (
                    <Badge variant="primary" size="sm">
                        Rencana Aksi
                    </Badge>
                );
            case 'pengukuran':
                return (
                    <Badge variant="success" size="sm">
                        Pengukuran
                    </Badge>
                );
            case 'kegiatan':
                return (
                    <Badge variant="warning" size="sm">
                        Kegiatan (SPJ)
                    </Badge>
                );
            default:
                return <Badge variant="muted" size="sm">{tahap}</Badge>;
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
                    <h2 className="text-sm font-semibold text-ink">
                        Standar Bukti Dukung Kinerja & Kegiatan
                    </h2>
                    <p className="text-xs text-muted mt-0.5">
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
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 bg-surface p-2.5 rounded-xl border border-border">
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
                                    ? 'bg-primary text-white shadow-xs'
                                    : 'text-muted hover:bg-soft hover:text-ink'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Search */}
                <div className="relative w-full sm:w-64">
                    <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-muted" />
                    <input
                        id="pencarian-jenis-berkas"
                        aria-label="Cari persyaratan berdasarkan nama atau indikator"
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Cari nama atau indikator..."
                        className="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-border text-ink placeholder:text-muted focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none bg-surface"
                    />
                </div>
            </div>

            {/* Main Data Table */}
            <Card className="overflow-hidden border-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-12 text-center whitespace-nowrap">NO</TableHead>
                            <TableHead className="min-w-[220px] whitespace-nowrap">NAMA PERSYARATAN</TableHead>
                            <TableHead className="whitespace-nowrap">TAHAP</TableHead>
                            <TableHead className="min-w-[180px] whitespace-nowrap">LINGKUP</TableHead>
                            <TableHead className="text-center whitespace-nowrap">MODE DIIZINKAN</TableHead>
                            <TableHead className="text-center whitespace-nowrap">KEWAJIBAN</TableHead>
                            <TableHead className="text-center whitespace-nowrap">STATUS</TableHead>
                            <TableHead className="whitespace-nowrap">BATAS TEKNIS</TableHead>
                            {(can.update || can.delete || can.pengaturan_update) && (
                                <TableHead className="text-center w-28 whitespace-nowrap">AKSI</TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filteredList.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={can.update || can.delete ? 9 : 8} className="px-4 py-12 text-center text-muted">
                                    <div className="flex flex-col items-center justify-center gap-2">
                                        <FileText className="w-8 h-8 text-muted/60" />
                                        <span className="font-medium text-ink">
                                            Tidak ada persyaratan jenis berkas ditemukan
                                        </span>
                                        <p className="text-[11px] text-muted max-w-sm">
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
                                </TableCell>
                            </TableRow>
                        ) : (
                            filteredList.map((item, index) => (
                                <TableRow key={item.id}>
                                    {/* No */}
                                    <TableCell className="text-center font-mono text-muted">
                                        {index + 1}
                                    </TableCell>

                                    {/* Nama & Keterangan */}
                                    <TableCell>
                                        <div className="font-semibold text-ink text-xs">
                                            {item.nama}
                                        </div>
                                        {item.keterangan && (
                                            <div className="text-[11px] text-muted mt-0.5 line-clamp-2">
                                                {item.keterangan}
                                            </div>
                                        )}
                                    </TableCell>

                                    {/* Tahap */}
                                    <TableCell className="whitespace-nowrap">
                                        {getTahapBadge(item.tahap)}
                                    </TableCell>

                                    {/* Lingkup Indikator */}
                                    <TableCell>
                                        {item.indikator ? (
                                            <div>
                                                <span className="inline-flex items-center gap-1 font-semibold text-primary text-[11px]">
                                                    <Target className="w-3 h-3" />
                                                    {item.indikator.kode}
                                                </span>
                                                <div className="text-[11px] text-muted line-clamp-1 mt-0.5">
                                                    {item.indikator.nama}
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 text-[11px] font-medium text-muted">
                                                <Globe className="w-3 h-3 text-muted" />
                                                Global (Semua Indikator)
                                            </span>
                                        )}
                                    </TableCell>

                                    {/* Mode yang Diizinkan */}
                                    <TableCell className="text-center">
                                        <div className="inline-flex items-center gap-1 flex-wrap justify-center">
                                            {item.izinkan_file && (
                                                <Badge variant="muted" size="sm">
                                                    FILE
                                                </Badge>
                                            )}
                                            {item.izinkan_tautan && (
                                                <Badge variant="info" size="sm">
                                                    TAUTAN
                                                </Badge>
                                            )}
                                            {item.izinkan_teks && (
                                                <Badge variant="secondary" size="sm">
                                                    TEKS
                                                </Badge>
                                            )}
                                        </div>
                                    </TableCell>

                                    {/* Sifat Kewajiban */}
                                    <TableCell className="text-center">
                                        <div className="flex flex-col items-center gap-1">
                                            {item.wajib ? (
                                                <Badge variant="danger" size="sm">
                                                    Wajib
                                                </Badge>
                                            ) : (
                                                <Badge variant="muted" size="sm">
                                                    Opsional
                                                </Badge>
                                            )}
                                            {item.semua_mode_wajib && (
                                                <Badge variant="warning" size="sm">
                                                    Semua Mode
                                                </Badge>
                                            )}
                                        </div>
                                    </TableCell>

                                    {/* Status */}
                                    <TableCell className="text-center whitespace-nowrap">
                                        {item.aktif !== false ? (
                                            <Badge variant="success" size="sm">
                                                Aktif
                                            </Badge>
                                        ) : (
                                            <Badge variant="muted" size="sm">
                                                Nonaktif
                                            </Badge>
                                        )}
                                    </TableCell>

                                    {/* Batas Teknis */}
                                    <TableCell>
                                        {item.izinkan_file ? (
                                            <div className="text-[11px] space-y-0.5">
                                                <div>
                                                    <span className="text-muted">Format: </span>
                                                    <span className="font-mono text-ink">
                                                        {item.format_diizinkan || 'Default'}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-muted">Maks: </span>
                                                    <span className="font-mono text-ink">
                                                        {item.ukuran_maks_kb ? `${item.ukuran_maks_kb} KB` : 'Default'}
                                                    </span>
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="text-muted text-[11px]">N/A</span>
                                        )}
                                    </TableCell>

                                    {/* Aksi */}
                                    {(can.update || can.delete || can.pengaturan_update) && (
                                        <TableCell className="text-center">
                                            <div className="inline-flex items-center gap-1 justify-center">
                                                {can.update && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenEdit(item, false)}
                                                        className="inline-flex items-center justify-center h-9 w-9 rounded-lg text-muted hover:text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25 transition-colors cursor-pointer"
                                                        title="Ubah Persyaratan"
                                                        aria-label={`Ubah persyaratan ${item.nama}`}
                                                    >
                                                        <Edit2 className="w-4 h-4" />
                                                    </button>
                                                )}
                                                {!can.update && can.pengaturan_update && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenEdit(item, true)}
                                                        className="inline-flex items-center justify-center h-9 w-9 rounded-lg text-muted hover:text-info-dark hover:bg-info/10 focus:outline-none focus:ring-2 focus:ring-info/25 transition-colors cursor-pointer"
                                                        title="Ubah Batas Teknis"
                                                        aria-label={`Ubah batas teknis untuk ${item.nama}`}
                                                    >
                                                        <SlidersHorizontal className="w-4 h-4" />
                                                    </button>
                                                )}
                                                {can.delete && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenDelete(item)}
                                                        className="inline-flex items-center justify-center h-9 w-9 rounded-lg text-muted hover:text-danger hover:bg-danger/10 focus:outline-none focus:ring-2 focus:ring-danger/25 transition-colors cursor-pointer"
                                                        title="Hapus Persyaratan"
                                                        aria-label={`Hapus persyaratan ${item.nama}`}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </Card>

            {/* Modal Tambah / Ubah */}
            <JenisBerkasModal
                isOpen={isFormModalOpen}
                isEditing={isEditing}
                isBatasTeknisOnly={isBatasTeknisOnly}
                data={formData}
                errors={formErrors}
                indikators={indikators}
                isLoading={isSubmitting}
                unggahanAktif={unggahanAktif}
                canManageSettings={can.pengaturan_update}
                onChange={handleFormChange}
                onClose={() => {
                    if (!isSubmitting) setIsFormModalOpen(false);
                }}
                onSubmit={handleFormSubmit}
            />

            {/* Modal Alasan Audit (Sensitif) */}
            <AuditReasonModal
                open={isAuditModalOpen}
                title={
                    auditAction === 'delete'
                        ? 'Konfirmasi Hapus Persyaratan'
                        : auditAction === 'update-batas-teknis'
                        ? 'Konfirmasi Perubahan Batas Teknis'
                        : 'Konfirmasi Perubahan Substantif'
                }
                description={
                    auditAction === 'delete'
                        ? 'Penghapusan katalog jenis berkas bersifat sensitif. Persyaratan yang dihapus tidak lagi berlaku untuk pengajuan berikutnya. Masukkan alasan penghapusan untuk rekaman audit.'
                        : auditAction === 'update-batas-teknis'
                        ? 'Perubahan batas teknis format dan batas ukuran file akan dicatat pada audit trail dengan rekaman izin pengaturan. Masukkan alasan perubahan batas teknis.'
                        : 'Perubahan katalog persyaratan bukti dukung bersifat sensitif dan akan dicatat pada audit trail dengan rekaman kondisi sebelum dan sesudah perubahan. Masukkan alasan perubahan.'
                }
                reason={auditReason}
                error={auditError || undefined}
                busy={isSubmitting}
                confirmLabel={auditAction === 'delete' ? 'Hapus Persyaratan' : 'Simpan Perubahan'}
                destructive={auditAction === 'delete'}
                onReasonChange={setAuditReason}
                onClose={() => {
                    if (!isSubmitting) {
                        setIsAuditModalOpen(false);
                        setAuditReason('');
                        setAuditError(null);
                    }
                }}
                onConfirm={handleConfirmAudit}
            />
        </AuthenticatedLayout>
    );
}
