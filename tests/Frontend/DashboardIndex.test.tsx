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

const zero = { total: 0, draft: 0, diajukan: 0, diverifikasi: 0, dikembalikan: 0, disahkan: 0 };

const props = (overrides: Partial<DashboardProps> = {}): DashboardProps => ({
    tahun: 2026,
    activeRenstra: { id: 'r1', nama: 'Renstra LLDIKTI XVI 2025–2029', tahun_mulai: 2025, tahun_selesai: 2029 },
    activeJadwal: { tahun: 2026, status: 'aktif' },
    activePeriode: { id: 'p2', nama_periode: 'Triwulan II 2026', status: 'aktif' },
    stats: { total: 200, draft: 0, diajukan: 0, diverifikasi: 1, dikembalikan: 0, disahkan: 199 },
    progresPeriode: [
        { periode_id: 'p1', nama: 'Triwulan I', ...zero, total: 3, disahkan: 3 },
        { periode_id: 'p2', nama: 'Triwulan II', ...zero, total: 200, diverifikasi: 1, disahkan: 199 },
    ],
    pengukurans: [
        {
            id: 'u1',
            status: 'diverifikasi',
            self_approval: false,
            reviu_terlambat: true,
            nilai: null,
            status_perhitungan: 'tidak_dapat_dihitung',
            satuan: '%',
            desimal_tampilan: 2,
            indikator: { kode: 'IK-01', nama: 'Persentase PT terakreditasi' },
            unit: { nama: 'Bagian Umum' },
            pic: null,
            action: { href: '/pengukuran/u1', label: 'Lihat pengukuran' },
        },
    ],
    ...overrides,
});

it('menampilkan ringkasan, grafik, dan pengukuran terbaru tanpa header dan kartu konteks', async () => {
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

    const terbaru = screen.getByRole('table', { name: /Pengukuran terbaru/ });
    expect(within(terbaru).getByText('Tidak dapat dihitung')).toBeTruthy();
    expect(within(terbaru).getByText('Reviu terlambat')).toBeTruthy();
    expect(within(terbaru).getByRole('link', { name: 'Lihat pengukuran IK-01' }).getAttribute('href')).toBe('/pengukuran/u1');
});

it('menampilkan keadaan kosong pada grafik dan tabel periode terakhir', async () => {
    render(
        <Index
            {...props({
                activePeriode: { ...props().activePeriode!, status: 'terakhir' },
                stats: zero,
                progresPeriode: [{ periode_id: 'p2', nama: 'Triwulan II', ...zero }],
                pengukurans: [],
            })}
        />,
    );

    expect(await screen.findByText('Belum ada pengukuran pada jadwal ini.')).toBeTruthy();
    expect(screen.getByText('Belum ada pengukuran pada periode ini.')).toBeTruthy();
    expect(screen.queryByTestId('chart-bar')).toBeNull();
    expect(screen.getByText('Belum ada pengukuran')).toBeTruthy();
});

it('menampilkan satu pemberitahuan dengan alasan bila belum ada periode', () => {
    render(<Index {...props({ activeJadwal: null, activePeriode: null, stats: zero, progresPeriode: [], pengukurans: [] })} />);

    expect(screen.getByRole('status').textContent).toContain('Belum ada Jadwal Tahunan 2026 untuk Renstra aktif.');
    expect(screen.queryByText('Total')).toBeNull();
    expect(screen.queryByRole('table')).toBeNull();
});

it('meng-escape nama periode pada judul tooltip grafik progres tanpa mengubah label sumbu', async () => {
    const payload = '<img src=x onerror=alert(1)>';
    render(<Index {...props({ progresPeriode: [{ periode_id: 'p1', nama: payload, ...zero, total: 1, disahkan: 1 }] })} />);
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
