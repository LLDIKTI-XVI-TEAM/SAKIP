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
            <Head title="Verifikasi Kinerja" />

            <div className="mb-6">
                <h2 className="text-sm font-semibold text-slate-700">
                    Antrean Verifikasi Capaian Kinerja
                </h2>
                <p className="text-xs text-slate-500">
                    Daftar capaian kinerja yang diajukan oleh PIC Unit Kerja dan memerlukan reviu substansi serta pengesahan resmi oleh Tim Perencanaan.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="w-4 h-4 text-[#122E92]" />
                        Daftar Pengajuan Masuk ({pagination.total})
                    </CardTitle>
                </CardHeader>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700">
                        <thead className="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th className="px-5 py-3.5">KODE & INDIKATOR</th>
                                <th className="px-5 py-3.5">UNIT KERJA & PIC</th>
                                <th className="px-5 py-3.5 text-right">TARGET</th>
                                <th className="px-5 py-3.5 text-right">REALISASI</th>
                                <th className="px-5 py-3.5 text-right">HASIL PERHITUNGAN</th>
                                <th className="px-5 py-3.5 text-center">BUKTI DUKUNG</th>
                                <th className="px-5 py-3.5 text-center">STATUS</th>
                                <th className="px-5 py-3.5 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pengukurans.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-5 py-12 text-center text-slate-400">
                                        <CheckCircle2 className="w-8 h-8 text-emerald-500 mx-auto mb-2 opacity-60" />
                                        <p className="text-sm font-medium text-slate-600">Semua pengajuan telah diproses!</p>
                                        <p className="text-xs text-slate-400 mt-0.5">
                                            Tidak ada antrean capaian yang menunggu verifikasi saat ini.
                                        </p>
                                    </td>
                                </tr>
                            ) : (
                                pengukurans.map((p) => {
                                    const iku = p.penugasan_indikator?.indikator_kinerja;
                                    const unit = p.penugasan_indikator?.unit_kerja;
                                    const pic = p.penugasan_indikator?.pic;

                                    return (
                                        <tr key={p.id} className="hover:bg-slate-50/80 transition-colors">
                                            <td className="px-5 py-3.5 max-w-xs">
                                                <div className="font-bold text-slate-900 text-xs">
                                                    {iku?.kode}
                                                </div>
                                                <div className="text-slate-600 mt-0.5 line-clamp-2">
                                                    {iku?.nama}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <div className="font-medium text-slate-800">{unit?.nama}</div>
                                                <div className="text-[11px] text-slate-400">
                                                    PIC: {pic?.nama || '-'}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-medium">
                                                {p.target === null ? 'Belum tersedia' : `${formatNilai(p.target, iku.desimal_tampilan)} ${iku.satuan}`}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-semibold">
                                                {p.nilai === null ? '—' : `${formatNilai(p.nilai, iku.desimal_tampilan)} ${iku.satuan}`}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                {statusPerhitungan[p.status_perhitungan]}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                {p.bukti_count > 0 ? (
                                                    <span className="inline-flex items-center gap-1 font-semibold text-[#122E92]">
                                                        <Paperclip className="w-3.5 h-3.5" />
                                                        {p.bukti_count} Dokumen
                                                    </span>
                                                ) : (
                                                    <span className="text-muted">Tanpa bukti</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                <Badge status={p.status} />
                                                {p.self_approval && <p className="mt-2 text-xs font-medium text-info-dark">Persetujuan sendiri</p>}
                                                {p.reviu_terlambat && <p className="mt-2 text-xs font-medium text-warning-dark">Reviu terlambat</p>}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                {p.can.view && <Link href={`/verifikasi/${p.id}`} className="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-2 text-xs font-medium text-white focus:outline-none focus:ring-2 focus:ring-primary"><Eye aria-hidden="true" className="h-3.5 w-3.5" />Lihat pengajuan</Link>}
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
