import React from 'react';
import { Head } from '@inertiajs/react';
import { CheckCircle2, Eye, Paperclip } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { EmptyState } from '@/Components/EmptyState';
import { IconButton } from '@/Components/IconButton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/Table';
import { Pagination } from '@/Components/Pagination';
import type { Pengukuran, PengukuranPagination } from '@/Pages/Pengukuran/types';
import { statusPerhitungan } from '@/Pages/Pengukuran/types';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useLabelUnit } from '@/hooks/useLabelUnit';

interface VerifikasiIndexProps {
    pengukurans: Pengukuran[];
    pagination: PengukuranPagination;
    filters?: {
        per_page?: string | number;
        page?: string | number;
    };
}

export default function VerifikasiIndex({
    pengukurans = [],
    pagination,
    filters = {},
}: VerifikasiIndexProps) {
    const formatNilai = useFormatNilai();
    const labelUnit = useLabelUnit();

    return (
        <AuthenticatedLayout
            title="Verifikasi & Pengesahan"
            breadcrumbs={[{ label: 'Verifikasi & Pengesahan' }]}
        >
            <Head title="Verifikasi & Pengesahan" />

            <div className="mx-auto max-w-7xl space-y-4 mt-1.5 sm:mt-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-ink">
                            Daftar Pengajuan Masuk ({pagination.total})
                        </CardTitle>
                    </CardHeader>
                    <Table className="min-w-[850px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[30%] px-5 py-3.5">KODE & INDIKATOR</TableHead>
                                <TableHead className="w-[22%] px-5 py-3.5">{labelUnit.toUpperCase()} & PIC</TableHead>
                                <TableHead className="w-[11%] px-4 py-3.5 text-right">TARGET</TableHead>
                                <TableHead className="w-[11%] px-4 py-3.5 text-right">REALISASI</TableHead>
                                <TableHead className="w-[11%] px-4 py-3.5 text-right">HASIL PERHITUNGAN</TableHead>
                                <TableHead className="w-[9%] px-4 py-3.5 text-center">BUKTI DUKUNG</TableHead>
                                <TableHead className="w-[9%] px-4 py-3.5 text-center">STATUS</TableHead>
                                <TableHead className="w-[5%] px-4 py-3.5 text-center">AKSI</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pengukurans.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-12 text-center">
                                        <EmptyState
                                            variant="inline"
                                            icon={CheckCircle2}
                                            iconClassName="text-success"
                                            title="Semua pengajuan telah diproses"
                                            description="Tidak ada antrean capaian yang menunggu verifikasi saat ini."
                                        />
                                    </TableCell>
                                </TableRow>
                            ) : (
                                pengukurans.map((p) => {
                                    const iku = p.penugasan_indikator?.indikator_kinerja;
                                    const unit = p.penugasan_indikator?.unit_kerja;
                                    const pic = p.penugasan_indikator?.pic;

                                    return (
                                        <TableRow key={p.id}>
                                            <TableCell className="px-5 py-4 max-w-xs">
                                                <div className="font-mono font-bold text-ink text-xs">
                                                    {iku?.kode}
                                                </div>
                                                <div className="text-muted mt-0.5 line-clamp-2 leading-relaxed">
                                                    {iku?.nama}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-5 py-4">
                                                <div className="font-medium text-ink">{unit?.nama}</div>
                                                <div className="text-[11px] text-muted mt-0.5">
                                                    PIC: {pic?.nama || '-'}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-4 py-4 text-right font-medium text-ink">
                                                {p.target === null ? (
                                                    <span className="text-muted">Belum tersedia</span>
                                                ) : (
                                                    <>
                                                        <span className="font-mono">{formatNilai(p.target, iku.desimal_tampilan)}</span> {iku.satuan}
                                                    </>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-4 py-4 text-right font-semibold text-ink">
                                                {p.nilai === null ? (
                                                    <span className="text-muted font-normal">Belum diisi</span>
                                                ) : (
                                                    <>
                                                        <span className="font-mono">{formatNilai(p.nilai, iku.desimal_tampilan)}</span> {iku.satuan}
                                                    </>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-4 py-4 text-right font-semibold text-ink">
                                                {statusPerhitungan[p.status_perhitungan]}
                                            </TableCell>
                                            <TableCell className="px-4 py-4 text-center">
                                                {p.bukti_count > 0 ? (
                                                    <span className="inline-flex items-center gap-1 font-semibold text-primary">
                                                        <Paperclip className="w-3.5 h-3.5" />
                                                        {p.bukti_count} Dokumen
                                                    </span>
                                                ) : (
                                                    <span className="text-muted">Tanpa bukti</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-4 py-4 text-center">
                                                <div className="flex flex-col items-center gap-1">
                                                    <Badge status={p.status} />
                                                    {p.self_approval && (
                                                        <Badge variant="info" size="sm">
                                                            Persetujuan sendiri
                                                        </Badge>
                                                    )}
                                                    {p.reviu_terlambat && (
                                                        <Badge variant="warning" size="sm">
                                                            Reviu terlambat
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-4 py-4 text-center">
                                                {p.can.view && (
                                                    <div className="flex items-center justify-center">
                                                        <IconButton
                                                            icon={Eye}
                                                            href={`/verifikasi/${p.id}`}
                                                            label="Lihat pengajuan"
                                                            tooltip="Lihat Pengajuan"
                                                            tooltipAlign="right"
                                                            variant="outline"
                                                            size="sm"
                                                        />
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                    <Pagination
                        pagination={pagination}
                        perPage={Number(filters?.per_page ?? pagination.per_page ?? 10)}
                        resourceName="pengukuran"
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
