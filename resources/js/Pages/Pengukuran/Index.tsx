import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Paperclip, FileText } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { EmptyState } from '@/Components/EmptyState';
import { Pagination } from '@/Components/Pagination';
import type { Pengukuran, PeriodePengukuran, PengukuranPagination } from './types';
import { statusPerhitungan } from './types';
import { useFormatNilai } from './formatNilai';

interface PengukuranIndexProps {
    periode: PeriodePengukuran | null;
    pengukurans: Pengukuran[];
    pagination: PengukuranPagination;
}

export default function PengukuranIndex({ periode, pengukurans = [], pagination }: PengukuranIndexProps) {
    const formatNilai = useFormatNilai();

    return (
        <AuthenticatedLayout
            title="Pengukuran Kinerja"
            breadcrumbs={[{ label: 'Pengukuran Kinerja' }]}
        >
            <Head title="Pengukuran Kinerja" />

            <div className="mb-6">
                <h2 className="text-sm font-semibold text-ink">
                    {periode ? periode.nama_periode : 'Periode Kinerja'}
                </h2>
                <p className="mt-0.5 text-xs text-muted">
                    Lakukan pengisian angka realisasi, lampiran bukti dukung, dan analisis capaian untuk indikator kinerja penugasan Anda.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Daftar Indikator Kinerja Penugasan</CardTitle>
                </CardHeader>
                <Table className="min-w-[768px]">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[38%]">KODE & INDIKATOR</TableHead>
                            <TableHead className="text-right">TARGET</TableHead>
                            <TableHead className="text-right">REALISASI</TableHead>
                            <TableHead className="text-right">HASIL PERHITUNGAN</TableHead>
                            <TableHead className="text-center">BUKTI DUKUNG</TableHead>
                            <TableHead className="text-center">STATUS</TableHead>
                            <TableHead className="text-center">AKSI</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {pengukurans.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={7} className="py-12 text-center">
                                    <EmptyState
                                        variant="inline"
                                        icon={FileText}
                                        title="Tidak ada penugasan aktif"
                                        description="Tidak ada penugasan indikator aktif untuk Anda pada periode ini."
                                    />
                                </TableCell>
                            </TableRow>
                        ) : (
                            pengukurans.map((p) => {
                                const iku = p.penugasan_indikator?.indikator_kinerja;

                                return (
                                    <TableRow key={p.id}>
                                        <TableCell className="max-w-sm">
                                            <div className="font-bold text-ink text-xs">
                                                {iku?.kode}
                                            </div>
                                            <div className="text-muted mt-0.5 leading-relaxed">
                                                {iku?.nama}
                                            </div>
                                            <div className="text-xs text-muted mt-0.5">
                                                Formula: <span className="font-semibold text-primary">{iku?.tipe_perhitungan}</span> ({iku?.satuan})
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right font-medium text-ink">
                                            {p.target === null ? 'Belum tersedia' : `${formatNilai(p.target, iku.desimal_tampilan)} ${iku.satuan}`}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold text-ink">
                                            {p.nilai !== null ? `${formatNilai(p.nilai, iku.desimal_tampilan)} ${iku.satuan}` : '-'}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold text-ink">
                                            {statusPerhitungan[p.status_perhitungan]}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {p.bukti_count > 0 ? (
                                                <span className="inline-flex items-center gap-1 text-xs text-primary font-semibold">
                                                    <Paperclip className="w-3.5 h-3.5" />
                                                    {p.bukti_count} Berkas
                                                </span>
                                            ) : (
                                                <span className="text-muted">-</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <div className="flex flex-col items-center gap-1">
                                                <Badge status={p.status} />
                                                {p.self_approval && <span className="text-[11px] font-medium text-info-dark">Persetujuan sendiri</span>}
                                                {p.reviu_terlambat && <span className="text-[11px] font-medium text-warning-dark">Reviu terlambat</span>}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {p.can.view && (
                                                <Link
                                                    href={`/pengukuran/${p.id}/edit`}
                                                    className="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:border-primary/30 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors shadow-2xs"
                                                >
                                                    Buka pengukuran
                                                </Link>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>
                <Pagination pagination={pagination} showPerPage={false} resourceName="penugasan" />
            </Card>
        </AuthenticatedLayout>
    );
}
