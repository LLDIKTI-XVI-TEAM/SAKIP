import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import DashboardIndex from '@/Pages/Dashboard/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => ({
        props: {
            auth: {
                user: { id: 'u-1', nama: 'Budi Santoso' },
                can: {
                    pengukuran: true,
                    verifikasi: true,
                    pk: true,
                },
            },
        },
    }),
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const defaultProps = {
    activeRenstra: { id: 'r-1', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029 },
    activePeriode: { id: 'p-1', nama_periode: 'Triwulan I 2026', status: 'aktif' as const },
    stats: {
        total: 10,
        draft: 2,
        diajukan: 3,
        diverifikasi: 1,
        dikembalikan: 1,
        disahkan: 3,
    },
    pengukurans: [
        {
            id: 'm-1',
            status: 'diajukan' as const,
            self_approval: false,
            reviu_terlambat: false,
            nilai: '85.5',
            status_perhitungan: 'terhitung' as const,
            satuan: '%',
            desimal_tampilan: 1,
            indikator: { kode: 'IKU-01', nama: 'Persentase Lulusan Bekerja' },
            unit: { nama: 'Bagian Akademik' },
            pic: { nama: 'Ahmad PIC' },
            action: { href: '/pengukuran/m-1', label: 'Buka pengukuran' },
        },
        {
            id: 'm-2',
            status: 'dikembalikan' as const,
            self_approval: true,
            reviu_terlambat: true,
            nilai: null,
            status_perhitungan: 'belum_diisi' as const,
            satuan: 'Dokumen',
            desimal_tampilan: 0,
            indikator: { kode: 'IKU-02', nama: 'Jumlah Kerjasama Industri' },
            unit: { nama: 'Bagian Kelembagaan' },
            pic: null,
            action: null,
        },
    ],
};

it('merender salam pengguna dan informasi periode aktif dengan benar', () => {
    render(<DashboardIndex {...defaultProps} />);

    expect(screen.getByText('Budi Santoso')).toBeDefined();
    expect(screen.getByText('Sistem Akuntabilitas Kinerja Instansi Pemerintah')).toBeDefined();
    expect(screen.getByText(/Renstra:/)).toBeDefined();
    expect(screen.getByText('2025-2029')).toBeDefined();
    expect(screen.getByText(/Periode aktif: Triwulan I 2026/)).toBeDefined();
});

it('merender seluruh 6 KPI status alur pengukuran secara lengkap', () => {
    render(<DashboardIndex {...defaultProps} />);

    expect(screen.getByText('Total Pengukuran')).toBeDefined();
    expect(screen.getAllByText('10').length).toBeGreaterThanOrEqual(1);

    // Draf, Diajukan, Diverifikasi, Dikembalikan, Disahkan
    expect(screen.getByText('Draf')).toBeDefined();
    expect(screen.getAllByText('Diajukan').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Diverifikasi')).toBeDefined();
    expect(screen.getAllByText('Dikembalikan').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Disahkan').length).toBeGreaterThanOrEqual(1);

    // Counts
    expect(screen.getAllByText('2').length).toBeGreaterThanOrEqual(1); // draft count / table row
    expect(screen.getAllByText('1').length).toBeGreaterThanOrEqual(2); // diverifikasi, dikembalikan, table row
});

it('merender layout 2 kolom: capaian indikator dan progres rencana aksi', () => {
    render(<DashboardIndex {...defaultProps} />);

    // Card Kiri: Capaian Indikator (Data Disahkan)
    expect(screen.getByText('Capaian Indikator (Data Disahkan)')).toBeDefined();
    expect(screen.getByText('Capaian')).toBeDefined();
    expect(screen.getByText('Tersahkan')).toBeDefined();
    expect(screen.getByText('Tercapai')).toBeDefined();
    expect(screen.getByText('Dalam Progres')).toBeDefined();
    expect(screen.getByText('Perlu Perhatian')).toBeDefined();
    expect(screen.getByText('Tidak Tercapai')).toBeDefined();
    expect(screen.getByText('Belum Ada Data')).toBeDefined();
    expect(screen.getByText(/Data capaian hanya berasal dari pengukuran dengan status/)).toBeDefined();

    // Card Kanan: Progres Rencana Aksi
    expect(screen.getByText('Progres Rencana Aksi')).toBeDefined();
    expect(screen.getByText('Rencana Aksi Belum Tersedia')).toBeDefined();
    expect(screen.getByText('Data rencana aksi belum tersedia pada periode ini.')).toBeDefined();
    expect(screen.getByText('Kesiapan Pengesahan')).toBeDefined();

    // No duplicate breakdown pills
    expect(screen.queryByText('DISTRIBUSI STATUS TAHAPAN')).toBeNull();
});

it('merender tabel dengan informasi lengkap: Kode, Indikator, Unit, PIC, Nilai, Hasil Perhitungan, Status, dan Aksi', () => {
    render(<DashboardIndex {...defaultProps} />);

    // Kode & Indikator (rendered on desktop and mobile)
    expect(screen.getAllByText('IKU-01').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Persentase Lulusan Bekerja').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('IKU-02').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Jumlah Kerjasama Industri').length).toBeGreaterThanOrEqual(1);

    // Unit & PIC
    expect(screen.getAllByText('Bagian Akademik').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText(/Ahmad PIC/).length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Bagian Kelembagaan').length).toBeGreaterThanOrEqual(1);

    // Nilai
    expect(screen.getAllByText('85,5 %').length).toBeGreaterThanOrEqual(1);

    // Hasil Perhitungan
    expect(screen.getAllByText('Terhitung').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Belum diisi').length).toBeGreaterThanOrEqual(1);

    // Badges
    expect(screen.getAllByText('Persetujuan sendiri').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Reviu terlambat').length).toBeGreaterThanOrEqual(1);

    // Action button
    expect(screen.getAllByText('Buka').length).toBeGreaterThanOrEqual(1);
});

it('menampilkan empty state yang ringkas saat tidak ada data pengukuran', () => {
    render(
        <DashboardIndex
            {...defaultProps}
            pengukurans={[]}
            stats={{ total: 0, draft: 0, diajukan: 0, diverifikasi: 0, dikembalikan: 0, disahkan: 0 }}
        />
    );

    const emptyMessages = screen.getAllByText('Belum ada data indikator pada periode aktif');
    expect(emptyMessages.length).toBeGreaterThanOrEqual(1);

    // Empty state of Capaian Indikator
    expect(screen.getAllByText('0%').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Capaian Indikator (Data Disahkan)')).toBeDefined();
    expect(screen.getByText('Tersahkan')).toBeDefined();
});

it('mendukung interaksi hover pada kategori capaian dan interaksi filter KPI pada tabel', async () => {
    const { fireEvent } = await import('@testing-library/react');
    render(<DashboardIndex {...defaultProps} />);

    // 1. Donut breakdown item hover updates dynamic description
    const tercapaiRow = screen.getByTestId('capaian-category-tercapai');
    fireEvent.mouseEnter(tercapaiRow);
    expect(screen.getAllByText(/Capaian ≥ 100% dari target resmi/).length).toBeGreaterThanOrEqual(1);

    fireEvent.mouseLeave(tercapaiRow);
    expect(screen.getByText(/Data capaian hanya berasal dari pengukuran dengan status/)).toBeDefined();

    // 2. Click KPI card to filter table
    const diajukanKpiCard = screen.getByRole('button', { name: /Diajukan/ });
    fireEvent.click(diajukanKpiCard);

    // Active filter banner is displayed
    expect(screen.getByText('Filter Aktif:')).toBeDefined();
    expect(screen.getByText('Tampilkan Semua')).toBeDefined();

    // Table shows only diajukan items (IKU-01) and hides dikembalikan (IKU-02)
    expect(screen.getAllByText('IKU-01').length).toBeGreaterThanOrEqual(1);
    expect(screen.queryByText('IKU-02')).toBeNull();

    // 3. Reset filter
    const resetBtn = screen.getByText('Tampilkan Semua');
    fireEvent.click(resetBtn);
    expect(screen.queryByText('Filter Aktif:')).toBeNull();
    expect(screen.getAllByText('IKU-02').length).toBeGreaterThanOrEqual(1);
});


