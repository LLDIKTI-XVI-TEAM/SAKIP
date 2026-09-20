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

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 className="text-sm font-semibold text-slate-700">
                        {periode ? periode.nama_periode : 'Periode Kinerja'}
                    </h2>
                    <p className="text-xs text-slate-500">
                        Lakukan pengisian angka realisasi, lampiran bukti dukung, dan analisis capaian untuk indikator kinerja penugasan Anda.
                    </p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Daftar Indikator Kinerja Penugasan</CardTitle>
                </CardHeader>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700">
                        <thead className="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th className="px-5 py-3.5">KODE & INDIKATOR</th>
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
                                    <td colSpan={7} className="px-5 py-8 text-center text-slate-400">
                                        Tidak ada penugasan indikator aktif untuk Anda pada periode ini.
                                    </td>
                                </tr>
                            ) : (
                                pengukurans.map((p) => {
                                    const iku = p.penugasan_indikator?.indikator_kinerja;
                                    const canEdit = p.can.update;

                                    return (
                                        <tr key={p.id} className="hover:bg-slate-50/80 transition-colors">
                                            <td className="px-5 py-3.5 max-w-sm">
                                                <div className="font-bold text-slate-900 text-xs">
                                                    {iku?.kode}
                                                </div>
                                                <div className="text-slate-600 mt-0.5">
                                                    {iku?.nama}
                                                </div>
                                                <div className="text-[10px] text-slate-400 mt-0.5">
                                                    Formula: <span className="font-medium text-[#122E92]">{iku?.tipe_perhitungan}</span> ({iku?.satuan})
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-medium">
                                                {p.target === null ? 'Belum tersedia' : `${formatNilai(p.target, iku.desimal_tampilan)} ${iku.satuan}`}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-semibold">
                                                {p.nilai !== null ? `${formatNilai(p.nilai, iku.desimal_tampilan)} ${iku.satuan}` : '—'}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-bold">
                                                {statusPerhitungan[p.status_perhitungan]}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                {p.bukti_count > 0 ? (
                                                    <span className="inline-flex items-center gap-1 text-xs text-[#122E92] font-semibold">
                                                        <Paperclip className="w-3.5 h-3.5" />
                                                        {p.bukti_count} Berkas
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                <Badge status={p.status} />
                                                {p.self_approval && <p className="mt-2 text-xs font-medium text-info-dark">Persetujuan sendiri</p>}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                {p.can.view && <Link href={`/pengukuran/${p.id}/edit`} className={`inline-flex rounded-lg px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary ${canEdit ? 'bg-primary text-white' : 'border border-border bg-surface text-ink'}`}>{canEdit ? 'Isi / Edit' : 'Lihat Detail'}</Link>}
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
