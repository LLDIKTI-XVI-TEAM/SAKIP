import { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Edit3, RefreshCw } from 'lucide-react';
import { HttpResponseError } from '@inertiajs/core';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { tipePerhitunganLabel } from '@/lib/indikator';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { FormulaModal } from '@/Pages/Perencanaan/SasaranIndikator/FormulaModal';
import { DefinitionPreview } from './DefinitionPreview';
import { loadDefinition, type DefinitionEditor } from './definition';

const peranLabels = { pembilang: 'Pembilang', penyebut: 'Penyebut', penjumlah: 'Penjumlah' };
const iconAction = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50';

export default function KomponenIndex({ editor }: { editor: DefinitionEditor }) {
    const [search, setSearch] = useState('');
    const [peran, setPeran] = useState('');
    const [loaded, setLoaded] = useState<DefinitionEditor | null>(null);
    const [error, setError] = useState('');
    const [reload, setReload] = useState(0);
    const [editing, setEditing] = useState<{ editor: DefinitionEditor; addRow: boolean; componentId?: string } | null>(null);
    const [showPreview, setShowPreview] = useState(false);
    const { recovery, handleHttpException } = useAuthRecovery();

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        setLoaded(null);
        setError('');
        const path = `/indikator/${editor.indikator.id}/komponen`;
        loadDefinition(path, controller.signal, reload === 0 ? editor : undefined)
            .then((value) => { if (current) setLoaded(value); })
            .catch((exception: unknown) => {
                if (!current) return;
                if (exception instanceof HttpResponseError) {
                    handleHttpException(exception.response, { effectiveMethod: 'get', path, mutation: false });
                }
                setError('Data editor belum lengkap atau telah berubah. Muat ulang untuk mencoba kembali.');
            });
        return () => { current = false; controller.abort(); };
    }, [editor, reload, handleHttpException]);

    const current = loaded ?? editor;
    const filteredRows = current.komponen?.filter((row) =>
        (!peran || row.peran === peran)
        && `${row.kode} ${row.label}`.toLocaleLowerCase('id').includes(search.toLocaleLowerCase('id'))
    );
    const canEdit = current.can.create || current.can.update || current.can.delete || current.can.update_indikator;
    const unavailable = !loaded || Boolean(editing) || Boolean(recovery);
    const detailHref = `/perencanaan/indikator/${current.indikator.id}`;
    const openEditor = (addRow: boolean, componentId?: string) => {
        if (loaded && !unavailable) setEditing({ editor: loaded, addRow, componentId });
    };

    return (
        <AuthenticatedLayout
            title="Komponen Indikator"
            breadcrumbs={[
                { label: 'Perencanaan' },
                { label: 'Sasaran & Indikator', href: '/perencanaan/sasaran-indikator' },
                { label: current.indikator.kode, href: detailHref },
                { label: 'Komponen' },
            ]}
        >
            <Head title={`Komponen — ${current.indikator.kode}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link href={detailHref} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke detail indikator
                    </Link>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" className="gap-1.5" disabled={Boolean(editing) || Boolean(recovery)} onClick={() => setReload((value) => value + 1)}>
                            <RefreshCw className="h-4 w-4" aria-hidden="true" />
                            Muat ulang data
                        </Button>
                        {canEdit && <Button size="sm" disabled={unavailable} onClick={() => openEditor(false)}>Atur Formula</Button>}
                    </div>
                </div>

                <AuthRecoveryNotice recovery={recovery} />
                {error ? (
                    <p role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm text-danger">{error}</p>
                ) : !loaded && <p role="status" className="rounded-lg bg-soft p-4 text-sm text-muted">Memuat seluruh komponen pada revisi yang sama…</p>}

                <Card>
                    <CardHeader className="items-start gap-4">
                        <div className="min-w-0">
                            <CardTitle>{current.indikator.nama}</CardTitle>
                            <p className="mt-1 text-sm text-muted">
                                <span className="font-mono">{current.indikator.kode}</span> · {current.indikator.unit_nama} · Satuan <span className="font-mono">{current.indikator.satuan}</span> · Presisi {current.indikator.presisi}
                            </p>
                        </div>
                        <Badge variant="primary" className="shrink-0">{tipePerhitunganLabel[current.indikator.tipe_perhitungan]}</Badge>
                    </CardHeader>
                    <CardContent>
                        <p className="text-xs font-semibold text-muted">Formula tersimpan</p>
                        <p className="mt-2 whitespace-pre-wrap break-words rounded-lg bg-soft px-4 py-3 font-mono text-sm text-ink">
                            {current.formulaContract?.formula_unavailable || current.formulaContract?.formula_text || 'Formula belum tersedia.'}
                        </p>
                        {current.validation?.messages.map((message) => <p key={message} className="mt-2 text-sm text-danger">{message}</p>)}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Komponen perhitungan</CardTitle>
                        {current.can.create && <Button variant="outline" size="sm" disabled={unavailable} onClick={() => openEditor(true)}>Tambah Komponen</Button>}
                    </CardHeader>
                    <div className="grid gap-3 border-b border-border p-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                        <Input id="komponen-search" label="Cari Komponen" placeholder="Cari kode atau label…" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Select id="komponen-filter" label="Filter Peran" value={peran} onChange={(event) => setPeran(event.target.value)}>
                            <option value="">Semua Peran</option>
                            <option value="pembilang">Pembilang</option>
                            <option value="penyebut">Penyebut</option>
                            <option value="penjumlah">Penjumlah</option>
                        </Select>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-28">Kode</TableHead>
                                <TableHead>Label</TableHead>
                                <TableHead className="w-32">Peran</TableHead>
                                <TableHead className="w-40 text-right">Bobot</TableHead>
                                <TableHead className="w-28">Status</TableHead>
                                <TableHead className="w-16 text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredRows?.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="whitespace-nowrap font-mono font-semibold">{row.kode}</TableCell>
                                    <TableCell className="text-sm">{row.label}</TableCell>
                                    <TableCell>{peranLabels[row.peran]}</TableCell>
                                    <TableCell className="whitespace-nowrap text-right font-mono">{row.bobot}</TableCell>
                                    <TableCell><Badge variant={row.aktif ? 'success' : 'muted'} size="sm">{row.aktif ? 'Aktif' : 'Nonaktif'}</Badge></TableCell>
                                    <TableCell className="text-right">
                                        {(current.can.update || current.can.delete) && (
                                            <Tooltip content="Ubah Komponen" align="right">
                                                <button type="button" className={iconAction} aria-label={`Ubah komponen ${row.kode}`} disabled={unavailable} onClick={() => openEditor(false, row.id)}>
                                                    <Edit3 className="h-4 w-4" aria-hidden="true" />
                                                </button>
                                            </Tooltip>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {loaded && filteredRows?.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-sm text-muted">
                                        {loaded.komponen?.length === 0 ? 'Belum ada komponen.' : 'Tidak ada komponen yang sesuai pencarian.'}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </Card>

                {loaded && current.indikator.tipe_perhitungan !== 'manual' && (
                    <Card>
                        <CardHeader className={showPreview ? undefined : 'border-b-0'}>
                            <CardTitle>Simulasi perhitungan</CardTitle>
                            <Button variant="outline" size="sm" className="gap-1.5" aria-expanded={showPreview} onClick={() => setShowPreview((value) => !value)}>
                                {showPreview ? 'Tutup Simulasi' : 'Simulasi Perhitungan'}
                                <ChevronDown className={`h-4 w-4 transition-transform ${showPreview ? 'rotate-180' : ''}`} aria-hidden="true" />
                            </Button>
                        </CardHeader>
                        {showPreview && (
                            <CardContent>
                                <DefinitionPreview key={`${loaded.indikator.id}:${loaded.revision}`} editor={loaded} paused={Boolean(editing) || Boolean(recovery)} />
                            </CardContent>
                        )}
                    </Card>
                )}
            </div>
            {editing && (
                <FormulaModal isOpen onClose={() => setEditing(null)} editor={editing.editor} addRow={editing.addRow} focusComponentId={editing.componentId} returnTo="komponen" />
            )}
        </AuthenticatedLayout>
    );
}
