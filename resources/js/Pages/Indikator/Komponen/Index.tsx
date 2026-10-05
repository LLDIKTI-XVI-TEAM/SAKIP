import { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { HttpResponseError } from '@inertiajs/core';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Button } from '@/Components/Button';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { FormulaModal } from '@/Pages/Perencanaan/SasaranIndikator/FormulaModal';
import { DefinitionPreview } from './DefinitionPreview';
import { loadDefinition, type DefinitionEditor } from './definition';

const tipeLabels = { manual: 'Manual', rasio_persen: 'Rasio Persen', penjumlahan: 'Penjumlahan' };
const peranLabels = { pembilang: 'Pembilang', penyebut: 'Penyebut', penjumlah: 'Penjumlah' };

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
    const openEditor = (addRow: boolean, componentId?: string) => {
        if (loaded && !unavailable) setEditing({ editor: loaded, addRow, componentId });
    };

    return (
        <AuthenticatedLayout
            title="Komponen Indikator"
            breadcrumbs={[{ label: 'Perencanaan' }, { label: 'Sasaran & Indikator', href: '/perencanaan/sasaran-indikator' }, { label: 'Komponen' }]}
        >
            <Head title={`Komponen — ${current.indikator.kode}`} />
            <div className="space-y-6">
                <div className="rounded-xl border border-border bg-surface p-5">
                    <Link href="/perencanaan/sasaran-indikator" className="text-sm text-primary">
                        Kembali ke Sasaran & Indikator
                    </Link>
                    <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-medium text-muted">{current.indikator.kode} · {current.indikator.unit_nama}</p>
                            <h1 className="mt-1 text-xl font-semibold text-ink">{current.indikator.nama}</h1>
                            <p className="mt-2 text-sm text-muted">
                                {tipeLabels[current.indikator.tipe_perhitungan]} · {current.indikator.satuan} · Presisi {current.indikator.presisi}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" disabled={Boolean(editing) || Boolean(recovery)} onClick={() => setReload((value) => value + 1)}>
                                Muat ulang data
                            </Button>
                            {canEdit && <Button disabled={unavailable} onClick={() => openEditor(false)}>Atur Formula</Button>}
                        </div>
                    </div>
                </div>

                <AuthRecoveryNotice recovery={recovery} />
                {error ? (
                    <p role="alert" className="rounded-lg border border-danger/30 p-4">{error}</p>
                ) : !loaded && <p role="status">Memuat seluruh komponen pada revisi yang sama…</p>}

                <section className="rounded-xl border border-border bg-surface p-5" aria-label="Formula tersimpan">
                    <h2 className="font-semibold text-ink">Formula Tersimpan</h2>
                    <p className="mt-2 whitespace-pre-wrap break-words font-mono text-sm">
                        {current.formulaContract?.formula_unavailable || current.formulaContract?.formula_text || 'Formula belum tersedia.'}
                    </p>
                    {current.validation?.messages.map((message) => <p key={message} className="mt-2 text-sm text-danger">{message}</p>)}
                </section>

                <section className="rounded-xl border border-border bg-surface p-5">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold text-ink">Komponen Perhitungan</h2>
                        {current.can.create && <Button variant="outline" disabled={unavailable} onClick={() => openEditor(true)}>Tambah Komponen</Button>}
                    </div>
                    <div className="mb-4 grid gap-3 sm:grid-cols-2">
                        <Input id="komponen-search" label="Cari Komponen" placeholder="Cari kode atau label…" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Select id="komponen-filter" label="Filter Peran" value={peran} onChange={(event) => setPeran(event.target.value)}>
                            <option value="">Semua Peran</option>
                            <option value="pembilang">Pembilang</option>
                            <option value="penyebut">Penyebut</option>
                            <option value="penjumlah">Penjumlah</option>
                        </Select>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border text-muted">
                                <tr>
                                    <th scope="col" className="p-3">Kode</th>
                                    <th scope="col" className="p-3">Label</th>
                                    <th scope="col" className="p-3">Peran</th>
                                    <th scope="col" className="p-3">Bobot</th>
                                    <th scope="col" className="p-3">Status</th>
                                    <th scope="col" className="p-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredRows?.map((row) => (
                                    <tr key={row.id} className="border-b border-border">
                                        <td className="p-3">{row.kode}</td>
                                        <td className="p-3">{row.label}</td>
                                        <td className="p-3">{peranLabels[row.peran]}</td>
                                        <td className="p-3 font-mono">{row.bobot}</td>
                                        <td className="p-3">{row.aktif ? 'Aktif' : 'Nonaktif'}</td>
                                        <td className="p-3">
                                            {(current.can.update || current.can.delete) && (
                                                <Button size="sm" variant="outline" title="Ubah Komponen" aria-label={`Ubah komponen ${row.kode}`} disabled={unavailable} onClick={() => openEditor(false, row.id)}>
                                                    Ubah
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {loaded && filteredRows?.length === 0 && loaded.komponen?.length !== 0 && <p className="mt-3 text-sm text-muted">Tidak ada komponen yang sesuai pencarian.</p>}
                    {loaded?.komponen?.length === 0 && <p className="mt-3 text-sm text-muted">Belum ada komponen.</p>}
                </section>

                {loaded && current.indikator.tipe_perhitungan !== 'manual' && (
                    <>
                        <Button variant="outline" onClick={() => setShowPreview((value) => !value)}>{showPreview ? 'Tutup Simulasi' : 'Simulasi Perhitungan'}</Button>
                        {showPreview && <DefinitionPreview key={`${loaded.indikator.id}:${loaded.revision}`} editor={loaded} paused={Boolean(editing) || Boolean(recovery)} />}
                    </>
                )}
            </div>
            {editing && (
                <FormulaModal isOpen onClose={() => setEditing(null)} editor={editing.editor} addRow={editing.addRow} focusComponentId={editing.componentId} returnTo="komponen" />
            )}
        </AuthenticatedLayout>
    );
}
