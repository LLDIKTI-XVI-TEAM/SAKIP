import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Select } from '@/Components/Select';
import {
    Plus,
    Edit3,
    Trash2,
    ArrowLeftRight,
    TrendingUp,
    TrendingDown,
    Calculator,
    Target,
    ChevronDown,
    ChevronUp,
    Sigma,
    BookOpen,
    UserCheck,
} from 'lucide-react';
import { SasaranModal } from './SasaranModal';
import { IndikatorModal } from './IndikatorModal';
import { PindahUnitModal } from './PindahUnitModal';
import { HttpResponseError } from '@inertiajs/core';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Modal } from '@/Components/Modal';
import { loadDefinition, hasParentMetadata, type DefinitionEditor } from '@/Pages/Indikator/Komponen/definition';
import { FormulaModal } from './FormulaModal';
import { TambahMenu } from './TambahMenu';
import { DeleteConfirmModal, type DeleteTarget } from './DeleteConfirmModal';
import { TargetModal } from '@/Pages/TargetTahunan/TargetModal';
import type {
    IndikatorKinerjaItem,
    IndikatorTipePerhitungan,
    RegulasiOption,
    RenstraOption,
    SasaranIndikatorCapabilities,
    SasaranStrategisItem,
    UnitOption,
} from '@/types/sasaran-indikator';

const tipePerhitunganLabel: Record<IndikatorTipePerhitungan, string> = {
    manual: 'Manual',
    rasio_persen: 'Rasio Persen',
    penjumlahan: 'Penjumlahan',
};

function formatRegulasiRingkas(regulasi: RegulasiOption): string {
    const jenisMap: Record<string, string> = {
        kepmen: 'Kepmen',
        permen: 'Permen',
        perpres: 'Perpres',
        uu: 'UU',
        pp: 'PP',
        keputusan_lainnya: 'Keputusan Lainnya',
    };
    const jenisText =
        jenisMap[regulasi.jenis.toLowerCase()] ||
        (regulasi.jenis.charAt(0).toUpperCase() + regulasi.jenis.slice(1));
    const nomor = regulasi.nomor.trim();
    if (regulasi.tahun && !nomor.includes(String(regulasi.tahun))) {
        return `${jenisText} ${nomor}/${regulasi.tahun}`;
    }
    return `${jenisText} ${nomor}`;
}

interface SasaranIndikatorIndexProps {
    renstras: RenstraOption[];
    selectedRenstraId: string | null;
    sasarans: SasaranStrategisItem[];
    units: UnitOption[];
    regulasis: RegulasiOption[];
    can: SasaranIndikatorCapabilities;
}

export default function SasaranIndikatorIndex({
    renstras,
    selectedRenstraId,
    sasarans,
    units,
    regulasis,
    can,
}: SasaranIndikatorIndexProps) {
    // Accordion collapse state per sasaran (default expanded)
    const [collapsedMap, setCollapsedMap] = useState<Record<string, boolean>>({});

    // Modals state
    const [sasaranModalOpen, setSasaranModalOpen] = useState(false);
    const [selectedSasaran, setSelectedSasaran] = useState<SasaranStrategisItem | null>(null);

    const [indikatorModalOpen, setIndikatorModalOpen] = useState(false);
    const [selectedIndikator, setSelectedIndikator] = useState<IndikatorKinerjaItem | null>(null);
    const [defaultSasaranId, setDefaultSasaranId] = useState<string | undefined>();

    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);

    const [pindahTarget, setPindahTarget] = useState<IndikatorKinerjaItem | null>(null);

    const [formulaTarget, setFormulaTarget] = useState<DefinitionEditor | null>(null);
    const [annualTarget, setAnnualTarget] = useState<{ id: string; year: number } | null>(null);
    const [annualMessage, setAnnualMessage] = useState('');
    const [selectedEditor, setSelectedEditor] = useState<DefinitionEditor | null>(null);
    const [editorLoading, setEditorLoading] = useState(false);
    const [editorError, setEditorError] = useState('');
    const editorRecovery = useAuthRecovery();
    const editorRequest = useRef<AbortController | null>(null);
    useEffect(() => () => editorRequest.current?.abort(), []);

    const toggleCollapse = (id: string) => {
        setCollapsedMap((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const handleRenstraChange = (newRenstraId: string) => {
        router.get(
            '/perencanaan/sasaran-indikator',
            { renstra_id: newRenstraId },
            { preserveState: true, replace: true }
        );
    };

    const openCreateSasaran = () => {
        setSelectedSasaran(null);
        setSasaranModalOpen(true);
    };

    const openEditSasaran = (sasaran: SasaranStrategisItem) => {
        setSelectedSasaran(sasaran);
        setSasaranModalOpen(true);
    };

    const openCreateIndikator = (sasaranId?: string) => {
        editorRequest.current?.abort();
        setSelectedEditor(null);
        setSelectedIndikator(null);
        setDefaultSasaranId(sasaranId || sasarans[0]?.id);
        setIndikatorModalOpen(true);
    };

    const openEditor = async (indikator: IndikatorKinerjaItem, mode: 'metadata' | 'formula') => {
        editorRequest.current?.abort();
        const controller = new AbortController();
        editorRequest.current = controller;
        setEditorLoading(true);
        setEditorError('');
        try {
            const editor = await loadDefinition(`/perencanaan/indikator/${indikator.id}/editor`, controller.signal);
            if (controller.signal.aborted) return;
            if (!hasParentMetadata(editor) || editor.indikator.id !== indikator.id) throw new Error('Metadata editor tidak lengkap.');
            if (mode === 'formula') setFormulaTarget(editor);
            else {
                setSelectedEditor(editor);
                setSelectedIndikator(editor.indikator);
                setDefaultSasaranId(editor.indikator.sasaran_strategis_id);
                setIndikatorModalOpen(true);
            }
        } catch (error) {
            if (!controller.signal.aborted) {
                if (error instanceof HttpResponseError) editorRecovery.handleHttpException(error.response, { effectiveMethod: 'get', path: `/perencanaan/indikator/${indikator.id}/editor`, mutation: false });
                setEditorError('Editor tidak dapat dimuat. Periksa koneksi dan akses, lalu buka kembali indikator.');
            }
        }
        finally { if (!controller.signal.aborted) setEditorLoading(false); }
    };
    const openEditIndikator = (indikator: IndikatorKinerjaItem) => { void openEditor(indikator, 'metadata'); };

    const selectedRenstra = renstras.find((r) => r.id === selectedRenstraId);

    const canAturFormula = can.komponen_read && (can.indikator_update || can.komponen_create || can.komponen_update || can.komponen_delete);

    return (
        <AuthenticatedLayout
            title="Sasaran & Indikator Kinerja"
            headerActions={can.penanggung_jawab_update && (
                <Link
                    href="/penanggung-jawab"
                    className="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-1"
                >
                    <UserCheck className="h-4 w-4 shrink-0" aria-hidden="true" />
                    Monitoring PJ
                </Link>
            )}
            breadcrumbs={[
                { label: 'Perencanaan' },
                { label: 'Sasaran & Indikator' },
            ]}
        >
            <Head title="Sasaran Strategis & Indikator Kinerja" />

            {annualMessage && <p role="status" className="mb-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm">{annualMessage}</p>}

            <div className="space-y-6">
                {/* Header Context Card */}
                <Card className="p-4 sm:p-5 bg-surface shadow-xs border border-border overflow-visible relative z-20">
                    <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                        {/* Metadata Renstra (Paling Kiri) */}
                        {selectedRenstra ? (
                            <div className="flex flex-wrap items-center gap-3.5 text-xs text-muted">
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-ink">Periode Renstra:</span>
                                    <span>{selectedRenstra.tahun_mulai} — {selectedRenstra.tahun_selesai}</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-ink">Status Renstra:</span>
                                    {selectedRenstra.is_aktif ? (
                                        <Badge variant="success" size="sm" dot>Renstra Aktif</Badge>
                                    ) : (
                                        <Badge variant="muted" size="sm">Non-aktif</Badge>
                                    )}
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-ink">Total Sasaran:</span>
                                    <span>{sasarans.length} Sasaran</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-ink">Total Indikator:</span>
                                    <span>
                                        {sasarans.reduce((acc, s) => acc + s.indikator_kinerjas.length, 0)} Indikator
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div />
                        )}

                        {/* Filter & Actions (Sisi Kanan) */}
                        <div className="flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:items-center lg:shrink-0">
                            {/* Renstra Selector */}
                            <div className="w-full lg:w-72">
                                <Select
                                    id="renstra_filter"
                                    className="renstra-filter h-10"
                                    value={selectedRenstraId || ''}
                                    onChange={(e) => handleRenstraChange(e.target.value)}
                                    aria-label="Pilih Periode Renstra"
                                >
                                    {renstras.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.nama} ({r.tahun_mulai} - {r.tahun_selesai}) {r.is_aktif ? 'Aktif' : ''}
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            {/* Top Actions */}
                            <TambahMenu
                                showSasaran={can.sasaran_create && Boolean(selectedRenstraId)}
                                showIndikator={can.indikator_create && sasarans.length > 0}
                                onAddSasaran={openCreateSasaran}
                                onAddIndikator={() => openCreateIndikator()}
                            />
                        </div>
                    </div>
                </Card>

                {/* Empty State when no renstras */}
                {renstras.length === 0 && (
                    <Card className="p-12 text-center border-dashed border-border bg-soft/50">
                        <Target className="mx-auto h-12 w-12 text-muted" aria-hidden="true" />
                        <h3 className="mt-3 text-base font-semibold text-ink">Belum Ada Renstra</h3>
                        <p className="mt-1 text-sm text-muted">
                            Silakan tambahkan data Rencana Strategis (Renstra) terlebih dahulu sebelum menyusun sasaran dan indikator.
                        </p>
                    </Card>
                )}

                {/* Empty State when renstra has no sasarans */}
                {renstras.length > 0 && sasarans.length === 0 && (
                    <Card className="p-12 text-center border-dashed border-border bg-soft/50">
                        <Target className="mx-auto h-12 w-12 text-muted" aria-hidden="true" />
                        <h3 className="mt-3 text-base font-semibold text-ink">Belum Ada Sasaran Strategis</h3>
                        <p className="mt-1 text-sm text-muted">
                            Periode Renstra yang dipilih belum memiliki Sasaran Strategis.
                        </p>
                        {can.sasaran_create && selectedRenstraId && (
                            <div className="mt-5">
                                <Button variant="primary" onClick={openCreateSasaran} className="gap-2">
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    Tambah Sasaran Pertama
                                </Button>
                            </div>
                        )}
                    </Card>
                )}

                {/* Sasaran and Indikator Hierarchy List */}
                {sasarans.map((sasaran) => {
                    const isCollapsed = Boolean(collapsedMap[sasaran.id]);

                    return (
                        <div
                            key={sasaran.id}
                            className="rounded-xl border border-border bg-surface shadow-xs transition-shadow hover:shadow-sm overflow-hidden"
                        >
                            {/* Sasaran Header */}
                            <div className="p-4 md:p-5 bg-surface border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div className="flex items-start gap-3 min-w-0 flex-1">
                                    <button
                                        type="button"
                                        onClick={() => toggleCollapse(sasaran.id)}
                                        className="mt-0.5 p-1 rounded-md text-muted hover:text-ink hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary"
                                        aria-label={isCollapsed ? 'Buka rincian sasaran' : 'Tutup rincian sasaran'}
                                    >
                                        {isCollapsed ? (
                                            <ChevronDown className="h-5 w-5" />
                                        ) : (
                                            <ChevronUp className="h-5 w-5" />
                                        )}
                                    </button>

                                    <div className="space-y-1 min-w-0 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-primary text-white">
                                                {sasaran.kode}
                                            </span>
                                            <Badge variant="primary" size="sm">
                                                {sasaran.indikator_kinerjas.length} Indikator
                                            </Badge>
                                        </div>
                                        <h2 className="text-base font-semibold text-ink leading-relaxed">
                                            {sasaran.deskripsi}
                                        </h2>
                                    </div>
                                </div>

                                {/* Sasaran Action Buttons */}
                                <div className="flex items-center gap-2 self-end md:self-center shrink-0">
                                    {can.indikator_create && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => openCreateIndikator(sasaran.id)}
                                            className="gap-1.5"
                                        >
                                            <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                                            Indikator
                                        </Button>
                                    )}

                                    {can.sasaran_update && (
                                        <button
                                            type="button"
                                            onClick={() => openEditSasaran(sasaran)}
                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted hover:text-primary hover:bg-primary/10 transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                                            title="Ubah Sasaran"
                                            aria-label={`Ubah sasaran ${sasaran.kode}`}
                                        >
                                            <Edit3 className="h-4 w-4" />
                                        </button>
                                    )}

                                    {can.sasaran_delete && (
                                        <button
                                            type="button"
                                            onClick={() => setDeleteTarget({ type: 'sasaran', item: sasaran })}
                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted hover:text-danger hover:bg-danger/10 transition-colors focus:outline-none focus:ring-2 focus:ring-danger"
                                            title="Hapus Sasaran"
                                            aria-label={`Hapus sasaran ${sasaran.kode}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </div>

                            {/* Indikator List (Collapsible) */}
                            {!isCollapsed && (
                                <div>
                                    {sasaran.indikator_kinerjas.length === 0 ? (
                                        <div className="p-8 text-center bg-soft/30">
                                            <p className="text-sm text-muted">
                                                Sasaran strategis ini belum memiliki indikator kinerja.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-sm">
                                                <thead className="bg-soft/50 text-[11px] font-bold text-muted uppercase tracking-wider border-b border-border">
                                                    <tr>
                                                        <th scope="col" className="px-5 py-3 w-28">Kode</th>
                                                        <th scope="col" className="px-5 py-3">Indikator Kinerja</th>
                                                        <th scope="col" className="px-5 py-3 w-64">Penanggung Jawab</th>
                                                        <th scope="col" className="px-5 py-3 w-36">Satuan</th>
                                                        <th scope="col" className="px-5 py-3 w-56 text-right">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border">
                                                    {sasaran.indikator_kinerjas.map((ind) => (
                                                        <tr
                                                            key={ind.id}
                                                            className={`hover:bg-soft/30 transition-colors ${
                                                                ind.status === 'arsip' ? 'opacity-60 bg-soft/20' : ''
                                                            }`}
                                                        >
                                                            {/* 1. Kode */}
                                                            <td className="px-5 py-4 align-middle">
                                                                <span className="font-mono text-xs font-bold px-2 py-0.5 rounded bg-soft text-ink border border-border">
                                                                    {ind.kode}
                                                                </span>
                                                            </td>

                                                            {/* 2. Indikator Kinerja */}
                                                            <td className="px-5 py-4 align-middle">
                                                                <div className="space-y-1">
                                                                    <p className="font-medium text-ink text-sm leading-snug">
                                                                        {ind.nama}
                                                                    </p>
                                                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                                                        <span className="text-muted font-medium">
                                                                            {tipePerhitunganLabel[ind.tipe_perhitungan] || ind.tipe_perhitungan}
                                                                        </span>
                                                                        {ind.status === 'arsip' && (
                                                                            <Badge variant="muted" size="sm">
                                                                                Arsip
                                                                            </Badge>
                                                                        )}
                                                                        {can.regulasi_read !== false && ind.regulasi && (
                                                                            <>
                                                                                <span className="text-muted/40" aria-hidden="true">·</span>
                                                                                <span className="text-muted flex items-center gap-1 font-medium">
                                                                                    <BookOpen className="h-3 w-3 text-muted/70 shrink-0" aria-hidden="true" />
                                                                                    <span>{formatRegulasiRingkas(ind.regulasi)}</span>
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            {/* 3. Penanggung Jawab */}
                                                            <td className="px-5 py-4 align-middle text-xs text-ink font-medium">
                                                                {ind.unit_nama || ind.unit_id}
                                                            </td>

                                                            {/* 4. Satuan & Arah Target */}
                                                            <td className="px-5 py-4 align-middle text-xs">
                                                                <div className="flex items-center gap-1.5">
                                                                    <span className="font-mono font-semibold text-ink bg-soft px-2 py-0.5 rounded border border-border">
                                                                        {ind.satuan}
                                                                    </span>
                                                                    {ind.arah === 'naik_baik' ? (
                                                                        <span className="text-success inline-flex items-center gap-0.5 text-xs font-medium" title="Target: Naik Lebih Baik">
                                                                            <TrendingUp className="h-3 w-3" /> Naik
                                                                        </span>
                                                                    ) : (
                                                                        <span className="text-info inline-flex items-center gap-0.5 text-xs font-medium" title="Target: Turun Lebih Baik">
                                                                            <TrendingDown className="h-3 w-3" /> Turun
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </td>

                                                            {/* 5. Aksi */}
                                                            <td className="px-5 py-4 align-middle text-right">
                                                                <div className="flex items-center justify-end gap-1.5">
                                                                    {can.penanggung_jawab_update && <Link href={`/perencanaan/indikator/${ind.id}/penanggung-jawab`} className="rounded-md border border-border px-2.5 py-1 text-xs font-medium text-primary hover:bg-soft" aria-label={`Penanggung jawab ${ind.kode}`}>PJ</Link>}
                                                                    {can.indikator_read && selectedRenstra && (
                                                                        <button
                                                                            type="button"
                                                                            aria-label={`Baseline & target ${ind.kode}`}
                                                                            onClick={() => {
                                                                                setAnnualMessage('');
                                                                                setAnnualTarget({ id: ind.id, year: Math.max(ind.tahun_mulai_berlaku, selectedRenstra.tahun_mulai, Math.min(new Date().getFullYear(), selectedRenstra.tahun_selesai)) });
                                                                            }}
                                                                            className="inline-flex min-h-9 items-center whitespace-nowrap rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-medium text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary"
                                                                        >Baseline & target</button>
                                                                    )}
                                                                    {ind.tipe_perhitungan !== 'manual' && can.komponen_read && (
                                                                        <Link
                                                                            href={`/indikator/${ind.id}/komponen`}
                                                                            className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2.5 py-1 text-xs font-medium text-ink shadow-2xs hover:bg-soft transition-colors"
                                                                            title="Konfigurasi Komponen Perhitungan"
                                                                            aria-label={`Kelola komponen ${ind.kode}`}
                                                                        >
                                                                            <Calculator className="h-3.5 w-3.5" />
                                                                            Komponen
                                                                        </Link>
                                                                    )}

                                                                    {canAturFormula && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => { void openEditor(ind, 'formula'); }}
                                                                            className="p-1.5 rounded-md text-muted hover:text-primary hover:bg-soft transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                                                                            title="Atur Formula"
                                                                            aria-label={`Atur formula indikator ${ind.kode}`}
                                                                        >
                                                                            <Sigma className="h-4 w-4" />
                                                                        </button>
                                                                    )}

                                                                    {can.indikator_update && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => openEditIndikator(ind)}
                                                                            className="p-1.5 rounded-md text-muted hover:text-primary hover:bg-soft transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                                                                            title="Ubah Indikator"
                                                                            aria-label={`Ubah indikator ${ind.kode}`}
                                                                        >
                                                                            <Edit3 className="h-4 w-4" />
                                                                        </button>
                                                                    )}

                                                                    {can.indikator_update && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => setPindahTarget(ind)}
                                                                            className="p-1.5 rounded-md text-muted hover:text-primary hover:bg-soft transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                                                                            title="Pindah Unit"
                                                                            aria-label={`Pindah unit indikator ${ind.kode}`}
                                                                        >
                                                                            <ArrowLeftRight className="h-4 w-4" />
                                                                        </button>
                                                                    )}

                                                                    {can.indikator_delete && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => setDeleteTarget({ type: 'indikator', item: ind })}
                                                                            className="p-1.5 rounded-md text-muted hover:text-danger hover:bg-danger/10 transition-colors focus:outline-none focus:ring-2 focus:ring-danger"
                                                                            title="Hapus / Arsipkan Indikator"
                                                                            aria-label={`Hapus indikator ${ind.kode}`}
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {/* Modal Tambah/Edit Sasaran */}
            {annualTarget && <TargetModal indikatorId={annualTarget.id} initialYear={annualTarget.year} onClose={() => setAnnualTarget(null)} onSaved={setAnnualMessage} />}
            {selectedRenstraId && (
                <SasaranModal
                    isOpen={sasaranModalOpen}
                    onClose={() => setSasaranModalOpen(false)}
                    renstraId={selectedRenstraId}
                    sasaran={selectedSasaran}
                />
            )}

            {/* Modal Tambah/Edit Indikator */}
            {indikatorModalOpen && <IndikatorModal
                isOpen={indikatorModalOpen}
                onClose={() => setIndikatorModalOpen(false)}
                sasarans={sasarans}
                defaultSasaranId={defaultSasaranId}
                units={units}
                regulasis={regulasis}
                indikator={selectedIndikator}
                editor={selectedEditor}
                can={can}
            />}

            {/* Modal Pindah Unit Penanggung Jawab */}
            <PindahUnitModal
                isOpen={pindahTarget !== null}
                onClose={() => setPindahTarget(null)}
                indikator={pindahTarget}
                units={units}
            />

            {/* Modal Transisi Formula Atomik */}
            {formulaTarget && <FormulaModal
                isOpen={formulaTarget !== null}
                onClose={() => setFormulaTarget(null)}
                editor={formulaTarget}
            />}
            <Modal isOpen={editorLoading || Boolean(editorError)} onClose={() => { editorRequest.current?.abort(); setEditorLoading(false); setEditorError(''); }} title="Memuat Editor Indikator"><AuthRecoveryNotice recovery={editorRecovery.recovery} /><p role={editorError ? 'alert' : 'status'}>{editorError || 'Memuat metadata dan seluruh komponen pada revisi yang sama…'}</p></Modal>

            {/* Modal Konfirmasi Hapus dengan Alasan Audit */}
            <DeleteConfirmModal
                target={deleteTarget}
                onClose={() => setDeleteTarget(null)}
            />
        </AuthenticatedLayout>
    );
}
