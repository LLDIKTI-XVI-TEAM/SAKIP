import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
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
// ApexCharts butuh API SVG yang tidak ada di jsdom; yang diuji di sini data yang dikirim ke grafik.
vi.mock('react-apexcharts/core', () => ({
    default: ({ type, series }: { type: string; series: unknown }) => <div data-testid={`chart-${type}`} data-series={JSON.stringify(series)} />,
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
    activePeriode: {
        id: 'p2',
        nama_periode: 'Triwulan II 2026',
        status: 'aktif',
        pengisian_mulai: '2026-04-01',
        pengisian_selesai: '2026-06-01',
        sisa_hari: 5,
    },
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

it('menampilkan konteks, ringkasan, grafik, dan pengukuran terbaru periode berjalan', async () => {
    render(<Index {...props()} />);

    expect(screen.getByText('Renstra LLDIKTI XVI 2025–2029')).toBeTruthy();
    expect(screen.getByText('Berjalan')).toBeTruthy();
    expect(screen.getByText('01 April 2026 – 01 Juni 2026')).toBeTruthy();
    expect(screen.getByText('Sisa 5 hari')).toBeTruthy();
    // 199/200 tidak boleh dibulatkan menjadi 100%.
    expect(screen.getByText('99% dari total')).toBeTruthy();
    expect(screen.getByRole('link', { name: /Antrean verifikasi/ }).getAttribute('href')).toBe('/verifikasi');
    expect(screen.getByRole('link', { name: /Lihat semua/ }).getAttribute('href')).toBe('/pengukuran');

    const bar = await screen.findByTestId('chart-bar');
    expect(JSON.parse(bar.dataset.series ?? '[]')).toContainEqual({ name: 'Disahkan', data: [3, 199] });
    expect(JSON.parse(screen.getByTestId('chart-donut').dataset.series ?? '[]')).toEqual([0, 0, 1, 0, 199]);
    const progres = screen.getByRole('table', { name: /Progres pengukuran per periode/ });
    expect(within(progres).getByRole('row', { name: /Triwulan II 0 0 1 0 199 200/ })).toBeTruthy();

    const terbaru = screen.getByRole('table', { name: /Pengukuran terbaru/ });
    expect(within(terbaru).getByText('Tidak dapat dihitung')).toBeTruthy();
    expect(within(terbaru).getByText('Reviu terlambat')).toBeTruthy();
    expect(within(terbaru).getByRole('link', { name: 'Lihat pengukuran IK-01' }).getAttribute('href')).toBe('/pengukuran/u1');
});

it('menandai periode terakhir yang pengisiannya sudah ditutup dan grafik kosong', async () => {
    render(
        <Index
            {...props({
                activePeriode: { ...props().activePeriode!, status: 'terakhir', sisa_hari: null },
                stats: zero,
                progresPeriode: [{ periode_id: 'p2', nama: 'Triwulan II', ...zero }],
                pengukurans: [],
            })}
        />,
    );

    expect(screen.getByText('Terakhir')).toBeTruthy();
    expect(screen.getByText('Pengisian periode ini telah ditutup')).toBeTruthy();
    expect(await screen.findByText('Belum ada pengukuran pada jadwal ini.')).toBeTruthy();
    expect(screen.getByText('Belum ada pengukuran pada periode ini.')).toBeTruthy();
    expect(screen.queryByTestId('chart-bar')).toBeNull();
    expect(screen.getByText('Belum ada pengukuran')).toBeTruthy();
});

it('menampilkan satu pemberitahuan dengan alasan bila belum ada periode', () => {
    can.verifikasi = false;
    render(<Index {...props({ activeJadwal: null, activePeriode: null, stats: zero, progresPeriode: [], pengukurans: [] })} />);

    expect(screen.getByRole('status').textContent).toContain('Belum ada Jadwal Tahunan 2026 untuk Renstra aktif.');
    expect(screen.queryByText('Total')).toBeNull();
    expect(screen.queryByRole('table')).toBeNull();
    expect(screen.queryByRole('link', { name: /Antrean verifikasi/ })).toBeNull();
    can.verifikasi = true;
});
