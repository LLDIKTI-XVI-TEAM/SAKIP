import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { PanelKegiatan, TabelKegiatanPerIku } from '@/Components/Dashboard/PanelKegiatan';
import { PanelRencanaAksi } from '@/Components/Dashboard/PanelRencanaAksi';
import type { DashboardKegiatan } from '@/types/dashboard';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { pengaturan: {} } }),
}));

afterEach(cleanup);

const kegiatan = (overrides: Partial<DashboardKegiatan> = {}): DashboardKegiatan => ({
    per_status: { rencana: 3, terlaksana: 5, tidak_terlaksana: 1, ditunda: 0, batal: 2 },
    terlambat_jumlah: 2,
    per_iku: [{ id: 'i1', kode: 'IKU 1.1', nama: 'Persentase PT terakreditasi', jumlah: { rencana: 2, terlaksana: 1, tidak_terlaksana: 0, ditunda: 0, batal: 1 } }],
    terlambat: [{ id: 'k1', nama: 'Bimtek akreditasi', unit: 'Bagian Kelembagaan', tanggal_rencana: '2026-03-01' }],
    ...overrides,
});

it('panel kegiatan berlabel belum diverifikasi dan memberi peringatan kegiatan terlambat', () => {
    render(<PanelKegiatan kegiatan={kegiatan()} periodeNama="Triwulan I 2026" />);

    const panel = screen.getByRole('region', { name: /Status kegiatan Triwulan I 2026/ });
    expect(within(panel).getByText('Belum diverifikasi')).toBeTruthy();
    const peringatan = within(panel).getByText('2 kegiatan melewati tanggal rencana dan belum terlaksana');
    // Kontras teks peringatan minimal 4,5:1 (design-system); bg-warning/15 hanya 4,46:1.
    expect(peringatan.closest('div')?.className).toContain('bg-warning/10');
    expect(within(panel).getByText('Bimtek akreditasi')).toBeTruthy();
    expect(panel.querySelector('[data-status="tidak_terlaksana"]')?.textContent).toBe('Tidak terlaksana1');
    expect(panel.querySelector('[data-status="batal"]')?.textContent).toBe('Batal2');
    expect(within(panel).getByText('11 kegiatan')).toBeTruthy();
    // Tabel per IKU dipindah ke kartu tersendiri (Q66).
    expect(within(panel).queryByRole('table')).toBeNull();
});

it('daftar status memakai label teks dan angka; bar proporsi hanya penanda tambahan (Q66)', () => {
    render(<PanelKegiatan kegiatan={kegiatan()} periodeNama="Triwulan I 2026" />);

    const panel = screen.getByRole('region', { name: /Status kegiatan Triwulan I 2026/ });
    const daftar = within(panel).getByRole('list', { name: 'Jumlah kegiatan per status' });
    expect(within(daftar).getAllByRole('listitem').map((li) => li.textContent)).toEqual(['Rencana3', 'Terlaksana5', 'Tidak terlaksana1', 'Ditunda0', 'Batal2']);
    const bar = panel.querySelector('[data-proporsi]') as HTMLElement;
    expect(bar.getAttribute('aria-hidden')).toBe('true');
    // Status bernilai 0 tidak digambar; lebar segmen sebanding jumlahnya.
    const segmen = Array.from(bar.children) as HTMLElement[];
    expect(segmen).toHaveLength(4);
    expect(segmen[1].style.width).toBe(`${(5 * 100) / 11}%`);
    expect(segmen[1].className).toContain('bg-success');
});

it('tanpa kegiatan bar proporsi kosong dan keadaan kosong tampil', () => {
    const nol = { rencana: 0, terlaksana: 0, tidak_terlaksana: 0, ditunda: 0, batal: 0 };
    render(<PanelKegiatan kegiatan={kegiatan({ per_status: nol, terlambat_jumlah: 0, per_iku: [], terlambat: [] })} periodeNama="Triwulan I 2026" />);

    const panel = screen.getByRole('region', { name: /Status kegiatan Triwulan I 2026/ });
    expect(panel.querySelector('[data-proporsi]')?.children).toHaveLength(0);
    expect(within(panel).getByText('0 kegiatan')).toBeTruthy();
    expect(within(panel).getByText('Belum ada kegiatan pada periode ini.')).toBeTruthy();
});

it('tabel kegiatan per IKU memakai kartu tersendiri dan tidak tampil tanpa kegiatan', () => {
    const { rerender } = render(<TabelKegiatanPerIku kegiatan={kegiatan()} periodeNama="Triwulan I 2026" />);

    const iku = screen.getByRole('table', { name: /Kegiatan per IKU/ });
    // Di bawah lg area konten terlalu sempit untuk enam kolom, jadi baris tetap berupa kartu.
    expect(iku.className).toContain('block lg:table');
    expect(within(iku).getByText('IKU 1.1')).toBeTruthy();
    expect(screen.getByText('Kegiatan yang diklaim lebih dari satu IKU dihitung pada setiap IKU tersebut.')).toBeTruthy();

    const nol = { rencana: 0, terlaksana: 0, tidak_terlaksana: 0, ditunda: 0, batal: 0 };
    rerender(<TabelKegiatanPerIku kegiatan={kegiatan({ per_status: nol, per_iku: [] })} periodeNama="Triwulan I 2026" />);
    expect(screen.queryByRole('region', { name: /Kegiatan per IKU/ })).toBeNull();
});

it('tanpa izin baca kegiatan, jumlah terlambat tetap tampil tetapi daftar nama tidak', () => {
    render(<PanelKegiatan kegiatan={kegiatan({ terlambat: null })} periodeNama="Triwulan I 2026" />);

    expect(screen.getByText('2 kegiatan melewati tanggal rencana dan belum terlaksana')).toBeTruthy();
    expect(screen.queryByText('Bimtek akreditasi')).toBeNull();
    expect(screen.queryByRole('list', { name: /Kegiatan terlambat/ })).toBeNull();
});

it('tanpa kegiatan terlambat tidak ada peringatan', () => {
    render(<PanelKegiatan kegiatan={kegiatan({ terlambat_jumlah: 0, terlambat: [] })} periodeNama="Triwulan I 2026" />);

    expect(screen.queryByText(/melewati tanggal rencana/)).toBeNull();
});

it('panel rencana aksi menampilkan indikator belum disusun', () => {
    render(<PanelRencanaAksi progres={{ belum_disusun: 15, draft: 2, diajukan: 1, diverifikasi: 0, dikembalikan: 0, disahkan: 5 }} tahun={2026} />);

    const panel = screen.getByRole('region', { name: /Progres Rencana Aksi 2026/ });
    expect(panel.querySelector('[data-status="belum_disusun"]')?.textContent).toBe('Belum disusun15');
    expect(panel.querySelector('[data-status="disahkan"]')?.textContent).toBe('Disahkan5');
    expect(within(panel).getByText('23 indikator')).toBeTruthy();
    // Tinggi kartu mengikuti baris grid agar sama dengan panel kegiatan di sebelahnya (Q66).
    expect(panel.className).toContain('h-full');
    expect((panel.firstElementChild as HTMLElement).className).toContain('h-full');
});

it('dua IKU berkode sama tetap tampil sebagai dua baris', () => {
    const jumlah = { rencana: 1, terlaksana: 0, tidak_terlaksana: 0, ditunda: 0, batal: 0 };
    const errors: unknown[] = [];
    const asli = console.error;
    console.error = (...args: unknown[]) => errors.push(args);
    render(<TabelKegiatanPerIku kegiatan={kegiatan({ per_iku: [{ id: 'i1', kode: 'IKU 1.1', nama: 'Lama', jumlah }, { id: 'i2', kode: 'IKU 1.1', nama: 'Baru', jumlah }] })} periodeNama="Triwulan I 2026" />);
    console.error = asli;

    expect(screen.getByText('Lama')).toBeTruthy();
    expect(screen.getByText('Baru')).toBeTruthy();
    expect(errors.some((e) => String(e).includes('same key'))).toBe(false);
});
