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
vi.mock('apexcharts/donut', () => ({}));
vi.mock('apexcharts/features/legend', () => ({}));

afterEach(cleanup);

const zero = { total: 0, belum_mengisi: 0, draft: 0, diajukan: 0, diverifikasi: 0, dikembalikan: 0, disahkan: 0 };
const ringkasanKosong = {
    belum_dibuka: 0, tidak_berlaku: 0, belum_mengisi: 0, tidak_mengisi: 0, dalam_proses: 0,
    belum_dapat_diukur: 0, tanpa_target_periode: 0, tidak_dapat_dihitung: 0, belum_ditetapkan: 0,
};
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
    stats: { ...zero, total: 200, diverifikasi: 1, disahkan: 199 },
    progresPeriode: [
        { periode_id: 'p1', nama: 'Triwulan I', dibuka: true, ...zero, total: 3, disahkan: 3 },
        { periode_id: 'p2', nama: 'Triwulan II', dibuka: true, ...zero, total: 200, diverifikasi: 1, disahkan: 199 },
    ],
    ringkasanStatus: { ...ringkasanKosong, tanpa_target_periode: 4, belum_dapat_diukur: 2, dalam_proses: 7, tidak_berlaku: 1 },
    statusIndikator: [
        {
            indikator: indikator('IK-01'), unit: { nama: 'Bagian Umum' }, pic: { nama: 'Ani' }, status: 'tanpa_target_periode', status_alur: 'disahkan',
            realisasi: '65.5', status_perhitungan: 'terhitung', target: null, tanpa_target_periode: true, target_pk: '70', selisih: '-4.5',
            action: { href: '/pengukuran/u1', label: 'Lihat pengukuran' },
        },
        {
            indikator: indikator('IK-02'), unit: { nama: 'Bagian Umum' }, pic: null, status: 'belum_dapat_diukur', status_alur: 'disahkan',
            realisasi: null, status_perhitungan: 'belum_dapat_diukur', target: '80', tanpa_target_periode: false, target_pk: '90', selisih: null, action: null,
        },
        {
            indikator: indikator('IK-03'), unit: { nama: 'Bagian Kelembagaan' }, pic: null, status: 'dalam_proses', status_alur: 'diverifikasi',
            realisasi: null, status_perhitungan: null, target: '50', tanpa_target_periode: false, target_pk: '60', selisih: null, action: null,
        },
    ],
    grafik: { indikator: [], seri: {} },
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
    expect(JSON.parse(screen.getByTestId('chart-donut').dataset.series ?? '[]')).toEqual([0, 0, 1, 0, 199]);
    // Data grafik tersedia sebagai tabel yang terlihat setelah dibuka, tanpa bergantung pada warna atau pointer.
    const toggle = screen.getByText('Lihat data dalam tabel');
    fireEvent.click(toggle);
    expect(toggle.closest('details')?.open).toBe(true);
    const progres = screen.getByRole('table', { name: /Progres pengukuran per periode/ });
    expect(within(progres).getByRole('row', { name: /Triwulan II 0 0 1 0 199 200/ })).toBeTruthy();

    expect(within(barisTabel('IK-01')).getByRole('link', { name: 'Lihat pengukuran IK-01' }).getAttribute('href')).toBe('/pengukuran/u1');
    expect(within(barisTabel('IK-03')).getByText('Diverifikasi')).toBeTruthy();
});

it('menampilkan filter dan ringkasan status capaian periode terpilih', () => {
    render(<Index {...props()} />);

    for (const label of ['Renstra', 'Tahun', 'Periode', 'Sasaran', 'Unit']) expect(screen.getByLabelText(label)).toBeTruthy();
    const ringkasan = screen.getByRole('region', { name: /Status capaian Triwulan II 2026/ });
    expect(within(ringkasan).getByText('Tanpa target periode').closest('div[data-status]')?.textContent).toContain('4');
    expect(within(ringkasan).getByText('Belum dapat diukur').closest('div[data-status]')?.textContent).toContain('2');
    expect(within(ringkasan).getByText(/1 indikator belum berlaku/)).toBeTruthy();
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

    expect(screen.getByRole('table', { name: /Status indikator/ }).className).toContain('block md:table');
    expect(container.querySelector('[class*="text-[11px]"]')).toBeNull();
    expect(container.querySelector('[class*="text-[10px]"]')).toBeNull();
});

it('menampilkan keadaan kosong pada grafik dan tabel periode terakhir', async () => {
    render(
        <Index
            {...props({
                activePeriode: { ...props().activePeriode!, status: 'terakhir' },
                stats: zero,
                progresPeriode: [{ periode_id: 'p2', nama: 'Triwulan II', dibuka: true, ...zero }],
                ringkasanStatus: ringkasanKosong,
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
    render(<Index {...props({ activeJadwal: null, activePeriode: null, stats: zero, progresPeriode: [], statusIndikator: [] })} />);

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
    for (const token of ['muted', 'warning', 'info', 'danger', 'success']) root.style.setProperty(`--color-${token}`, `token-${token}`);
    onTestFinished(() => root.removeAttribute('style'));
    render(<Index {...props()} />);
    await screen.findByTestId('chart-bar');

    // Urutan seri mengikuti STATUS_ALUR: Draft, Diajukan, Diverifikasi, Dikembalikan, Disahkan.
    expect(chartProps.get('bar')!.options.colors).toEqual(['token-muted', 'token-warning', 'token-info', 'token-danger', 'token-success']);
});
