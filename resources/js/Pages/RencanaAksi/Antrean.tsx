import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Eye, Paperclip } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import type { RencanaAksiRingkas, RencanaAksiPagination } from './types';
import Pagination from '@/Pages/Pengukuran/Pagination';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import TabFilter from './TabFilter';

export type StatusAntrean = 'antrean' | 'disahkan';

interface RencanaAksiAntreanProps {
    rencanaAksis: RencanaAksiRingkas[];
    pagination: RencanaAksiPagination;
    status: StatusAntrean;
}

export default function RencanaAksiAntrean({ rencanaAksis = [], pagination, status = 'antrean' }: RencanaAksiAntreanProps) {
    const labelUnit = useLabelUnit();

    return (
        <AuthenticatedLayout
            title="Pengesahan Rencana Aksi"
            breadcrumbs={[{ label: 'Rencana Aksi' }]}
        >
            <Head title="Rencana Aksi" />

            <TabFilter active={status} />

            <div className="mb-6">
                <h2 className="text-sm font-semibold text-ink">
                    {status === 'disahkan' ? 'Daftar Rencana Aksi Disahkan' : 'Antrean Pengesahan Rencana Aksi'}
                </h2>
                <p className="mt-0.5 text-xs text-muted">
                    {status === 'disahkan'
                        ? 'Rencana aksi yang telah disahkan dan menjadi dokumen resmi.'
                        : 'Daftar rencana aksi yang diajukan dan memerlukan pengesahan resmi oleh Tim Perencanaan.'}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-ink">
                        {status === 'disahkan' ? 'Daftar Disahkan' : 'Daftar Pengajuan Masuk'} ({pagination.total})
                    </CardTitle>
                </CardHeader>
                <Table className="text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Kode & Indikator</TableHead>
                            <TableHead>{labelUnit} & PIC</TableHead>
                            <TableHead className="text-center">Tahun</TableHead>
                            <TableHead className="text-center">Bukti</TableHead>
                            <TableHead className="text-center">Status</TableHead>
                            <TableHead className="text-center">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rencanaAksis.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="px-6 py-12 text-center">
                                    <div className="flex flex-col items-center justify-center">
                                        <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success border border-success/20">
                                            <CheckCircle2 className="h-6 w-6" aria-hidden="true" />
                                        </div>
                                        <p className="text-sm font-semibold text-ink">
                                            {status === 'disahkan' ? 'Belum ada rencana aksi disahkan' : 'Semua pengajuan telah diproses'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted max-w-sm">
                                            {status === 'disahkan'
                                                ? 'Rencana aksi yang telah disahkan akan tampil di sini.'
                                                : 'Tidak ada antrean rencana aksi yang menunggu pengesahan saat ini.'}
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ) : (
                            rencanaAksis.map((ra) => (
                                <TableRow key={ra.id}>
                                    <TableCell className="px-6 py-4 max-w-xs">
                                        <div className="font-bold text-ink text-xs">
                                            {ra.indikator?.kode}
                                        </div>
                                        <div className="text-muted mt-0.5 line-clamp-2 leading-relaxed">
                                            {ra.indikator?.nama}
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-6 py-4">
                                        <div className="font-medium text-ink">{ra.unit_kerja?.nama}</div>
                                        <div className="text-[11px] text-muted mt-0.5">
                                            PIC: {ra.pic?.nama || '-'}
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-6 py-4 text-center font-medium text-ink">
                                        {ra.tahun}
                                    </TableCell>
                                    <TableCell className="px-6 py-4 text-center">
                                        {ra.bukti_count > 0 ? (
                                            <span className="inline-flex items-center gap-1 font-semibold text-primary">
                                                <Paperclip className="w-3.5 h-3.5" />
                                                {ra.bukti_count} Dokumen
                                            </span>
                                        ) : (
                                            <span className="text-muted">Tanpa bukti</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="px-6 py-4 text-center">
                                        <Badge status={ra.status} />
                                    </TableCell>
                                    <TableCell className="px-6 py-4 text-center">
                                        {ra.can.view && (
                                            <Link
                                                href={`/rencana-aksi/${ra.id}/reviu`}
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors shadow-xs"
                                            >
                                                <Eye aria-hidden="true" className="h-3.5 w-3.5" />
                                                Lihat
                                            </Link>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
                <Pagination pagination={pagination} entityLabel="rencana aksi" />
            </Card>
        </AuthenticatedLayout>
    );
}
