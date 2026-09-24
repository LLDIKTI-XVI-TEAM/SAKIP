import React, { useState, useMemo, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { 
    Plus, 
    Layers, 
    Calculator, 
    CheckCircle2, 
    AlertTriangle, 
    Play, 
    RefreshCw, 
    Edit2, 
    Trash2, 
    TrendingUp, 
    TrendingDown, 
    Building2, 
    AlertCircle
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { 
    Table, 
    TableHeader, 
    TableBody, 
    TableRow, 
    TableHead, 
    TableCell 
} from '@/Components/Table';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Switch } from '@/Components/Switch';
import { Modal } from '@/Components/Modal';
import { AuditReasonModal } from '@/Components/AuditReasonModal';

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

export interface KomponenItem {
    id: string;
    indikator_id: string;
    kode: string;
    label: string;
    peran: 'pembilang' | 'penyebut' | 'penjumlah';
    bobot: number;
    urutan: number;
    satuan: string | null;
    aktif: boolean;
    created_at?: string;
    updated_at?: string;
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

export interface KomponenFormData {
    id?: string;
    kode: string;
    label: string;
    satuan: string;
    peran: 'pembilang' | 'penyebut' | 'penjumlah';
    bobot: number | string;
    urutan: number | string;
    aktif: boolean;
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

const HoverScrollText: React.FC<{
    icon: React.ReactNode;
    text: string;
    isCardHovered: boolean;
}> = ({ icon, text, isCardHovered }) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const textRef = useRef<HTMLSpanElement>(null);
    const [overflow, setOverflow] = useState(0);
    const [isSelfHovered, setIsSelfHovered] = useState(false);

    const updateOverflow = () => {
        if (containerRef.current && textRef.current) {
            const diff = textRef.current.scrollWidth - containerRef.current.clientWidth;
            setOverflow(diff > 0 ? diff : 0);
        }
    };

    useEffect(() => {
        updateOverflow();
        const timer1 = setTimeout(updateOverflow, 200);
        const timer2 = setTimeout(updateOverflow, 800);
        window.addEventListener('resize', updateOverflow);
        return () => {
            clearTimeout(timer1);
            clearTimeout(timer2);
            window.removeEventListener('resize', updateOverflow);
        };
    }, [text]);

    const activeHover = isCardHovered || isSelfHovered;
    const isScrolling = activeHover && overflow > 0;
    const duration = Math.max(4, Math.round(overflow / 25));

    return (
        <div 
            className="flex items-center gap-1.5 text-sm font-semibold text-ink overflow-hidden"
            onMouseEnter={() => {
                updateOverflow();
                setIsSelfHovered(true);
            }}
            onMouseLeave={() => setIsSelfHovered(false)}
        >
            <span className="shrink-0 z-10 bg-surface pr-0.5">{icon}</span>
            <div ref={containerRef} className="overflow-hidden relative w-full flex items-center">
                <span
                    ref={textRef}
                    className={`inline-block whitespace-nowrap select-none ${
                        !isScrolling && overflow > 0 ? 'truncate' : ''
                    }`}
                    style={
                        isScrolling
                            ? {
                                  animation: `tickerLoop ${duration}s ease-in-out infinite`,
                                  ['--ticker-offset' as any]: `-${overflow + 8}px`,
                              }
                            : {
                                  transform: 'translateX(0)',
                                  transition: 'transform 0.25s ease-out',
                              }
                    }
                >
                    {text}
                </span>
                {!isScrolling && overflow > 0 && (
                    <div className="pointer-events-none absolute right-0 top-0 bottom-0 w-4 bg-gradient-to-l from-surface to-transparent z-10" />
                )}
            </div>
        </div>
    );
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
    const [isUnitHovered, setIsUnitHovered] = useState(false);

    // Simulator State
    const [showSimulator, setShowSimulator] = useState(false);
    const [simulasiValues, setSimulasiValues] = useState<Record<string, number | ''>>({});

    // Modal Form Tambah / Ubah State
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState<KomponenFormData>(defaultFormData);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Modal Audit Reason State (Sensitif)
    const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
    const [auditAction, setAuditAction] = useState<'update' | 'delete' | null>(null);
    const [targetKomponen, setTargetKomponen] = useState<KomponenItem | null>(null);
    const [auditReason, setAuditReason] = useState('');
    const [auditError, setAuditError] = useState<string | null>(null);

    const aktifKomponen = useMemo(() => {
        return komponen.filter(k => k.aktif);
    }, [komponen]);

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

    // Simulator Handlers
    const handleSimulasiChange = (kode: string, value: string) => {
        if (value === '') {
            setSimulasiValues(prev => ({ ...prev, [kode]: '' }));
            return;
        }
        const num = parseFloat(value);
        if (!isNaN(num)) {
            setSimulasiValues(prev => ({ ...prev, [kode]: num }));
        }
    };

    const handleResetSimulasi = () => {
        setSimulasiValues({});
    };

    const simulasiResult = useMemo(() => {
        if (!validation.is_valid || aktifKomponen.length === 0) return null;

        const hasInput = aktifKomponen.some(k => simulasiValues[k.kode] !== undefined && simulasiValues[k.kode] !== '');
        if (!hasInput) return null;

        if (indikator.tipe_perhitungan === 'rasio_persen') {
            const pembilangKomponen = aktifKomponen.filter(k => k.peran === 'pembilang');
            const penyebutKomponen = aktifKomponen.find(k => k.peran === 'penyebut');

            if (!penyebutKomponen) return { error: 'Penyebut tidak ditemukan' };

            const rawPenyebut = simulasiValues[penyebutKomponen.kode];
            const valPenyebut = typeof rawPenyebut === 'number' ? rawPenyebut : 0;
            const effectivePenyebut = valPenyebut * Number(penyebutKomponen.bobot || 1);

            if (effectivePenyebut === 0) {
                return { value: null, note: 'Nilai tidak dapat dihitung (pembagian dengan nol / penyebut 0)' };
            }

            let sumPembilang = 0;
            pembilangKomponen.forEach(k => {
                const val = typeof simulasiValues[k.kode] === 'number' ? simulasiValues[k.kode] : 0;
                sumPembilang += (val as number) * Number(k.bobot || 1);
            });

            const hasil = (sumPembilang / effectivePenyebut) * 100;
            return { value: hasil, note: `${sumPembilang} / ${effectivePenyebut} × 100%` };
        }

        if (indikator.tipe_perhitungan === 'penjumlahan') {
            let total = 0;
            aktifKomponen.forEach(k => {
                const val = typeof simulasiValues[k.kode] === 'number' ? simulasiValues[k.kode] : 0;
                total += (val as number) * Number(k.bobot || 1);
            });
            return { value: total, note: 'Penjumlahan tertimbang komponen aktif' };
        }

        return null;
    }, [indikator.tipe_perhitungan, validation.is_valid, aktifKomponen, simulasiValues]);

    // Form Handlers
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

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing && formData.id) {
            setTargetKomponen(komponen.find(k => k.id === formData.id) || null);
            setAuditAction('update');
            setAuditReason('');
            setAuditError(null);
            setIsFormModalOpen(false);
            setIsAuditModalOpen(true);
        } else {
            setIsSubmitting(true);
            router.post(`/indikator/${indikator.id}/komponen`, formData as any, {
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

    const handleOpenDelete = (item: KomponenItem) => {
        setTargetKomponen(item);
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

    const getPeranBadge = (peran: string) => {
        switch (peran) {
            case 'pembilang':
                return <Badge variant="info" size="sm" className="whitespace-nowrap">Pembilang</Badge>;
            case 'penyebut':
                return <Badge variant="warning" size="sm" className="whitespace-nowrap">Penyebut</Badge>;
            case 'penjumlah':
                return <Badge variant="success" size="sm" className="whitespace-nowrap">Penjumlah (+)</Badge>;
            default:
                return <Badge variant="muted" size="sm" className="whitespace-nowrap">{peran}</Badge>;
        }
    };

    const getTipeBadge = (tipe: string) => {
        switch (tipe) {
            case 'rasio_persen':
                return <Badge variant="primary" size="sm">Rasio Persen (%)</Badge>;
            case 'penjumlahan':
                return <Badge variant="success" size="sm">Penjumlahan Tertimbang</Badge>;
            case 'manual':
                return <Badge variant="secondary" size="sm">Manual</Badge>;
            default:
                return <Badge variant="muted" size="sm">{tipe}</Badge>;
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

                    <div className="flex items-center gap-2 shrink-0">
                        {can.create && (
                            <Button
                                variant="primary"
                                onClick={handleOpenCreate}
                                className="shadow-sm whitespace-nowrap shrink-0"
                            >
                                <Plus className="h-4 w-4 mr-1.5 shrink-0" />
                                <span className="whitespace-nowrap">Tambah Komponen</span>
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
                    <div 
                        onMouseEnter={() => setIsUnitHovered(true)}
                        onMouseLeave={() => setIsUnitHovered(false)}
                        className="rounded-lg border border-border bg-surface p-3 space-y-1 group relative overflow-hidden transition-all duration-200 hover:border-primary/40 hover:shadow-xs cursor-default"
                    >
                        <span className="text-[11px] font-medium text-muted uppercase tracking-wider block">
                            Unit Penanggung Jawab
                        </span>
                        <HoverScrollText
                            icon={<Building2 className="h-4 w-4 text-muted shrink-0" />}
                            text={indikator.unit?.nama || '-'}
                            isCardHovered={isUnitHovered}
                        />
                    </div>
                    <div className="rounded-lg border border-border bg-surface p-3 space-y-1">
                        <span className="text-[11px] font-medium text-muted uppercase tracking-wider block">
                            Total Komponen
                        </span>
                        <span className="text-sm font-semibold text-ink">
                            {komponen.length} Komponen ({aktifKomponen.length} Aktif)
                        </span>
                    </div>
                </div>

                {/* Formula Contract & Server Evaluation Card */}
                <Card className="border border-border/80 bg-surface shadow-xs transition-shadow">
                    <CardHeader className="border-b border-border/60 pb-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2.5">
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Calculator className="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <CardTitle className="text-base font-semibold text-ink">
                                        Kontrak & Evaluasi Formula Server
                                    </CardTitle>
                                    <p className="text-xs text-muted">
                                        Dihitung oleh domain engine server (sumber kebenaran tunggal).
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {getTipeBadge(indikator.tipe_perhitungan)}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-4 space-y-4">
                        {/* Visual Box Formula */}
                        <div className="rounded-lg border border-border bg-soft/60 p-3.5">
                            <div className="flex items-center justify-between mb-1.5">
                                <span className="text-xs font-medium uppercase tracking-wider text-muted">
                                    Formula Representatif
                                </span>
                                <span className="text-[11px] text-muted italic">
                                    Evaluasi server-side
                                </span>
                            </div>
                            <div className="font-mono text-sm font-semibold text-ink bg-surface rounded-md px-3.5 py-2.5 border border-border/70 overflow-x-auto">
                                {formulaContract.formula_text || 'Belum ada formula aktif yang terdefinisi.'}
                            </div>
                        </div>

                        {/* Validasi Komponen Status */}
                        {validation.is_valid ? (
                            <div className="flex items-start gap-2.5 rounded-lg border border-success/30 bg-success/5 p-3 text-sm text-success-dark">
                                <CheckCircle2 className="h-5 w-5 shrink-0 text-success mt-0.5" aria-hidden="true" />
                                <div>
                                    <span className="font-semibold text-success-dark">Struktur Komponen Valid</span>
                                    <p className="text-xs text-muted mt-0.5">
                                        Definisi komponen aktif memenuhi seluruh aturan kalkulasi untuk tipe perhitungan <span className="font-medium text-ink">{indikator.tipe_perhitungan}</span>.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="flex items-start gap-2.5 rounded-lg border border-warning/30 bg-warning/5 p-3 text-sm text-warning-dark">
                                <AlertTriangle className="h-5 w-5 shrink-0 text-warning mt-0.5" aria-hidden="true" />
                                <div className="space-y-1">
                                    <span className="font-semibold text-warning-dark">Konfigurasi Komponen Belum Lengkap</span>
                                    <ul className="list-disc list-inside text-xs text-ink/80 space-y-0.5">
                                        {validation.messages.map((msg, idx) => (
                                            <li key={idx}>{msg}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        )}

                        {/* Interactive Simulator */}
                        {validation.is_valid && aktifKomponen.length > 0 && (
                            <div className="border-t border-border/60 pt-3">
                                <div className="flex items-center justify-between">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setShowSimulator(prev => !prev)}
                                        className="h-8 text-xs text-primary hover:text-primary/90 p-0"
                                    >
                                        <Play className="h-3.5 w-3.5 mr-1" aria-hidden="true" />
                                        {showSimulator ? 'Sembunyikan Simulator Cepat' : 'Buka Simulator Perhitungan Nilai'}
                                    </Button>
                                    {showSimulator && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleResetSimulasi}
                                            className="h-8 text-xs text-muted hover:text-ink p-0"
                                        >
                                            <RefreshCw className="h-3 w-3 mr-1" />
                                            Reset Nilai
                                        </Button>
                                    )}
                                </div>

                                {showSimulator && (
                                    <div className="mt-3 rounded-lg border border-border bg-soft/40 p-4 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <h4 className="text-xs font-semibold uppercase tracking-wider text-muted">
                                                Simulasi Input Komponen
                                            </h4>
                                            <span className="text-[11px] text-muted">
                                                {aktifKomponen.length} Komponen Aktif
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            {aktifKomponen.map(k => (
                                                <div key={k.id} className="space-y-1">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="font-mono font-medium text-ink">
                                                            {k.kode}
                                                            <span className="text-muted font-sans ml-1">
                                                                ({k.peran}, bobot {Number(k.bobot)})
                                                            </span>
                                                        </span>
                                                        {k.satuan && (
                                                            <span className="text-[11px] text-muted">{k.satuan}</span>
                                                        )}
                                                    </div>
                                                    <Input
                                                        type="number"
                                                        step="any"
                                                        placeholder={`Nilai ${k.kode}...`}
                                                        value={simulasiValues[k.kode] ?? ''}
                                                        onChange={e => handleSimulasiChange(k.kode, e.target.value)}
                                                        className="h-9 text-xs"
                                                    />
                                                </div>
                                            ))}
                                        </div>

                                        <div className="mt-3 rounded-md border border-border bg-surface p-3 flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <span className="text-xs text-muted block">Hasil Simulasi Evaluasi:</span>
                                                {simulasiResult ? (
                                                    simulasiResult.value === null ? (
                                                        <span className="text-sm font-semibold text-danger">
                                                            {simulasiResult.note}
                                                        </span>
                                                    ) : (
                                                        <div className="flex items-baseline gap-2">
                                                            <span className="text-lg font-bold text-primary">
                                                                {Number(simulasiResult.value).toFixed(2)}
                                                                {indikator.tipe_perhitungan === 'rasio_persen' && '%'}
                                                            </span>
                                                            {simulasiResult.note && (
                                                                <span className="text-xs text-muted">({simulasiResult.note})</span>
                                                            )}
                                                        </div>
                                                    )
                                                ) : (
                                                    <span className="text-xs text-muted italic">
                                                        Masukkan nilai pada komponen di atas untuk melihat simulasi hasil.
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-right">
                                                <Badge variant="muted" size="sm">
                                                    Simulasi Klien
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Table Filter & Search Card */}
                <Card className="border border-border/80 bg-surface shadow-xs">
                    <CardHeader className="border-b border-border/60 pb-3">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <CardTitle className="text-base font-semibold text-ink">
                                Daftar Komponen Angka
                            </CardTitle>
                            
                            <div className="flex flex-wrap items-center gap-2">
                                {/* Search input menggunakan komponen reusable @/Components/Input */}
                                <div className="w-full sm:w-64">
                                    <Input
                                        placeholder="Cari kode atau label..."
                                        value={searchQuery}
                                        onChange={e => setSearchQuery(e.target.value)}
                                        className="h-9 text-xs"
                                    />
                                </div>

                                {/* Filter Peran menggunakan komponen reusable @/Components/Select */}
                                <div className="w-full sm:w-44">
                                    <Select
                                        value={filterPeran}
                                        onChange={e => setFilterPeran(e.target.value)}
                                        options={[
                                            { value: 'semua', label: 'Semua Peran' },
                                            { value: 'pembilang', label: 'Pembilang' },
                                            { value: 'penyebut', label: 'Penyebut' },
                                            { value: 'penjumlah', label: 'Penjumlah' },
                                        ]}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-soft/50">
                                        <TableHead className="w-12 text-center">#</TableHead>
                                        <TableHead className="w-24">Kode</TableHead>
                                        <TableHead className="w-1/3 min-w-[180px]">Label Komponen</TableHead>
                                        <TableHead className="w-48 whitespace-nowrap">Peran</TableHead>
                                        <TableHead className="w-20 text-right">Bobot</TableHead>
                                        <TableHead className="w-24">Satuan</TableHead>
                                        <TableHead className="w-24 text-center">Status</TableHead>
                                        {(can.update || can.delete) && (
                                            <TableHead className="w-24 text-right pr-4">Aksi</TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredKomponen.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={can.update || can.delete ? 8 : 7}
                                                className="py-12 text-center"
                                            >
                                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-soft text-muted">
                                                    <Layers className="h-6 w-6" aria-hidden="true" />
                                                </div>
                                                <h3 className="mt-3 text-sm font-semibold text-ink">
                                                    Tidak ada komponen ditemukan
                                                </h3>
                                                <p className="mt-1 text-xs text-muted max-w-sm mx-auto">
                                                    {searchQuery || filterPeran !== 'semua'
                                                        ? 'Tidak ada komponen yang cocok dengan kriteria pencarian atau filter.'
                                                        : 'Indikator ini belum memiliki komponen angka terdefinisi. Tambahkan komponen untuk memulai.'}
                                                </p>
                                                {can.create && !searchQuery && filterPeran === 'semua' && (
                                                    <div className="mt-4">
                                                        <Button
                                                            variant="primary"
                                                            size="sm"
                                                            onClick={handleOpenCreate}
                                                        >
                                                            <Plus className="h-4 w-4 mr-1.5" />
                                                            Tambah Komponen Pertama
                                                        </Button>
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        filteredKomponen.map((item) => (
                                            <TableRow 
                                                key={item.id}
                                                className="hover:bg-soft/40 transition-colors"
                                            >
                                                <TableCell className="text-center font-mono text-xs text-muted">
                                                    {item.urutan}
                                                </TableCell>
                                                <TableCell>
                                                    <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-soft text-ink border border-border">
                                                        {item.kode}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-sm font-medium text-ink block">
                                                        {item.label}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    {getPeranBadge(item.peran)}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm font-medium text-ink">
                                                    {Number(item.bobot).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 4 })}
                                                </TableCell>
                                                <TableCell className="text-xs text-muted">
                                                    {item.satuan || '-'}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {item.aktif ? (
                                                        <span className="inline-flex items-center gap-1 text-xs font-medium text-success">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-success" />
                                                            Aktif
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 text-xs font-medium text-muted">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-muted" />
                                                            Nonaktif
                                                        </span>
                                                    )}
                                                </TableCell>
                                                {(can.update || can.delete) && (
                                                    <TableCell className="text-right pr-4">
                                                        <div className="flex items-center justify-end gap-1">
                                                            {can.update && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleOpenEdit(item)}
                                                                    title="Ubah Komponen"
                                                                    className="h-8 w-8 p-0"
                                                                >
                                                                    <Edit2 className="h-3.5 w-3.5 text-muted hover:text-ink" />
                                                                </Button>
                                                            )}
                                                            {can.delete && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleOpenDelete(item)}
                                                                    title="Hapus Komponen"
                                                                    className="h-8 w-8 p-0 hover:bg-danger/10 hover:text-danger"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5 text-muted hover:text-danger" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Modal Form Tambah/Ubah menggunakan komponen reusable @/Components/Modal */}
            <Modal
                isOpen={isFormModalOpen}
                onClose={() => setIsFormModalOpen(false)}
                title={isEditing ? 'Ubah Komponen Indikator' : 'Tambah Komponen Indikator'}
                description={
                    isEditing
                        ? 'Perbarui definisi komponen indikator data-driven. Perubahan memerlukan pengisian alasan audit.'
                        : 'Tambahkan variabel komponen baru sebagai input pembentuk nilai indikator.'
                }
                size="lg"
                footer={
                    <div className="flex items-center justify-end gap-2.5">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsFormModalOpen(false)}
                            disabled={isSubmitting}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            onClick={handleFormSubmit}
                            isLoading={isSubmitting}
                        >
                            {isEditing ? 'Lanjutkan Perubahan' : 'Simpan Komponen'}
                        </Button>
                    </div>
                }
            >
                <form onSubmit={handleFormSubmit} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {/* Kode Komponen */}
                        <div>
                            <Input
                                label="Kode Komponen"
                                required
                                placeholder="misal: sakip, zi_wbk, n, t"
                                value={formData.kode}
                                onChange={e => setFormData(prev => ({ ...prev, kode: e.target.value.toLowerCase().replace(/\s+/g, '_') }))}
                                error={formErrors.kode}
                                helperText="Hanya huruf kecil, angka, dan garis bawah (_)."
                            />
                        </div>

                        {/* Peran Komponen */}
                        <div>
                            <Select
                                label="Peran Komponen"
                                required
                                value={formData.peran}
                                onChange={e => setFormData(prev => ({ ...prev, peran: e.target.value as any }))}
                                error={formErrors.peran}
                                options={[
                                    { value: 'pembilang', label: 'Pembilang (Numerator)' },
                                    { value: 'penyebut', label: 'Penyebut (Denominator)' },
                                    { value: 'penjumlah', label: 'Penjumlah (Additive)' },
                                ]}
                            />
                        </div>
                    </div>

                    {/* Label Komponen */}
                    <div>
                        <Input
                            label="Label Komponen"
                            required
                            placeholder="misal: Skor Evaluasi SAKIP LLDIKTI XVI"
                            value={formData.label}
                            onChange={e => setFormData(prev => ({ ...prev, label: e.target.value }))}
                            error={formErrors.label}
                            helperText="Deskripsi lengkap dan jelas mengenai angka yang diinput."
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {/* Bobot */}
                        <div>
                            <Input
                                label="Bobot Komponen"
                                type="number"
                                step="any"
                                min="0"
                                required
                                placeholder="1.0"
                                value={formData.bobot}
                                onChange={e => setFormData(prev => ({ ...prev, bobot: e.target.value }))}
                                error={formErrors.bobot}
                                helperText="Pengali bobot pada formula."
                            />
                        </div>

                        {/* Urutan */}
                        <div>
                            <Input
                                label="Urutan"
                                type="number"
                                min="1"
                                required
                                placeholder="1"
                                value={formData.urutan}
                                onChange={e => setFormData(prev => ({ ...prev, urutan: e.target.value }))}
                                error={formErrors.urutan}
                                helperText="Urutan posisi tampilan komponen."
                            />
                        </div>

                        {/* Satuan */}
                        <div>
                            <Input
                                label="Satuan Nilai"
                                placeholder="misal: %, Skor, PTS"
                                value={formData.satuan}
                                onChange={e => setFormData(prev => ({ ...prev, satuan: e.target.value }))}
                                error={formErrors.satuan}
                                helperText="Opsional."
                            />
                        </div>
                    </div>

                    {/* Switch Aktif */}
                    <div className="rounded-lg border border-border bg-soft/50 p-3.5 flex items-center justify-between">
                        <div>
                            <span className="text-sm font-medium text-ink block">
                                Status Komponen Aktif
                            </span>
                            <p className="text-xs text-muted">
                                Hanya komponen berstatus aktif yang disertakan dalam perhitungan formula server.
                            </p>
                        </div>
                        <Switch
                            checked={formData.aktif}
                            onChange={val => setFormData(prev => ({ ...prev, aktif: val }))}
                            aria-label="Status Komponen Aktif"
                        />
                    </div>
                </form>
            </Modal>

            {/* Modal Alasan Audit (Sensitif) menggunakan komponen reusable @/Components/AuditReasonModal */}
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
