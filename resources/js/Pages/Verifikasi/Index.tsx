import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock, Eye, AlertCircle, Paperclip } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import type { Pengukuran, PengukuranPagination } from '@/Pages/Pengukuran/types';
import { statusPerhitungan } from '@/Pages/Pengukuran/types';
import Pagination from '@/Pages/Pengukuran/Pagination';
import { formatNilai } from '@/Pages/Pengukuran/formatNilai';

interface VerifikasiIndexProps {
    pengukurans: Pengukuran[];
    pagination: PengukuranPagination;
}

export default function VerifikasiIndex({ pengukurans = [], pagination }: VerifikasiIndexProps) {
    return (
        <AuthenticatedLayout
            title="Verifikasi & Pengesahan Kinerja"
            breadcrumbs={[{ label: 'Verifikasi Kinerja' }]}
        >
            <Head title="Verifikasi Kinerja — SAKIP LLDIKTI XVI" />

            <div className="mb-6">
                <h2 className="text-sm font-semibold text-ink">
                    Antrean Verifikasi Capaian Kinerja
                </h2>
                <p className="mt-0.5 text-xs text-muted">
                    Daftar capaian kinerja yang diajukan oleh PIC Unit Kerja dan memerlukan reviu substansi serta pengesahan resmi oleh Tim Perencanaan.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-ink">
                        <Clock className="w-4 h-4 text-primary" />
                        Daftar Pengajuan Masuk ({pagination.total})
                    </CardTitle>
                </CardHeader>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-ink">
                        <thead className="bg-soft text-muted font-semibold text-[11px] uppercase tracking-wider border-b border-border">
                            <tr>
                                <th className="px-6 py-3.5">KODE & INDIKATOR</th>
                                <th className="px-6 py-3.5">UNIT KERJA & PIC</th>
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
                                    <td colSpan={8} className="px-6 py-12 text-center">
                                        <div className="flex flex-col items-center justify-center">
                                            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success border border-success/20">
                                                <CheckCircle2 className="h-6 w-6" aria-hidden="true" />
                                            </div>
                                            <p className="text-sm font-semibold text-ink">
                                                Semua pengajuan telah diproses
                                            </p>
                                            <p className="mt-1 text-xs text-muted max-w-sm">
                                                Tidak ada antrean capaian yang menunggu verifikasi saat ini.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                pengukurans.map((p) => {
                                    const iku = p.penugasan_indikator?.indikator_kinerja;
                                    const unit = p.penugasan_indikator?.unit_kerja;
                                    const pic = p.penugasan_indikator?.pic;

                                    return (
                                        <tr key={p.id} className="hover:bg-soft transition-colors">
                                            <td className="px-6 py-4 max-w-xs">
                                                <div className="font-bold text-ink text-xs">
                                                    {iku?.kode}
                                                </div>
                                                <div className="text-muted mt-0.5 line-clamp-2 leading-relaxed">
                                                    {iku?.nama}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-medium text-ink">{unit?.nama}</div>
                                                <div className="text-[11px] text-muted mt-0.5">
                                                    PIC: {pic?.nama || '-'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right font-medium text-ink">
                                                {p.target === null ? 'Belum tersedia' : `${formatNilai(p.target, iku.desimal_tampilan)} ${iku.satuan}`}
                                            </td>
                                            <td className="px-6 py-4 text-right font-semibold text-ink">
                                                {p.nilai === null ? '—' : `${formatNilai(p.nilai, iku.desimal_tampilan)} ${iku.satuan}`}
                                            </td>
                                            <td className="px-6 py-4 text-right font-semibold text-ink">
                                                {statusPerhitungan[p.status_perhitungan]}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {p.bukti_count > 0 ? (
                                                    <span className="inline-flex items-center gap-1 font-semibold text-primary">
                                                        <Paperclip className="w-3.5 h-3.5" />
                                                        {p.bukti_count} Dokumen
                                                    </span>
                                                ) : (
                                                    <span className="text-muted">Tanpa bukti</span>
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
                                                        href={`/verifikasi/${p.id}`}
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 transition-colors shadow-xs"
                                                    >
                                                        <Eye aria-hidden="true" className="h-3.5 w-3.5" />
                                                        Lihat pengajuan
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
