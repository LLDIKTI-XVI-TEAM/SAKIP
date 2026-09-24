import React, { useState, useMemo } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { 
    Plus, 
    Search,
    AlertCircle,
    CheckCircle2,
    TrendingUp,
    TrendingDown,
    Building2,
    X
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { FormulaCard } from './Partials/FormulaCard';
import { KomponenModal, KomponenFormData } from './Partials/KomponenModal';
import { KomponenTable, KomponenItem } from './Partials/KomponenTable';

export interface IndikatorKinerjaData {
    id: string;
    kode: string;
    nama: string;
    satuan: string;
    tipe_perhitungan: string;
    arah: string;
    presisi: number;
    desimal_tampilan: number;
    is_aktif: boolean;
    sasaran_strategis?: {
        id: string;
        kode: string;
        deskripsi: string;
        renstra?: {
            id: string;
            nama: string;
            tahun_mulai: number;
            tahun_selesai: number;
        };
    };
    unit?: {
        id: string;
        nama: string;
    };
}

export interface FormulaContractData {
    tipe_perhitungan: string;
    formula_text: string;
    is_valid: boolean;
    messages: string[];
    komponen_list: Array<{
        kode: string;
        label: string;
        peran: string;
        bobot: number;
        urutan: number;
        satuan: string | null;
    }>;
}

export interface DefinisiValidationData {
    is_valid: boolean;
    messages: string[];
}

interface IndexProps {
    indikator: IndikatorKinerjaData;
    komponen: KomponenItem[];
    formulaContract: FormulaContractData;
    validation: DefinisiValidationData;
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

const defaultFormData: KomponenFormData = {
    kode: '',
    label: '',
    satuan: '',
    peran: 'pembilang',
    bobot: 1.0,
    urutan: 1,
    aktif: true,
};

export default function KomponenIndex({
    indikator,
    komponen = [],
    formulaContract,
    validation,
    can = { create: false, update: false, delete: false },
}: IndexProps) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;
    const [searchQuery, setSearchQuery] = useState('');
    const [filterPeran, setFilterPeran] = useState<string>('semua');

    // Modal Form State
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState<KomponenFormData>(defaultFormData);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Modal Audit Reason State
    const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
    const [auditAction, setAuditAction] = useState<'update' | 'delete' | null>(null);
    const [targetKomponen, setTargetKomponen] = useState<KomponenItem | null>(null);
    const [auditReason, setAuditReason] = useState('');
    const [auditError, setAuditError] = useState<string | null>(null);

    // Filtered list
    const filteredKomponen = useMemo(() => {
        return komponen.filter(k => {
            const matchesSearch = 
                k.kode.toLowerCase().includes(searchQuery.toLowerCase()) ||
                k.label.toLowerCase().includes(searchQuery.toLowerCase());
            
            const matchesPeran = filterPeran === 'semua' || k.peran === filterPeran;

            return matchesSearch && matchesPeran;
        });
    }, [komponen, searchQuery, filterPeran]);

    // Handle Create
    const handleOpenCreate = () => {
        setIsEditing(false);
        setFormData({
            ...defaultFormData,
            urutan: komponen.length + 1,
            peran: indikator.tipe_perhitungan === 'penjumlahan' ? 'penjumlah' : 'pembilang',
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Handle Edit
    const handleOpenEdit = (item: KomponenItem) => {
        setIsEditing(true);
        setFormData({
            id: item.id,
            kode: item.kode,
            label: item.label,
            satuan: item.satuan || '',
            peran: item.peran,
            bobot: Number(item.bobot),
            urutan: item.urutan,
            aktif: item.aktif,
        });
        setFormErrors({});
        setIsFormModalOpen(true);
    };

    // Submit form (create directly, or open audit modal if editing)
    const handleFormSubmit = (data: KomponenFormData) => {
        if (isEditing && data.id) {
            setTargetKomponen(komponen.find(k => k.id === data.id) || null);
            setAuditAction('update');
            setAuditReason('');
            setAuditError(null);
            setIsFormModalOpen(false);
            setIsAuditModalOpen(true);
        } else {
            setIsSubmitting(true);
            router.post(`/indikator/${indikator.id}/komponen`, data as any, {
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

    // Handle Delete
    const handleOpenDelete = (item: KomponenItem) => {
        setTargetKomponen(item);
        setAuditAction('delete');
        setAuditReason('');
        setAuditError(null);
        setIsAuditModalOpen(true);
    };

    // Confirm Audit Modal (Update / Delete)
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

        if (auditAction === 'update' && formData.id) {
            setIsSubmitting(true);
            router.put(`/indikator/${indikator.id}/komponen/${formData.id}`, {
                ...formData,
                alasan: trimmed,
            } as any, {
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setTargetKomponen(null);
                },
                onError: (errs) => {
                    if (errs.alasan) {
                        setAuditError(errs.alasan);
                    } else {
                        setFormErrors(errs);
                        setIsAuditModalOpen(false);
                        setIsFormModalOpen(true);
                    }
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        } else if (auditAction === 'delete' && targetKomponen) {
            setIsSubmitting(true);
            router.delete(`/indikator/${indikator.id}/komponen/${targetKomponen.id}`, {
                data: {
                    alasan: trimmed,
                },
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setTargetKomponen(null);
                },
                onError: (errs) => {
                    setAuditError(errs.alasan || 'Gagal menghapus komponen indikator.');
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    return (
        <AuthenticatedLayout
            title={`Konfigurasi Komponen - ${indikator.kode}`}
            breadcrumbs={[
                { label: 'Perencanaan' },
                { label: 'Indikator Kinerja' },
                { label: indikator.kode },
                { label: 'Komponen Angka' },
            ]}
        >
            <Head title={`Komponen Indikator: ${indikator.kode}`} />

            <div className="space-y-6">
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="flex items-center gap-3 rounded-xl border border-success/30 bg-success/10 p-4 text-sm font-medium text-success-dark">
                        <CheckCircle2 className="h-5 w-5 shrink-0 text-success" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-center gap-3 rounded-xl border border-danger/30 bg-danger/10 p-4 text-sm font-medium text-danger-dark">
                        <AlertCircle className="h-5 w-5 shrink-0 text-danger" />
                        <span>{flash.error}</span>
                    </div>
                )}

                {/* Page Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-primary/10 text-primary border border-primary/20">
                                {indikator.kode}
                            </span>
                            {indikator.is_aktif ? (
                                <Badge variant="success" size="sm">Aktif</Badge>
                            ) : (
                                <Badge variant="muted" size="sm">Nonaktif</Badge>
                            )}
                        </div>
                        <h1 className="text-xl sm:text-2xl font-bold text-ink tracking-tight">
                            Konfigurasi Komponen: {indikator.nama}
                        </h1>
                        <p className="text-xs sm:text-sm text-muted mt-1">
                            Kelola variabel komponen data-driven yang diinput unit untuk pembentukan nilai capaian kinerja.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {can.create && (
                            <Button
                                variant="primary"
                                onClick={handleOpenCreate}
                                className="shadow-sm"
                            >
                                <Plus className="h-4 w-4 mr-1.5" />
                                Tambah Komponen
                            </Button>
                        )}
                    </div>
                </div>

                {/* Quick Info Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div className="rounded-lg border border-border bg-surface p-3 space-y-1">
                        <span className="text-[11px] font-medium text-muted uppercase tracking-wider block">
                            Satuan Pengukuran
                        </span>
                        <span className="text-sm font-semibold text-ink">
                            {indikator.satuan || '-'}
                        </span>
                    </div>
                    <div className="rounded-lg border border-border bg-surface p-3 space-y-1">
                        <span className="text-[11px] font-medium text-muted uppercase tracking-wider block">
                            Arah Nilai
                        </span>
                        <div className="flex items-center gap-1.5 text-sm font-semibold text-ink">
                            {indikator.arah === 'naik_baik' ? (
                                <>
                                    <TrendingUp className="h-4 w-4 text-success" />
                                    <span>Naik Lebih Baik</span>
                                </>
                            ) : (
                                <>
                                    <TrendingDown className="h-4 w-4 text-info" />
                                    <span>Turun Lebih Baik</span>
                                </>
                            )}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-surface p-3 space-y-1">
                        <span className="text-[11px] font-medium text-muted uppercase tracking-wider block">
                            Unit Penanggung Jawab
                        </span>
                        <div className="flex items-center gap-1.5 text-sm font-semibold text-ink truncate">
                            <Building2 className="h-4 w-4 text-muted shrink-0" />
                            <span className="truncate">{indikator.unit?.nama || '-'}</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-surface p-3 space-y-1">
                        <span className="text-[11px] font-medium text-muted uppercase tracking-wider block">
                            Total Komponen
                        </span>
                        <span className="text-sm font-semibold text-ink">
                            {komponen.length} Komponen ({komponen.filter(k => k.aktif).length} Aktif)
                        </span>
                    </div>
                </div>

                {/* Formula Contract & Server Evaluation Card */}
                <FormulaCard
                    tipePerhitungan={indikator.tipe_perhitungan}
                    formulaText={formulaContract.formula_text}
                    isValid={validation.is_valid}
                    validationMessages={validation.messages}
                    komponenList={komponen}
                />

                {/* Table Filter & Search Card */}
                <Card className="border border-border/80 bg-surface shadow-xs">
                    <CardHeader className="border-b border-border/60 pb-3">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <CardTitle className="text-base font-semibold text-ink">
                                Daftar Komponen Angka
                            </CardTitle>
                            
                            <div className="flex flex-wrap items-center gap-2">
                                {/* Search */}
                                <div className="relative min-w-[200px] flex-1 sm:flex-none">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted" />
                                    <input
                                        type="text"
                                        placeholder="Cari kode atau label..."
                                        value={searchQuery}
                                        onChange={e => setSearchQuery(e.target.value)}
                                        className="h-9 w-full rounded-lg border border-border bg-surface pl-9 pr-3 text-xs text-ink placeholder:text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    />
                                    {searchQuery && (
                                        <button
                                            type="button"
                                            onClick={() => setSearchQuery('')}
                                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted hover:text-ink"
                                        >
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                </div>

                                {/* Filter Peran (3 roles) */}
                                <select
                                    value={filterPeran}
                                    onChange={e => setFilterPeran(e.target.value)}
                                    className="h-9 rounded-lg border border-border bg-surface px-2.5 text-xs text-ink focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                                >
                                    <option value="semua">Semua Peran</option>
                                    <option value="pembilang">Pembilang</option>
                                    <option value="penyebut">Penyebut</option>
                                    <option value="penjumlah">Penjumlah</option>
                                </select>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <KomponenTable
                            komponen={filteredKomponen}
                            can={can}
                            hasFilterOrSearch={Boolean(searchQuery || filterPeran !== 'semua')}
                            onOpenCreate={handleOpenCreate}
                            onOpenEdit={handleOpenEdit}
                            onOpenDelete={handleOpenDelete}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Modal Form Tambah/Ubah */}
            <KomponenModal
                open={isFormModalOpen}
                isEditing={isEditing}
                initialData={formData}
                errors={formErrors}
                isSubmitting={isSubmitting}
                onClose={() => setIsFormModalOpen(false)}
                onSubmit={handleFormSubmit}
            />

            {/* Modal Alasan Audit (Sensitif) */}
            <AuditReasonModal
                open={isAuditModalOpen}
                title={auditAction === 'delete' ? 'Konfirmasi Penghapusan Komponen' : 'Alasan Pembaruan Komponen'}
                description={
                    auditAction === 'delete'
                        ? `Anda akan menghapus komponen "${targetKomponen?.kode} (${targetKomponen?.label})". Tindakan ini dicatat ke log audit dan memerlukan alasan resmi.`
                        : `Anda memperbarui komponen "${formData.kode}". Tindakan ini memengaruhi kalkulasi formula dan memerlukan alasan resmi.`
                }
                reason={auditReason}
                error={auditError || undefined}
                busy={isSubmitting}
                confirmLabel={auditAction === 'delete' ? 'Hapus Komponen' : 'Simpan Perubahan'}
                destructive={auditAction === 'delete'}
                onReasonChange={setAuditReason}
                onClose={() => {
                    setIsAuditModalOpen(false);
                    setTargetKomponen(null);
                }}
                onConfirm={handleConfirmAudit}
            />
        </AuthenticatedLayout>
    );
}
