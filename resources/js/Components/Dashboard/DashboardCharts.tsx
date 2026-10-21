import { useState } from 'react';
import Chart from 'react-apexcharts/core';
import 'apexcharts/bar';
import 'apexcharts/donut';
import 'apexcharts/line';
import 'apexcharts/features/legend';
import type { ApexOptions } from 'apexcharts';
import { BarChart3, LineChart, PieChart } from 'lucide-react';
import { Card } from '@/Components/Card';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { STATUS_ALUR, persenDisahkan, warnaToken } from '@/Components/Dashboard/statusAlur';
import { STATUS_TAMPILAN } from '@/Components/Dashboard/statusTampilan';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { DashboardProps, DashboardStats, ProgresPeriode } from '@/types/dashboard';

// Kewajiban tanpa pengukuran ikut digambar agar batang progres sama dengan total kewajiban.
const SERI_PROGRES: { key: Exclude<keyof DashboardStats, 'total'>; label: string; token: string }[] = [
    { key: 'belum_mengisi', label: 'Belum mengisi', token: 'secondary' },
    ...STATUS_ALUR,
];

// Tabel data ringkas di dalam kartu grafik: header huruf biasa dan boleh terlipat agar delapan kolom muat tanpa scroll mulai 1280px.
const dataHead = 'px-3 normal-case tracking-normal whitespace-normal';

// Judul tooltip ApexCharts ditulis lewat innerHTML; nama periode adalah input pengguna.
const escapeHtml = (text: string) => text.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

const reduceMotion = () => typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const baseOptions = (muted: string): ApexOptions => ({
    chart: {
        fontFamily: 'Poppins, ui-sans-serif, system-ui, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        animations: { enabled: !reduceMotion() },
        parentHeightOffset: 0,
    },
    colors: SERI_PROGRES.map((status) => warnaToken(status.token)),
    dataLabels: { enabled: false },
    legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: muted },
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
    grafik: DashboardProps['grafik'];
}

/**
 * Grafik dashboard: progres status per periode jadwal (batang bertumpuk) dan komposisi status periode berjalan (donat).
 * Dimuat terpisah (React.lazy) agar pustaka grafik tidak membebani halaman lain. Grafik disembunyikan dari pembaca layar;
 * datanya tersedia sebagai tabel yang dapat dibuka di bawah grafik progres dan pada kartu ringkasan.
 */
export default function DashboardCharts({ stats, progres, periodeNama, tahun, grafik }: DashboardChartsProps) {
    const progresKosong = progres.every((item) => item.total === 0);
    const persen = persenDisahkan(stats.disahkan, stats.total);
    const muted = warnaToken('muted');
    const base = baseOptions(muted);

    const progresOptions: ApexOptions = {
        ...base,
        chart: { ...base.chart, stacked: true },
        plotOptions: {
            bar: { columnWidth: progres.length > 6 ? '60%' : '40%', borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' },
        },
        xaxis: {
            categories: progres.map((item) => item.nama),
            labels: { style: { colors: muted, fontSize: '12px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: { style: { colors: muted, fontSize: '12px' }, formatter: (value: number) => `${Math.round(value)}` },
        },
        grid: { borderColor: warnaToken('border'), strokeDashArray: 4, padding: { left: 4, right: 4 } },
        tooltip: { ...base.tooltip, shared: true, intersect: false, x: { formatter: (value) => escapeHtml(String(value)) } },
        // Layar sempit: nama periode dimiringkan agar tidak saling menimpa.
        responsive: [{ breakpoint: 640, options: { xaxis: { labels: { rotate: -45, rotateAlways: true } } } }],
    };

    const komposisiOptions: ApexOptions = {
        ...base,
        labels: SERI_PROGRES.map((status) => status.label),
        stroke: { width: 2, colors: [warnaToken('surface')] },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { fontSize: '12px', color: muted, offsetY: 20 },
                        value: { fontSize: '26px', fontWeight: 700, color: warnaToken('ink'), offsetY: -12 },
                        total: { show: true, showAlways: true, label: 'Disahkan', fontSize: '12px', color: muted, formatter: () => `${persen}%` },
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
                                series={SERI_PROGRES.map((status) => ({ name: status.label, data: progres.map((item) => item[status.key]) }))}
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
                                        {SERI_PROGRES.map((status) => (
                                            <TableHead key={status.key} className={`${dataHead} text-right`}>
                                                <span className="mr-1.5 inline-block h-2 w-2 rounded-full" style={{ backgroundColor: `var(--color-${status.token})` }} aria-hidden="true" />
                                                {status.label}
                                            </TableHead>
                                        ))}
                                        <TableHead className={`${dataHead} text-right`}>Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {progres.map((item) => (
                                        <TableRow key={item.periode_id}>
                                            <TableHead scope="row" className={`${dataHead} font-medium`}>
                                                {item.nama}
                                            </TableHead>
                                            {SERI_PROGRES.map((status) => (
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
                            <Chart type="donut" height={300} options={komposisiOptions} series={SERI_PROGRES.map((status) => stats[status.key])} />
                        </div>
                        <p className="sr-only">
                            {persen}% pengukuran {periodeNama} telah disahkan. Rincian per status tersedia pada kartu ringkasan.
                        </p>
                    </>
                )}
            </Card>

            <GrafikTargetRealisasi grafik={grafik} base={base} tahun={tahun} />
        </div>
    );
}

/**
 * Target periode vs realisasi disahkan untuk satu indikator lintas periode; satuan berbeda tidak digabung pada satu sumbu.
 * Periode tanpa target atau realisasi belum dapat diukur digambar kosong, bukan nol.
 */
function GrafikTargetRealisasi({ grafik, base, tahun }: { grafik: DashboardProps['grafik']; base: ApexOptions; tahun: number }) {
    const formatNilai = useFormatNilai();
    const [dipilih, setDipilih] = useState(grafik.indikator[0]?.id ?? '');
    const indikator = grafik.indikator.find((item) => item.id === dipilih) ?? grafik.indikator[0];
    const titik = indikator ? (grafik.seri[indikator.id] ?? []) : [];
    const angka = (nilai: string | null) => (nilai === null ? null : Number(nilai));
    const tampil = (nilai: string | null) => (nilai === null || !indikator ? '—' : `${formatNilai(nilai, indikator.desimal_tampilan)} ${indikator.satuan}`);
    const tanpaTarget = titik.filter((item) => item.tanpa_target).map((item) => item.nama);
    const muted = warnaToken('muted');

    const options: ApexOptions = {
        ...base,
        colors: [warnaToken('secondary'), warnaToken('primary')],
        stroke: { width: 3, curve: 'straight' },
        markers: { size: 4 },
        xaxis: {
            categories: titik.map((item) => item.nama),
            labels: { style: { colors: muted, fontSize: '12px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { style: { colors: muted, fontSize: '12px' } } },
        grid: { borderColor: warnaToken('border'), strokeDashArray: 4 },
        tooltip: {
            ...base.tooltip,
            shared: true,
            intersect: false,
            x: { formatter: (value) => escapeHtml(String(value)) },
            // Nilai tooltip juga ditulis lewat innerHTML dan memuat satuan indikator (input pengguna).
            y: { formatter: (value: number | null) => (value === null || value === undefined ? '—' : escapeHtml(tampil(String(value)))) },
        },
    };

    return (
        <Card className="lg:col-span-3">
            <div className="flex flex-wrap items-end justify-between gap-3 px-5 pt-5 pb-2">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                        <LineChart className="h-4 w-4" aria-hidden="true" />
                    </span>
                    <div className="min-w-0">
                        <h2 className="text-base font-semibold tracking-tight text-ink">Target vs realisasi</h2>
                        <p className="mt-0.5 text-xs text-muted">Target periode dan realisasi yang disahkan, Jadwal Tahunan {tahun}.</p>
                    </div>
                </div>
                {grafik.indikator.length > 0 && (
                    <div className="w-full sm:w-80">
                        <Select
                            label="Indikator"
                            name="grafik-indikator"
                            value={indikator?.id ?? ''}
                            options={grafik.indikator.map((item) => ({
                                value: item.id,
                                // Koreksi snapshot dapat memecah satu indikator menjadi beberapa seri beda satuan.
                                label: `${item.kode} — ${item.nama}${grafik.indikator.filter((lain) => lain.kode === item.kode).length > 1 ? ` (${item.satuan})` : ''}`,
                            }))}
                            onChange={(event) => setDipilih(event.target.value)}
                        />
                    </div>
                )}
            </div>
            {!indikator ? (
                <ChartEmpty message="Belum ada indikator pada jadwal ini." />
            ) : (
                <>
                    <div className="px-3 pb-2" aria-hidden="true">
                        <Chart
                            type="line"
                            height={300}
                            options={options}
                            series={[
                                { name: 'Target', data: titik.map((item) => (item.tanpa_target ? null : angka(item.target))) },
                                { name: 'Realisasi', data: titik.map((item) => angka(item.realisasi)) },
                            ]}
                        />
                    </div>
                    {tanpaTarget.length > 0 && <p className="px-5 pb-3 text-xs text-muted">Tanpa target periode: {tanpaTarget.join(', ')}.</p>}
                    <details className="border-t border-border">
                        <summary className="cursor-pointer px-5 py-3 text-xs font-semibold text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/30">
                            Lihat target dan realisasi dalam tabel
                        </summary>
                        <Table>
                            <caption className="sr-only">Target dan realisasi {indikator.kode} per periode</caption>
                            <TableHeader>
                                <TableRow>
                                    {['Periode', 'Target', 'Realisasi', 'Status'].map((label) => (
                                        <TableHead key={label} className={dataHead}>
                                            {label}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {titik.map((item) => (
                                    <TableRow key={item.periode_id}>
                                        <TableHead scope="row" className={`${dataHead} font-medium`}>
                                            {item.nama}
                                        </TableHead>
                                        <TableCell className="px-3 tabular-nums">{item.tanpa_target ? 'Tidak ditargetkan' : tampil(item.target)}</TableCell>
                                        <TableCell className="px-3 tabular-nums">{tampil(item.realisasi)}</TableCell>
                                        <TableCell className="px-3">{STATUS_TAMPILAN[item.status].label}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </details>
                </>
            )}
        </Card>
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
