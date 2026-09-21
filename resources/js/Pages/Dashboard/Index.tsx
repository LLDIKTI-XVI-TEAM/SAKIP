import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    BarChart2,
    FileText,
    Send,
    ShieldCheck,
    RotateCcw,
    Check,
    Plus,
    Target,
    ChevronRight,
    ArrowUpDown,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
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

export default function DashboardIndex({ activeRenstra, activePeriode, stats, pengukurans }: DashboardProps) {
    const { auth } = usePage<SharedPageProps>().props;
    const [currentDate, setCurrentDate] = useState<string>('');

    useEffect(() => {
        const now = new Date();
        const formatted = now.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
        setCurrentDate(formatted);
    }, []);

    const cards = [
        {
            key: 'total' as const,
            label: 'Total pengukuran',
            icon: BarChart2,
            iconStyle: 'bg-[#ebf2fe] text-[#2563eb]',
            desc: 'Seluruh periode',
        },
        {
            key: 'draft' as const,
            label: 'Draf',
            icon: FileText,
            iconStyle: 'bg-[#eef2f6] text-[#3b82f6]',
            desc: 'Menunggu penyelesaian',
        },
        {
            key: 'diajukan' as const,
            label: 'Diajukan',
            icon: Send,
            iconStyle: 'bg-[#ecfdf5] text-[#10b981]',
            desc: 'Menunggu verifikasi',
        },
        {
            key: 'diverifikasi' as const,
            label: 'Diverifikasi',
            icon: ShieldCheck,
            iconStyle: 'bg-[#fefce8] text-[#d97706]',
            desc: 'Telah diverifikasi',
        },
        {
            key: 'dikembalikan' as const,
            label: 'Dikembalikan',
            icon: RotateCcw,
            iconStyle: 'bg-[#fef2f2] text-[#ef4444]',
            desc: 'Perlu perbaikan',
        },
        {
            key: 'disahkan' as const,
            label: 'Disahkan',
            icon: Check,
            iconStyle: 'bg-[#f5f3ff] text-[#8b5cf6]',
            desc: 'Terselesaikan',
        },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard Kinerja — SAKIP LLDIKTI XVI" />

            {/* Hero Card */}
            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#081a4a] via-[#0d286d] to-[#1546b8] p-5 sm:p-6 lg:p-7 text-white shadow-sm mb-6 border border-white/10">
                {/* Decorative Light Glows */}
                <div className="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-sky-400/20 blur-3xl pointer-events-none" />
                <div className="absolute right-36 -bottom-20 h-56 w-56 rounded-full bg-blue-500/20 blur-2xl pointer-events-none" />
                <div className="absolute -left-12 -bottom-12 h-44 w-44 rounded-full bg-indigo-500/15 blur-2xl pointer-events-none" />

                {/* Geometric Pattern Overlay */}
                <div
                    className="absolute inset-0 opacity-5 pointer-events-none"
                    style={{
                        backgroundImage: `radial-gradient(circle at 1px 1px, white 1px, transparent 0)`,
                        backgroundSize: '24px 24px',
                    }}
                />

                <div className="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    {/* Left Content */}
                    <div className="max-w-xl">
                        {/* Top Eyebrow */}
                        <div className="flex items-center gap-2.5 mb-2.5">
                            <span className="h-1.5 w-7 rounded-full bg-white shrink-0" />
                            <span className="text-[10px] sm:text-[11px] font-bold uppercase tracking-[0.16em] text-white/85">
                                SISTEM AKUNTABILITAS KINERJA INSTANSI PEMERINTAH
                            </span>
                        </div>

                        {/* Title */}
                        <h2 className="text-2xl sm:text-3xl lg:text-[32px] font-extrabold tracking-tight text-white leading-tight">
                            Selamat Datang di <span className="text-white">SAKIP LLDIKTI XVI</span>
                        </h2>

                        {/* Description */}
                        <p className="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Pantau dan kelola capaian kinerja secara terintegrasi untuk mendukung tata kelola pemerintahan yang akuntabel, efektif, dan berorientasi hasil.
                        </p>

                        {/* Context Metadata Chips */}
                        <div className="mt-5 flex flex-wrap items-center gap-3">
                            <div className="inline-flex items-center gap-2.5 rounded-xl bg-white/10 px-4 py-2 text-xs font-medium text-white backdrop-blur-md border border-white/15 shadow-2xs">
                                <Calendar className="h-4 w-4 text-sky-400" />
                                <span>{currentDate || 'Selasa, 22 September 2026'}</span>
                            </div>
                            <div className="inline-flex items-center gap-2.5 rounded-xl bg-white/10 px-4 py-2 text-xs font-medium text-white backdrop-blur-md border border-white/15 shadow-2xs">
                                <Target className="h-4 w-4 text-sky-400" />
                                <span>Renstra: {activeRenstra ? `${activeRenstra.tahun_mulai}–${activeRenstra.tahun_selesai}` : '2020–2024'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Right: Status Pengukuran Widget */}
                    <div className="shrink-0 w-full lg:w-auto">
                        <div className="rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 p-5 text-white shadow-xl min-w-[260px] lg:min-w-[280px]">
                            {/* Top Header */}
                            <div className="flex items-center justify-between gap-4">
                                <div className="flex items-center gap-2">
                                    <BarChart2 className="h-4 w-4 text-sky-400" />
                                    <span className="text-xs sm:text-sm font-semibold text-white">Status Pengukuran</span>
                                </div>
                                <div className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 px-2.5 py-0.5 text-[11px] font-bold text-emerald-300">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                    <span>Aktif</span>
                                </div>
                            </div>

                            {/* Counter Value */}
                            <div className="mt-3.5 flex items-baseline gap-2">
                                <span className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight leading-none">
                                    {stats.disahkan}
                                </span>
                                <span className="text-xs sm:text-sm font-medium text-blue-200">
                                    / {stats.total} Disahkan
                                </span>
                            </div>

                            {/* Progress Track */}
                            <div className="mt-3.5 h-2 w-full rounded-full bg-white/15 overflow-hidden">
                                <div
                                    className="h-full rounded-full bg-gradient-to-r from-sky-400 to-blue-400 transition-all duration-500"
                                    style={{ width: stats.total > 0 ? `${Math.round((stats.disahkan / stats.total) * 100)}%` : '0%' }}
                                />
                            </div>

                            {/* Bottom Label */}
                            <p className="mt-2.5 text-right text-[11px] font-medium text-blue-200/80">
                                {stats.total > 0 ? `${Math.round((stats.disahkan / stats.total) * 100)}% terselesaikan` : 'Belum ada data'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* 6 KPI Metric Cards */}
            <div className="grid grid-cols-2 gap-3.5 mb-6 sm:grid-cols-3 xl:grid-cols-6">
                {cards.map(({ key, label, icon: Icon, iconStyle, desc }) => (
                    <div
                        key={key}
                        className="flex flex-col justify-between rounded-2xl border border-slate-100/80 bg-white p-4 sm:p-5 shadow-xs transition-all hover:shadow-md hover:border-slate-200"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${iconStyle}`}>
                                    <Icon className="h-5 w-5" />
                                </div>
                                <ChevronRight className="h-4 w-4 text-slate-300" />
                            </div>
                            <p className="mt-4 text-xs font-semibold text-slate-600">
                                {label}
                            </p>
                            <p className="mt-1 text-3xl font-extrabold text-[#0c2356]">
                                {stats[key]}
                            </p>
                        </div>
                        <p className="mt-4 text-xs text-slate-400 font-medium">
                            {desc}
                        </p>
                    </div>
                ))}
            </div>

            {/* Main Section: Pengukuran Terbaru Card */}
            <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-2xs">
                {/* Header */}
                <div className="flex flex-col gap-4 border-b border-border p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0 max-w-xl">
                        <h2 className="text-base font-bold leading-tight text-ink">
                            Pengukuran terbaru
                        </h2>
                        <p className="mt-0.5 text-xs text-muted leading-relaxed">
                            Nilai ditampilkan dengan satuan masing-masing indikator. Hasil resmi berstatus Disahkan.
                        </p>
                    </div>

                    {/* Right Filter & Action Buttons */}
                    <div className="flex items-center gap-2.5 shrink-0 flex-wrap sm:flex-nowrap">
                        <div
                            className="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-soft px-3.5 text-xs font-medium text-ink shrink-0 select-none"
                            aria-label="Periode aktif"
                        >
                            <Calendar className="h-3.5 w-3.5 text-muted shrink-0" />
                            <span>Periode: {activePeriode ? activePeriode.nama_periode : 'Semua periode'}</span>
                        </div>

                        {auth.can.pengukuran && (
                            <Link
                                href="/pengukuran"
                                className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-secondary px-3.5 text-xs font-semibold text-ink shadow-xs transition-colors hover:bg-secondary/90 focus:outline-none focus:ring-2 focus:ring-secondary/40 shrink-0 select-none"
                            >
                                <BarChart2 className="h-4 w-4 shrink-0" />
                                <span>Buka pengukuran</span>
                            </Link>
                        )}

                        {auth.can.verifikasi && (
                            <Link
                                href="/verifikasi"
                                className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 shrink-0 select-none"
                            >
                                <Plus className="h-4 w-4 shrink-0" />
                                <span>Buka antrean verifikasi</span>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Table View */}
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs text-ink">
                        <thead className="bg-[#f0f4f9] text-slate-700 font-bold text-[11px] uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th scope="col" className="px-6 py-3.5">
                                    <div className="flex items-center gap-1.5">
                                        <span>KODE & INDIKATOR</span>
                                        <ArrowUpDown className="h-3 w-3 text-slate-400" />
                                    </div>
                                </th>
                                <th scope="col" className="px-6 py-3.5">
                                    UNIT & PIC
                                </th>
                                <th scope="col" className="px-6 py-3.5 text-right">
                                    NILAI
                                </th>
                                <th scope="col" className="px-6 py-3.5">
                                    HASIL PERHITUNGAN
                                </th>
                                <th scope="col" className="px-6 py-3.5 text-center">
                                    STATUS
                                </th>
                                <th scope="col" className="px-6 py-3.5 text-center">
                                    AKSI
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {pengukurans.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-14 text-center">
                                        <div className="flex flex-col items-center justify-center">
                                            {/* Custom Document with info circle badge */}
                                            <div className="relative mb-3">
                                                <svg width="46" height="52" viewBox="0 0 48 54" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M6 0C2.68629 0 0 2.68629 0 6V48C0 51.3137 2.68629 54 6 54H42C45.3137 54 48 51.3137 48 48V14L34 0H6Z" fill="#E2E8F0"/>
                                                    <path d="M34 0V14H48L34 0Z" fill="#CBD5E1"/>
                                                    <rect x="10" y="20" width="22" height="3" rx="1.5" fill="#CBD5E1"/>
                                                    <rect x="10" y="26" width="16" height="3" rx="1.5" fill="#CBD5E1"/>
                                                    <circle cx="34" cy="38" r="9" fill="#64748B"/>
                                                    <path d="M34 33V35M34 37V42" stroke="white" strokeWidth="2" strokeLinecap="round"/>
                                                </svg>
                                            </div>
                                            <p className="text-sm font-bold text-[#0c2356]">
                                                Belum ada pengukuran untuk periode ini.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                pengukurans.map((item) => (
                                    <tr key={item.id} className="transition-colors hover:bg-soft">
                                        <td className="max-w-sm px-6 py-4">
                                            <p className="font-semibold text-ink text-xs">
                                                {item.indikator.kode}
                                            </p>
                                            <p className="mt-0.5 text-muted line-clamp-2 leading-relaxed">
                                                {item.indikator.nama}
                                            </p>
                                        </td>
                                        <td className="px-6 py-4">
                                            <p className="font-medium text-ink">
                                                {item.unit.nama}
                                            </p>
                                            <p className="mt-0.5 text-muted">
                                                PIC: {item.pic?.nama || '—'}
                                            </p>
                                        </td>
                                        <td className="px-6 py-4 text-right font-semibold text-ink">
                                            {item.nilai === null
                                                ? '—'
                                                : `${formatNilai(item.nilai, item.desimal_tampilan)} ${item.satuan}`}
                                        </td>
                                        <td className="px-6 py-4 text-muted">
                                            {statusPerhitungan[item.status_perhitungan]}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <div className="flex flex-col items-center gap-1">
                                                <Badge status={item.status} />
                                                {item.self_approval && (
                                                    <span className="text-[11px] font-medium text-info-dark">
                                                        Persetujuan sendiri
                                                    </span>
                                                )}
                                                {item.reviu_terlambat && (
                                                    <span className="text-[11px] font-medium text-warning-dark">
                                                        Reviu terlambat
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            {item.action && (
                                                <Link
                                                    href={item.action.href}
                                                    className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-xs transition-colors hover:border-primary/30 hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                                                >
                                                    {item.action.label}
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
