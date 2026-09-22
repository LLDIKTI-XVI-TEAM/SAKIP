import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { FileEdit, CheckCircle, Clock, AlertTriangle, Paperclip } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import type { Pengukuran, PeriodePengukuran, PengukuranPagination } from './types';
import { statusPerhitungan } from './types';
import Pagination from './Pagination';
import { formatNilai } from './formatNilai';

interface PengukuranIndexProps {
    periode: PeriodePengukuran | null;
    pengukurans: Pengukuran[];
    pagination: PengukuranPagination;
}

export default function PengukuranIndex({ periode, pengukurans = [], pagination }: PengukuranIndexProps) {
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
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-ink">
                        <thead className="bg-soft text-muted font-semibold text-[11px] uppercase tracking-wider border-b border-border">
                            <tr>
                                <th className="px-6 py-3.5">KODE & INDIKATOR</th>
                                <th className="px-6 py-3.5 text-right">TARGET</th>
                                <th className="px-6 py-3.5 text-right">REALISASI</th>
                                <th className="px-6 py-3.5 text-right">HASIL PERHITUNGAN</th>
                                <th className="px-6 py-3.5 text-center">BUKTI DUKUNG</th>
                                <th className="px-6 py-3.5 text-center">STATUS</th>
                                <th className="px-6 py-3.5 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {pengukurans.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-12 text-center">
                                        <div className="flex flex-col items-center justify-center">
                                            <p className="text-sm font-semibold text-ink">
                                                Tidak ada penugasan aktif
                                            </p>
                                            <p className="mt-1 text-xs text-muted max-w-sm">
                                                Tidak ada penugasan indikator aktif untuk Anda pada periode ini.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                pengukurans.map((p) => {
                                    const iku = p.penugasan_indikator?.indikator_kinerja;

                                    return (
                                        <tr key={p.id} className="hover:bg-soft transition-colors">
                                            <td className="px-6 py-4 max-w-sm">
                                                <div className="font-bold text-ink text-xs">
                                                    {iku?.kode}
                                                </div>
                                                <div className="text-muted mt-0.5 leading-relaxed">
                                                    {iku?.nama}
                                                </div>
                                                <div className="text-[11px] text-muted mt-0.5">
                                                    Formula: <span className="font-semibold text-primary">{iku?.tipe_perhitungan}</span> ({iku?.satuan})
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right font-medium text-ink">
                                                {p.target === null ? 'Belum tersedia' : `${formatNilai(p.target, iku.desimal_tampilan)} ${iku.satuan}`}
                                            </td>
                                            <td className="px-6 py-4 text-right font-semibold text-ink">
                                                {p.nilai !== null ? `${formatNilai(p.nilai, iku.desimal_tampilan)} ${iku.satuan}` : '—'}
                                            </td>
                                            <td className="px-6 py-4 text-right font-semibold text-ink">
                                                {statusPerhitungan[p.status_perhitungan]}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {p.bukti_count > 0 ? (
                                                    <span className="inline-flex items-center gap-1 text-xs text-primary font-semibold">
                                                        <Paperclip className="w-3.5 h-3.5" />
                                                        {p.bukti_count} Berkas
                                                    </span>
                                                ) : (
                                                    <span className="text-muted">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <div className="flex flex-col items-center gap-1">
                                                    <Badge status={p.status} />
                                                    {p.self_approval && <span className="text-[11px] font-medium text-info-dark">Persetujuan sendiri</span>}
                                                    {p.reviu_terlambat && <span className="text-[11px] font-medium text-warning-dark">Reviu terlambat</span>}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {p.can.view && (
                                                    <Link
                                                        href={`/pengukuran/${p.id}/edit`}
                                                        className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:border-primary/30 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors shadow-xs"
                                                    >
                                                        Buka pengukuran
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination pagination={pagination} />
            </Card>
        </AuthenticatedLayout>
    );
}
