import Chart from 'react-apexcharts/core';
import 'apexcharts/bar';
import 'apexcharts/donut';
import 'apexcharts/features/legend';
import type { ApexOptions } from 'apexcharts';
import { BarChart3, PieChart } from 'lucide-react';
import { Card } from '@/Components/Card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { STATUS_ALUR, persenDisahkan } from '@/Components/Dashboard/statusAlur';
import type { DashboardStats, ProgresPeriode } from '@/types/dashboard';

// Token @theme di app.css: ink, muted, border.
const INK = '#0F172A';
const MUTED = '#64748B';
const BORDER = '#E2E8F0';
// Tabel data ringkas di dalam kartu grafik: header huruf biasa agar tujuh kolom muat tanpa scroll di desktop.
const dataHead = 'px-3 normal-case tracking-normal';

// Judul tooltip ApexCharts ditulis lewat innerHTML; nama periode adalah input pengguna.
const escapeHtml = (text: string) => text.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

const reduceMotion = () => typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const baseOptions = (): ApexOptions => ({
    chart: {
        fontFamily: 'Poppins, ui-sans-serif, system-ui, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: !reduceMotion() },
        parentHeightOffset: 0,
    },
    colors: STATUS_ALUR.map((status) => status.color),
    dataLabels: { enabled: false },
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: MUTED },
        markers: { size: 5, shape: 'circle', offsetX: -2 },
        itemMargin: { horizontal: 8, vertical: 4 },
    },
    tooltip: { theme: 'light', y: { formatter: (value: number) => `${value} pengukuran` } },
    states: { active: { filter: { type: 'none' } } },
});

interface DashboardChartsProps {
    stats: DashboardStats;
    progres: ProgresPeriode[];
    periodeNama: string;
    tahun: number;
}

/**
 * Grafik dashboard: progres status per periode jadwal (batang bertumpuk) dan komposisi status periode berjalan (donat).
 * Dimuat terpisah (React.lazy) agar pustaka grafik tidak membebani halaman lain. Grafik disembunyikan dari pembaca layar;
 * datanya tersedia sebagai tabel yang dapat dibuka di bawah grafik progres dan pada kartu ringkasan.
 */
export default function DashboardCharts({ stats, progres, periodeNama, tahun }: DashboardChartsProps) {
    const progresKosong = progres.every((item) => item.total === 0);
    const persen = persenDisahkan(stats.disahkan, stats.total);
    const base = baseOptions();

    const progresOptions: ApexOptions = {
        ...base,
        chart: { ...base.chart, stacked: true },
        plotOptions: {
            bar: { columnWidth: progres.length > 6 ? '60%' : '40%', borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' },
        },
        xaxis: {
            categories: progres.map((item) => item.nama),
            labels: { style: { colors: MUTED, fontSize: '12px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: { style: { colors: MUTED, fontSize: '12px' }, formatter: (value: number) => `${Math.round(value)}` },
        },
        grid: { borderColor: BORDER, strokeDashArray: 4, padding: { left: 4, right: 4 } },
        tooltip: { ...base.tooltip, shared: true, intersect: false, x: { formatter: (value) => escapeHtml(String(value)) } },
        // Layar sempit: nama periode dimiringkan agar tidak saling menimpa.
        responsive: [{ breakpoint: 640, options: { xaxis: { labels: { rotate: -45, rotateAlways: true } } } }],
    };

    const komposisiOptions: ApexOptions = {
        ...base,
        labels: STATUS_ALUR.map((status) => status.label),
        stroke: { width: 2, colors: ['#FFFFFF'] },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { fontSize: '12px', color: MUTED, offsetY: 20 },
                        value: { fontSize: '26px', fontWeight: 700, color: INK, offsetY: -12 },
                        total: { show: true, showAlways: true, label: 'Disahkan', fontSize: '12px', color: MUTED, formatter: () => `${persen}%` },
                    },
                },
            },
        },
    };

    return (
        <div className="grid gap-5 lg:grid-cols-3">
            <Card className="lg:col-span-2">
                <ChartHeader
                    icon={BarChart3}
                    title="Progres per periode"
                    description={`Jumlah pengukuran per status, Jadwal Tahunan ${tahun}.`}
                />
                {progresKosong ? (
                    <ChartEmpty message="Belum ada pengukuran pada jadwal ini." />
                ) : (
                    <>
                        <div className="px-3 pb-3" aria-hidden="true">
                            <Chart
                                type="bar"
                                height={300}
                                options={progresOptions}
                                series={STATUS_ALUR.map((status) => ({ name: status.label, data: progres.map((item) => item[status.key]) }))}
                            />
                        </div>
                        {/* Alternatif grafik yang terlihat dan dapat dibuka dengan keyboard; tidak bergantung pada warna. */}
                        <details className="border-t border-border">
                            <summary className="cursor-pointer px-5 py-3 text-xs font-semibold text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/30">
                                Lihat data dalam tabel
                            </summary>
                            <Table>
                                <caption className="sr-only">Progres pengukuran per periode Jadwal Tahunan {tahun}</caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className={dataHead}>Periode</TableHead>
                                        {STATUS_ALUR.map((status) => (
                                            <TableHead key={status.key} className={`${dataHead} text-right`}>
                                                <span className="mr-1.5 inline-block h-2 w-2 rounded-full" style={{ backgroundColor: status.color }} aria-hidden="true" />
                                                {status.label}
                                            </TableHead>
                                        ))}
                                        <TableHead className={`${dataHead} text-right`}>Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {progres.map((item) => (
                                        <TableRow key={item.periode_id}>
                                            <TableHead scope="row" className="px-3 font-medium normal-case tracking-normal">
                                                {item.nama}
                                            </TableHead>
                                            {STATUS_ALUR.map((status) => (
                                                <TableCell key={status.key} className="px-3 text-right tabular-nums">
                                                    {item[status.key]}
                                                </TableCell>
                                            ))}
                                            <TableCell className="px-3 text-right font-semibold tabular-nums">{item.total}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </details>
                    </>
                )}
            </Card>

            <Card>
                <ChartHeader icon={PieChart} title="Komposisi status" description={`Sebaran status ${periodeNama}.`} />
                {stats.total === 0 ? (
                    <ChartEmpty message="Belum ada pengukuran pada periode ini." />
                ) : (
                    <>
                        <div className="px-3 pb-3" aria-hidden="true">
                            <Chart type="donut" height={300} options={komposisiOptions} series={STATUS_ALUR.map((status) => stats[status.key])} />
                        </div>
                        <p className="sr-only">
                            {persen}% pengukuran {periodeNama} telah disahkan. Rincian per status tersedia pada kartu ringkasan.
                        </p>
                    </>
                )}
            </Card>
        </div>
    );
}

function ChartHeader({ icon: Icon, title, description }: { icon: typeof BarChart3; title: string; description: string }) {
    return (
        <div className="flex items-start gap-3 px-5 pt-5 pb-2">
            <span className="rounded-lg bg-primary/10 p-2 text-primary">
                <Icon className="h-4 w-4" aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <h2 className="text-base font-semibold tracking-tight text-ink">{title}</h2>
                <p className="mt-0.5 text-xs text-muted">{description}</p>
            </div>
        </div>
    );
}

function ChartEmpty({ message }: { message: string }) {
    return (
        <div className="flex h-[300px] items-center justify-center px-5 pb-5">
            <p className="text-sm text-muted">{message}</p>
        </div>
    );
}
