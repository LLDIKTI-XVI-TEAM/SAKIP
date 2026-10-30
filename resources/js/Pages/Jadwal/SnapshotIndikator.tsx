import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Layers } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { KoreksiSnapshotModal } from '@/Components/Jadwal/KoreksiSnapshotModal';
import { Table, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive } from '@/Components/Table';
import { formatTanggal } from '@/hooks/useFormatTanggal';
import { statusJadwal } from '@/lib/jadwal';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { SnapshotIndikatorProps, SnapshotIndikatorRow } from '@/types/snapshot-indikator';

const linkClass = 'text-sm font-medium text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/30 rounded';

const berbeda = (baris: SnapshotIndikatorRow): boolean => baris.selisih.kolom.length > 0 || baris.selisih.komponen !== null;

/** Snapshot indikator per jadwal: versi berlaku, riwayat versi, dan koreksi dari data master (Q79). */
export default function SnapshotIndikator({ jadwal, periode, indikator, can }: SnapshotIndikatorProps) {
    // Hanya identitas yang disimpan; baris dibaca dari props terbaru agar modal mengikuti versi baru setelah penolakan usang.
    const [dipilihId, setDipilihId] = useState<string | null>(null);
    const dipilih = indikator.find((baris) => baris.indikator_id === dipilihId) ?? null;
    const [unknown, setUnknown] = useState(false);
    const formatNilai = useFormatNilai();
    const title = `Snapshot Indikator ${jadwal.tahun}`;
    const status = statusJadwal[jadwal.status];

    return (
        <AuthenticatedLayout
            title={title}
            breadcrumbs={[
                { label: 'Jadwal Tahunan', href: '/jadwal' },
                { label: `Tahun ${jadwal.tahun}`, href: `/jadwal/${jadwal.id}` },
                { label: 'Snapshot Indikator' },
            ]}
        >
            <Head title={title} />
            <div className="mb-5">
                <Link href={`/jadwal/${jadwal.id}`} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke jadwal
                </Link>
            </div>
            {unknown && (
                <div role="alert" className="mb-5 space-y-1 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm">
                    <p>Hasil koreksi belum terkonfirmasi. Periksa snapshot terbaru sebelum mencoba lagi.</p>
                    <Link href={`/jadwal/${jadwal.id}/snapshot`} className={linkClass}>
                        Periksa snapshot terbaru
                    </Link>
                </div>
            )}
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-semibold">Konteks indikator yang dibekukan</h2>
                        <Badge variant={status.variant}>{status.label}</Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted">
                        {jadwal.renstra ? `${jadwal.renstra}. ` : ''}Koreksi menerbitkan versi baru dari data master terkini; versi lama tetap dipakai data yang
                        sudah merujuknya. Perbaiki data di Sasaran & Indikator atau Perjanjian Kinerja terlebih dahulu.
                    </p>
                </CardHeader>
                <CardContent className="p-0">
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead>Indikator</TableHead>
                                <TableHead>Versi berlaku</TableHead>
                                <TableHead>Target PK</TableHead>
                                <TableHead>Data master</TableHead>
                                <TableHead>
                                    <span className="sr-only">Aksi</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {indikator.length === 0 && (
                                <TableEmptyRow
                                    colSpan={5}
                                    icon={Layers}
                                    title={can.baca_indikator ? 'Belum ada snapshot pada jadwal ini' : 'Isi snapshot tidak ditampilkan'}
                                    description={can.baca_indikator ? undefined : 'Akun Anda tidak memiliki izin membaca indikator.'}
                                />
                            )}
                            {indikator.map((baris) => (
                                <TableRow key={baris.indikator_id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                    <TableCell className={`col-span-2 align-top ${tableCellResponsive}`}>
                                        <p className="text-sm font-semibold">{baris.kode ?? '—'}</p>
                                        <p className="break-words text-sm">{baris.nama}</p>
                                        {baris.unit && <p className="text-xs text-muted">{baris.unit}</p>}
                                        <details className="mt-2 text-sm">
                                            <summary className="cursor-pointer text-primary">Riwayat versi ({baris.riwayat.length})</summary>
                                            <ol className="mt-2 space-y-2">
                                                {baris.riwayat.map((versi) => (
                                                    <li key={versi.snapshot_id} className="rounded-lg border border-border p-2">
                                                        <p className="font-semibold">
                                                            Versi {versi.nomor_versi}
                                                            {versi.periode_mulai ? ` · mulai ${versi.periode_mulai}` : ''}
                                                        </p>
                                                        <p className="text-xs text-muted">
                                                            {versi.diterbitkan_pada ? (
                                                                <time dateTime={versi.diterbitkan_pada}>{formatTanggal(versi.diterbitkan_pada, { withTime: true })}</time>
                                                            ) : (
                                                                'Waktu terbit tidak tercatat'
                                                            )}
                                                        </p>
                                                        {versi.alasan_koreksi && <p className="break-words">Alasan: {versi.alasan_koreksi}</p>}
                                                        {versi.rujukan_koreksi && <p className="break-words">Rujukan: {versi.rujukan_koreksi}</p>}
                                                    </li>
                                                ))}
                                            </ol>
                                        </details>
                                    </TableCell>
                                    <TableCell className={`align-top ${tableCellResponsive}`}>
                                        <TableCellLabel>Versi berlaku</TableCellLabel>
                                        <span className="text-sm">Versi {baris.nomor_versi}</span>
                                        {baris.periode_mulai && <span className="block text-xs text-muted">Mulai {baris.periode_mulai.nama}</span>}
                                    </TableCell>
                                    <TableCell className={`align-top ${tableCellResponsive}`}>
                                        <TableCellLabel>Target PK</TableCellLabel>
                                        <span className="text-sm">{baris.target === null ? '—' : formatNilai(baris.target, baris.desimal_tampilan)} {baris.satuan}</span>
                                    </TableCell>
                                    <TableCell className={`align-top ${tableCellResponsive}`}>
                                        <TableCellLabel>Data master</TableCellLabel>
                                        {berbeda(baris) ? <Badge variant="warning">Berbeda</Badge> : <Badge variant="muted">Sama</Badge>}
                                    </TableCell>
                                    <TableCell className={`align-top ${tableCellResponsive}`}>
                                        {can.koreksi && berbeda(baris) && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                aria-label={`Koreksi snapshot ${baris.kode ?? baris.nama}`}
                                                disabled={unknown}
                                                onClick={() => setDipilihId(baris.indikator_id)}
                                            >
                                                Koreksi
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
            {dipilih && (
                <KoreksiSnapshotModal
                    key={dipilih.indikator_id}
                    jadwalId={jadwal.id}
                    periode={periode}
                    baris={dipilih}
                    onClose={() => setDipilihId(null)}
                    onUnknown={() => {
                        setDipilihId(null);
                        setUnknown(true);
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
