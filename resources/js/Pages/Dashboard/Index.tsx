import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    Target,
    Clock,
    ChevronRight,
    BarChart2,
    FileText,
    Send,
    ShieldCheck,
    RotateCcw,
    Check,
    FileSearch,
    ArrowRight,
    Info,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/Components/Table';
import { Badge } from '@/Components/Badge';
import { EmptyState } from '@/Components/EmptyState';
import { InfoTooltip } from '@/Components/InfoTooltip';
import { HoverScrollText } from '@/Components/HoverScrollText';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { statusPerhitungan, type Pengukuran } from '@/Pages/Pengukuran/types';
import type { SharedPageProps } from '@/types/auth';

interface DashboardProps {
    activeRenstra: { id: string; nama: string; tahun_mulai: number; tahun_selesai: number } | null;
    activePeriode: { id: string; nama_periode: string; status: 'aktif' | 'terakhir' } | null;
    stats: {
        total: number;
        draft: number;
        diajukan: number;
        diverifikasi: number;
        dikembalikan: number;
        disahkan: number;
    };
    rencanaAksiStats?: {
        total: number;
        disahkan: number;
    };
    pengukurans: {
        id: string;
        status: Pengukuran['status'];
        self_approval: boolean;
        reviu_terlambat: boolean;
        nilai: Pengukuran['nilai'];
        target?: number | string | null;
        arah?: string;
        status_perhitungan: Pengukuran['status_perhitungan'];
        satuan: string;
        desimal_tampilan: number;
        indikator: { kode: string; nama: string };
        unit: { nama: string };
        pic: { nama: string } | null;
        action: { href: string; label: string } | null;
    }[];
}

export default function DashboardIndex({ activeRenstra, activePeriode, stats, rencanaAksiStats, pengukurans }: DashboardProps) {
    const { auth } = usePage<SharedPageProps>().props;
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const [currentDate, setCurrentDate] = useState<string>(() => formatTanggal(new Date(), { withDay: true }));
    const [hoveredCategory, setHoveredCategory] = useState<string | null>(null);
    const [kpiFilter, setKpiFilter] = useState<string | null>(null);
    const hasPeriode = activePeriode !== null;
    const isActivePeriode = activePeriode?.status === 'aktif';
    const periodeLabel = isActivePeriode ? 'Periode aktif' : 'Periode terakhir';

    const raTotal = rencanaAksiStats?.total ?? 0;
    const raDisahkan = rencanaAksiStats?.disahkan ?? 0;
    const raRate = raTotal > 0 ? Math.round((raDisahkan / raTotal) * 100) : 0;
    const currentYear = activePeriode?.nama_periode.match(/\d{4}/)?.[0]
        || (activeRenstra ? `${activeRenstra.tahun_mulai}` : `${new Date().getFullYear()}`);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setCurrentDate(formatTanggal(new Date(), { withDay: true }));
        }, 60_000);

        return () => window.clearInterval(interval);
    }, [formatTanggal]);

    // 5 Kategori Capaian Indikator (Data Disahkan) sesuai PRD §23 & Mockup
    const disahkanItems = pengukurans.filter((p) => p.status === 'disahkan');
    let tercapaiCount = 0;
    let dalamProgresCount = 0;
    let perluPerhatianCount = 0;
    let tidakTercapaiCount = 0;

    disahkanItems.forEach((p) => {
        if (p.nilai !== null && p.target !== null && p.target !== undefined && Number(p.target) > 0) {
            const targetNum = Number(p.target);
            const nilaiNum = Number(p.nilai);
            const arah = p.arah || 'naik_baik';
            let pct = 0;
            if (arah === 'turun_baik') {
                pct = nilaiNum <= targetNum ? 100 : (targetNum / nilaiNum) * 100;
            } else {
                pct = (nilaiNum / targetNum) * 100;
            }

            if (pct >= 100) {
                tercapaiCount++;
            } else if (pct >= 80) {
                dalamProgresCount++;
            } else if (pct >= 50) {
                perluPerhatianCount++;
            } else {
                tidakTercapaiCount++;
            }
        } else {
            tidakTercapaiCount++;
        }
    });

    const totalEvaluated = stats.total > 0 ? stats.total : pengukurans.length;
    const belumAdaDataCount = Math.max(0, totalEvaluated - (tercapaiCount + dalamProgresCount + perluPerhatianCount + tidakTercapaiCount));

    const tercapaiPct = totalEvaluated > 0 ? Math.round((tercapaiCount / totalEvaluated) * 100) : 0;
    const dalamProgresPct = totalEvaluated > 0 ? Math.round((dalamProgresCount / totalEvaluated) * 100) : 0;
    const perluPerhatianPct = totalEvaluated > 0 ? Math.round((perluPerhatianCount / totalEvaluated) * 100) : 0;
    const tidakTercapaiPct = totalEvaluated > 0 ? Math.round((tidakTercapaiCount / totalEvaluated) * 100) : 0;
    const belumAdaDataPct = totalEvaluated > 0 ? Math.max(0, 100 - (tercapaiPct + dalamProgresPct + perluPerhatianPct + tidakTercapaiPct)) : 0;

    const capaianCategories = [
        {
            label: 'Tercapai',
            color: '#16A34A',
            count: tercapaiCount,
            pct: tercapaiPct,
            dotClass: 'bg-success',
            desc: 'Capaian ≥ 100% dari target resmi',
        },
        {
            label: 'Dalam Progres',
            color: '#2563EB',
            count: dalamProgresCount,
            pct: dalamProgresPct,
            dotClass: 'bg-info',
            desc: 'Capaian 80% – 99% dari target resmi',
        },
        {
            label: 'Perlu Perhatian',
            color: '#EAB308',
            count: perluPerhatianCount,
            pct: perluPerhatianPct,
            dotClass: 'bg-warning',
            desc: 'Capaian 50% – 79% dari target resmi',
        },
        {
            label: 'Tidak Tercapai',
            color: '#DC2626',
            count: tidakTercapaiCount,
            pct: tidakTercapaiPct,
            dotClass: 'bg-danger',
            desc: 'Capaian < 50% dari target resmi',
        },
        {
            label: 'Belum Ada Data',
            color: '#94A3B8',
            count: belumAdaDataCount,
            pct: belumAdaDataPct,
            dotClass: 'bg-muted/40 border border-border',
            desc: 'Belum memiliki data capaian yang disahkan',
        },
    ];

    const activeCategoryData = hoveredCategory
        ? capaianCategories.find((c) => c.label === hoveredCategory)
        : null;

    // SVG Circular Progress calculation (Enlarged radius 56 for wider, bolder ring)
    const circleRadius = 56;
    const circleCircumference = 2 * Math.PI * circleRadius;

    // 6 KPI Status Cards mapped directly to SAKIP measurement lifecycle
    const kpiCards = [
        {
            key: 'total',
            label: 'Total Pengukuran',
            count: hasPeriode ? stats.total : '-',
            subtext: 'Target periode ini',
            icon: BarChart2,
            iconBg: 'bg-primary/10 text-primary border border-primary/20',
            href: auth.can.pengukuran ? '/pengukuran' : undefined,
        },
        {
            key: 'draft',
            label: 'Draf',
            count: hasPeriode ? stats.draft : '-',
            subtext: 'Unit & PIC',
            icon: FileText,
            iconBg: 'bg-soft text-muted border border-border',
            href: auth.can.pengukuran ? '/pengukuran' : undefined,
        },
        {
            key: 'diajukan',
            label: 'Diajukan',
            count: hasPeriode ? stats.diajukan : '-',
            subtext: 'Menunggu telaah',
            icon: Send,
            iconBg: 'bg-warning/10 text-warning-dark border border-warning/20',
            href: auth.can.verifikasi ? '/verifikasi' : undefined,
        },
        {
            key: 'diverifikasi',
            label: 'Diverifikasi',
            count: hasPeriode ? stats.diverifikasi : '-',
            subtext: 'Telah diverifikasi',
            icon: ShieldCheck,
            iconBg: 'bg-info/10 text-info-dark border border-info/20',
            href: auth.can.verifikasi ? '/verifikasi' : undefined,
        },
        {
            key: 'dikembalikan',
            label: 'Dikembalikan',
            count: hasPeriode ? stats.dikembalikan : '-',
            subtext: 'Perlu revisi',
            icon: RotateCcw,
            iconBg: 'bg-danger/10 text-danger border border-danger/20',
            href: auth.can.pengukuran ? '/pengukuran' : undefined,
        },
        {
            key: 'disahkan',
            label: 'Disahkan',
            count: hasPeriode ? stats.disahkan : '-',
            subtext: 'Capaian resmi',
            icon: Check,
            iconBg: 'bg-success/10 text-success border border-success/20',
            href: auth.can.verifikasi ? '/verifikasi' : undefined,
        },
    ];

    const handleKpiClick = (cardKey: string) => {
        if (cardKey === 'total') {
            setKpiFilter(null);
        } else {
            setKpiFilter((prev) => (prev === cardKey ? null : cardKey));
        }
    };

    const filteredPengukurans = kpiFilter && kpiFilter !== 'total'
        ? pengukurans.filter((p) => p.status === kpiFilter)
        : pengukurans;

    const renderEmptyState = () => {
        const isFilteredEmpty = pengukurans.length > 0 && filteredPengukurans.length === 0;
        return (
            <EmptyState
                icon={FileSearch}
                iconClassName="h-5 w-5"
                title={
                    isFilteredEmpty
                        ? `Tidak ada pengukuran dengan status ${kpiCards.find((c) => c.key === kpiFilter)?.label}`
                        : 'Belum ada data indikator pada periode aktif'
                }
                titleClassName="text-xs"
                action={
                    isFilteredEmpty ? (
                        <button
                            type="button"
                            onClick={() => setKpiFilter(null)}
                            className="mt-1 text-xs font-semibold text-primary hover:underline cursor-pointer"
                        >
                            Tampilkan semua pengukuran
                        </button>
                    ) : undefined
                }
                className="py-2 max-w-sm"
            />
        );
    };

    const renderStatusBadges = (item: DashboardProps['pengukurans'][number], direction: 'row' | 'col' = 'col') => (
        <div className={`flex ${direction === 'row' ? 'flex-wrap items-center shrink-0' : 'flex-col items-center'} gap-1`}>
            <Badge status={item.status} size="sm" />
            {item.self_approval && (
                <Badge variant="info" size="sm">
                    Persetujuan sendiri
                </Badge>
            )}
            {item.reviu_terlambat && (
                <Badge variant="warning" size="sm">
                    Reviu terlambat
                </Badge>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout hasCustomHeading={true}>
            <Head title="Dashboard Kinerja" />

            {/* Hero Banner: Clean Formal Enterprise with Office Building Backdrop */}
            <div className="relative mb-5 sm:mb-6 overflow-hidden rounded-2xl border border-border border-t-[3px] border-t-primary bg-surface shadow-xs min-h-[175px] sm:min-h-[195px] flex items-center">
                {/* Office Building Image seamlessly blended on the right */}
                <div
                    className="absolute inset-y-0 right-0 hidden md:block w-7/12 lg:w-1/2 bg-cover bg-no-repeat bg-center pointer-events-none"
                    style={{
                        backgroundImage: "url('/img/kantor-lldikti16.jpg')",
                    }}
                >
                    <div className="absolute inset-0 bg-gradient-to-r from-surface via-surface/55 to-transparent" />
                </div>

                {/* Banner Content */}
                <div className="relative z-10 px-5 py-6 sm:px-8 sm:py-7 max-w-xl lg:max-w-2xl w-full">
                    <p className="text-[11px] sm:text-xs font-semibold text-primary uppercase tracking-wider mb-1">
                        LLDIKTI Wilayah XVI
                    </p>

                    <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-ink leading-tight">
                        Hai, <span className="text-ink">{auth.user?.nama || 'Pengguna'}</span>
                    </h1>

                    <p className="mt-0.5 text-xs sm:text-sm font-medium text-muted leading-snug">
                        Sistem Akuntabilitas Kinerja Instansi Pemerintah
                    </p>

                    <div className="mt-3.5 sm:mt-4.5 flex flex-wrap items-center gap-2">
                        <div className="inline-flex items-center gap-1.5 rounded-md border border-border bg-soft/80 px-2.5 py-1 text-[11px] sm:text-xs font-medium text-ink shadow-2xs">
                            <Calendar className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
                            <span>{currentDate || 'Memuat tanggal...'}</span>
                        </div>
                        <div className="inline-flex items-center gap-1.5 rounded-md border border-border bg-soft/80 px-2.5 py-1 text-[11px] sm:text-xs font-medium text-ink shadow-2xs">
                            <Target className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
                            <span>Renstra: <strong className="font-semibold text-ink">{activeRenstra ? `${activeRenstra.tahun_mulai}-${activeRenstra.tahun_selesai}` : 'Belum ditetapkan'}</strong></span>
                        </div>
                        <div className="inline-flex items-center gap-1.5 rounded-md border border-border bg-soft/80 px-2.5 py-1 text-[11px] sm:text-xs font-medium text-ink shadow-2xs">
                            <Clock className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
                            <span>{activePeriode ? `${periodeLabel}: ${activePeriode.nama_periode}` : 'Jadwal belum aktif'}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* 6 KPI Cards: Interactive click-to-filter */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 sm:gap-3.5 mb-5 sm:mb-6">
                {kpiCards.map(({ key, label, count, subtext, icon: Icon, iconBg, href }) => {
                    const isSelected = kpiFilter === key;
                    return (
                        <div
                            key={key}
                            role="button"
                            tabIndex={0}
                            onClick={() => handleKpiClick(key)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    handleKpiClick(key);
                                }
                            }}
                            aria-pressed={isSelected}
                            className={`group flex flex-col justify-between rounded-xl border p-3 sm:p-3.5 transition-all duration-150 touch-manipulation min-h-[88px] sm:min-h-[94px] cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/40 ${isSelected
                                    ? 'border-primary ring-2 ring-primary/20 bg-primary/5 shadow-xs'
                                    : 'border-border bg-surface shadow-xs hover:border-primary/40 hover:bg-soft/40 active:scale-[0.99]'
                                }`}
                        >
                            <div className="flex items-center justify-between gap-1.5">
                                <span className={`text-[11px] sm:text-xs font-semibold leading-tight truncate transition-colors ${isSelected ? 'text-primary font-bold' : 'text-muted'
                                    }`}>
                                    {label}
                                </span>
                                <div className={`flex h-6 w-6 sm:h-7 sm:w-7 shrink-0 items-center justify-center rounded-md ${iconBg}`}>
                                    <Icon className="h-3 w-3 sm:h-3.5 sm:w-3.5" aria-hidden="true" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline justify-between gap-1">
                                <span className="text-xl sm:text-2xl font-extrabold text-ink font-mono tabular-nums leading-none">
                                    {count}
                                </span>
                                {href ? (
                                    <Link
                                        href={href}
                                        onClick={(e) => e.stopPropagation()}
                                        className="p-1 -m-1 text-muted/60 hover:text-primary transition-colors rounded focus:outline-none focus:ring-1 focus:ring-primary/40"
                                        aria-label={`Buka modul ${label}`}
                                    >
                                        <ChevronRight className="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-primary shrink-0" aria-hidden="true" />
                                    </Link>
                                ) : (
                                    <span className="text-[10px] text-muted/80 font-medium truncate">
                                        {subtext}
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* 2-Column Section: Capaian Indikator & Progres Rencana Aksi (Side-by-Side Modern Layout) */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-5 sm:mb-6">
                {/* CARD KIRI: Capaian Indikator (Data Disahkan) */}
                <Card className="rounded-xl border border-border bg-surface shadow-xs overflow-hidden flex flex-col justify-between">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 px-4 py-3 sm:px-5 sm:py-3.5 border-b border-border/60">
                        <div className="flex items-center gap-1.5">
                            <CardTitle className="text-sm font-bold text-ink">
                                Capaian Indikator (Data Disahkan)
                            </CardTitle>
                            <InfoTooltip
                                title="Capaian Indikator (Data Disahkan)"
                                content="Distribusi status capaian indikator yang dihitung khusus dari pengukuran dengan status Disahkan."
                                label="Informasi capaian indikator"
                            />
                        </div>
                        <div className="inline-flex items-center rounded-lg border border-border bg-soft px-2.5 py-1 text-xs font-medium text-ink shadow-2xs">
                            <span>Tahun {currentYear}</span>
                        </div>
                    </CardHeader>

                    <CardContent className="p-4 sm:p-5 flex-1 flex flex-col justify-between">
                        <div className="flex flex-col sm:flex-row items-center gap-5 sm:gap-6">
                            {/* Donut Chart Gauge Multi-segment (Interactive Hover) */}
                            <div className="relative flex h-36 w-36 sm:h-40 sm:w-40 md:h-44 md:w-44 shrink-0 items-center justify-center">
                                <svg className="h-full w-full -rotate-90 transform" viewBox="0 0 140 140">
                                    <circle
                                        cx="70"
                                        cy="70"
                                        r={circleRadius}
                                        className="text-border/70"
                                        strokeWidth="16"
                                        stroke="currentColor"
                                        fill="transparent"
                                    />
                                    {disahkanItems.length > 0 && (() => {
                                        let accumulatedOffset = 0;
                                        return capaianCategories.map((cat) => {
                                            if (cat.pct <= 0) return null;
                                            const dash = (cat.pct / 100) * circleCircumference;
                                            const offset = (accumulatedOffset / 100) * circleCircumference;
                                            accumulatedOffset += cat.pct;

                                            const isHovered = hoveredCategory === cat.label;
                                            const isAnyHovered = hoveredCategory !== null;

                                            return (
                                                <circle
                                                    key={cat.label}
                                                    cx="70"
                                                    cy="70"
                                                    r={circleRadius}
                                                    stroke={cat.color}
                                                    strokeWidth={isHovered ? 20 : 16}
                                                    strokeDasharray={`${dash} ${circleCircumference - dash}`}
                                                    strokeDashoffset={-offset}
                                                    fill="transparent"
                                                    className="transition-all duration-300 ease-out cursor-pointer"
                                                    style={{
                                                        opacity: isAnyHovered ? (isHovered ? 1 : 0.35) : 1,
                                                    }}
                                                    onMouseEnter={() => setHoveredCategory(cat.label)}
                                                    onMouseLeave={() => setHoveredCategory(null)}
                                                />
                                            );
                                        });
                                    })()}
                                </svg>
                                <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-1 pointer-events-none transition-all duration-200">
                                    {activeCategoryData ? (
                                        <>
                                            <span
                                                className="text-2xl sm:text-3xl font-extrabold font-mono tabular-nums leading-none transition-colors"
                                                style={{ color: activeCategoryData.color }}
                                            >
                                                {activeCategoryData.pct}%
                                            </span>
                                            <div className="mt-1 flex flex-col items-center leading-tight w-full max-w-[84px] sm:max-w-[94px]">
                                                <HoverScrollText
                                                    text={activeCategoryData.label}
                                                    isParentHovered={true}
                                                    fadeFromColor="from-surface"
                                                    centerWhenNoOverflow={true}
                                                    className="w-full text-[10px] sm:text-xs font-bold text-ink"
                                                    textClassName="text-[10px] sm:text-xs font-bold text-ink"
                                                    scrollSpeed={30}
                                                    startDelay={0.3}
                                                />
                                                <span className="text-[9px] sm:text-[10px] font-medium text-muted font-mono mt-0.5">
                                                    {activeCategoryData.count} Indikator
                                                </span>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <span className="text-2xl sm:text-3xl font-extrabold text-ink font-mono tabular-nums leading-none">
                                                {tercapaiPct}%
                                            </span>
                                            <div className="mt-1 flex flex-col items-center leading-tight">
                                                <span className="text-[10px] sm:text-xs font-medium text-muted">
                                                    Capaian
                                                </span>
                                                <span className="text-[10px] sm:text-xs font-medium text-muted">
                                                    Tersahkan
                                                </span>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>

                            {/* 5 Kategori Breakdown List with Hover Interactivity */}
                            <div className="flex-1 space-y-1.5 sm:space-y-2 w-full">
                                {capaianCategories.map((cat) => {
                                    const isHovered = hoveredCategory === cat.label;
                                    return (
                                        <div
                                            key={cat.label}
                                            data-testid={`capaian-category-${cat.label.toLowerCase().replace(/\s+/g, '-')}`}
                                            onMouseEnter={() => setHoveredCategory(cat.label)}
                                            onMouseLeave={() => setHoveredCategory(null)}
                                            className={`w-full flex items-center justify-between text-xs sm:text-sm px-2.5 py-1.5 -mx-2.5 rounded-lg cursor-pointer transition-all duration-150 ${isHovered
                                                    ? 'bg-soft/90 shadow-2xs'
                                                    : 'hover:bg-soft/40'
                                                }`}
                                        >
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                <span
                                                    className={`h-2.5 w-2.5 rounded-full shrink-0 transition-transform duration-150 ${cat.dotClass} ${isHovered ? 'scale-125' : ''
                                                        }`}
                                                    aria-hidden="true"
                                                />
                                                <span className={`font-medium truncate transition-colors ${isHovered ? 'text-primary font-semibold' : 'text-ink'
                                                    }`}>
                                                    {cat.label}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-4 sm:gap-5 font-mono tabular-nums shrink-0">
                                                <span className="text-xs sm:text-sm font-semibold text-muted w-4 sm:w-5 text-right">
                                                    {cat.count > 0 ? cat.count : '-'}
                                                </span>
                                                <span className={`text-sm sm:text-base font-bold w-10 sm:w-12 text-right transition-colors ${isHovered ? 'text-primary' : 'text-ink'
                                                    }`}>
                                                    {cat.pct}%
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Catatan Bawah: Data Disahkan / Hover Dynamic Description */}
                        <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-border/80 bg-soft/60 px-3.5 py-2.5 text-xs text-muted min-h-[42px] transition-all">
                            <Info className="h-4 w-4 text-primary shrink-0" aria-hidden="true" />
                            {activeCategoryData ? (
                                <p className="leading-relaxed">
                                    <strong className="font-semibold text-ink">{activeCategoryData.label}:</strong> {activeCategoryData.desc} ({activeCategoryData.count} indikator).
                                </p>
                            ) : (
                                <p className="leading-relaxed">
                                    Data capaian hanya berasal dari pengukuran dengan status <strong className="font-semibold text-ink">Disahkan</strong>.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* CARD KANAN: Progres Rencana Aksi */}
                <Card className="rounded-xl border border-border bg-surface shadow-xs overflow-hidden flex flex-col justify-between">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 px-4 py-3 sm:px-5 sm:py-3.5 border-b border-border/60">
                        <div className="flex items-center gap-1.5">
                            <CardTitle className="text-sm font-bold text-ink">
                                Progres Rencana Aksi
                            </CardTitle>
                            <InfoTooltip
                                title="Progres Rencana Aksi"
                                content="Progres penyusunan dan pengesahan rencana aksi indikator kinerja per periode oleh PIC dan Tim Perencanaan."
                                label="Informasi progres rencana aksi"
                            />
                        </div>
                        {activePeriode && (
                            <div className="inline-flex items-center rounded-md border border-border bg-soft px-2.5 py-1 text-xs font-medium text-muted">
                                <span>{activePeriode.nama_periode}</span>
                            </div>
                        )}
                    </CardHeader>

                    <CardContent className="p-4 sm:p-5 flex-1 flex flex-col justify-between">
                        {/* Header info bar */}
                        <div>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="text-sm sm:text-base font-bold text-ink leading-snug">
                                        {raTotal > 0 ? 'Status Rencana Aksi:' : 'Rencana Aksi Belum Tersedia'}
                                    </h3>
                                    {raTotal === 0 && (
                                        <p className="mt-1 text-xs text-muted leading-relaxed">
                                            Data rencana aksi belum tersedia pada periode ini.
                                        </p>
                                    )}
                                </div>
                                <div className="text-right shrink-0">
                                    <span className={`text-2xl sm:text-3xl font-extrabold font-mono tabular-nums leading-none ${raTotal > 0 ? 'text-ink' : 'text-muted'}`}>
                                        {raTotal > 0 ? `${raRate}%` : '-'}
                                    </span>
                                    <p className="text-[10px] sm:text-[11px] font-medium text-muted mt-0.5">
                                        Tingkat Pengesahan
                                    </p>
                                </div>
                            </div>

                            {/* Progress bar with clear target count */}
                            <div className="space-y-1.5 group/progress w-full mt-4">
                                <div className="flex items-center justify-between text-[11px] text-muted transition-opacity duration-150">
                                    <span className="font-medium">Progres Target Periode</span>
                                    <span className="font-mono font-semibold text-ink">
                                        {raTotal > 0 ? `${raDisahkan} dari ${raTotal} Target` : '-'}
                                    </span>
                                </div>
                                <div className="h-2.5 w-full overflow-hidden rounded-full bg-soft border border-border transition-all duration-200 group-hover/progress:h-3">
                                    <div
                                        className={`h-full rounded-full transition-all duration-500 ${raTotal > 0 ? 'bg-primary group-hover/progress:brightness-110' : 'bg-primary/40'
                                            }`}
                                        style={{ width: `${raTotal > 0 ? raRate : 0}%` }}
                                    />
                                </div>
                            </div>

                            {/* Ringkasan status rencana aksi: 2 Metrik Bersih */}
                            <div className="grid grid-cols-2 gap-2.5 mt-3.5">
                                <div
                                    className="w-full flex-1 rounded-lg border border-border bg-soft/50 p-2.5 hover:bg-soft hover:border-primary/40 hover:shadow-2xs transition-all duration-150"
                                >
                                    <p className="text-[11px] font-medium text-muted">Disahkan</p>
                                    <p className="mt-0.5 text-base sm:text-lg font-bold font-mono text-ink">
                                        {raTotal > 0 ? raDisahkan : '-'}
                                        <span className="text-[11px] font-normal text-muted ml-1">rencana</span>
                                    </p>
                                </div>
                                <div
                                    className="w-full flex-1 rounded-lg border border-border bg-soft/50 p-2.5 hover:bg-soft hover:border-primary/40 hover:shadow-2xs transition-all duration-150"
                                >
                                    <p className="text-[11px] font-medium text-muted">Belum Disahkan</p>
                                    <p className="mt-0.5 text-base sm:text-lg font-bold font-mono text-ink">
                                        {raTotal > 0 ? raTotal - raDisahkan : '-'}
                                        <span className="text-[11px] font-normal text-muted ml-1">rencana</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Catatan Bawah: Status Kesiapan */}
                        <div
                            className="mt-4 flex items-center justify-between rounded-xl border border-border/80 bg-soft/60 px-3.5 py-2.5 text-xs text-muted min-h-[42px]"
                        >
                            <div className="flex items-center gap-2">
                                <Info className="h-4 w-4 text-primary shrink-0" aria-hidden="true" />
                                <span>Kesiapan Pengesahan</span>
                                <strong className="font-semibold text-ink">
                                    {raTotal > 0 ? (raDisahkan === raTotal ? 'Lengkap (Siap Pengukuran)' : 'Sedang Berjalan') : 'Menunggu Modul ISS-05'}
                                </strong>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Bottom Card: Daftar Pengukuran Terbaru */}
            <Card className="rounded-xl border border-border bg-surface shadow-xs overflow-hidden" id="tabel-pengukuran">
                <CardHeader className="flex flex-row items-center justify-between gap-3 px-4 py-3 sm:px-5 sm:py-3.5 border-b border-border/60">
                    <div className="flex items-center gap-1.5">
                        <CardTitle className="text-sm font-bold text-ink">
                            Pengukuran Terbaru
                        </CardTitle>
                        <InfoTooltip
                            title="Pengukuran Terbaru"
                            content="Daftar pengukuran kinerja terbaru pada periode aktif."
                            label="Informasi pengukuran terbaru"
                        />
                    </div>

                    {auth.can.pengukuran && (
                        <Link
                            href="/pengukuran"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                        >
                            <span>Lihat Semua Pengukuran</span>
                            <ArrowRight className="h-3 w-3" aria-hidden="true" />
                        </Link>
                    )}
                </CardHeader>

                {/* Filter status banner if filtered */}
                {kpiFilter && kpiFilter !== 'total' && (
                    <div className="flex items-center justify-between bg-primary/5 border-b border-primary/20 px-4 py-2 sm:px-5 text-xs">
                        <div className="flex items-center gap-2">
                            <span className="font-semibold text-primary">Filter Aktif:</span>
                            <span className="capitalize font-semibold text-ink">
                                {kpiCards.find((c) => c.key === kpiFilter)?.label || kpiFilter}
                            </span>
                            <span className="text-muted">
                                ({filteredPengukurans.length} dari {pengukurans.length} data)
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setKpiFilter(null)}
                            className="inline-flex items-center gap-1 font-semibold text-primary hover:underline cursor-pointer text-xs"
                        >
                            <RotateCcw className="h-3 w-3" />
                            <span>Tampilkan Semua</span>
                        </button>
                    </div>
                )}

                {/* Mobile View: Clean, Touch-Friendly Responsive Card List */}
                <div className="block md:hidden divide-y divide-border/60">
                    {filteredPengukurans.length === 0 ? (
                        <div className="py-8 px-4 text-center">
                            {renderEmptyState()}
                        </div>
                    ) : (
                        filteredPengukurans.map((item, index) => (
                            <div key={item.id} className="p-4 space-y-2.5 transition-colors hover:bg-soft/30">
                                {/* Header: Code badge, Index, and Status badges */}
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <span className="text-[11px] font-mono text-muted">#{index + 1}</span>
                                        {item.action ? (
                                            <Link
                                                href={item.action.href}
                                                className="inline-flex items-center font-mono font-bold text-xs text-primary hover:underline bg-primary/10 px-2 py-0.5 rounded-md"
                                                aria-label={`Buka ${item.indikator.kode}`}
                                            >
                                                {item.indikator.kode}
                                            </Link>
                                        ) : (
                                            <span className="inline-flex items-center font-mono font-bold text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                                                {item.indikator.kode}
                                            </span>
                                        )}
                                    </div>
                                    {renderStatusBadges(item, 'row')}
                                </div>

                                {/* Body: Indicator Name, Unit & PIC */}
                                <div>
                                    <p className="text-xs font-semibold text-ink leading-snug">
                                        {item.indikator.nama}
                                    </p>
                                    <p className="text-[11px] text-muted mt-0.5 font-medium">
                                        {item.unit.nama} <span className="text-border mx-1">|</span> PIC: {item.pic?.nama || '-'}
                                    </p>
                                </div>

                                {/* Metrics Strip: Nilai & Hasil Perhitungan */}
                                <div className="flex items-center justify-between rounded-lg bg-soft/70 px-3 py-2 text-xs">
                                    <div>
                                        <span className="text-[10px] text-muted block uppercase tracking-wider font-semibold">Hasil Perhitungan</span>
                                        <span className="text-xs text-muted font-medium mt-0.5 block">
                                            {statusPerhitungan[item.status_perhitungan]}
                                        </span>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-[10px] text-muted block uppercase tracking-wider font-semibold">Nilai Capaian</span>
                                        <span className="font-mono font-extrabold text-ink mt-0.5 block tabular-nums">
                                            {item.nilai === null
                                                ? '-'
                                                : `${formatNilai(item.nilai, item.desimal_tampilan)} ${item.satuan}`}
                                        </span>
                                    </div>
                                </div>

                                {/* Action Button if available */}
                                {item.action && (
                                    <div className="pt-0.5">
                                        <Link
                                            href={item.action.href}
                                            className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:border-primary/30 transition-colors touch-manipulation min-h-[44px]"
                                        >
                                            <span>{item.action.label || 'Lihat Lembar Kerja'}</span>
                                            <ArrowRight className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
                                        </Link>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>

                {/* Desktop View: Full Structured Table with All 6 Canonical Columns */}
                <div className="overflow-x-auto hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-soft/70">
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider w-12 text-center">
                                    No.
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider w-28">
                                    Kode
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider">
                                    Indikator Kinerja
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider">
                                    Unit & PIC
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider text-right w-28 whitespace-nowrap">
                                    Nilai
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider">
                                    Hasil Perhitungan
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider text-center w-36">
                                    Status
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider text-center w-24">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredPengukurans.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-8 px-6 text-center">
                                        {renderEmptyState()}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filteredPengukurans.map((item, index) => (
                                    <TableRow key={item.id} className="transition-colors hover:bg-soft/40">
                                        <TableCell className="px-5 py-3 text-center text-xs text-muted font-mono">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 font-mono font-bold text-xs text-primary">
                                            {item.action ? (
                                                <Link
                                                    href={item.action.href}
                                                    className="hover:underline focus:outline-none focus:ring-1 focus:ring-primary/40 rounded inline-block"
                                                    aria-label={`Buka ${item.indikator.kode}`}
                                                >
                                                    {item.indikator.kode}
                                                </Link>
                                            ) : (
                                                item.indikator.kode
                                            )}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-xs text-ink font-medium max-w-xs">
                                            <p className="leading-snug">{item.indikator.nama}</p>
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-xs text-ink">
                                            <p className="font-medium">{item.unit.nama}</p>
                                            <p className="text-[11px] text-muted mt-0.5">PIC: {item.pic?.nama || '-'}</p>
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-right font-mono font-bold text-xs text-ink tabular-nums whitespace-nowrap">
                                            {item.nilai === null
                                                ? '-'
                                                : `${formatNilai(item.nilai, item.desimal_tampilan)} ${item.satuan}`}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-xs text-muted">
                                            {statusPerhitungan[item.status_perhitungan]}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-center">
                                            {renderStatusBadges(item, 'col')}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-center">
                                            {item.action ? (
                                                <Link
                                                    href={item.action.href}
                                                    className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-2.5 py-1 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:border-primary/30 transition-colors"
                                                >
                                                    {item.action.label ? 'Buka' : 'Lihat'}
                                                </Link>
                                            ) : (
                                                <span className="text-xs text-muted">-</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
