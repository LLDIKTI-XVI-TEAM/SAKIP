import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock, Eye, AlertCircle, Paperclip } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';

interface VerifikasiIndexProps {
    pengukurans: any[];
}

export default function VerifikasiIndex({ pengukurans = [] }: VerifikasiIndexProps) {
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
                        <Clock className="w-4 h-4 text-primary" />
                        Daftar Pengajuan Masuk ({pengukurans.length})
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
                                <th className="px-5 py-3.5 text-right">CAPAIAN (%)</th>
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
                                                <div className="font-medium text-slate-800">{unit?.singkatan || unit?.nama}</div>
                                                <div className="text-[11px] text-slate-400">
                                                    PIC: {pic?.name || '-'}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-medium">
                                                {p.target} {iku?.satuan}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-semibold">
                                                {p.realisasi} {iku?.satuan}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <span className={`font-bold px-2 py-0.5 rounded text-xs ${
                                                    p.capaian_persen >= 100
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : p.capaian_persen >= 80
                                                        ? 'bg-blue-50 text-blue-700'
                                                        : 'bg-rose-50 text-rose-700'
                                                }`}>
                                                    {p.capaian_persen}%
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                {p.bukti_dukungs?.length > 0 ? (
                                                    <span className="inline-flex items-center gap-1 font-semibold text-primary">
                                                        <Paperclip className="w-3.5 h-3.5" />
                                                        {p.bukti_dukungs.length} Dokumen
                                                    </span>
                                                ) : (
                                                    <span className="text-amber-600 font-medium">Tanpa Bukti</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                <Badge status={p.status} />
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                <Link href={`/verifikasi/${p.id}`}>
                                                    <Button variant="primary" size="sm">
                                                        <Eye className="w-3.5 h-3.5 mr-1" />
                                                        Reviu & Sahkan
                                                    </Button>
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
