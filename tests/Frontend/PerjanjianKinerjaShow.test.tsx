import type { ReactNode } from 'react';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Show from '@/Pages/PerjanjianKinerja/Show';
import type { RenstraPkSummary } from '@/types/perjanjian-kinerja';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, breadcrumbs }: { children: ReactNode; breadcrumbs?: { label: string }[] }) => (
        <main>
            <nav aria-label="Breadcrumb">{breadcrumbs?.map((item) => <span key={item.label}>{item.label}</span>)}</nav>
            {children}
        </main>
    ),
}));
const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;
beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

const pk: RenstraPkSummary = {
    id: 'pk-1', renstra_id: 'ren-1', tahun: 2026, nomor_pk: 'PK/LLDIKTI16/2026/001', tanggal_pk: '2026-01-15',
    created_at: '2026-01-15T02:00:00Z', updated_at: '2026-01-15T02:00:00Z',
    creator: { id: 'user-1', nama: 'Superadmin' },
    renstra: { id: 'ren-1', kode: 'REN-2025', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029 },
    berkas: [
        { id: 'berkas-1', mode: 'file', nama_asli: 'naskah-pk.pdf', mime: 'application/pdf', ukuran_bytes: 2048, tautan: null, isi_teks: null,
            created_at: '2026-01-15T02:00:00Z', pengunggah: { id: 'user-1', nama: 'Superadmin' } },
        { id: 'berkas-2', mode: 'tautan', nama_asli: null, mime: null, ukuran_bytes: null, tautan: 'https://contoh.go.id/pk', isi_teks: null },
    ],
};

it('detail PK memakai pola detail bersama, menautkan unduhan ber-otorisasi, dan menamai tombol hapus per lampiran', () => {
    render(<Show pk={pk} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true }} />);
    expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/perjanjian-kinerja');
    expect(screen.getByRole('heading', { name: 'PK/LLDIKTI16/2026/001' })).toBeTruthy();
    expect(screen.getByText('Belum Aktif')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Unduh file' }).getAttribute('href')).toBe('/perjanjian-kinerja/pk-1/berkas/berkas-1/unduh');
    expect(screen.getByText(/Diunggah oleh Superadmin/)).toBeTruthy();
    expect(screen.getByText('application/pdf · 2 KB')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Hapus lampiran naskah-pk.pdf' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Hapus lampiran Lampiran Dokumen #2' })).toBeTruthy();
    expect(screen.queryByText(/lampiran yang ada tidak dapat dihapus/)).toBeNull();
});

it('lampiran berketerangan menampilkan keterangannya di daftar lampiran', () => {
    const berkas = { ...pk.berkas![1], keterangan: 'PK perubahan anggaran' };
    render(<Show pk={{ ...pk, berkas: [berkas] }} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true }} />);
    expect(screen.getByText(/Keterangan: PK perubahan anggaran/)).toBeTruthy();
});

it('PK tanpa nomor memakai label cadangan PK {tahun} pada judul dan breadcrumb', () => {
    const { container } = render(<Show pk={{ ...pk, nomor_pk: null }} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true }} />);
    expect(screen.getByRole('heading', { name: 'PK 2026' })).toBeTruthy();
    expect(within(screen.getByRole('navigation', { name: 'Breadcrumb' })).getByText('PK 2026')).toBeTruthy();
    expect(container.textContent).not.toContain('null');
});

it('jadwal aktif menampilkan penanda terkunci tanpa tombol hapus, dan tanpa izin baca berkas tidak ada tautan unduh', () => {
    render(<Show pk={pk} jadwal_status="aktif" is_jadwal_aktif can={{ update: false, delete_berkas: true, read_berkas: false }} />);
    expect(screen.getByText(/^Jadwal tahunan aktif: lampiran yang ada tidak dapat dihapus/)).toBeTruthy();
    expect(screen.queryByRole('button', { name: /^Hapus lampiran/ })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Unduh file' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Buka tautan' })).toBeNull();
    expect(screen.queryByRole('button', { name: /Edit Perjanjian Kinerja/ })).toBeNull();
});

const targetPk = [{
    id: 's1', kode: 'S1', deskripsi: 'Meningkatnya tata kelola',
    indikator: [
        { id: 'i1', kode: 'IKU 1.1', nama: 'Indeks reformasi birokrasi', satuan: 'nilai', desimal_tampilan: 2, arah: 'naik_baik', baseline: '74.2', target_tahunan: '94.05', target_renstra: '95', status_deviasi: 'menyimpang', read_only_reason: null },
        { id: 'i2', kode: 'IKU 1.2', nama: 'Nilai SAKIP', satuan: 'nilai', desimal_tampilan: 1, arah: 'naik_baik', baseline: null, target_tahunan: null, target_renstra: null, status_deviasi: 'renstra_belum_ada', read_only_reason: null },
        { id: 'i3', kode: 'IKU 1.3', nama: 'Indikator Renstra nonaktif', satuan: 'persen', desimal_tampilan: 0, arah: 'turun_baik', baseline: '10', target_tahunan: '12', target_renstra: '11', status_deviasi: 'menyimpang', read_only_reason: 'Renstra nonaktif atau diarsipkan hanya dapat dibaca.' },
    ],
}];

/** Label HP di dalam sel menandai sel Target PK pada baris yang sama. */
const selTargetPk = (baris: HTMLElement) => {
    const sel = within(baris).getByText('Target PK 2026').closest('td');
    if (!sel) throw new Error('Sel Target PK tidak ditemukan.');
    return sel;
};

it('target Renstra tampil kecil di bawah target PK beserta anjuran dan penanda deviasi menurut arah', () => {
    render(<Show pk={pk} target_pk={targetPk} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true, update_target: true }} />);
    const tabel = screen.getByRole('table', { name: 'Target PK 2026 sasaran S1' });
    expect(within(tabel).getAllByRole('columnheader').map((header) => header.textContent)).toEqual(['Kode', 'Indikator', 'Baseline (data pendukung)', 'Target PK 2026', 'Aksi']);
    expect(within(tabel).queryByRole('columnheader', { name: 'Target Renstra 2026' })).toBeNull();
    expect(screen.getByText('Baseline adalah data pendukung, bukan isi dokumen PK. Target Renstra tahun yang sama tampil kecil di bawah Target PK.')).toBeTruthy();
    const [baris1, baris2, baris3] = within(tabel).getAllByRole('row').slice(1);
    expect(within(selTargetPk(baris1)).getByText('Renstra: 95,00')).toBeTruthy();
    // Lencana boleh dua baris di kartu HP tanpa terpotong atau berimpit.
    const lencana = within(selTargetPk(baris1)).getByText('Di bawah target Renstra');
    expect(['whitespace-normal', 'leading-tight'].every((kelas) => lencana.classList.contains(kelas))).toBe(true);
    expect(['whitespace-nowrap', 'leading-none'].some((kelas) => lencana.classList.contains(kelas))).toBe(false);
    expect(within(selTargetPk(baris2)).getByText('Target Renstra belum ada')).toBeTruthy();
    expect(within(baris2).queryByText(/target Renstra$/)).toBeNull();
    expect(within(selTargetPk(baris3)).getByText('Renstra: 11')).toBeTruthy();
    expect(within(selTargetPk(baris3)).getByText('Di atas target Renstra')).toBeTruthy();
    // Tabel memuat indikator naik_baik dan turun_baik, jadi anjuran menyebut kedua arah (Q65 §65.2 butir 13).
    expect(screen.getByText('Anjuran: target PK minimal sama atau naik satu poin dari target Renstra; untuk indikator yang membaik bila nilainya turun, minimal sama atau turun satu poin. Anjuran ini panduan, bukan aturan sistem.')).toBeTruthy();
});

it('kode sasaran, target Renstra, dan penanda deviasi memakai teks minimal 12px', () => {
    render(<Show pk={pk} target_pk={targetPk} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true, update_target: true }} />);
    for (const badge of [screen.getByText('S1'), screen.getByText('Renstra: 95,00'), screen.getByText('Target Renstra belum ada'), screen.getByText('Di bawah target Renstra'), screen.getByText('Di atas target Renstra')]) {
        expect(badge.classList.contains('text-[11px]')).toBe(false);
        expect(badge.classList.contains('text-xs')).toBe(true);
    }
});

it('target PK tampil per sasaran: baseline sebagai data pendukung, angka sesuai desimal tampilan, dan Ubah hanya untuk baris yang boleh diubah', () => {
    render(<Show pk={pk} target_pk={targetPk} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true, update_target: true }} />);
    const tabel = screen.getByRole('table', { name: 'Target PK 2026 sasaran S1' });
    expect(within(tabel).getByRole('columnheader', { name: 'Baseline (data pendukung)' })).toBeTruthy();
    expect(within(tabel).getByRole('columnheader', { name: 'Target PK 2026' })).toBeTruthy();
    expect(within(tabel).getByText('74,20')).toBeTruthy();
    expect(within(tabel).getByText('94,05')).toBeTruthy();
    expect(within(tabel).getAllByText('Belum diisi')).toHaveLength(2);
    expect(screen.getByRole('button', { name: 'Ubah target PK IKU 1.1' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Ubah target PK IKU 1.2' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Ubah target PK IKU 1.3' })).toBeNull();
});

it.each(['aktif', 'ditutup'])('jadwal %s menampilkan pemberitahuan bahwa perubahan target PK tidak mengubah target yang dipakai', (status) => {
    render(<Show pk={pk} target_pk={targetPk} jadwal_status={status} is_jadwal_aktif={status === 'aktif'} is_jadwal_terkunci can={{ update: true, delete_berkas: true, update_target: true }} />);
    expect(screen.getByText('Perubahan target PK tidak mengubah target yang dipakai Rencana Aksi dan pengukuran tahun 2026.')).toBeTruthy();
});

it.each([null, 'draft'])('jadwal %s tidak menampilkan pemberitahuan target beku', (status) => {
    render(<Show pk={pk} target_pk={targetPk} jadwal_status={status} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true, update_target: true }} />);
    expect(screen.queryByText(/tidak mengubah target yang dipakai Rencana Aksi/)).toBeNull();
});

it('tanpa izin ubah target, nilai target PK tetap terlihat tanpa tombol Ubah', () => {
    render(<Show pk={pk} target_pk={targetPk} jadwal_status={null} is_jadwal_aktif={false} can={{ update: false, delete_berkas: false, update_target: false }} />);
    expect(within(screen.getByRole('table', { name: 'Target PK 2026 sasaran S1' })).getByText('94,05')).toBeTruthy();
    expect(screen.queryByRole('button', { name: /^Ubah target PK/ })).toBeNull();
});

it('Ubah membuka editor dari route target PK tanpa pilihan tahun', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(new Response('{}', { status: 403 }));
    vi.stubGlobal('fetch', fetchMock);
    try {
        render(<Show pk={pk} target_pk={targetPk} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true, update_target: true }} />);
        fireEvent.click(screen.getByRole('button', { name: 'Ubah target PK IKU 1.1' }));
        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        expect(fetchMock.mock.calls[0][0]).toBe('/perjanjian-kinerja/pk-1/target/i1/editor');
        expect(screen.queryByLabelText('Tahun')).toBeNull();
    } finally { vi.unstubAllGlobals(); }
});
