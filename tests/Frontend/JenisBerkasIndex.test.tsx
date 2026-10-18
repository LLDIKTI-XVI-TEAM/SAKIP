import type { ReactNode } from 'react';
import { cleanup, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import JenisBerkasIndex, { type JenisBerkasItem } from '@/Pages/JenisBerkas/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 'perencanaan', role: 'perencanaan' } }, errors: {}, pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native yang dipakai AuditReasonModal.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

const jb = (item: Partial<JenisBerkasItem> & Pick<JenisBerkasItem, 'id' | 'nama' | 'tahap'>): JenisBerkasItem => ({
    indikator_id: null,
    wajib: false,
    keterangan: null,
    izinkan_file: true,
    izinkan_tautan: false,
    izinkan_teks: false,
    semua_mode_wajib: false,
    urutan: 0,
    format_diizinkan: null,
    ukuran_maks_kb: null,
    aktif: true,
    indikator: null,
    created_at: '2026-09-01T00:00:00Z',
    updated_at: '2026-09-01T00:00:00Z',
    ...item,
});

const list = [
    jb({ id: 'j1', nama: 'Laporan Capaian Triwulan', tahap: 'pengukuran', wajib: true, format_diizinkan: 'pdf,docx', ukuran_maks_kb: 10240 }),
    jb({ id: 'j2', nama: 'Kerangka Acuan Kerja', tahap: 'rencana_aksi', izinkan_file: false, izinkan_teks: true, aktif: false }),
];
const indikators = [{ id: 'i1', kode: 'IK-01', nama: 'Persentase PT terakreditasi' }];
const canAll = { create: true, update: true, delete: true, pengaturan_update: true };

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'put').mockImplementation(() => undefined);
    vi.spyOn(router, 'patch').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

it('menyaring persyaratan lewat pencarian dan tahap yang berlabel', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={canAll} />);

    expect(screen.getByRole('button', { name: /Tambah persyaratan/ })).toBeTruthy();
    await user.selectOptions(screen.getByLabelText('Tahap'), 'rencana_aksi');
    expect(screen.queryByText('Laporan Capaian Triwulan')).toBeNull();
    expect(screen.getByText('Kerangka Acuan Kerja')).toBeTruthy();

    await user.type(screen.getByLabelText('Cari persyaratan'), 'laporan');
    expect(screen.getByText('Tidak ada persyaratan yang cocok')).toBeTruthy();
});

it('merangkum ketentuan bukti dalam satu kolom', () => {
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={canAll} />);

    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Persyaratan', 'Tahap', 'Lingkup', 'Ketentuan bukti', 'Status', 'Aksi']);
    const laporan = screen.getByRole('row', { name: /Laporan Capaian Triwulan/ });
    expect(within(laporan).getByText('Wajib')).toBeTruthy();
    expect(within(laporan).getByText('File')).toBeTruthy();
    expect(within(laporan).getByText('pdf, docx · maks 10.240 KB')).toBeTruthy();
    // Baris tahap lama (Rencana Aksi) tanpa mode file tidak menampilkan batas teknis.
    const kak = screen.getByRole('row', { name: /Kerangka Acuan Kerja/ });
    expect(within(kak).getByText('Rencana Aksi')).toBeTruthy();
    expect(within(kak).getByText('Teks')).toBeTruthy();
    expect(within(kak).queryByText(/maks/)).toBeNull();
    expect(within(kak).getByText('Nonaktif')).toBeTruthy();
});

it('kolom kosong mengikuti jumlah kolom saat pengguna hanya boleh mengubah batas teknis', () => {
    render(<JenisBerkasIndex jenisBerkasList={[]} indikators={indikators} can={{ create: false, update: false, delete: false, pengaturan_update: true }} />);

    const cell = screen.getByText('Belum ada persyaratan').closest('td');
    expect(cell?.colSpan).toBe(screen.getAllByRole('columnheader').length);
});

it('menyimpan persyaratan baru langsung tanpa alasan audit', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={canAll} />);

    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));
    await user.type(screen.getByLabelText(/nama persyaratan/i), 'Surat Tugas');
    await user.click(screen.getByRole('button', { name: /simpan persyaratan/i }));

    expect(router.post).toHaveBeenCalledWith(
        '/jenis-berkas',
        expect.objectContaining({ nama: 'Surat Tugas', tahap: 'pengukuran', izinkan_file: true }),
        expect.anything(),
    );
});

it('meminta alasan audit sebelum menyimpan perubahan persyaratan', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={canAll} />);

    await user.click(screen.getByRole('button', { name: 'Edit Laporan Capaian Triwulan' }));
    await waitFor(() => expect(document.activeElement).toBe(screen.getByLabelText(/nama persyaratan/i)));
    await user.click(screen.getByRole('button', { name: /lanjut ke konfirmasi/i }));
    expect(router.put).not.toHaveBeenCalled();
    await user.type(screen.getByLabelText(/alasan perubahan/i), 'Penyesuaian format laporan');
    await user.click(screen.getByRole('button', { name: /simpan perubahan/i }));

    expect(router.put).toHaveBeenCalledWith(
        '/jenis-berkas/j1',
        expect.objectContaining({ nama: 'Laporan Capaian Triwulan', alasan: 'Penyesuaian format laporan' }),
        expect.anything(),
    );
});

it('mengosongkan batas teknis mengirim null saat hanya boleh mengubah batas teknis', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={{ create: false, update: false, delete: false, pengaturan_update: true }} />);

    await user.click(screen.getByRole('button', { name: 'Ubah batas teknis Laporan Capaian Triwulan' }));
    const formatInput = screen.getByLabelText(/format file/i);
    // Polyfill dialog jsdom tidak mengisolasi fokus; tuntaskan autofocus form sebelum membuka konfirmasi audit.
    await waitFor(() => expect(document.activeElement).toBe(formatInput));
    await user.clear(formatInput);
    await user.click(screen.getByRole('button', { name: /lanjut ke konfirmasi/i }));
    await user.type(screen.getByLabelText(/alasan perubahan/i), 'Format mengikuti bawaan');
    await user.click(screen.getByRole('button', { name: /simpan perubahan/i }));

    expect(router.patch).toHaveBeenCalledWith(
        '/jenis-berkas/j1/batas-teknis',
        { alasan: 'Format mengikuti bawaan', expected_updated_at: '2026-09-01T00:00:00Z', format_diizinkan: null, ukuran_maks_kb: 10240 },
        expect.anything(),
    );
});

it('peringatan unggahan nonaktif tidak menampilkan kunci konfigurasi internal', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={canAll} unggahanAktif={false} />);

    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));
    await user.click(screen.getByLabelText(/bukti wajib/i));

    expect(screen.getByText(/Unggahan file sedang dinonaktifkan/)).toBeTruthy();
    expect(screen.queryByText(/berkas\.unggahan_aktif/)).toBeNull();
});

it('hanya menawarkan ubah batas teknis untuk persyaratan yang menerima file', () => {
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={{ create: false, update: false, delete: false, pengaturan_update: true }} />);

    expect(screen.getByRole('button', { name: 'Ubah batas teknis Laporan Capaian Triwulan' })).toBeTruthy();
    // Tanpa mode file semua isian batas teknis nonaktif; menyimpannya hanya menghasilkan catatan audit kosong.
    expect(screen.queryByRole('button', { name: 'Ubah batas teknis Kerangka Acuan Kerja' })).toBeNull();
});

it('persyaratan perencanaan kegiatan menampilkan penanda beranggaran serta mengunggah dan menghapus contoh beralasan', async () => {
    const user = userEvent.setup();
    const remove = vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    const rab = jb({ id: 'j3', nama: 'RAB', tahap: 'kegiatan_perencanaan', wajib: true, hanya_kegiatan_beranggaran: true,
        contoh_berkas: [{ id: 'c1', nama_asli: 'contoh-rab.xlsx', ukuran_bytes: 2048 }] });
    render(<JenisBerkasIndex jenisBerkasList={[...list, rab]} indikators={indikators} can={canAll} />);

    expect(screen.getAllByText('Perencanaan Kegiatan').length).toBeGreaterThan(0);
    expect(screen.getByText(/hanya kegiatan beranggaran/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'contoh-rab.xlsx' }).getAttribute('href')).toBe('/jenis-berkas/j3/contoh/c1');

    await user.click(screen.getByRole('button', { name: 'Hapus contoh contoh-rab.xlsx' }));
    await user.type(screen.getByRole('textbox', { name: /alasan/i }), 'Contoh usang');
    await user.click(screen.getByRole('button', { name: 'Hapus contoh' }));
    expect(remove).toHaveBeenCalledWith('/jenis-berkas/j3/contoh/c1', expect.objectContaining({ data: { alasan: 'Contoh usang' } }));

    const file = new File(['isi'], 'rab-baru.xlsx');
    await user.upload(screen.getByLabelText(/Unggah contoh/), file);
    expect(router.post).toHaveBeenCalledWith('/jenis-berkas/j3/contoh', { file }, expect.objectContaining({ forceFormData: true }));
});

it('penanda hanya kegiatan beranggaran hanya ditawarkan pada tahap perencanaan kegiatan', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));

    expect(screen.queryByLabelText('Hanya kegiatan beranggaran')).toBeNull();
    await user.selectOptions(document.getElementById('jb-tahap') as HTMLSelectElement, 'kegiatan_perencanaan');
    await user.click(screen.getByLabelText('Hanya kegiatan beranggaran'));
    await user.type(screen.getByLabelText(/Nama persyaratan/), 'RAB');
    await user.click(screen.getByRole('button', { name: 'Simpan persyaratan' }));
    expect(router.post).toHaveBeenCalledWith('/jenis-berkas', expect.objectContaining({ tahap: 'kegiatan_perencanaan', hanya_kegiatan_beranggaran: true }), expect.anything());
});
