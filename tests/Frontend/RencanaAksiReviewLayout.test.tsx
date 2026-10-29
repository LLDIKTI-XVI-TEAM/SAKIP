import type { ReactNode } from 'react';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, expect, it, vi } from 'vitest';
import Review from '@/Pages/RencanaAksi/Review';
import type { ReviewRencanaAksi } from '@/Pages/RencanaAksi/review-types';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...await importOriginal<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => ({ props: {} }),
}));
// Judul dan breadcrumb ikut dirender agar istilah halaman dapat diperiksa.
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, title, breadcrumbs }: { children: ReactNode; title?: string; breadcrumbs?: { label: string }[] }) => (
        <main><h1>{title}</h1><nav aria-label="Jejak navigasi">{breadcrumbs?.map((item) => item.label).join(' / ')}</nav>{children}</main>
    ),
}));

const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => {
    for (const [name, descriptor] of [['showModal', originalShowModal], ['close', originalClose]] as const) {
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    }
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const rencanaAksi: ReviewRencanaAksi = {
    id: 'ra-1', tahun: 2026, status_alur: 'diverifikasi', expected_versi: 7,
    versi_pengajuan: { id: 'versi-2', nomor: 2, diajukan_at: '2026-10-01T01:00:00Z', jalur_pengajuan: 'pic', pengaju: { id: 'user-1', nama: 'Pengaju Lama' } },
    indikator: { kode: 'IKU-1', nama: 'Label indikator dibekukan', satuan: '%', desimal_tampilan: 2 },
    unit_kerja: { id: 'unit-1', nama: 'Unit versi pengajuan' }, pic: { id: 'user-1', nama: 'PJ saat pengajuan' },
    uraian: 'Substansi versi pengajuan', alasan_deviasi_pk: 'Alasan deviasi beku',
    target_periode: [
        { periode_id: 'p-1', periode_nama: 'Triwulan I', periode_urutan: 1, nilai: '74.000000000000', status_perhitungan: 'terhitung', keterangan: null,
            komponen: [{ komponen_id: 'k-1', kode: 'n', label: 'Pembilang', nilai: '37.000000000000', keterangan: null }, { komponen_id: 'k-2', kode: 'd', label: 'Penyebut', nilai: '50', keterangan: null }] },
        { periode_id: 'p-2', periode_nama: 'Triwulan II', periode_urutan: 2, nilai: null, status_perhitungan: 'belum_diisi', keterangan: null,
            komponen: [{ komponen_id: 'k-1', kode: 'n', label: 'Pembilang', nilai: null, keterangan: null }, { komponen_id: 'k-2', kode: 'd', label: 'Penyebut', nilai: '50', keterangan: null }] },
    ],
    klaim: [
        { id: 'klaim-1', periode_nama: 'Triwulan I', nama: 'Bimtek beku', status: 'rencana', komponen: null, arah_dampak: 'menambah', catatan: 'Catatan beku', anggaran: '1500000' },
        { id: 'klaim-2', periode_nama: 'Triwulan II', nama: 'Rapat beku', status: 'ditunda', komponen: 'n — Pembilang', arah_dampak: 'mengurangi', catatan: null, anggaran: null },
    ],
    anggaran_kegiatan: { total: '1500000', belum_diisi: 1, per_periode: {} },
    bukti_dukungs: [{ id: 'bukti-1', mode: 'file', nama_asli: 'versi-dua.pdf', tautan: null, isi_teks: null, download_url: '/unduh' }],
    can: { verifikasi: false, kembalikan: true, sahkan: false, buka_kembali: false, evidence: true, kegiatan: true },
    businessErrors: { verifikasi: [], kembalikan: [], sahkan: ['F1: pengaju tidak boleh mengesahkan pengajuannya sendiri.'], buka_kembali: [] },
    pengesahan: null,
    dibuka_kembali: false,
    histori: [{ id: 'versi-2', nomor: 2, diajukan_at: '2026-10-01T01:00:00Z', jalur_pengajuan: 'pic' }, { id: 'versi-1', nomor: 1, diajukan_at: '2026-09-01T01:00:00Z', jalur_pengajuan: 'perencanaan' }],
    pagination: { current_page: 1, last_page: 1, total: 2, prev_page_url: null, next_page_url: null },
};
const renderReview = (override: Partial<ReviewRencanaAksi> = {}) => render(<Review rencanaAksi={{ ...rencanaAksi, ...override }} />);
const kepalaKolom = (wilayah: HTMLElement): string[] => within(wilayah).getAllByRole('columnheader').map((kepala) => kepala.textContent ?? '');
const barisData = (wilayah: HTMLElement): HTMLElement[] => within(wilayah).getAllByRole('row').filter((row) => row.closest('tbody') !== null);

it('memakai istilah Reviu pada judul dan breadcrumb halaman', () => {
    renderReview();

    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Reviu IKU-1 · 2026');
    expect(screen.getByRole('navigation', { name: 'Jejak navigasi' }).textContent).toBe('Rencana Aksi / Reviu / Versi 2');
});

it('menyusun kartu dengan urutan substansi yang sama seperti halaman detail rencana aksi', () => {
    renderReview();

    expect(screen.getAllByRole('heading', { level: 3 }).map((judul) => judul.textContent)).toEqual([
        'IKU-1 · Label indikator dibekukan',
        'Target per periode',
        'Uraian dan alasan deviasi',
        'Kegiatan yang diklaim',
        'Bukti dukung',
        'Histori pengajuan',
    ]);
    const uraian = within(screen.getByRole('region', { name: 'Uraian dan alasan deviasi' }));
    expect(uraian.getByText('Substansi versi pengajuan')).toBeTruthy();
    expect(uraian.getByText('Alasan deviasi beku')).toBeTruthy();
});

it('mengumpulkan keterangan versi beku, pengesahan, dibuka kembali, dan aksi tidak tersedia dalam satu wilayah pemberitahuan', () => {
    const { unmount } = renderReview();
    let wilayah = within(screen.getByRole('region', { name: 'Pemberitahuan' }));
    expect(wilayah.getByText(/substansi pengajuan versi 2 yang dibekukan/)).toBeTruthy();
    expect(wilayah.getByText('Pengesahan tidak tersedia')).toBeTruthy();
    expect(wilayah.getByText('F1: pengaju tidak boleh mengesahkan pengajuannya sendiri.')).toBeTruthy();
    unmount();

    renderReview({ status_alur: 'disahkan', businessErrors: { verifikasi: [], kembalikan: [], sahkan: [], buka_kembali: [] }, pengesahan: { oleh: 'Perencana Pengesah', pada: null, self_approval: true } });
    wilayah = within(screen.getByRole('region', { name: 'Pemberitahuan' }));
    expect(wilayah.getByText(/Disahkan oleh Perencana Pengesah/)).toBeTruthy();
    expect(wilayah.getByText(/self-approval/)).toBeTruthy();
    cleanup();

    renderReview({ status_alur: 'dikembalikan', dibuka_kembali: true, businessErrors: { verifikasi: [], kembalikan: [], sahkan: [], buka_kembali: [] } });
    expect(within(screen.getByRole('region', { name: 'Pemberitahuan' })).getByText('Dibuka kembali setelah pengesahan')).toBeTruthy();
});

it('menampilkan target per periode dengan satu kolom per komponen', () => {
    renderReview();

    const target = screen.getByRole('region', { name: 'Target per periode' });
    expect(kepalaKolom(target)).toEqual(['Periode', 'n · Pembilang', 'd · Penyebut', 'Target tersimpan']);
    const [tw1, tw2] = barisData(target);
    expect(within(tw1).getByText('37,00')).toBeTruthy();
    expect(within(tw1).getByText('74,00 %')).toBeTruthy();
    expect(within(tw1).getByText('Terhitung')).toBeTruthy();
    expect(within(tw2).getByText('Belum tersedia', { selector: 'td:nth-child(2) *' })).toBeTruthy();
});

it('menampilkan target manual tanpa kolom komponen', () => {
    renderReview({ target_periode: [{ periode_id: 'p-1', periode_nama: 'Triwulan I', periode_urutan: 1, nilai: '10.25', status_perhitungan: null, keterangan: 'Penjelasan target manual.', komponen: [] }] });

    const target = screen.getByRole('region', { name: 'Target per periode' });
    expect(kepalaKolom(target)).toEqual(['Periode', 'Target tersimpan']);
    expect(within(target).getByText('10,25 %')).toBeTruthy();
    expect(within(target).getByText('Penjelasan target manual.')).toBeTruthy();
});

it('menampilkan kegiatan yang diklaim sebagai tabel dengan total saat pengajuan', () => {
    renderReview();

    const kegiatan = screen.getByRole('region', { name: 'Kegiatan yang diklaim' });
    expect(kepalaKolom(kegiatan)).toEqual(['Periode', 'Kegiatan', 'Komponen', 'Arah dampak', 'Anggaran', 'Status']);
    const [bimtek, rapat] = barisData(kegiatan);
    expect(within(bimtek).getByText('Bimtek beku')).toBeTruthy();
    expect(within(bimtek).getByText('Catatan beku')).toBeTruthy();
    expect(within(bimtek).getByText('Tanpa komponen tertentu')).toBeTruthy();
    expect(within(bimtek).getByText(/^Rp\s?1\.500\.000$/)).toBeTruthy();
    expect(within(bimtek).getByText('Rencana')).toBeTruthy();
    expect(within(rapat).getByText('Mengurangi')).toBeTruthy();
    expect(within(rapat).getByText('Belum diisi')).toBeTruthy();
    expect(within(rapat).getByText('Ditunda')).toBeTruthy();
    expect(within(kegiatan).getByText(/Total anggaran saat pengajuan:/).textContent).toMatch(/Rp\s?1\.500\.000 \(1 kegiatan belum diisi\)/);
});

it('menampilkan histori pengajuan sebagai tabel dengan penanda versi yang ditampilkan', () => {
    renderReview();

    const histori = screen.getByRole('region', { name: 'Histori pengajuan' });
    expect(kepalaKolom(histori)).toEqual(['Versi', 'Diajukan', 'Jalur', 'Aksi']);
    const [v2, v1] = barisData(histori);
    expect(within(v2).getByText('Versi ditampilkan').getAttribute('aria-current')).toBe('page');
    expect(within(v1).getByText('Perencanaan')).toBeTruthy();
    expect(within(v1).getByRole('link', { name: 'Lihat versi' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/review?versi=versi-1');
});

it('modal buka kembali hanya memuat judul dan isian alasan, tanpa kalimat penjelasan dan ikon peringatan', () => {
    renderReview({ status_alur: 'disahkan', can: { ...rencanaAksi.can, kembalikan: false, buka_kembali: true }, businessErrors: { verifikasi: [], kembalikan: [], sahkan: [], buka_kembali: [] } });
    fireEvent.click(screen.getByRole('button', { name: 'Buka kembali' }));

    const dialog = screen.getByRole('dialog', { name: 'Buka kembali rencana aksi' });
    expect(within(dialog).queryByText(/Tuliskan dasar koreksi/)).toBeNull();
    expect(dialog.getAttribute('aria-describedby')).toBeNull();
    // Keputusan PM 28 Oktober 2026: ikon peringatan di samping judul dihapus; ikon yang tersisa hanya tombol tutup.
    expect(dialog.querySelectorAll('svg')).toHaveLength(1);
    expect(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ })).toBeTruthy();
});

it('modal kembalikan juga hanya memuat judul dan isian alasan, tanpa kalimat penjelasan dan ikon peringatan', () => {
    renderReview();
    fireEvent.click(screen.getByRole('button', { name: 'Kembalikan untuk revisi' }));

    const dialog = screen.getByRole('dialog', { name: 'Kembalikan rencana aksi' });
    expect(within(dialog).queryByText(/Tuliskan perbaikan versi/)).toBeNull();
    expect(dialog.getAttribute('aria-describedby')).toBeNull();
    expect(dialog.querySelectorAll('svg')).toHaveLength(1);
    expect(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ })).toBeTruthy();
});
