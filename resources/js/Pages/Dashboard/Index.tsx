import { lazy, Suspense } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BarChart2, Check, FileText, Info, RotateCcw, Send, ShieldCheck, UserX } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { DashboardFilter } from '@/Components/Dashboard/DashboardFilter';
import { STATUS_ALUR, persenDisahkan } from '@/Components/Dashboard/statusAlur';
import { STATUS_DIHITUNG, STATUS_TAMPILAN } from '@/Components/Dashboard/statusTampilan';
import { TabelStatusIndikator } from '@/Components/Dashboard/TabelStatusIndikator';
import type { DashboardProps, StatusAlur } from '@/types/dashboard';
import type { SharedPageProps } from '@/types/auth';

const DashboardCharts = lazy(() => import('@/Components/Dashboard/DashboardCharts'));

const outlineLink =
    'inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-all duration-150 hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25 focus:ring-offset-1';

const kartuStatus: Record<StatusAlur, { icon: typeof FileText; tone: string; desc: string }> = {
    draft: { icon: FileText, tone: 'bg-soft text-muted', desc: 'Belum diajukan' },
    diajukan: { icon: Send, tone: 'bg-warning/15 text-warning-dark', desc: 'Menunggu verifikasi' },
    diverifikasi: { icon: ShieldCheck, tone: 'bg-info/15 text-info-dark', desc: 'Menunggu pengesahan' },
    dikembalikan: { icon: RotateCcw, tone: 'bg-danger/15 text-danger-dark', desc: 'Perlu perbaikan' },
    disahkan: { icon: Check, tone: 'bg-success/15 text-success-dark', desc: '' },
};

export default function DashboardIndex({
    tahun,
    filter,
    pilihan,
    activeRenstra,
    activeJadwal,
    activePeriode,
    stats,
    progresPeriode,
    ringkasanStatus,
    statusIndikator,
}: DashboardProps) {
    const { auth, errors } = usePage<SharedPageProps & { errors?: Record<string, string> }>().props;
    const persen = persenDisahkan(stats.disahkan, stats.total);

    const alasanKosong = !activeRenstra
        ? `Belum ada Renstra aktif yang mencakup tahun ${tahun}.`
        : !activeJadwal
          ? `Belum ada Jadwal Tahunan ${tahun} untuk Renstra aktif.`
          : `Jendela pengisian pertama Jadwal Tahunan ${tahun} belum dimulai.`;

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            {/* Tanpa judul tampak; heading tetap ada untuk navigasi pembaca layar. */}
            <h1 className="sr-only">Dashboard</h1>

            <div className="space-y-5">
                {pilihan.renstra.length > 0 && (
                    <Card className="p-4">
                        <DashboardFilter filter={filter} pilihan={pilihan} errors={errors} />
                    </Card>
                )}

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
                        <section aria-labelledby="status-capaian" className="space-y-3">
                            <div>
                                <h2 id="status-capaian" className="text-base font-semibold tracking-tight text-ink">
                                    Status capaian {activePeriode.nama_periode}
                                </h2>
                                <p className="mt-0.5 text-xs text-muted">
                                    Angka resmi hanya dari pengukuran yang disahkan. Status Tercapai/Belum tercapai tampil setelah status capaian
                                    ditetapkan.
                                </p>
                            </div>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                                {STATUS_DIHITUNG.map((key) => (
                                    <Card key={key} className="p-4">
                                        <div data-status={key}>
                                            <Badge variant={STATUS_TAMPILAN[key].variant}>
                                                {STATUS_TAMPILAN[key].label}
                                            </Badge>
                                            <p className="mt-2 text-2xl font-bold tabular-nums text-ink">{ringkasanStatus[key]}</p>
                                        </div>
                                    </Card>
                                ))}
                            </div>
                            {ringkasanStatus.tidak_berlaku > 0 && (
                                <p className="text-xs text-muted">
                                    {ringkasanStatus.tidak_berlaku} indikator belum berlaku pada periode ini dan tidak dihitung sebagai kewajiban.
                                </p>
                            )}
                        </section>

                        <section aria-labelledby="ringkasan-status" className="space-y-3">
                            <h2 id="ringkasan-status" className="sr-only">
                                Ringkasan status pengukuran {activePeriode.nama_periode}
                            </h2>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                                <KartuRingkasan
                                    icon={BarChart2}
                                    tone="bg-primary/10 text-primary"
                                    label="Total"
                                    value={stats.total}
                                    desc={activePeriode.nama_periode}
                                />
                                <KartuRingkasan icon={UserX} tone="bg-soft text-muted" label="Belum mengisi" value={stats.belum_mengisi} desc="Belum ada pengukuran" />
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
                                    <h2 className="text-base font-semibold tracking-tight text-ink">Status per indikator</h2>
                                    <p className="mt-0.5 text-xs text-muted">
                                        {activePeriode.nama_periode}. Realisasi, target PK, dan selisih hanya dari pengukuran yang disahkan.
                                    </p>
                                </div>
                                {auth.can.pengukuran && (
                                    <Link href="/pengukuran" className={outlineLink}>
                                        Lihat semua
                                        <ArrowRight className="h-4 w-4" aria-hidden="true" />
                                    </Link>
                                )}
                            </div>
                            <TabelStatusIndikator rows={statusIndikator} periodeNama={activePeriode.nama_periode} />
                        </Card>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function KartuRingkasan({
    icon: Icon,
    tone,
    label,
    value,
    desc,
}: {
    icon: typeof FileText;
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
