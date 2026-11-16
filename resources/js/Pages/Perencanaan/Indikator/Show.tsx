import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { IndikatorModal } from '@/Pages/Perencanaan/SasaranIndikator/IndikatorModal';
import { useIndikatorEditorLoader, type LoadedIndikatorEditor } from '@/Pages/Perencanaan/SasaranIndikator/useIndikatorEditorLoader';
import { arahLabel, tipePerhitunganLabel } from '@/lib/indikator';
import { formatRegulasiRingkas } from '@/lib/regulasi';
import type {
    IndikatorKinerjaItem,
    RegulasiOption,
    RenstraOption,
    SasaranIndikatorCapabilities,
    SasaranStrategisItem,
    UnitOption,
} from '@/types/sasaran-indikator';

interface ShowIndikatorProps {
    indikator: IndikatorKinerjaItem;
    sasaran: { id: string; kode: string; deskripsi: string };
    renstra: RenstraOption;
    jumlahKomponenAktif: number | null;
    sasarans: SasaranStrategisItem[];
    units: UnitOption[];
    regulasis: RegulasiOption[];
    can: SasaranIndikatorCapabilities;
}

const linkAction = 'inline-flex h-9 items-center justify-center rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';

function Field({ label, children, wide = false }: { label: string; children: React.ReactNode; wide?: boolean }) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs font-semibold text-muted">{label}</dt>
            <dd className="mt-1 text-sm leading-6 text-ink">{children}</dd>
        </div>
    );
}

export default function ShowIndikator({ indikator, sasaran, renstra, jumlahKomponenAktif, sasarans, units, regulasis, can }: ShowIndikatorProps) {
    const editorLoader = useIndikatorEditorLoader();
    const [editor, setEditor] = useState<LoadedIndikatorEditor | null>(null);
    const daftarHref = `/perencanaan/sasaran-indikator?renstra_id=${renstra.id}`;
    const nonManual = indikator.tipe_perhitungan !== 'manual';

    const openEdit = async () => {
        const loaded = await editorLoader.load(indikator.id);
        if (loaded) setEditor(loaded);
    };

    return (
        <AuthenticatedLayout
            title="Detail Indikator"
            breadcrumbs={[
                { label: 'Perencanaan' },
                { label: 'Sasaran & Indikator', href: daftarHref },
                { label: indikator.kode },
            ]}
        >
            <Head title={`Detail Indikator: ${indikator.kode}`} />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link href={daftarHref} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke daftar
                    </Link>

                    <div className="flex flex-wrap items-center gap-2">
                        {can.penanggung_jawab_update && (
                            <Link href={`/perencanaan/indikator/${indikator.id}/penanggung-jawab`} className={linkAction}>Penanggung Jawab</Link>
                        )}
                        {nonManual && can.komponen_read && (
                            <Link href={`/indikator/${indikator.id}/komponen`} className={linkAction}>Kelola Komponen</Link>
                        )}
                        {can.indikator_update && (
                            <Button type="button" size="sm" onClick={() => { void openEdit(); }}>Ubah Indikator</Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader className="items-start gap-4">
                        <div className="min-w-0">
                            <CardTitle>{indikator.nama}</CardTitle>
                            <p className="mt-1 font-mono text-sm text-muted">{indikator.kode}</p>
                        </div>
                        <Badge variant={indikator.status === 'aktif' ? 'success' : 'muted'} dot className="shrink-0">
                            {indikator.status === 'aktif' ? 'Aktif' : 'Arsip'}
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <Field label="Sasaran strategis" wide>
                                <span className="font-mono font-semibold">{sasaran.kode}</span> · {sasaran.deskripsi}
                            </Field>
                            <Field label="Renstra">{renstra.nama} ({renstra.tahun_mulai}–{renstra.tahun_selesai})</Field>
                            <Field label="Tahun mulai berlaku">{indikator.tahun_mulai_berlaku}</Field>
                            <Field label="Unit">{indikator.unit_nama || indikator.unit_id}</Field>
                            {can.penanggung_jawab_update && (
                                <Field label="Penanggung jawab">
                                    {indikator.penanggung_jawab ? (
                                        <span className="inline-flex flex-wrap items-center gap-1.5">
                                            {indikator.penanggung_jawab.nama}
                                            {indikator.penanggung_jawab.status !== 'aktif' && <Badge variant="muted" size="sm">Nonaktif</Badge>}
                                        </span>
                                    ) : (
                                        <span className="font-medium text-warning-dark">Belum ditetapkan</span>
                                    )}
                                </Field>
                            )}
                            <Field label="Satuan"><span className="font-mono">{indikator.satuan}</span></Field>
                            <Field label="Arah kinerja">{arahLabel[indikator.arah]}</Field>
                            <Field label="Tipe perhitungan">
                                {tipePerhitunganLabel[indikator.tipe_perhitungan]}
                                {nonManual && jumlahKomponenAktif !== null && <span className="text-muted"> · {jumlahKomponenAktif} komponen aktif</span>}
                            </Field>
                            <Field label="Presisi">{indikator.presisi} desimal disimpan, {indikator.desimal_tampilan} ditampilkan</Field>
                            <Field label="Catatan saat pengisian">{indikator.wajib_catatan ? 'Wajib' : 'Tidak wajib'}</Field>
                            {can.regulasi_read && (
                                <Field label="Regulasi rujukan" wide>
                                    {indikator.regulasi ? (
                                        <span className="block space-y-1">
                                            <span className="block font-semibold">{formatRegulasiRingkas(indikator.regulasi)}</span>
                                            <span className="block">{indikator.regulasi.tentang}</span>
                                            <Link href={`/regulasi/${indikator.regulasi.id}`} className="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
                                                Buka dasar aturan <ExternalLink className="h-4 w-4" aria-hidden="true" />
                                            </Link>
                                        </span>
                                    ) : (
                                        <span className="text-muted">Tidak dicantumkan</span>
                                    )}
                                </Field>
                            )}
                            <Field label="Definisi operasional" wide>
                                <span className="whitespace-pre-wrap">{indikator.definisi_operasional || 'Tidak dicantumkan.'}</span>
                            </Field>
                        </dl>
                    </CardContent>
                </Card>
            </div>

            {editor && (
                <IndikatorModal
                    isOpen
                    onClose={() => setEditor(null)}
                    sasarans={sasarans}
                    defaultSasaranId={indikator.sasaran_strategis_id}
                    units={units}
                    regulasis={regulasis}
                    indikator={editor.indikator}
                    editor={editor}
                    can={can}
                    kembaliKe="detail"
                />
            )}
            {editorLoader.loaderModal}
        </AuthenticatedLayout>
    );
}
