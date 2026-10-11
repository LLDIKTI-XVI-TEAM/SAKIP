import { lazy, Suspense, type ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart2,
    CalendarRange,
    Check,
    ClipboardCheck,
    ClipboardList,
    Eye,
    FileText,
    Info,
    RotateCcw,
    Send,
    ShieldCheck,
    Target,
    Timer,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { STATUS_ALUR, persenDisahkan } from '@/Components/Dashboard/statusAlur';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { statusPerhitungan } from '@/Pages/Pengukuran/types';
import type { DashboardProps, StatusAlur } from '@/types/dashboard';
import type { SharedPageProps } from '@/types/auth';

const DashboardCharts = lazy(() => import('@/Components/Dashboard/DashboardCharts'));

const outlineLink =
    'inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-all duration-150 hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25 focus:ring-offset-1';
const iconAction =
    'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

const kartuStatus: Record<StatusAlur, { icon: typeof FileText; tone: string; desc: string }> = {
    draft: { icon: FileText, tone: 'bg-soft text-muted', desc: 'Belum diajukan' },
    diajukan: { icon: Send, tone: 'bg-warning/15 text-warning-dark', desc: 'Menunggu verifikasi' },
    diverifikasi: { icon: ShieldCheck, tone: 'bg-info/15 text-info-dark', desc: 'Menunggu pengesahan' },
    dikembalikan: { icon: RotateCcw, tone: 'bg-danger/15 text-danger-dark', desc: 'Perlu perbaikan' },
    disahkan: { icon: Check, tone: 'bg-success/15 text-success-dark', desc: '' },
};

export default function DashboardIndex({
    tahun,
    activeRenstra,
    activeJadwal,
    activePeriode,
    stats,
    progresPeriode,
    pengukurans,
}: DashboardProps) {
    const { auth } = usePage<SharedPageProps>().props;
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const persen = persenDisahkan(stats.disahkan, stats.total);

    const alasanKosong = !activeRenstra
        ? `Belum ada Renstra aktif yang mencakup tahun ${tahun}.`
        : !activeJadwal
          ? `Belum ada Jadwal Tahunan ${tahun} untuk Renstra aktif.`
          : `Jendela pengisian pertama Jadwal Tahunan ${tahun} belum dimulai.`;

    const sisaHari = activePeriode?.sisa_hari ?? null;
    const keteranganJendela =
        sisaHari === null ? 'Pengisian periode ini telah ditutup' : sisaHari === 0 ? 'Hari terakhir pengisian' : `Sisa ${sisaHari} hari`;

    return (
        <AuthenticatedLayout
            title="Dashboard"
            breadcrumbs={[{ label: 'Dashboard' }]}
            headerActions={
                auth.can.verifikasi && (
                    <Link href="/verifikasi" className={outlineLink}>
                        <ClipboardCheck className="h-4 w-4" aria-hidden="true" />
                        Antrean verifikasi
                    </Link>
                )
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-5">
                <Card>
                    <div className="grid divide-y divide-border md:grid-cols-3 md:divide-x md:divide-y-0">
                        <KonteksItem icon={Target} label="Renstra aktif">
                            {activeRenstra ? (
                                <>
                                    <p className="break-words text-sm font-semibold text-ink">{activeRenstra.nama}</p>
                                    <p className="mt-0.5 text-xs text-muted">
                                        Periode {activeRenstra.tahun_mulai}–{activeRenstra.tahun_selesai}
                                    </p>
                                </>
                            ) : (
                                <p className="text-sm font-semibold text-muted">Belum ada Renstra aktif</p>
                            )}
                        </KonteksItem>
                        <KonteksItem icon={CalendarRange} label="Periode pelaporan">
                            {activePeriode ? (
                                <>
                                    <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink">
                                        {activePeriode.nama_periode}
                                        <Badge variant={activePeriode.status === 'aktif' ? 'success' : 'muted'} size="sm" dot>
                                            {activePeriode.status === 'aktif' ? 'Berjalan' : 'Terakhir'}
                                        </Badge>
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted">Jadwal Tahunan {activeJadwal?.tahun}</p>
                                </>
                            ) : (
                                <p className="text-sm font-semibold text-muted">Belum ada periode berjalan</p>
                            )}
                        </KonteksItem>
                        <KonteksItem icon={Timer} label="Jendela pengisian">
                            {activePeriode ? (
                                <>
                                    <p className="text-sm font-semibold text-ink">
                                        {formatTanggal(activePeriode.pengisian_mulai)} – {formatTanggal(activePeriode.pengisian_selesai)}
                                    </p>
                                    <p
                                        className={`mt-0.5 text-xs font-medium ${sisaHari !== null && sisaHari <= 7 ? 'text-warning-dark' : 'text-muted'}`}
                                    >
                                        {keteranganJendela}
                                    </p>
                                </>
                            ) : (
                                <p className="text-sm font-semibold text-muted">—</p>
                            )}
                        </KonteksItem>
                    </div>
                </Card>

                {!activePeriode ? (
                    <Card>
                        <div className="flex items-start gap-3 p-5" role="status">
                            <span className="rounded-lg bg-info/15 p-2 text-info-dark">
                                <Info className="h-4 w-4" aria-hidden="true" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-ink">Ringkasan pengukuran belum tersedia</p>
                                <p className="mt-0.5 text-xs text-muted">
                                    {alasanKosong} Ringkasan, grafik, dan daftar pengukuran akan tampil setelah jendela pengisian dibuka.
                                </p>
                            </div>
                        </div>
                    </Card>
                ) : (
                    <>
                        <section aria-labelledby="ringkasan-status" className="space-y-3">
                            <h2 id="ringkasan-status" className="sr-only">
                                Ringkasan status pengukuran {activePeriode.nama_periode}
                            </h2>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                                <KartuRingkasan
                                    icon={BarChart2}
                                    tone="bg-primary/10 text-primary"
                                    label="Total"
                                    value={stats.total}
                                    desc={activePeriode.nama_periode}
                                />
                                {STATUS_ALUR.map(({ key, label }) => (
                                    <KartuRingkasan
                                        key={key}
                                        icon={kartuStatus[key].icon}
                                        tone={kartuStatus[key].tone}
                                        label={label}
                                        value={stats[key]}
                                        desc={key === 'disahkan' ? `${persen}% dari total` : kartuStatus[key].desc}
                                    />
                                ))}
                            </div>
                        </section>

                        <Suspense
                            fallback={
                                <div className="grid gap-5 lg:grid-cols-3" aria-hidden="true">
                                    <div className="h-[372px] animate-pulse rounded-xl border border-border bg-surface motion-reduce:animate-none lg:col-span-2" />
                                    <div className="h-[372px] animate-pulse rounded-xl border border-border bg-surface motion-reduce:animate-none" />
                                </div>
                            }
                        >
                            <DashboardCharts
                                stats={stats}
                                progres={progresPeriode}
                                periodeNama={activePeriode.nama_periode}
                                tahun={activeJadwal?.tahun ?? tahun}
                            />
                        </Suspense>

                        <Card>
                            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
                                <div className="min-w-0">
                                    <h2 className="text-base font-semibold tracking-tight text-ink">Pengukuran terbaru</h2>
                                    <p className="mt-0.5 text-xs text-muted">
                                        Maksimal 20 pengukuran {activePeriode.nama_periode} yang terakhir diperbarui. Hasil resmi hanya yang
                                        berstatus Disahkan.
                                    </p>
                                </div>
                                {auth.can.pengukuran && (
                                    <Link href="/pengukuran" className={outlineLink}>
                                        Lihat semua
                                        <ArrowRight className="h-4 w-4" aria-hidden="true" />
                                    </Link>
                                )}
                            </div>
                            <Table wrapperClassName="overflow-x-visible" className="block md:table">
                                <caption className="sr-only">Pengukuran terbaru {activePeriode.nama_periode}</caption>
                                <TableHeader className="hidden md:table-header-group">
                                    <TableRow>
                                        {['Indikator', 'Unit & PIC', 'Nilai', 'Status', 'Aksi'].map((label) => (
                                            <TableHead
                                                key={label}
                                                className={`whitespace-normal ${label === 'Nilai' || label === 'Aksi' ? 'text-right' : ''}`}
                                            >
                                                {label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="block md:table-row-group">
                                    {pengukurans.length === 0 && (
                                        <TableRow className="block md:table-row">
                                            <TableCell colSpan={5} className="block py-12 text-center md:table-cell">
                                                <ClipboardList className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                                <p className="mt-2 text-sm font-semibold text-ink">Belum ada pengukuran</p>
                                                <p className="mt-1 text-xs text-muted">
                                                    Pengukuran {activePeriode.nama_periode} akan tampil di sini setelah dibuat.
                                                </p>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {pengukurans.map((item) => (
                                        <TableRow key={item.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                            <TableCell className="col-span-2 min-w-0 p-0 md:max-w-sm md:px-4 md:py-3.5">
                                                <p className="text-xs font-semibold text-ink">{item.indikator.kode}</p>
                                                <p className="mt-0.5 break-words text-xs leading-relaxed text-muted">{item.indikator.nama}</p>
                                            </TableCell>
                                            <TableCell className="col-span-2 min-w-0 p-0 md:px-4 md:py-3.5">
                                                <p className="break-words font-medium text-ink">{item.unit.nama}</p>
                                                <p className="mt-0.5 text-muted">PIC: {item.pic?.nama ?? '—'}</p>
                                            </TableCell>
                                            <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5 md:text-right">
                                                <span className="mr-2 text-muted md:hidden">Nilai</span>
                                                <span className="font-semibold tabular-nums text-ink">
                                                    {item.nilai === null
                                                        ? '—'
                                                        : `${formatNilai(item.nilai, item.desimal_tampilan)} ${item.satuan}`}
                                                </span>
                                                {item.status_perhitungan !== 'terhitung' && (
                                                    <p className="mt-0.5 text-[11px] text-muted">{statusPerhitungan[item.status_perhitungan]}</p>
                                                )}
                                            </TableCell>
                                            <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                                <div className="flex flex-col items-start gap-1">
                                                    <Badge status={item.status} />
                                                    {item.self_approval && (
                                                        <span className="text-[11px] font-medium text-info-dark">Persetujuan sendiri</span>
                                                    )}
                                                    {item.reviu_terlambat && (
                                                        <span className="text-[11px] font-medium text-warning-dark">Reviu terlambat</span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="col-span-2 min-w-0 p-0 md:px-4 md:py-3.5 md:text-right">
                                                {item.action && (
                                                    <div className="flex justify-end">
                                                        <Tooltip content={item.action.label} align="right">
                                                            <Link
                                                                href={item.action.href}
                                                                className={iconAction}
                                                                aria-label={`${item.action.label} ${item.indikator.kode}`}
                                                            >
                                                                <Eye className="h-4 w-4" aria-hidden="true" />
                                                            </Link>
                                                        </Tooltip>
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function KonteksItem({ icon: Icon, label, children }: { icon: typeof Target; label: string; children: ReactNode }) {
    return (
        <div className="flex min-w-0 items-start gap-3 p-4 sm:px-5">
            <span className="rounded-lg bg-primary/10 p-2 text-primary">
                <Icon className="h-4 w-4" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-medium text-muted">{label}</p>
                <div className="mt-1">{children}</div>
            </div>
        </div>
    );
}

function KartuRingkasan({
    icon: Icon,
    tone,
    label,
    value,
    desc,
}: {
    icon: typeof Target;
    tone: string;
    label: string;
    value: number;
    desc: string;
}) {
    return (
        <Card className="p-4">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium text-muted">{label}</p>
                <span className={`rounded-lg p-1.5 ${tone}`}>
                    <Icon className="h-4 w-4" aria-hidden="true" />
                </span>
            </div>
            <p className="mt-2 text-2xl font-bold tabular-nums text-ink">{value}</p>
            <p className="mt-1 text-xs text-muted">{desc}</p>
        </Card>
    );
}
