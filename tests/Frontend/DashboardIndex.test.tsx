import type { ReactNode } from 'react';
import type { ApexOptions } from 'apexcharts';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, expect, it, onTestFinished, vi } from 'vitest';
import Index from '@/Pages/Dashboard/Index';
import type { DashboardProps } from '@/types/dashboard';

const can = { pengukuran: true, verifikasi: true };

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can }, pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));
// ApexCharts butuh API SVG yang tidak ada di jsdom; yang diuji di sini data dan opsi yang dikirim ke grafik.
const chartProps = vi.hoisted(() => new Map<string, { options: ApexOptions }>());
vi.mock('react-apexcharts/core', () => ({
    default: ({ type, series, options }: { type: string; series: unknown; options: ApexOptions }) => {
        chartProps.set(type, { options });
        return <div data-testid={`chart-${type}`} data-series={JSON.stringify(series)} />;
    },
}));
vi.mock('apexcharts/bar', () => ({}));
vi.mock('apexcharts/line', () => ({}));
vi.mock('apexcharts/donut', () => ({}));
vi.mock('apexcharts/features/legend', () => ({}));

afterEach(cleanup);

const zero = { total: 0, belum_mengisi: 0, draft: 0, diajukan: 0, diverifikasi: 0, dikembalikan: 0, disahkan: 0 };
const statsNol = { ...zero, tidak_mengisi: 0 };
const kegiatanKosong = { per_status: { rencana: 0, terlaksana: 0, tidak_terlaksana: 0, ditunda: 0, batal: 0 }, terlambat_jumlah: 0, per_iku: [], terlambat: [] };
const indikator = (kode: string) => ({ id: kode, kode, nama: `Indikator ${kode}`, satuan: '%', desimal_tampilan: 2, arah: 'naik_baik' as const });

const props = (overrides: Partial<DashboardProps> = {}): DashboardProps => ({
    tahun: 2026,
    filter: { renstra_id: 'r1', tahun: 2026, periode_id: 'p2', sasaran_id: null, unit_id: null },
    pilihan: {
        renstra: [{ id: 'r1', nama: 'Renstra LLDIKTI XVI 2025–2029' }],
        tahun: [2026],
        periode: [{ id: 'p1', nama: 'Triwulan I' }, { id: 'p2', nama: 'Triwulan II' }],
        sasaran: [],
        unit: [{ id: 'u1', nama: 'Bagian Umum' }],
    },
    activeRenstra: { id: 'r1', nama: 'Renstra LLDIKTI XVI 2025–2029', tahun_mulai: 2025, tahun_selesai: 2029 },
    activeJadwal: { tahun: 2026, status: 'aktif' },
    activePeriode: { id: 'p2', nama_periode: 'Triwulan II 2026', status: 'aktif' },
    stats: { ...statsNol, total: 200, diverifikasi: 1, disahkan: 199 },
    progresPeriode: [
        { periode_id: 'p1', nama: 'Triwulan I', dibuka: true, ...zero, total: 3, disahkan: 3 },
        { periode_id: 'p2', nama: 'Triwulan II', dibuka: true, ...zero, total: 200, diverifikasi: 1, disahkan: 199 },
    ],
    statusIndikator: [
        {
            indikator: indikator('IK-01'), unit: { nama: 'Bagian Umum' }, pic: { nama: 'Ani' }, status: 'tanpa_target_periode', status_alur: 'disahkan',
            self_approval: true, reviu_terlambat: true, realisasi: '65.5', status_perhitungan: 'terhitung', target: null, tanpa_target_periode: true, target_pk: '70', selisih: '-4.5',
            action: { href: '/pengukuran/u1', label: 'Lihat pengukuran' },
        },
        {
            indikator: indikator('IK-02'), unit: { nama: 'Bagian Umum' }, pic: null, status: 'belum_dapat_diukur', status_alur: 'disahkan',
            self_approval: false, reviu_terlambat: false, realisasi: null, status_perhitungan: 'belum_dapat_diukur', target: '80', tanpa_target_periode: false, target_pk: '90', selisih: null, action: null,
        },
        {
            indikator: indikator('IK-03'), unit: { nama: 'Bagian Kelembagaan' }, pic: null, status: 'dalam_proses', status_alur: 'diverifikasi',
            self_approval: false, reviu_terlambat: false, realisasi: null, status_perhitungan: null, target: '50', tanpa_target_periode: false, target_pk: '60', selisih: null, action: null,
        },
    ],
    grafik: {
        indikator: [
            { id: 'IK-01', kode: 'IK-01', nama: 'Indikator IK-01', satuan: '%', desimal_tampilan: 2 },
            { id: 'IK-02', kode: 'IK-02', nama: 'Indikator IK-02', satuan: '%', desimal_tampilan: 2 },
        ],
        seri: {
            'IK-01': [
                { periode_id: 'p1', nama: 'Triwulan I', target: null, tanpa_target: true, realisasi: '40', status: 'tanpa_target_periode' },
                { periode_id: 'p2', nama: 'Triwulan II', target: null, tanpa_target: true, realisasi: '65.5', status: 'tanpa_target_periode' },
            ],
            'IK-02': [
                { periode_id: 'p1', nama: 'Triwulan I', target: '70', tanpa_target: false, realisasi: '72', status: 'belum_ditetapkan' },
                { periode_id: 'p2', nama: 'Triwulan II', target: '80', tanpa_target: false, realisasi: null, status: 'belum_dapat_diukur' },
            ],
        },
    },
    progresRencanaAksi: { belum_disusun: 0, draft: 0, diajukan: 0, diverifikasi: 0, dikembalikan: 0, disahkan: 0 },
    kegiatan: kegiatanKosong,
    ...overrides,
});

const barisTabel = (kode: string) => within(screen.getByRole('table', { name: /Status indikator Triwulan II 2026/ })).getByText(kode).closest('tr') as HTMLElement;

it('menampilkan ringkasan, grafik, dan tabel status tanpa header dan kartu konteks', async () => {
    render(<Index {...props()} />);

    expect(screen.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeTruthy();
    expect(screen.queryByText('Renstra aktif')).toBeNull();
    expect(screen.queryByText('Jendela pengisian')).toBeNull();
    expect(screen.queryByRole('link', { name: /Antrean verifikasi/ })).toBeNull();
    // 199/200 tidak boleh dibulatkan menjadi 100%.
    expect(screen.getByText('99% dari total')).toBeTruthy();
    expect(screen.getByRole('link', { name: /Lihat semua/ }).getAttribute('href')).toBe('/pengukuran');

    const bar = await screen.findByTestId('chart-bar');
    expect(JSON.parse(bar.dataset.series ?? '[]')).toContainEqual({ name: 'Disahkan', data: [3, 199] });
    expect(JSON.parse(screen.getByTestId('chart-donut').dataset.series ?? '[]')).toEqual([0, 0, 0, 1, 0, 199]);
    // Data grafik tersedia sebagai tabel yang terlihat setelah dibuka, tanpa bergantung pada warna atau pointer.
    const toggle = screen.getByText('Lihat data dalam tabel');
    fireEvent.click(toggle);
    expect(toggle.closest('details')?.open).toBe(true);
    const progres = screen.getByRole('table', { name: /Progres pengukuran per periode/ });
    expect(within(progres).getByRole('row', { name: /Triwulan II 0 0 0 1 0 199 200/ })).toBeTruthy();

    expect(within(barisTabel('IK-01')).getByRole('link', { name: 'Lihat pengukuran IK-01' }).getAttribute('href')).toBe('/pengukuran/u1');
    expect(within(barisTabel('IK-03')).getByText('Diverifikasi')).toBeTruthy();
});

it('menampilkan filter dan ringkasan status alur tanpa kartu status capaian (Q66)', () => {
    const tidakBerlaku = { ...props().statusIndikator[2], indikator: indikator('IK-04'), status: 'tidak_berlaku' as const, status_alur: null };
    render(<Index {...props({ statusIndikator: [...props().statusIndikator, tidakBerlaku] })} />);

    for (const label of ['Renstra', 'Tahun', 'Periode', 'Sasaran', 'Unit']) expect(screen.getByLabelText(label)).toBeTruthy();
    expect(screen.queryByRole('region', { name: /Status capaian/ })).toBeNull();
    expect(screen.queryByText(/indikator belum berlaku/)).toBeNull();
    // Kontrol positif: ringkasan status alur dan status tampilan per baris tetap tampil.
    const ringkasan = screen.getByRole('region', { name: /Ringkasan status pengukuran Triwulan II 2026/ });
    for (const label of ['Total', 'Belum mengisi', 'Disahkan']) expect(within(ringkasan).getByText(label)).toBeTruthy();
    expect(within(barisTabel('IK-04')).getByText('Tidak berlaku')).toBeTruthy();
    expect(within(barisTabel('IK-01')).getByText('Tanpa target periode')).toBeTruthy();
});

it('kartu Draft menyebut jumlah tidak mengisi bila ada (Q66)', () => {
    const { rerender } = render(<Index {...props({ stats: { ...statsNol, total: 5, draft: 3, tidak_mengisi: 2 } })} />);
    const ringkasan = () => screen.getByRole('region', { name: /Ringkasan status pengukuran/ });

    const keterangan = within(ringkasan()).getByText('2 tidak mengisi (melewati batas pengisian)');
    expect(keterangan.parentElement?.textContent).toContain('Draft');

    // Kontrol positif: tanpa tidak mengisi, keterangan Draft kembali "Belum diajukan".
    rerender(<Index {...props({ stats: { ...statsNol, total: 5, draft: 3 } })} />);
    expect(within(ringkasan()).queryByText(/tidak mengisi/)).toBeNull();
    expect(within(ringkasan()).getByText('Belum diajukan')).toBeTruthy();
});

it('baris tanpa target periode menampilkan realisasi, target PK, dan selisih', () => {
    render(<Index {...props()} />);
    const row = barisTabel('IK-01');

    expect(within(row).getByText('Tanpa target periode')).toBeTruthy();
    expect(within(row).getByText('65,50 %')).toBeTruthy();
    expect(within(row).getByText('70,00 %')).toBeTruthy();
    expect(within(row).getByText('−4,50 %')).toBeTruthy();
    expect(within(row).getByText('di bawah target')).toBeTruthy();
});

it('baris belum dapat diukur tidak menampilkan realisasi nol', () => {
    render(<Index {...props()} />);
    const row = barisTabel('IK-02');

    expect(within(row).getAllByText('Belum dapat diukur').length).toBeGreaterThan(0);
    expect(within(row).queryByText('0,00 %')).toBeNull();
    expect(within(row).getByText('90,00 %')).toBeTruthy();
});

it('tabel status memakai pola kartu di HP dan tidak memakai teks di bawah 12px', () => {
    const { container } = render(<Index {...props()} />);

    // Delapan kolom baru muat sebagai tabel mulai xl; di bawahnya kolom kanan terpotong oleh kartu.
    expect(screen.getByRole('table', { name: /Status indikator/ }).className).toContain('block xl:table');
    expect(container.querySelector('[class*="text-[11px]"]')).toBeNull();
    expect(container.querySelector('[class*="text-[10px]"]')).toBeNull();
});

it('label status dan header data grafik boleh terlipat dan grid kartu tidak memaksa tujuh kolom di layar menengah', async () => {
    render(<Index {...props()} />);
    await screen.findByTestId('chart-line');

    const badgeBaris = within(barisTabel('IK-02')).getAllByText(/^Belum dapat diukur$/i);
    expect(badgeBaris).toHaveLength(2);
    badgeBaris.forEach((badge) => expect(badge.className).not.toContain('whitespace-nowrap'));
    const grid = screen.getByRole('region', { name: /Ringkasan status pengukuran/ }).querySelector('.grid');
    expect([grid?.classList.contains('xl:grid-cols-7'), grid?.classList.contains('2xl:grid-cols-7')]).toEqual([false, true]);
    const progres = screen.getByRole('table', { name: /Progres pengukuran per periode/ });
    within(progres).getAllByRole('columnheader').forEach((th) => expect(th.className).toContain('whitespace-normal'));
});

it('menampilkan keadaan kosong pada grafik dan tabel periode terakhir', async () => {
    render(
        <Index
            {...props({
                activePeriode: { ...props().activePeriode!, status: 'terakhir' },
                stats: statsNol,
                progresPeriode: [{ periode_id: 'p2', nama: 'Triwulan II', dibuka: true, ...zero }],
                statusIndikator: [],
            })}
        />,
    );

    expect(await screen.findByText('Belum ada pengukuran pada jadwal ini.')).toBeTruthy();
    expect(screen.getByText('Belum ada pengukuran pada periode ini.')).toBeTruthy();
    expect(screen.queryByTestId('chart-bar')).toBeNull();
    expect(screen.getByText('Belum ada indikator pada periode ini')).toBeTruthy();
});

it('menampilkan satu pemberitahuan dengan alasan bila belum ada periode', () => {
    render(<Index {...props({ activeJadwal: null, activePeriode: null, stats: statsNol, progresPeriode: [], statusIndikator: [] })} />);

    expect(screen.getByRole('status').textContent).toContain('Belum ada Jadwal Tahunan 2026 untuk Renstra aktif.');
    expect(screen.queryByText('Total')).toBeNull();
    expect(screen.queryByRole('table')).toBeNull();
});

it('meng-escape nama periode pada judul tooltip grafik progres tanpa mengubah label sumbu', async () => {
    const payload = '<img src=x onerror=alert(1)>';
    render(<Index {...props({ progresPeriode: [{ periode_id: 'p1', nama: payload, dibuka: true, ...zero, total: 1, disahkan: 1 }] })} />);
    await screen.findByTestId('chart-bar');

    // ApexCharts menulis judul tooltip lewat innerHTML; hasil formatter harus berupa teks, bukan markup.
    const options = chartProps.get('bar')!.options;
    const format = options.tooltip?.x?.formatter;
    expect(format).toBeTypeOf('function');
    const title = format!(payload);
    expect(title).not.toMatch(/[<>]/);
    expect(title).toBe('&#60;img src=x onerror=alert(1)&#62;');
    // Label sumbu dirender sebagai text node, jadi kategori tetap nama asli.
    expect(options.xaxis?.categories).toEqual([payload]);
});

it('mengambil warna seri grafik dari token design system', async () => {
    const root = document.documentElement;
    for (const token of ['secondary', 'muted', 'warning', 'info', 'danger', 'success']) root.style.setProperty(`--color-${token}`, `token-${token}`);
    onTestFinished(() => root.removeAttribute('style'));
    render(<Index {...props()} />);
    await screen.findByTestId('chart-bar');

    // Urutan seri: Belum mengisi, lalu STATUS_ALUR (Draft, Diajukan, Diverifikasi, Dikembalikan, Disahkan).
    expect(chartProps.get('bar')!.options.colors).toEqual(['token-secondary', 'token-muted', 'token-warning', 'token-info', 'token-danger', 'token-success']);
});

it('grafik target vs realisasi tidak menggambar nol untuk tanpa target atau belum dapat diukur', async () => {
    render(<Index {...props()} />);
    const seri = async () => JSON.parse((await screen.findByTestId('chart-line')).dataset.series ?? '[]');

    expect(await seri()).toEqual([
        { name: 'Target', data: [null, null] },
        { name: 'Realisasi', data: [40, 65.5] },
    ]);
    expect(screen.getByText(/Tanpa target periode: Triwulan I, Triwulan II/)).toBeTruthy();

    fireEvent.change(screen.getByLabelText('Indikator'), { target: { value: 'IK-02' } });
    expect(await seri()).toEqual([
        { name: 'Target', data: [70, 80] },
        { name: 'Realisasi', data: [72, null] },
    ]);
});

it('panel rencana aksi dan kegiatan berdampingan sama lebar, tabel per IKU hanya tampil bila ada kegiatan (Q66)', () => {
    render(<Index {...props()} />);

    const ra = screen.getByRole('region', { name: /Progres Rencana Aksi 2026/ });
    const kegiatan = screen.getByRole('region', { name: /Status kegiatan Triwulan II 2026/ });
    expect(ra.parentElement).toBe(kegiatan.parentElement);
    expect(ra.parentElement?.className).toContain('lg:grid-cols-2');
    expect(screen.queryByRole('region', { name: /Kegiatan per IKU/ })).toBeNull();
});

it('tabel kegiatan per IKU tampil pada kartu tersendiri di bawah kedua panel (Q66)', () => {
    const jumlah = { rencana: 1, terlaksana: 0, tidak_terlaksana: 0, ditunda: 0, batal: 0 };
    const kegiatan = { per_status: jumlah, terlambat_jumlah: 0, per_iku: [{ id: 'i1', kode: 'IKU 1.1', nama: 'Persentase PT terakreditasi', jumlah }], terlambat: [] };
    render(<Index {...props({ kegiatan })} />);

    const panel = screen.getByRole('region', { name: /Status kegiatan Triwulan II 2026/ });
    const iku = screen.getByRole('region', { name: /Kegiatan per IKU Triwulan II 2026/ });
    expect(panel.contains(iku)).toBe(false);
    expect(iku.parentElement).not.toBe(panel.parentElement);
    expect(within(iku).getByText('IKU 1.1')).toBeTruthy();
});

it('grafik yang disembunyikan dari pembaca layar tidak membuat elemen yang dapat difokus keyboard', async () => {
    render(<Index {...props()} />);
    await screen.findByTestId('chart-line');

    // Bawaan ApexCharts memberi SVG dan legenda tabindex 0 walau pembungkusnya aria-hidden; datanya tersedia di tabel.
    for (const type of ['bar', 'donut', 'line']) expect(chartProps.get(type)?.options.chart?.accessibility?.enabled, type).toBe(false);
});

it('label sumbu Y grafik target vs realisasi memakai format angka aplikasi dan desimal tampilan indikator', async () => {
    render(<Index {...props()} />);
    await screen.findByTestId('chart-line');

    // Bawaan pustaka memakai titik desimal dan jumlah desimal dari data, berbeda dari tabel dan tooltip.
    const yaxis = chartProps.get('line')!.options.yaxis as { labels?: { formatter?: (value: number) => string } };
    expect(yaxis.labels?.formatter?.(62.5)).toBe('62,50');
    expect(yaxis.labels?.formatter?.(66.66666666666667)).toBe('66,67');
});

it('meng-escape satuan indikator pada nilai tooltip grafik target vs realisasi', async () => {
    const payload = '<img src=x onerror=alert(1)>';
    const base = props();
    render(<Index {...props({ grafik: { ...base.grafik, indikator: base.grafik.indikator.map((item) => ({ ...item, satuan: payload })) } })} />);
    await screen.findByTestId('chart-line');

    // ApexCharts menulis nilai tooltip lewat innerHTML; satuan adalah input pengguna.
    const format = chartProps.get('line')!.options.tooltip?.y as { formatter: (value: number) => string };
    expect(format.formatter(40)).not.toMatch(/[<>]/);
});

it('menandai persetujuan sendiri dan reviu terlambat pada baris status', () => {
    render(<Index {...props()} />);

    expect(within(barisTabel('IK-01')).getByText('Persetujuan sendiri')).toBeTruthy();
    expect(within(barisTabel('IK-01')).getByText('Reviu terlambat')).toBeTruthy();
    expect(within(barisTabel('IK-02')).queryByText('Persetujuan sendiri')).toBeNull();
    expect(within(barisTabel('IK-02')).queryByText('Reviu terlambat')).toBeNull();
});

it('panel progres rencana aksi tetap tampil sebelum jendela pengisian pertama dibuka', () => {
    render(<Index {...props({ activePeriode: null, stats: statsNol, progresPeriode: [], statusIndikator: [] })} />);

    expect(screen.getByRole('status').textContent).toContain('Jendela pengisian pertama Jadwal Tahunan 2026 belum dimulai.');
    // Grid yang sama dengan saat periode dibuka, agar panel tetap selebar setengah layar.
    expect(screen.getByRole('region', { name: /Progres Rencana Aksi 2026/ }).parentElement?.className).toContain('lg:grid-cols-2');
    expect(screen.queryByRole('region', { name: /Status kegiatan/ })).toBeNull();
});

it('pilihan indikator grafik menyebut satuan bila satu indikator memiliki lebih dari satu seri', async () => {
    const titik = (periode: string, realisasi: string) => ({ periode_id: periode, nama: periode, target: null, tanpa_target: false, realisasi, status: 'belum_ditetapkan' as const });
    render(
        <Index
            {...props({
                grafik: {
                    indikator: [
                        { id: 'IK-01:poin:2', kode: 'IK-01', nama: 'Indikator IK-01', satuan: 'poin', desimal_tampilan: 2 },
                        { id: 'IK-01:%:1', kode: 'IK-01', nama: 'Indikator IK-01', satuan: '%', desimal_tampilan: 1 },
                    ],
                    seri: { 'IK-01:poin:2': [titik('p1', '40')], 'IK-01:%:1': [titik('p2', '65.5')] },
                },
            })}
        />,
    );
    await screen.findByTestId('chart-line');

    const pilihan = within(screen.getByLabelText('Indikator')).getAllByRole('option').map((o) => o.textContent);
    expect(pilihan).toEqual(['IK-01 — Indikator IK-01 (poin)', 'IK-01 — Indikator IK-01 (%)']);
    fireEvent.change(screen.getByLabelText('Indikator'), { target: { value: 'IK-01:%:1' } });
    expect(JSON.parse((await screen.findByTestId('chart-line')).dataset.series ?? '[]')[1]).toEqual({ name: 'Realisasi', data: [65.5] });
});

it('keterangan tabel status menyebut realisasi dan selisih dari pengukuran disahkan, target PK dari snapshot jadwal', () => {
    render(<Index {...props()} />);

    expect(screen.getByText(/Realisasi dan selisih hanya dari pengukuran yang disahkan; target PK dari snapshot jadwal\./)).toBeTruthy();
});
