import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { ActionMenu, type ActionMenuItem } from '@/Components/ActionMenu';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { formatRegulasiRingkas } from '@/lib/regulasi';
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
    MoreHorizontal,
    Eye,
} from 'lucide-react';
import { SasaranModal } from './SasaranModal';
import { IndikatorModal } from './IndikatorModal';
import { PindahUnitModal } from './PindahUnitModal';
import { type DefinitionEditor } from '@/Pages/Indikator/Komponen/definition';
import { useIndikatorEditorLoader } from './useIndikatorEditorLoader';
import { tipePerhitunganLabel } from '@/lib/indikator';
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

const iconAction = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
const iconDangerAction = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-danger/40 hover:bg-danger/10 hover:text-danger focus:outline-none focus:ring-2 focus:ring-danger/20';

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
    const [defaultSasaranId, setDefaultSasaranId] = useState<string | undefined>();

    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);

    const [pindahTarget, setPindahTarget] = useState<IndikatorKinerjaItem | null>(null);

    const [formulaTarget, setFormulaTarget] = useState<DefinitionEditor | null>(null);
    const [annualTarget, setAnnualTarget] = useState<{ id: string; year: number } | null>(null);
    const [annualMessage, setAnnualMessage] = useState('');
    const editorLoader = useIndikatorEditorLoader();

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
        editorLoader.cancel();
        setDefaultSasaranId(sasaranId || sasarans[0]?.id);
        setIndikatorModalOpen(true);
    };

    const openFormula = async (indikator: IndikatorKinerjaItem) => {
        const editor = await editorLoader.load(indikator.id);
        if (editor) setFormulaTarget(editor);
    };

    const selectedRenstra = renstras.find((r) => r.id === selectedRenstraId);

    const canAturFormula = can.komponen_read && (can.indikator_update || can.komponen_create || can.komponen_update || can.komponen_delete);

    const indikatorActions = (ind: IndikatorKinerjaItem): ActionMenuItem[] => [
        ...(can.indikator_read ? [{ key: 'detail', label: 'Lihat Detail', icon: Eye, href: `/perencanaan/indikator/${ind.id}`, ariaLabel: `Lihat detail indikator ${ind.kode}` }] : []),
        ...(can.penanggung_jawab_update ? [{ key: 'pj', label: 'Penanggung Jawab', icon: UserCheck, href: `/perencanaan/indikator/${ind.id}/penanggung-jawab`, ariaLabel: `Penanggung jawab ${ind.kode}` }] : []),
        ...(can.indikator_read && selectedRenstra ? [{
            key: 'target', label: 'Baseline & Target', icon: Target, ariaLabel: `Baseline & target ${ind.kode}`,
            onSelect: () => {
                setAnnualMessage('');
                setAnnualTarget({ id: ind.id, year: Math.max(ind.tahun_mulai_berlaku, selectedRenstra.tahun_mulai, Math.min(new Date().getFullYear(), selectedRenstra.tahun_selesai)) });
            },
        }] : []),
        ...(ind.tipe_perhitungan !== 'manual' && can.komponen_read ? [{ key: 'komponen', label: 'Komponen Perhitungan', icon: Calculator, href: `/indikator/${ind.id}/komponen`, ariaLabel: `Kelola komponen ${ind.kode}` }] : []),
        ...(canAturFormula ? [{ key: 'formula', label: 'Atur Formula', icon: Sigma, ariaLabel: `Atur formula indikator ${ind.kode}`, onSelect: () => { void openFormula(ind); } }] : []),
        ...(can.indikator_update ? [
            { key: 'pindah', label: 'Pindah Unit', icon: ArrowLeftRight, ariaLabel: `Pindah unit indikator ${ind.kode}`, onSelect: () => setPindahTarget(ind) },
        ] : []),
        ...(can.indikator_delete ? [{ key: 'arsip', label: 'Arsipkan Indikator', icon: Trash2, ariaLabel: `Hapus indikator ${ind.kode}`, danger: true, onSelect: () => setDeleteTarget({ type: 'indikator', item: ind }) }] : []),
    ];

    return (
        <AuthenticatedLayout
            title="Sasaran & Indikator Kinerja"
            breadcrumbs={[
                { label: 'Perencanaan' },
                { label: 'Sasaran & Indikator' },
            ]}
        >
            <Head title="Sasaran Strategis & Indikator Kinerja" />

            {annualMessage && <p role="status" className="mb-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm">{annualMessage}</p>}

            <div className="space-y-6">
                {(renstras.length > 0 || can.penanggung_jawab_update) && (
                    <Card className="overflow-visible p-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            {renstras.length > 0 && (
                                <div className="w-full sm:w-96">
                                    <Select
                                        id="renstra_filter"
                                        label="Periode Renstra"
                                        value={selectedRenstraId || ''}
                                        onChange={(e) => handleRenstraChange(e.target.value)}
                                    >
                                        {renstras.map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.nama} ({r.tahun_mulai}–{r.tahun_selesai}){r.is_aktif ? ' · Aktif' : ''}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            )}
                            <div className="flex items-center gap-2 sm:ml-auto">
                                {can.penanggung_jawab_update && (
                                    <Link
                                        href="/penanggung-jawab"
                                        className="inline-flex h-[42px] shrink-0 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                                    >
                                        <UserCheck className="h-4 w-4 shrink-0" aria-hidden="true" />
                                        Monitoring PJ
                                    </Link>
                                )}
                                <TambahMenu
                                    showSasaran={can.sasaran_create && Boolean(selectedRenstraId)}
                                    showIndikator={can.indikator_create && sasarans.length > 0}
                                    onAddSasaran={openCreateSasaran}
                                    onAddIndikator={() => openCreateIndikator()}
                                />
                            </div>
                        </div>
                    </Card>
                )}

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
                                <Button variant="outline" size="sm" onClick={openCreateSasaran} className="gap-1.5">
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
                                            <Badge variant="primary" size="sm" className="font-mono">
                                                {sasaran.kode}
                                            </Badge>
                                            <span className="text-xs text-muted">
                                                {sasaran.indikator_kinerjas.length} indikator
                                            </span>
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
                                        <Tooltip content="Ubah Sasaran">
                                            <button
                                                type="button"
                                                onClick={() => openEditSasaran(sasaran)}
                                                className={iconAction}
                                                aria-label={`Ubah sasaran ${sasaran.kode}`}
                                            >
                                                <Edit3 className="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </Tooltip>
                                    )}

                                    {can.sasaran_delete && (
                                        <Tooltip content="Hapus Sasaran" align="right">
                                            <button
                                                type="button"
                                                onClick={() => setDeleteTarget({ type: 'sasaran', item: sasaran })}
                                                className={iconDangerAction}
                                                aria-label={`Hapus sasaran ${sasaran.kode}`}
                                            >
                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </Tooltip>
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
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-28">Kode</TableHead>
                                                    <TableHead>Indikator Kinerja</TableHead>
                                                    <TableHead className="w-48">Unit</TableHead>
                                                    {can.penanggung_jawab_update && <TableHead className="w-48">Penanggung Jawab</TableHead>}
                                                    <TableHead className="w-36">Satuan</TableHead>
                                                    <TableHead className="w-16 text-right">Aksi</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {sasaran.indikator_kinerjas.map((ind) => (
                                                    <TableRow key={ind.id} className={ind.status === 'arsip' ? 'bg-soft/20 opacity-60' : undefined}>
                                                        <TableCell className="whitespace-nowrap font-mono font-semibold">{ind.kode}</TableCell>

                                                        <TableCell>
                                                            <div className="space-y-1">
                                                                <p className="text-sm font-medium leading-snug text-ink">{ind.nama}</p>
                                                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                                                    <span>{tipePerhitunganLabel[ind.tipe_perhitungan] || ind.tipe_perhitungan}</span>
                                                                    {selectedRenstra && ind.tahun_mulai_berlaku > selectedRenstra.tahun_mulai && (
                                                                        <>
                                                                            <span className="text-muted/40" aria-hidden="true">·</span>
                                                                            <span>Mulai {ind.tahun_mulai_berlaku}</span>
                                                                        </>
                                                                    )}
                                                                    {ind.status === 'arsip' && (
                                                                        <Badge variant="muted" size="sm">
                                                                            Arsip
                                                                        </Badge>
                                                                    )}
                                                                    {can.regulasi_read !== false && ind.regulasi && (
                                                                        <>
                                                                            <span className="text-muted/40" aria-hidden="true">·</span>
                                                                            <span className="flex items-center gap-1">
                                                                                <BookOpen className="h-3 w-3 shrink-0" aria-hidden="true" />
                                                                                <span>{formatRegulasiRingkas(ind.regulasi)}</span>
                                                                            </span>
                                                                        </>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </TableCell>

                                                        <TableCell className="font-medium">{ind.unit_nama || ind.unit_id}</TableCell>

                                                        {can.penanggung_jawab_update && (
                                                            <TableCell>
                                                                {ind.penanggung_jawab ? (
                                                                    <span className="inline-flex flex-wrap items-center gap-1.5 font-medium">
                                                                        {ind.penanggung_jawab.nama}
                                                                        {ind.penanggung_jawab.status !== 'aktif' && <Badge variant="muted" size="sm">Nonaktif</Badge>}
                                                                    </span>
                                                                ) : (
                                                                    <span className="font-medium text-warning-dark">Belum ditetapkan</span>
                                                                )}
                                                            </TableCell>
                                                        )}

                                                        <TableCell className="whitespace-nowrap">
                                                            <span className="font-mono font-semibold text-ink">{ind.satuan}</span>
                                                            <span className="ml-2 inline-flex items-center gap-0.5 text-muted">
                                                                {ind.arah === 'naik_baik' ? (
                                                                    <><TrendingUp className="h-3 w-3" aria-hidden="true" /> Naik</>
                                                                ) : (
                                                                    <><TrendingDown className="h-3 w-3" aria-hidden="true" /> Turun</>
                                                                )}
                                                            </span>
                                                        </TableCell>

                                                        <TableCell className="text-right">
                                                            <ActionMenu
                                                                items={indikatorActions(ind)}
                                                                trigger={<MoreHorizontal className="h-4 w-4" aria-hidden="true" />}
                                                                triggerLabel={`Aksi ${ind.kode}`}
                                                                triggerClassName="h-8 w-8 px-0"
                                                            />
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
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
                indikator={null}
                editor={null}
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
            {editorLoader.loaderModal}

            {/* Modal Konfirmasi Hapus dengan Alasan Audit */}
            <DeleteConfirmModal
                target={deleteTarget}
                onClose={() => setDeleteTarget(null)}
            />
        </AuthenticatedLayout>
    );
}
