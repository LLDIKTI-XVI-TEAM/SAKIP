import { Head, Link, usePage } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { formatNilai } from '@/Pages/Pengukuran/formatNilai';
import { statusPerhitungan, type Pengukuran } from '@/Pages/Pengukuran/types';
import type { SharedPageProps } from '@/types/auth';

interface DashboardProps {
    activeRenstra: { id: string; nama: string; tahun_mulai: number; tahun_selesai: number } | null;
    activePeriode: { id: string; nama_periode: string } | null;
    stats: {
        total: number;
        draft: number;
        diajukan: number;
        diverifikasi: number;
        dikembalikan: number;
        disahkan: number;
    };
    pengukurans: {
        id: string;
        status: Pengukuran['status'];
        self_approval: boolean;
        reviu_terlambat: boolean;
        nilai: Pengukuran['nilai'];
        status_perhitungan: Pengukuran['status_perhitungan'];
        satuan: string;
        desimal_tampilan: number;
        indikator: { kode: string; nama: string };
        unit: { nama: string };
        pic: { nama: string } | null;
        action: { href: string; label: string } | null;
    }[];
}

const summaries = [
    { key: 'total', label: 'Total pengukuran' },
    { key: 'draft', label: 'Draf' },
    { key: 'diajukan', label: 'Diajukan' },
    { key: 'diverifikasi', label: 'Diverifikasi' },
    { key: 'dikembalikan', label: 'Dikembalikan' },
    { key: 'disahkan', label: 'Disahkan' },
] as const;

export default function DashboardIndex({ activeRenstra, activePeriode, stats, pengukurans }: DashboardProps) {
    const { auth } = usePage<SharedPageProps>().props;

    return (
        <AuthenticatedLayout title="Dashboard Kinerja">
            <Head title="Dashboard" />

            {activePeriode && (
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-secondary/30 bg-primary p-4 text-white">
                    <div className="flex items-center gap-3">
                        <Calendar aria-hidden="true" className="h-6 w-6 shrink-0 text-secondary" />
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-secondary">Periode pelaporan</p>
                            <h2 className="mt-1 text-lg font-semibold">{activePeriode.nama_periode}</h2>
                            {activeRenstra && <p className="mt-1 text-xs text-white/80">{activeRenstra.nama} · {activeRenstra.tahun_mulai}–{activeRenstra.tahun_selesai}</p>}
                        </div>
                    </div>
                    {auth.can.pengukuran && (
                        <Link href="/pengukuran" className="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-secondary focus:ring-offset-2">
                            Buka Pengukuran
                        </Link>
                    )}
                </div>
            )}

            <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                {summaries.map(({ key, label }) => (
                    <Card key={key}>
                        <CardContent className="p-4">
                            <p className="text-xs font-medium text-muted">{label}</p>
                            <p className="mt-2 text-2xl font-semibold text-primary">{stats[key]}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Card>
                <CardHeader>
                    <div>
                        <CardTitle>Pengukuran terbaru</CardTitle>
                        <p className="mt-1 text-xs text-muted">Nilai ditampilkan dengan satuan masing-masing indikator. Hasil resmi berstatus Disahkan.</p>
                    </div>
                    {auth.can.verifikasi && (
                        <Link href="/verifikasi" className="rounded-lg bg-primary px-3 py-2 text-xs font-medium text-white focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            Buka antrean verifikasi
                        </Link>
                    )}
                </CardHeader>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-ink">
                        <thead className="border-b border-border bg-soft font-semibold">
                            <tr>
                                <th scope="col" className="px-5 py-3.5">KODE & INDIKATOR</th>
                                <th scope="col" className="px-5 py-3.5">UNIT & PIC</th>
                                <th scope="col" className="px-5 py-3.5 text-right">NILAI</th>
                                <th scope="col" className="px-5 py-3.5">HASIL PERHITUNGAN</th>
                                <th scope="col" className="px-5 py-3.5 text-center">STATUS</th>
                                <th scope="col" className="px-5 py-3.5 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {pengukurans.length === 0 ? (
                                <tr><td colSpan={6} className="px-5 py-8 text-center text-muted">Belum ada pengukuran untuk periode ini.</td></tr>
                            ) : pengukurans.map((item) => (
                                <tr key={item.id} className="hover:bg-soft">
                                    <td className="max-w-sm px-5 py-3.5">
                                        <p className="font-semibold">{item.indikator.kode}</p>
                                        <p className="mt-1 text-muted">{item.indikator.nama}</p>
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <p className="font-medium">{item.unit.nama}</p>
                                        <p className="mt-1 text-muted">PIC: {item.pic?.nama || '—'}</p>
                                    </td>
                                    <td className="px-5 py-3.5 text-right font-semibold">
                                        {item.nilai === null ? '—' : `${formatNilai(item.nilai, item.desimal_tampilan)} ${item.satuan}`}
                                    </td>
                                    <td className="px-5 py-3.5 text-muted">{statusPerhitungan[item.status_perhitungan]}</td>
                                    <td className="px-5 py-3.5 text-center">
                                        <Badge status={item.status} />
                                        {item.self_approval && <p className="mt-2 text-xs font-medium text-info-dark">Persetujuan sendiri</p>}
                                        {item.reviu_terlambat && <p className="mt-2 text-xs font-medium text-warning-dark">Reviu terlambat</p>}
                                    </td>
                                    <td className="px-5 py-3.5 text-center">
                                        {item.action && <Link href={item.action.href} className="inline-flex rounded-lg border border-border bg-surface px-3 py-2 font-medium hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary">{item.action.label}</Link>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
