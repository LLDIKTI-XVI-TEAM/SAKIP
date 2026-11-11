import { useId, useState, type ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, History, PenLine } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { StatusCapaianBadge } from '@/Components/Pengukuran/StatusCapaianBadge';
import {
    Table, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive,
} from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import type { ModeStatusCapaian, StatusCapaianRincianProps } from '@/types/statusCapaian';
import DialogStatusCapaian, { RincianAngka } from './DialogStatusCapaian';

const sel = `${tableCellResponsive} block align-top md:table-cell`;

function Butir({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-muted">{label}</dt>
            <dd className="mt-1 break-words text-sm font-medium text-ink">{children}</dd>
        </div>
    );
}

/**
 * Rincian satu pengukuran pada daftar Status Capaian. Hanya menampilkan baris yang dikirim server;
 * penetapan dan revisi tetap lewat dialog yang sama dengan daftar, mengikuti `can` server.
 */
export default function StatusCapaianRincian({ pengukuran: baris, can }: StatusCapaianRincianProps) {
    const formatTanggal = useFormatTanggal();
    const labelUnit = useLabelUnit();
    const idIdentitas = useId();
    const idAngka = useId();
    const idRiwayat = useId();
    const [mode, setMode] = useState<ModeStatusCapaian | null>(null);

    const berlaku = baris.status_berlaku;
    const sebutan = baris.indikator.kode ?? baris.indikator.nama ?? '';
    // Kembali ke tab tempat baris ini berada: yang sudah punya status berlaku ada di tab "Sudah ditetapkan".
    const daftar = `/verifikasi/status-capaian${berlaku ? '?tab=ditetapkan' : ''}`;
    const waktu = (nilai: string | null | undefined) => nilai ? formatTanggal(nilai, { withTime: true }) : '-';

    return (
        <AuthenticatedLayout
            title="Rincian Status Capaian"
            breadcrumbs={[
                { label: 'Verifikasi Kinerja', href: '/verifikasi' },
                { label: 'Status Capaian', href: daftar },
                { label: sebutan || 'Rincian' },
            ]}
        >
            <Head title={`Status Capaian ${sebutan}`.trim()} />

            <div className="min-w-0 space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Link
                        href={daftar}
                        className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                    >
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke daftar
                    </Link>
                    {can.tetapkan && baris.can.tetapkan && (
                        <Button type="button" size="sm" onClick={() => setMode('tetapkan')} aria-label={`Tetapkan status capaian ${sebutan}`}>
                            <ClipboardCheck className="h-3.5 w-3.5" aria-hidden="true" />
                            Tetapkan
                        </Button>
                    )}
                    {can.tetapkan && baris.can.revisi && (
                        <Button type="button" size="sm" variant="outline" onClick={() => setMode('revisi')} aria-label={`Revisi status capaian ${sebutan}`}>
                            <PenLine className="h-3.5 w-3.5" aria-hidden="true" />
                            Revisi
                        </Button>
                    )}
                </div>

                <Card role="region" aria-labelledby={idIdentitas}>
                    <CardHeader className="items-start">
                        <CardTitle id={idIdentitas} className="min-w-0 flex-1 break-words">
                            {baris.indikator.kode ?? '-'} · {baris.indikator.nama ?? '-'}
                        </CardTitle>
                        <StatusCapaianBadge kode={berlaku?.status ?? 'belum_ditetapkan'} className="shrink-0" />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                            <Butir label={labelUnit}>{baris.unit.nama ?? '-'}</Butir>
                            <Butir label="Periode">{`${baris.periode.nama ?? '-'} ${baris.tahun}`}</Butir>
                            <Butir label="Versi pengukuran">{baris.versi_pengukuran === null ? '-' : `Versi ${baris.versi_pengukuran.nomor}`}</Butir>
                            <Butir label="Tanggal disahkan">{baris.versi_pengukuran?.disahkan_pada ? formatTanggal(baris.versi_pengukuran.disahkan_pada) : '-'}</Butir>
                            {berlaku && (
                                <>
                                    <Butir label="Ditetapkan oleh">{berlaku.penetap ?? '-'}</Butir>
                                    <Butir label="Waktu penetapan">{waktu(berlaku.ditetapkan_pada)}</Butir>
                                </>
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <Card role="region" aria-labelledby={idAngka}>
                    <CardHeader>
                        <div className="min-w-0">
                            <CardTitle id={idAngka}>Angka pengukuran</CardTitle>
                            <p className="mt-1 text-xs leading-relaxed text-muted">Dibaca dari versi pengukuran yang disahkan dan tidak berubah mengikuti data terbaru.</p>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <RincianAngka baris={baris} luas />
                    </CardContent>
                </Card>

                <Card role="region" aria-labelledby={idRiwayat}>
                    <CardHeader>
                        <div className="min-w-0">
                            <CardTitle id={idRiwayat}>Riwayat status</CardTitle>
                            <p className="mt-1 text-xs leading-relaxed text-muted">Seluruh penetapan dan revisi pada pengukuran ini, dari yang terbaru.</p>
                        </div>
                        <Badge variant="muted" size="sm">{baris.riwayat.length} catatan</Badge>
                    </CardHeader>
                    <Table wrapperClassName="overflow-x-visible" className="block md:table" aria-label="Riwayat status capaian">
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead>STATUS</TableHead>
                                <TableHead>VERSI</TableHead>
                                <TableHead>DITETAPKAN OLEH</TableHead>
                                <TableHead>WAKTU</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {baris.riwayat.length === 0 ? (
                                <TableEmptyRow
                                    colSpan={4}
                                    icon={History}
                                    title="Belum ada riwayat status"
                                    description="Penetapan dan revisi status capaian pengukuran ini akan tercatat di sini."
                                />
                            ) : baris.riwayat.map((item) => (
                                <TableRow key={item.id} className="block min-w-0 space-y-2 p-4 md:table-row md:space-y-0 md:p-0">
                                    <TableCell className={sel}>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusCapaianBadge kode={item.status} size="sm" />
                                            {item.id === berlaku?.id && <Badge variant="primary" size="sm">Berlaku</Badge>}
                                        </div>
                                    </TableCell>
                                    <TableCell className={sel}>
                                        <TableCellLabel>Versi</TableCellLabel>
                                        <span className="text-xs text-ink">{item.versi_nomor === null ? '-' : `Versi ${item.versi_nomor}`}</span>
                                    </TableCell>
                                    <TableCell className={sel}>
                                        <TableCellLabel>Ditetapkan oleh</TableCellLabel>
                                        <span className="break-words text-xs text-ink">{item.penetap ?? '-'}</span>
                                    </TableCell>
                                    <TableCell className={sel}>
                                        <TableCellLabel>Waktu</TableCellLabel>
                                        <span className="text-xs text-ink md:whitespace-nowrap">{waktu(item.ditetapkan_pada)}</span>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            </div>

            {mode && (
                <DialogStatusCapaian
                    key={`${mode}-${baris.id}-${baris.versi}`}
                    baris={baris}
                    mode={mode}
                    onClose={() => setMode(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
