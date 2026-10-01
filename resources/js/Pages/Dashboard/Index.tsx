import { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    Target,
    Clock,
    ChevronRight,
    BarChart2,
    ClipboardList,
    Users,
    FileSearch,
    ArrowRight,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/Components/Table';
import { Badge } from '@/Components/Badge';
import { InfoTooltip } from '@/Components/InfoTooltip';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { Pengukuran } from '@/Pages/Pengukuran/types';
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
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const [currentDate, setCurrentDate] = useState<string>('');
    const hasPeriode = activePeriode !== null;
    const isActivePeriode = activePeriode?.status === 'aktif';
    const periodeLabel = isActivePeriode ? 'Periode aktif' : 'Periode terakhir';
    const currentYear = new Date().getFullYear();

    useEffect(() => {
        const updateBusinessDate = () => {
            setCurrentDate(formatTanggal(new Date(), { withDay: true }));
        };

        updateBusinessDate();
        const interval = window.setInterval(updateBusinessDate, 60_000);

        return () => window.clearInterval(interval);
    }, [formatTanggal]);

    const completionRate = hasPeriode && stats.total > 0
        ? Math.round((stats.disahkan / stats.total) * 100)
        : 0;

    // SVG Circular Progress calculation
    const circleRadius = 56;
    const circleCircumference = 2 * Math.PI * circleRadius;
    const circleStrokeDashoffset = circleCircumference - (completionRate / 100) * circleCircumference;

    const topCards = [
        {
            key: 'target',
            label: 'Total Target Indikator',
            count: hasPeriode ? stats.total : '-',
            icon: Target,
            iconBg: 'bg-primary/10 text-primary border border-primary/20',
            href: auth.can.pengukuran ? '/pengukuran' : undefined,
        },
        {
            key: 'capaian',
            label: 'Capaian Tersahkan',
            count: hasPeriode ? stats.disahkan : '-',
            icon: BarChart2,
            iconBg: 'bg-success/10 text-success border border-success/20',
            href: auth.can.verifikasi ? '/verifikasi' : undefined,
        },
        {
            key: 'rencana_aksi',
            label: 'Rencana Aksi',
            count: '-',
            icon: ClipboardList,
            iconBg: 'bg-warning/15 text-warning-dark border border-warning/25',
            href: undefined,
        },
        {
            key: 'kegiatan',
            label: 'Status Kegiatan',
            count: '-',
            icon: Users,
            iconBg: 'bg-primary/10 text-primary border border-primary/20',
            href: undefined,
        },
    ];

    return (
        <AuthenticatedLayout hasCustomHeading={true}>
            <Head title="Dashboard Kinerja" />

            {/* Hero Banner with Office Building Imagery — Clean Formal Enterprise (Issue #48 compliant) */}
            <div className="relative mb-5 sm:mb-6 overflow-hidden rounded-2xl border border-border border-t-[3px] border-t-primary bg-surface shadow-xs min-h-[160px] sm:min-h-[190px] lg:min-h-[200px] flex items-center">
                {/* Office Building Image seamlessly blended on the right */}
                <div
                    className="absolute inset-y-0 right-0 hidden md:block w-7/12 lg:w-1/2 bg-cover bg-no-repeat bg-center pointer-events-none"
                    style={{
                        backgroundImage: "url('/img/kantor-lldikti16.jpg')",
                    }}
                >
                    {/* Multi-stop smooth gradient overlay for seamless blending */}
                    <div className="absolute inset-0 bg-gradient-to-r from-surface via-surface/85 to-transparent" />
                </div>

                {/* Banner Content (Spacious, Clear Hierarchy) */}
                <div className="relative z-10 px-4 py-5 sm:px-8 sm:py-6 lg:py-7 max-w-xl lg:max-w-2xl w-full">
                    <p className="text-xs sm:text-sm font-semibold text-primary tracking-wide mb-1.5">
                        LLDIKTI Wilayah XVI
                    </p>

                    <h1 className="text-lg sm:text-2xl lg:text-[28px] font-extrabold tracking-tight text-ink leading-tight">
                        Sistem Akuntabilitas Kinerja <span className="block sm:inline sm:ml-1">Instansi Pemerintah</span>
                    </h1>

                    <div className="mt-3.5 sm:mt-5 flex flex-wrap items-center gap-2 sm:gap-2.5">
                        <div className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-soft/80 px-2.5 py-1 sm:px-3.5 sm:py-1.5 text-[11px] sm:text-xs font-semibold text-ink shadow-2xs">
                            <Calendar className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
                            <span>{currentDate || 'Memuat tanggal...'}</span>
                        </div>
                        <div className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-soft/80 px-2.5 py-1 sm:px-3.5 sm:py-1.5 text-[11px] sm:text-xs font-semibold text-ink shadow-2xs">
                            <Target className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
                            <span>Renstra: <strong className="font-semibold text-ink">{activeRenstra ? `${activeRenstra.tahun_mulai}-${activeRenstra.tahun_selesai}` : 'Belum ditetapkan'}</strong></span>
                        </div>
                        <div className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-soft/80 px-2.5 py-1 sm:px-3.5 sm:py-1.5 text-[11px] sm:text-xs font-semibold text-ink shadow-2xs">
                            <Clock className="h-3.5 w-3.5 text-muted shrink-0" aria-hidden="true" />
                            <span>{activePeriode ? `${periodeLabel}: ${activePeriode.nama_periode}` : 'Jadwal belum aktif'}</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* 4 Metric Cards Grid: 2x2 on Mobile, 4 Columns on Desktop */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4 mb-5 sm:mb-6">
                {topCards.map(({ key, label, count, icon: Icon, iconBg, href }) => {
                    const CardWrapper = href ? Link : 'div';
                    return (
                        <CardWrapper
                            key={key}
                            {...(href ? { href } : {})}
                            className={`group flex items-center justify-between rounded-xl border border-border bg-surface p-3 sm:p-4 shadow-xs transition-all duration-150 touch-manipulation min-h-[60px] sm:min-h-[64px] ${
                                href
                                    ? 'cursor-pointer hover:border-primary/30 active:scale-[0.99] active:bg-soft/60'
                                    : 'cursor-default'
                            }`}
                        >
                            <div className="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1">
                                <div className="flex h-8 w-8 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-lg sm:rounded-xl transition-transform duration-150 group-hover:scale-105">
                                    <div className={`flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg sm:rounded-xl ${iconBg}`}>
                                        <Icon className="h-4 w-4 sm:h-5 sm:w-5" aria-hidden="true" />
                                    </div>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[11px] sm:text-xs font-bold text-ink leading-tight line-clamp-2 sm:line-clamp-1">
                                        {label}
                                    </p>
                                    <p className="mt-0.5 sm:mt-1 text-base sm:text-2xl font-extrabold text-ink font-mono tabular-nums leading-none">
                                        {count}
                                    </p>
                                </div>
                            </div>
                            {href && (
                                <ChevronRight className="hidden sm:block h-4 w-4 text-muted/60 transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-primary shrink-0 ml-1" aria-hidden="true" />
                            )}
                        </CardWrapper>
                    );
                })}
            </div>

            {/* Middle Section: Capaian Indikator & Tahapan Alur Pengukuran */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 mb-5 sm:mb-6 items-stretch">
                {/* Left Card: Capaian Indikator (Data Disahkan) */}
                <Card className="rounded-xl border border-border bg-surface shadow-xs overflow-hidden flex flex-col h-full">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 p-4 sm:p-5 border-b border-border/60">
                        <div className="flex items-center gap-1.5">
                            <CardTitle className="text-sm font-bold text-ink">
                                Capaian Indikator (Data Disahkan)
                            </CardTitle>
                            <InfoTooltip
                                title="Capaian Indikator"
                                content="Persentase dan ringkasan capaian kinerja yang dihitung secara resmi dari data yang telah disahkan (ratified) oleh Tim Perencanaan."
                                label="Informasi capaian indikator"
                            />
                        </div>
                        <div className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-soft px-2.5 py-1 text-xs font-semibold text-ink">
                            <span>Tahun {currentYear}</span>
                        </div>
                    </CardHeader>

                    <CardContent className="p-4 sm:p-5 flex-1 flex flex-col justify-center">
                        {/* Chart and Legend Breakdown */}
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-5 sm:gap-6">
                            {/* Circular Ring Gauge */}
                            <div className="relative flex flex-col items-center justify-center shrink-0 my-1 sm:my-0">
                                <svg className="h-28 w-28 sm:h-32 sm:w-32 -rotate-90 transform" viewBox="0 0 130 130">
                                    <circle
                                        cx="65"
                                        cy="65"
                                        r={circleRadius}
                                        className="text-border"
                                        strokeWidth="10"
                                        stroke="currentColor"
                                        fill="transparent"
                                    />
                                    <circle
                                        cx="65"
                                        cy="65"
                                        r={circleRadius}
                                        className="text-primary transition-all duration-700 ease-in-out"
                                        strokeWidth="10"
                                        strokeDasharray={circleCircumference}
                                        strokeDashoffset={hasPeriode && stats.total > 0 ? circleStrokeDashoffset : circleCircumference}
                                        strokeLinecap="round"
                                        stroke="currentColor"
                                        fill="transparent"
                                    />
                                </svg>
                                <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                                    <span className="text-2xl font-extrabold text-ink font-mono tabular-nums leading-none">
                                        {hasPeriode && stats.total > 0 ? `${completionRate}%` : '0%'}
                                    </span>
                                    <span className="mt-1 text-[11px] font-semibold text-muted leading-tight">
                                        Capaian<br />Tersahkan
                                    </span>
                                </div>
                            </div>

                            {/* Legend Breakdown (PRD §23 Categories) */}
                            <div className="flex-1 w-full space-y-1.5 sm:space-y-2.5">
                                <div className="flex items-center justify-between text-xs px-2 py-1.5 rounded-lg bg-soft/30 sm:bg-transparent">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2.5 w-2.5 rounded-full bg-success shrink-0" aria-hidden="true" />
                                        <span className="font-medium text-ink">Tercapai</span>
                                    </div>
                                    <div className="flex items-center gap-3 font-mono font-semibold text-ink">
                                        <span className="text-muted text-[11px] font-normal">0 data</span>
                                        <span className="w-8 text-right">0%</span>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between text-xs px-2 py-1.5 rounded-lg bg-soft/30 sm:bg-transparent">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2.5 w-2.5 rounded-full bg-info shrink-0" aria-hidden="true" />
                                        <span className="font-medium text-ink">Dalam Progres</span>
                                    </div>
                                    <div className="flex items-center gap-3 font-mono font-semibold text-ink">
                                        <span className="text-muted text-[11px] font-normal">0 data</span>
                                        <span className="w-8 text-right">0%</span>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between text-xs px-2 py-1.5 rounded-lg bg-soft/30 sm:bg-transparent">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2.5 w-2.5 rounded-full bg-warning shrink-0" aria-hidden="true" />
                                        <span className="font-medium text-ink">Perlu Perhatian</span>
                                    </div>
                                    <div className="flex items-center gap-3 font-mono font-semibold text-ink">
                                        <span className="text-muted text-[11px] font-normal">0 data</span>
                                        <span className="w-8 text-right">0%</span>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between text-xs px-2 py-1.5 rounded-lg bg-soft/30 sm:bg-transparent">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2.5 w-2.5 rounded-full bg-danger shrink-0" aria-hidden="true" />
                                        <span className="font-medium text-ink">Tidak Tercapai</span>
                                    </div>
                                    <div className="flex items-center gap-3 font-mono font-semibold text-ink">
                                        <span className="text-muted text-[11px] font-normal">0 data</span>
                                        <span className="w-8 text-right">0%</span>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between text-xs px-2 py-1.5 rounded-lg bg-soft/30 sm:bg-transparent">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2.5 w-2.5 rounded-full bg-muted/60 shrink-0" aria-hidden="true" />
                                        <span className="font-medium text-ink">Belum Ada Data</span>
                                    </div>
                                    <div className="flex items-center gap-3 font-mono font-semibold text-ink">
                                        <span className="text-muted text-[11px] font-normal">0 data</span>
                                        <span className="w-8 text-right">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Right Card: Tahapan Alur Pengukuran */}
                <Card className="rounded-xl border border-border bg-surface shadow-xs overflow-hidden flex flex-col h-full">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 p-4 sm:p-5 border-b border-border/60">
                        <div className="flex items-center gap-1.5">
                            <CardTitle className="text-sm font-bold text-ink">
                                Tahapan Alur Pengukuran
                            </CardTitle>
                            <InfoTooltip
                                title="Tahapan Alur Pengukuran"
                                content="Alur progres pengukuran dari pengisian draft oleh Unit/PIC, pengajuan telaah, verifikasi tim verifikator, hingga pengesahan resmi."
                                label="Informasi tahapan alur pengukuran"
                            />
                        </div>
                        <span className="text-xs font-medium text-muted">
                            {hasPeriode ? activePeriode.nama_periode : 'Belum ada periode aktif'}
                        </span>
                    </CardHeader>

                    <CardContent className="p-4 sm:p-6 flex-1 flex flex-col justify-center">
                        {/* Responsive Stepper: 2x2 Clean Metric Blocks on Mobile, Centered & Prominent Stepper on Desktop */}
                        <div className="relative">
                            {/* Stepper Connecting Track Line (Desktop Only, aligned to refined circles) */}
                            <div className="hidden sm:block absolute top-3.5 sm:top-4 md:top-4.5 left-10 sm:left-12 right-10 sm:right-12 -translate-y-1/2 h-0.5 bg-border z-0" aria-hidden="true" />

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 relative z-10 text-center">
                                {/* Step 1: Draft */}
                                <div className="flex flex-col items-center rounded-xl border border-border/70 bg-soft/40 p-2.5 sm:border-0 sm:bg-transparent sm:p-0">
                                    <span className="flex h-7 w-7 sm:h-8 sm:w-8 md:h-9 md:w-9 items-center justify-center rounded-full bg-primary text-white font-bold text-xs ring-3 sm:ring-4 ring-surface shadow-2xs">
                                        1
                                    </span>
                                    <p className="mt-2 text-xs sm:text-sm font-bold text-ink">Draft</p>
                                    <p className="text-[10px] sm:text-xs text-muted font-medium leading-tight">Unit & PIC</p>
                                    <p className="mt-2 sm:mt-3 text-lg sm:text-xl md:text-2xl font-extrabold text-ink font-mono tabular-nums leading-none">
                                        {hasPeriode ? stats.draft : '-'}
                                    </p>
                                </div>

                                {/* Step 2: Diajukan */}
                                <div className="flex flex-col items-center rounded-xl border border-border/70 bg-soft/40 p-2.5 sm:border-0 sm:bg-transparent sm:p-0">
                                    <span className="flex h-7 w-7 sm:h-8 sm:w-8 md:h-9 md:w-9 items-center justify-center rounded-full bg-surface border border-border text-muted font-bold text-xs ring-3 sm:ring-4 ring-surface shadow-2xs">
                                        2
                                    </span>
                                    <p className="mt-2 text-xs sm:text-sm font-bold text-ink">Diajukan</p>
                                    <p className="text-[10px] sm:text-xs text-muted font-medium leading-tight">Antrean Telaah</p>
                                    <p className="mt-2 sm:mt-3 text-lg sm:text-xl md:text-2xl font-extrabold text-ink font-mono tabular-nums leading-none">
                                        {hasPeriode ? stats.diajukan : '-'}
                                    </p>
                                </div>

                                {/* Step 3: Diverifikasi */}
                                <div className="flex flex-col items-center rounded-xl border border-border/70 bg-soft/40 p-2.5 sm:border-0 sm:bg-transparent sm:p-0">
                                    <span className="flex h-7 w-7 sm:h-8 sm:w-8 md:h-9 md:w-9 items-center justify-center rounded-full bg-surface border border-border text-muted font-bold text-xs ring-3 sm:ring-4 ring-surface shadow-2xs">
                                        3
                                    </span>
                                    <p className="mt-2 text-xs sm:text-sm font-bold text-ink">Diverifikasi</p>
                                    <p className="text-[10px] sm:text-xs text-muted font-medium leading-tight">Verifikator</p>
                                    <p className="mt-2 sm:mt-3 text-lg sm:text-xl md:text-2xl font-extrabold text-ink font-mono tabular-nums leading-none">
                                        {hasPeriode ? stats.diverifikasi : '-'}
                                    </p>
                                </div>

                                {/* Step 4: Disahkan */}
                                <div className="flex flex-col items-center rounded-xl border border-border/70 bg-soft/40 p-2.5 sm:border-0 sm:bg-transparent sm:p-0">
                                    <span className="flex h-7 w-7 sm:h-8 sm:w-8 md:h-9 md:w-9 items-center justify-center rounded-full bg-surface border border-border text-muted font-bold text-xs ring-3 sm:ring-4 ring-surface shadow-2xs">
                                        4
                                    </span>
                                    <p className="mt-2 text-xs sm:text-sm font-bold text-ink">Disahkan</p>
                                    <p className="text-[10px] sm:text-xs text-muted font-medium leading-tight">Data Resmi</p>
                                    <p className="mt-2 sm:mt-3 text-lg sm:text-xl md:text-2xl font-extrabold text-success font-mono tabular-nums leading-none">
                                        {hasPeriode ? stats.disahkan : '-'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Bottom Card: Daftar Indikator Kinerja */}
            <Card className="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
                <CardHeader className="flex flex-row items-center justify-between gap-3 p-4 sm:p-5 border-b border-border/60">
                    <div className="flex items-center gap-1.5">
                        <CardTitle className="text-sm font-bold text-ink">
                            Daftar Indikator Kinerja
                        </CardTitle>
                        <InfoTooltip
                            title="Daftar Indikator Kinerja"
                            content="Daftar indikator kinerja dan realisasi pengukuran pada periode pelaporan aktif. Klik pada kode indikator untuk membuka formulir pengukuran."
                            label="Informasi daftar indikator kinerja"
                        />
                    </div>

                    {auth.can.pengukuran && (
                        <Link
                            href="/pengukuran"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                        >
                            <span>Lihat Semua</span>
                            <ArrowRight className="h-3 w-3" aria-hidden="true" />
                        </Link>
                    )}
                </CardHeader>

                {/* Mobile View: Clean, Touch-Friendly Responsive Card List */}
                <div className="block md:hidden divide-y divide-border/60">
                    {pengukurans.length === 0 ? (
                        <div className="py-8 px-4 text-center">
                            <div className="flex flex-col items-center justify-center max-w-sm mx-auto">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-soft text-muted mb-2 border border-border">
                                    <FileSearch className="h-5 w-5 text-muted" aria-hidden="true" />
                                </div>
                                <p className="text-xs font-medium text-muted">
                                    Belum ada data indikator pada periode aktif
                                </p>
                            </div>
                        </div>
                    ) : (
                        pengukurans.map((item, index) => (
                            <div key={item.id} className="p-4 space-y-2.5 transition-colors hover:bg-soft/30">
                                {/* Header: Code badge, Index, and Status badges */}
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <span className="text-[11px] font-mono text-muted">#{index + 1}</span>
                                        {item.action ? (
                                            <Link
                                                href={item.action.href}
                                                className="inline-flex items-center font-mono font-bold text-xs text-primary hover:underline bg-primary/10 px-2 py-0.5 rounded-md"
                                                title={`Buka ${item.indikator.kode}`}
                                            >
                                                {item.indikator.kode}
                                            </Link>
                                        ) : (
                                            <span className="inline-flex items-center font-mono font-bold text-xs text-primary bg-primary/10 px-2 py-0.5 rounded-md">
                                                {item.indikator.kode}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1 shrink-0">
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
                                </div>

                                {/* Body: Indicator Name & Unit */}
                                <div>
                                    <p className="text-xs font-semibold text-ink leading-snug">
                                        {item.indikator.nama}
                                    </p>
                                    <p className="text-[11px] text-muted mt-0.5 font-medium">
                                        {item.unit.nama}
                                    </p>
                                </div>

                                {/* Metrics Strip */}
                                <div className="flex items-center justify-between rounded-lg bg-soft/70 px-3 py-2 text-xs">
                                    <div>
                                        <span className="text-[10px] text-muted block uppercase tracking-wider font-semibold">Target {currentYear}</span>
                                        <span className="font-mono font-semibold text-ink mt-0.5 block">-</span>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-[10px] text-muted block uppercase tracking-wider font-semibold">Capaian</span>
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
                                            <span>Lihat Lembar Kerja</span>
                                            <ArrowRight className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
                                        </Link>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>

                {/* Desktop View: Full Structured Table */}
                <div className="overflow-x-auto hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-soft/70">
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider w-14 text-center">
                                    No.
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider w-28">
                                    Kode
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider">
                                    Indikator Kinerja
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider text-right">
                                    Target {currentYear}
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider text-right">
                                    Capaian
                                </TableHead>
                                <TableHead className="px-5 py-3 text-muted text-[11px] font-bold uppercase tracking-wider text-center w-36">
                                    Status
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pengukurans.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 px-6 text-center">
                                        <div className="flex flex-col items-center justify-center py-2 max-w-sm mx-auto">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-soft text-muted mb-2 border border-border">
                                                <FileSearch className="h-5 w-5 text-muted" aria-hidden="true" />
                                            </div>
                                            <p className="text-xs font-medium text-muted">
                                                Belum ada data indikator pada periode aktif
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                pengukurans.map((item, index) => (
                                    <TableRow key={item.id} className="transition-colors hover:bg-soft/40">
                                        <TableCell className="px-5 py-3 text-center text-xs text-muted font-mono">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 font-mono font-bold text-xs text-primary">
                                            {item.action ? (
                                                <Link
                                                    href={item.action.href}
                                                    className="hover:underline focus:outline-none focus:ring-1 focus:ring-primary/40 rounded inline-block"
                                                    title={`Buka ${item.indikator.kode}`}
                                                >
                                                    {item.indikator.kode}
                                                </Link>
                                            ) : (
                                                item.indikator.kode
                                            )}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-xs text-ink font-medium">
                                            <p>{item.indikator.nama}</p>
                                            <p className="text-[11px] text-muted mt-0.5">{item.unit.nama}</p>
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-right font-mono text-xs text-ink">
                                            -
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-right font-mono font-bold text-xs text-ink tabular-nums">
                                            {item.nilai === null
                                                ? '-'
                                                : `${formatNilai(item.nilai, item.desimal_tampilan)} ${item.satuan}`}
                                        </TableCell>
                                        <TableCell className="px-5 py-3 text-center">
                                            <div className="flex flex-col items-center gap-1">
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
