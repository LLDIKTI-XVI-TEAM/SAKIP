import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { 
    TrendingUp, 
    CheckCircle2, 
    Clock, 
    FileEdit, 
    ArrowUpRight,
    AlertCircle,
    Calendar,
    Target
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';

interface DashboardProps {
    activeRenstra: any;
    activePeriode: any;
    stats: {
        total: number;
        draft: number;
        diajukan: number;
        dikembalikan: number;
        disahkan: number;
        rata_rata_capaian: number;
    };
    pengukurans: any[];
}

export default function DashboardIndex({
    activeRenstra,
    activePeriode,
    stats,
    pengukurans = [],
}: DashboardProps) {
    const { auth } = usePage<any>().props;
    const user = auth?.user;
    const role = user?.roles?.[0] || 'pegawai';

    return (
        <AuthenticatedLayout title="Dashboard Capaian Kinerja">
            <Head title="Dashboard" />

            {/* Periode Banner */}
            {activePeriode && (
                <div className="mb-6 p-4 rounded-xl bg-gradient-to-r from-[#122E92] to-[#0a1b5c] text-white shadow-md flex flex-wrap items-center justify-between gap-4 border border-[#D6AC48]/30">
                    <div className="flex items-center gap-3.5">
                        <div className="w-12 h-12 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center shrink-0">
                            <Calendar className="w-6 h-6 text-[#D6AC48]" />
                        </div>
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wider text-[#D6AC48]">
                                Periode Pelaporan Aktif
                            </div>
                            <h2 className="text-lg font-bold">
                                {activePeriode.nama_periode} (Triwulan {activePeriode.triwulan})
                            </h2>
                            <p className="text-xs text-slate-300">
                                Batas Pengisian PIC: {new Date(activePeriode.tanggal_selesai).toLocaleDateString('id-ID', { dateStyle: 'full' })}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                            ● Status Jadwal Buka
                        </span>
                        {role === 'pegawai' && (
                            <Link href="/pengukuran">
                                <Button variant="secondary" size="sm">
                                    Isi Capaian Sekarang
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>
            )}

            {/* Stat Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card>
                    <CardContent className="p-5 flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Rata-rata Capaian
                            </p>
                            <div className="text-2xl font-bold text-[#122E92] mt-1">
                                {stats.rata_rata_capaian}%
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5">
                                Seluruh IKU terverifikasi
                            </p>
                        </div>
                        <div className="w-11 h-11 rounded-lg bg-blue-50 text-[#122E92] flex items-center justify-center">
                            <TrendingUp className="w-5 h-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Kinerja Disahkan
                            </p>
                            <div className="text-2xl font-bold text-emerald-600 mt-1">
                                {stats.disahkan} <span className="text-xs font-normal text-slate-500">/ {stats.total}</span>
                            </div>
                            <p className="text-[11px] text-emerald-600 font-medium mt-0.5">
                                Telah resmi disahkan
                            </p>
                        </div>
                        <div className="w-11 h-11 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Menunggu Verifikasi
                            </p>
                            <div className="text-2xl font-bold text-blue-600 mt-1">
                                {stats.diajukan}
                            </div>
                            <p className="text-[11px] text-blue-600 font-medium mt-0.5">
                                Diajukan oleh PIC
                            </p>
                        </div>
                        <div className="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <Clock className="w-5 h-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Draft / Belum Selesai
                            </p>
                            <div className="text-2xl font-bold text-amber-600 mt-1">
                                {stats.draft + stats.dikembalikan}
                            </div>
                            <p className="text-[11px] text-amber-600 font-medium mt-0.5">
                                {stats.dikembalikan > 0 ? `${stats.dikembalikan} butuh revisi` : 'Dalam pengerjaan'}
                            </p>
                        </div>
                        <div className="w-11 h-11 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                            <FileEdit className="w-5 h-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Performance Measurement Table */}
            <Card>
                <CardHeader>
                    <div>
                        <CardTitle>Daftar Indikator Kinerja Utama (IKU)</CardTitle>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Status pemenuhan target kinerja pada periode berjalan
                        </p>
                    </div>
                    {role === 'perencanaan' && stats.diajukan > 0 && (
                        <Link href="/verifikasi">
                            <Button variant="primary" size="sm">
                                Reviu {stats.diajukan} Pengajuan
                            </Button>
                        </Link>
                    )}
                </CardHeader>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-slate-700">
                        <thead className="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th className="px-5 py-3.5">KODE & INDIKATOR</th>
                                <th className="px-5 py-3.5">UNIT PENANGGUNG JAWAB</th>
                                <th className="px-5 py-3.5 text-right">TARGET TW1</th>
                                <th className="px-5 py-3.5 text-right">REALISASI</th>
                                <th className="px-5 py-3.5 text-right">CAPAIAN (%)</th>
                                <th className="px-5 py-3.5 text-center">STATUS</th>
                                <th className="px-5 py-3.5 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pengukurans.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-5 py-8 text-center text-slate-400">
                                        Belum ada data indikator kinerja untuk periode ini.
                                    </td>
                                </tr>
                            ) : (
                                pengukurans.map((p) => {
                                    const iku = p.penugasan_indikator?.indikator_kinerja;
                                    const unit = p.penugasan_indikator?.unit_kerja;
                                    const isPic = role === 'pegawai';

                                    return (
                                        <tr key={p.id} className="hover:bg-slate-50/80 transition-colors">
                                            <td className="px-5 py-3.5 max-w-xs">
                                                <div className="font-bold text-slate-900 text-xs">
                                                    {iku?.kode}
                                                </div>
                                                <div className="text-slate-600 mt-0.5 line-clamp-2">
                                                    {iku?.nama}
                                                </div>
                                                <div className="text-[10px] text-slate-400 mt-0.5">
                                                    Tipe: <span className="font-medium text-[#122E92]">{iku?.tipe_perhitungan}</span> ({iku?.satuan})
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5 text-slate-600">
                                                <div className="font-medium">{unit?.singkatan || unit?.nama}</div>
                                                <div className="text-[11px] text-slate-400">
                                                    PIC: {p.penugasan_indikator?.pic?.name || '-'}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-medium">
                                                {p.target} {iku?.satuan}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-semibold">
                                                {p.realisasi !== null ? `${p.realisasi} ${iku?.satuan}` : '-'}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                {p.capaian_persen !== null ? (
                                                    <span className={`font-bold px-2 py-0.5 rounded text-xs ${
                                                        p.capaian_persen >= 100
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : p.capaian_persen >= 80
                                                            ? 'bg-blue-50 text-blue-700'
                                                            : 'bg-rose-50 text-rose-700'
                                                    }`}>
                                                        {p.capaian_persen}%
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                <Badge status={p.status} />
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                {isPic ? (
                                                    <Link href={`/pengukuran/${p.id}/edit`}>
                                                        <Button variant="outline" size="sm">
                                                            {p.status === 'draft' || p.status === 'dikembalikan' ? 'Isi / Edit' : 'Detail'}
                                                        </Button>
                                                    </Link>
                                                ) : (
                                                    <Link href={`/verifikasi/${p.id}`}>
                                                        <Button variant="outline" size="sm">
                                                            Reviu
                                                        </Button>
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
            </Card>
        </AuthenticatedLayout>
    );
}
