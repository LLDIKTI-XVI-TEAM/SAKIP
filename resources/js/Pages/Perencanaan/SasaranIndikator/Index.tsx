import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Select } from '@/Components/Select';
import {
    Plus,
    Edit3,
    Trash2,
    TrendingUp,
    TrendingDown,
    Calculator,
    Target,
    Building2,
    BookOpen,
    HelpCircle,
    ChevronDown,
    ChevronUp,
    FileCheck2,
} from 'lucide-react';
import { SasaranModal } from './SasaranModal';
import { IndikatorModal } from './IndikatorModal';
import { DeleteConfirmModal, type DeleteTarget } from './DeleteConfirmModal';
import type {
    IndikatorKinerjaItem,
    RegulasiOption,
    RenstraOption,
    SasaranIndikatorCapabilities,
    SasaranStrategisItem,
    UnitOption,
} from '@/types/sasaran-indikator';

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
        setSelectedIndikator(null);
        setDefaultSasaranId(sasaranId || sasarans[0]?.id);
        setIndikatorModalOpen(true);
    };

    const openEditIndikator = (indikator: IndikatorKinerjaItem) => {
        setSelectedIndikator(indikator);
        setDefaultSasaranId(indikator.sasaran_strategis_id);
        setIndikatorModalOpen(true);
    };

    const selectedRenstra = renstras.find((r) => r.id === selectedRenstraId);

    const formatTipePerhitungan = (tipe: string) => {
        switch (tipe) {
            case 'rasio_persen':
                return 'Rasio Persen (%)';
            case 'penjumlahan':
                return 'Penjumlahan';
            case 'manual':
            default:
                return 'Manual';
        }
    };

    return (
        <AuthenticatedLayout
            title="Sasaran & Indikator Kinerja"
            breadcrumbs={[
                { label: 'Perencanaan' },
                { label: 'Sasaran & Indikator' },
            ]}
        >
            <Head title="Sasaran Strategis & Indikator Kinerja" />

            <div className="space-y-6">
                {/* Header Context Card */}
                <Card className="p-5 md:p-6 bg-surface shadow-xs border border-border">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div className="space-y-1">
                            <h1 className="text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                                <Target className="h-6 w-6 text-primary" aria-hidden="true" />
                                Sasaran Strategis & Indikator Kinerja
                            </h1>
                            <p className="text-sm text-muted">
                                Pengelolaan sasaran strategis dan Indikator Kinerja Utama (IKU) berdasarkan Rencana Strategis (Renstra).
                            </p>
                        </div>

                        {/* Renstra Selector and Top Actions */}
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="w-64">
                                <Select
                                    id="renstra_filter"
                                    value={selectedRenstraId || ''}
                                    onChange={(e) => handleRenstraChange(e.target.value)}
                                    aria-label="Pilih Periode Renstra"
                                >
                                    {renstras.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.nama} ({r.tahun_mulai} - {r.tahun_selesai}) {r.is_aktif ? '★ Aktif' : ''}
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            {can.sasaran_create && selectedRenstraId && (
                                <Button
                                    variant="primary"
                                    onClick={openCreateSasaran}
                                    className="gap-2"
                                >
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    Tambah Sasaran
                                </Button>
                            )}

                            {can.indikator_create && sasarans.length > 0 && (
                                <Button
                                    variant="secondary"
                                    onClick={() => openCreateIndikator()}
                                    className="gap-2"
                                >
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    Tambah Indikator
                                </Button>
                            )}
                        </div>
                    </div>

                    {selectedRenstra && (
                        <div className="mt-4 pt-4 border-t border-border flex flex-wrap items-center gap-4 text-xs text-muted">
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
                    )}
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
                                            <span className="text-xs font-medium text-muted">
                                                Urutan #{sasaran.urutan}
                                            </span>
                                            <Badge variant="secondary" size="sm">
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
                                            variant="secondary"
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
                                            className="p-1.5 rounded-lg text-muted hover:text-primary hover:bg-primary/10 transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
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
                                            className="p-1.5 rounded-lg text-muted hover:text-danger hover:bg-danger/10 transition-colors focus:outline-none focus:ring-2 focus:ring-danger"
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
                                            <Calculator className="mx-auto h-8 w-8 text-muted" aria-hidden="true" />
                                            <p className="mt-2 text-sm text-muted">
                                                Sasaran strategis ini belum memiliki indikator kinerja.
                                            </p>
                                            {can.indikator_create && (
                                                <div className="mt-3">
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => openCreateIndikator(sasaran.id)}
                                                        className="gap-1.5"
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                        Tambah Indikator Pertama
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-sm">
                                                <thead className="bg-soft/60 text-xs font-semibold text-muted uppercase tracking-wider border-b border-border">
                                                    <tr>
                                                        <th scope="col" className="px-4 py-3 w-28">Kode</th>
                                                        <th scope="col" className="px-4 py-3">Indikator Kinerja</th>
                                                        <th scope="col" className="px-4 py-3 w-24">Satuan</th>
                                                        <th scope="col" className="px-4 py-3">Penanggung Jawab</th>
                                                        <th scope="col" className="px-4 py-3">Karakteristik</th>
                                                        <th scope="col" className="px-4 py-3">Regulasi Rujukan</th>
                                                        <th scope="col" className="px-4 py-3 w-24 text-center">Status</th>
                                                        <th scope="col" className="px-4 py-3 w-24 text-right">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border">
                                                    {sasaran.indikator_kinerjas.map((ind) => (
                                                        <tr
                                                            key={ind.id}
                                                            className={`hover:bg-soft/40 transition-colors ${
                                                                !ind.is_aktif ? 'opacity-60 bg-soft/20' : ''
                                                            }`}
                                                        >
                                                            <td className="px-4 py-3 font-semibold text-ink align-top">
                                                                <span className="font-mono text-xs px-2 py-0.5 rounded-sm bg-soft border border-border">
                                                                    {ind.kode}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3 align-top">
                                                                <div className="space-y-1">
                                                                    <div className="font-medium text-ink">
                                                                        {ind.nama}
                                                                    </div>
                                                                    {ind.definisi_operasional && (
                                                                        <p className="text-xs text-muted flex items-start gap-1">
                                                                            <HelpCircle className="h-3.5 w-3.5 mt-0.5 shrink-0 text-muted" aria-hidden="true" />
                                                                            <span>{ind.definisi_operasional}</span>
                                                                        </p>
                                                                    )}
                                                                    {ind.wajib_catatan && (
                                                                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                                                            <FileCheck2 className="h-3 w-3" /> Wajib Catatan
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 text-muted align-top">
                                                                <span className="text-xs font-mono font-medium text-ink bg-surface px-2 py-0.5 rounded border border-border">
                                                                    {ind.satuan}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3 text-muted align-top">
                                                                <div className="flex items-center gap-1.5 text-xs text-ink font-medium">
                                                                    <Building2 className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
                                                                    <span>{ind.unit_nama || ind.unit_id}</span>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 align-top">
                                                                <div className="flex flex-col gap-1.5">
                                                                    <div className="flex items-center gap-1 text-xs">
                                                                        {ind.arah === 'naik_baik' ? (
                                                                            <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                                                                <TrendingUp className="h-3 w-3" /> Naik Baik
                                                                            </span>
                                                                        ) : (
                                                                            <span className="inline-flex items-center gap-1 text-[11px] font-medium text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">
                                                                                <TrendingDown className="h-3 w-3" /> Turun Baik
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                    <div className="flex items-center gap-1 text-xs text-muted">
                                                                        <Calculator className="h-3 w-3 text-muted" aria-hidden="true" />
                                                                        <span>{formatTipePerhitungan(ind.tipe_perhitungan)}</span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 align-top text-xs text-muted">
                                                                {ind.regulasi ? (
                                                                    <div className="space-y-0.5">
                                                                        <div className="font-medium text-ink flex items-center gap-1">
                                                                            <BookOpen className="h-3 w-3 text-primary shrink-0" aria-hidden="true" />
                                                                            <span>{ind.regulasi.jenis.toUpperCase()} No. {ind.regulasi.nomor}/{ind.regulasi.tahun}</span>
                                                                        </div>
                                                                        <p className="text-[11px] text-muted line-clamp-1">
                                                                            {ind.regulasi.tentang}
                                                                        </p>
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-muted/60">—</span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 align-top text-center">
                                                                {ind.is_aktif ? (
                                                                    <Badge variant="success" size="sm" dot>Aktif</Badge>
                                                                ) : (
                                                                    <Badge variant="muted" size="sm">Non-aktif</Badge>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 align-top text-right">
                                                                <div className="flex items-center justify-end gap-1">
                                                                    {can.indikator_update && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => openEditIndikator(ind)}
                                                                            className="p-1 rounded-md text-muted hover:text-primary hover:bg-primary/10 transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
                                                                            title="Ubah Indikator"
                                                                            aria-label={`Ubah indikator ${ind.kode}`}
                                                                        >
                                                                            <Edit3 className="h-3.5 w-3.5" />
                                                                        </button>
                                                                    )}

                                                                    {can.indikator_delete && (
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => setDeleteTarget({ type: 'indikator', item: ind })}
                                                                            className="p-1 rounded-md text-muted hover:text-danger hover:bg-danger/10 transition-colors focus:outline-none focus:ring-2 focus:ring-danger"
                                                                            title="Hapus / Nonaktifkan Indikator"
                                                                            aria-label={`Hapus indikator ${ind.kode}`}
                                                                        >
                                                                            <Trash2 className="h-3.5 w-3.5" />
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
            {selectedRenstraId && (
                <SasaranModal
                    isOpen={sasaranModalOpen}
                    onClose={() => setSasaranModalOpen(false)}
                    renstraId={selectedRenstraId}
                    sasaran={selectedSasaran}
                />
            )}

            {/* Modal Tambah/Edit Indikator */}
            <IndikatorModal
                isOpen={indikatorModalOpen}
                onClose={() => setIndikatorModalOpen(false)}
                sasarans={sasarans}
                defaultSasaranId={defaultSasaranId}
                units={units}
                regulasis={regulasis}
                indikator={selectedIndikator}
            />

            {/* Modal Konfirmasi Hapus dengan Alasan Audit */}
            <DeleteConfirmModal
                target={deleteTarget}
                onClose={() => setDeleteTarget(null)}
            />
        </AuthenticatedLayout>
    );
}
