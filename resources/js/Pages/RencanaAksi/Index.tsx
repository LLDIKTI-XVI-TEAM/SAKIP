import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Eye, Paperclip } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import type { RencanaAksiRingkas, RencanaAksiPagination } from './types';
import Pagination from '@/Pages/Pengukuran/Pagination';
import { useLabelUnit } from '@/hooks/useLabelUnit';

type StatusFilter = 'antrean' | 'disahkan';

interface RencanaAksiIndexProps {
    rencanaAksis: RencanaAksiRingkas[];
    pagination: RencanaAksiPagination;
    status: StatusFilter;
}

const TAB_LABELS: Record<StatusFilter, string> = { antrean: 'Antrean', disahkan: 'Disahkan' };

export default function RencanaAksiIndex({ rencanaAksis = [], pagination, status = 'antrean' }: RencanaAksiIndexProps) {
    const labelUnit = useLabelUnit();

    return (
        <AuthenticatedLayout
            title="Pengesahan Rencana Aksi"
            breadcrumbs={[{ label: 'Rencana Aksi' }]}
        >
            <Head title="Rencana Aksi" />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-ink">
                        {status === 'disahkan' ? 'Daftar Rencana Aksi Disahkan' : 'Antrean Pengesahan Rencana Aksi'}
                    </h2>
                    <p className="mt-0.5 text-xs text-muted">
                        {status === 'disahkan'
                            ? 'Rencana aksi yang telah disahkan dan menjadi dokumen resmi.'
                            : 'Daftar rencana aksi yang diajukan dan memerlukan pengesahan resmi oleh Tim Perencanaan.'}
                    </p>
                </div>
                <nav aria-label="Filter status rencana aksi" className="flex gap-1 rounded-lg border border-border bg-soft p-1">
                    {(Object.keys(TAB_LABELS) as StatusFilter[]).map((tab) => (
                        <Link
                            key={tab}
                            href={`/rencana-aksi?status=${tab}`}
                            aria-current={status === tab ? 'page' : undefined}
                            className={status === tab
                                ? 'rounded-md bg-surface px-3 py-1.5 text-xs font-semibold text-primary shadow-xs'
                                : 'rounded-md px-3 py-1.5 text-xs font-medium text-muted hover:text-ink'}
                        >
                            {TAB_LABELS[tab]}
                        </Link>
                    ))}
                </nav>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-ink">
                        {status === 'disahkan' ? 'Daftar Disahkan' : 'Daftar Pengajuan Masuk'} ({pagination.total})
                    </CardTitle>
                </CardHeader>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-ink">
                        <thead className="bg-soft text-muted font-semibold text-[11px] uppercase tracking-wider border-b border-border">
                            <tr>
                                <th className="px-6 py-3.5">KODE & INDIKATOR</th>
                                <th className="px-6 py-3.5">{labelUnit.toUpperCase()} & PIC</th>
                                <th className="px-6 py-3.5 text-center">TAHUN</th>
                                <th className="px-6 py-3.5 text-center">BUKTI</th>
                                <th className="px-6 py-3.5 text-center">STATUS</th>
                                <th className="px-6 py-3.5 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {rencanaAksis.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-12 text-center">
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
                                    </td>
                                </tr>
                            ) : (
                                rencanaAksis.map((ra) => (
                                    <tr key={ra.id} className="hover:bg-soft transition-colors">
                                        <td className="px-6 py-4 max-w-xs">
                                            <div className="font-bold text-ink text-xs">
                                                {ra.indikator?.kode}
                                            </div>
                                            <div className="text-muted mt-0.5 line-clamp-2 leading-relaxed">
                                                {ra.indikator?.nama}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-medium text-ink">{ra.unit_kerja?.nama}</div>
                                            <div className="text-[11px] text-muted mt-0.5">
                                                PIC: {ra.pic?.nama || '-'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-center font-medium text-ink">
                                            {ra.tahun}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            {ra.bukti_count > 0 ? (
                                                <span className="inline-flex items-center gap-1 font-semibold text-primary">
                                                    <Paperclip className="w-3.5 h-3.5" />
                                                    {ra.bukti_count} Dokumen
                                                </span>
                                            ) : (
                                                <span className="text-muted">Tanpa bukti</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <Badge status={ra.status} />
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            {ra.can.view && (
                                                <Link
                                                    href={`/rencana-aksi/${ra.id}`}
                                                    className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors shadow-xs"
                                                >
                                                    <Eye aria-hidden="true" className="h-3.5 w-3.5" />
                                                    Lihat
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination pagination={pagination} entityLabel="rencana aksi" />
            </Card>
        </AuthenticatedLayout>
    );
}
