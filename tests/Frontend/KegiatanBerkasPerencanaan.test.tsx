import { router } from '@inertiajs/react';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { BerkasPerencanaanPanel } from '@/Pages/Kegiatan/Partials/BerkasPerencanaanPanel';
import type { BerkasKegiatanItem, BerkasPerencanaanKegiatan, PersyaratanPerencanaanKegiatan } from '@/types/kegiatan';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: '/rencana-aksi/ra-1/kegiatan/k1', props: { auth: {}, pengaturan: {}, flash: {} } }),
}));

const dialogMethods = ['showModal', 'close'] as const;
const asli = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native yang dipakai AuditReasonModal.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = asli[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'delete').mockImplementation(() => undefined);
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const rab = (atribut: Partial<PersyaratanPerencanaanKegiatan> = {}): PersyaratanPerencanaanKegiatan => ({
    id: 'jb-rab', nama: 'RAB', keterangan: null, wajib: true, semua_mode_wajib: false, izinkan_file: true, izinkan_tautan: true, izinkan_teks: false,
    format_diizinkan: 'xlsx,pdf', ukuran_maks_kb: 10240, hanya_kegiatan_beranggaran: true,
    pemenuhan: { terpenuhi: false, mode_kurang: ['file', 'tautan'] }, berkas: [], contoh: [{ id: 'c1', nama_asli: 'contoh-rab.xlsx' }], ...atribut,
});
const panel = (atribut: Partial<BerkasPerencanaanKegiatan> = {}): BerkasPerencanaanKegiatan => ({
    persyaratan: [rab()], alasan_tolak_unggah: null, alasan_tolak_ganti: null, alasan_tolak_hapus: null, ...atribut,
});
const berkas = (atribut: Partial<BerkasKegiatanItem> = {}): BerkasKegiatanItem => ({
    id: 'b1', mode: 'file', nama_asli: 'rab.xlsx', ukuran_bytes: 1024, tautan: null, isi_teks: null, menggantikan_id: null, created_at: null, ...atribut,
});

it('menampilkan persyaratan sebagai tabel bersama berkolom tetap seperti bukti dukung rencana aksi', () => {
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel()} />);

    expect(screen.getByRole('region', { name: 'Berkas perencanaan kegiatan' })).toBeTruthy();
    const tabel = screen.getByRole('table', { name: 'Persyaratan perencanaan kegiatan' });
    expect(within(tabel).getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Persyaratan', 'Mode diizinkan', 'Status', 'Aksi']);
    const [, baris] = within(tabel).getAllByRole('row');
    expect(within(baris).getByText('RAB')).toBeTruthy();
    expect(within(baris).getByText('Kegiatan beranggaran')).toBeTruthy();
    // Mode dan batas berkas dipisah per baris agar tidak patah di tengah daftar format.
    expect(within(baris).getByText('File, Tautan')).toBeTruthy();
    expect(within(baris).getByText('xlsx, pdf · maks. 10 MB')).toBeTruthy();
    // Badge tidak membungkus teks; rincian mode kurang tampil sebagai teks biasa agar tidak meluber ke kolom aksi.
    expect(within(baris).getByText('Belum terpenuhi')).toBeTruthy();
    expect(within(baris).getByText('Kurang: File, Tautan').tagName).toBe('P');
    expect(within(baris).getByRole('link', { name: /contoh-rab.xlsx/ }).getAttribute('href')).toBe('/jenis-berkas/jb-rab/contoh/c1');
    expect(screen.getByText('Belum ada berkas yang dilampirkan.')).toBeTruthy();
});

it('aksi penuhi berupa tombol ikon dengan tooltip dan modal lampiran bersama mengikuti mode persyaratan', async () => {
    const user = userEvent.setup();
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel()} />);

    const tombol = screen.getByRole('button', { name: 'Penuhi RAB' });
    expect(tombol.textContent).toBe('');
    expect(within(tombol.parentElement as HTMLElement).getByRole('tooltip').textContent).toBe('Penuhi persyaratan');

    await user.click(tombol);
    const dialog = within(screen.getByRole('dialog', { name: 'Penuhi RAB' }));
    expect(dialog.queryByRole('button', { name: 'Teks' })).toBeNull();
    expect(dialog.getByRole('button', { name: 'File' }).getAttribute('aria-pressed')).toBe('true');
    expect(dialog.getByLabelText('File lampiran 1').getAttribute('accept')).toBe('.xlsx,.pdf');
    await user.click(dialog.getByRole('button', { name: 'Tautan' }));
    await user.type(dialog.getByLabelText('Tautan lampiran 1'), 'https://contoh.go.id/rab');
    await user.click(screen.getByRole('button', { name: 'Simpan berkas' }));

    const [url, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/k1/berkas');
    expect(options).toMatchObject({ forceFormData: true });
    // Payload tetap datar sesuai kontrak endpoint berkas kegiatan, bukan larik lampiran.
    expect(data).toEqual({ jenis_berkas_id: 'jb-rab', mode: 'tautan', file: null, tautan: 'https://contoh.go.id/rab', isi_teks: '', menggantikan_id: '', alasan_koreksi: '' });
});

it('berkas terlampir memakai item lampiran bersama dengan aksi ganti dan hapus berupa ikon bertooltip', async () => {
    const user = userEvent.setup();
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel({ persyaratan: [rab({ berkas: [berkas()], pemenuhan: { terpenuhi: true, mode_kurang: [] } })] })} />);

    expect(screen.getByText('Terpenuhi')).toBeTruthy();
    const daftar = within(screen.getByRole('list', { name: 'Berkas terlampir (1)' }));
    expect(daftar.getByText('rab.xlsx')).toBeTruthy();
    expect(daftar.getByText('RAB')).toBeTruthy();
    expect(daftar.getByRole('link', { name: /Unduh file/ }).getAttribute('href')).toBe('/kegiatan/k1/berkas/b1');

    const ganti = daftar.getByRole('button', { name: 'Ganti berkas rab.xlsx' });
    expect(ganti.textContent).toBe('');
    expect(within(ganti.parentElement as HTMLElement).getByRole('tooltip').textContent).toBe('Ganti berkas');
    const hapus = daftar.getByRole('button', { name: 'Hapus berkas rab.xlsx' });
    expect(within(hapus.parentElement as HTMLElement).getByRole('tooltip').textContent).toBe('Hapus berkas');

    await user.click(ganti);
    const dialog = within(screen.getByRole('dialog', { name: 'Ganti berkas RAB' }));
    const file = new File(['isi'], 'rab-revisi.xlsx');
    await user.upload(dialog.getByLabelText('File lampiran 1'), file);
    expect((dialog.getByLabelText(/Alasan penggantian/) as HTMLTextAreaElement).required).toBe(true);
    await user.type(dialog.getByLabelText(/Alasan penggantian/), 'Nilai satuan diperbaiki');
    fireEvent.submit(document.getElementById('berkas-kegiatan-form')!);
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ jenis_berkas_id: 'jb-rab', mode: 'file', file, menggantikan_id: 'b1', alasan_koreksi: 'Nilai satuan diperbaiki' });
    await user.click(screen.getByRole('button', { name: 'Batal' }));

    await user.click(hapus);
    await user.type(screen.getByRole('textbox'), 'Salah unggah');
    await user.click(screen.getByRole('button', { name: 'Hapus' }));
    expect(router.delete).toHaveBeenCalledWith('/kegiatan/k1/berkas/b1', expect.objectContaining({ data: { alasan: 'Salah unggah' } }));
});

it('menyembunyikan ganti dan hapus tetapi tetap menawarkan penuhi saat RA pengklaim sudah diajukan', () => {
    const alasan = 'Berkas perencanaan kegiatan tidak dapat dihapus atau diganti sejak rencana aksi yang mengklaimnya diajukan; koreksi lewat pengembalian rencana aksi.';
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel({ persyaratan: [rab({ berkas: [berkas({ mode: 'teks', nama_asli: null, isi_teks: 'Rincian' })] })],
        alasan_tolak_ganti: alasan, alasan_tolak_hapus: alasan })} />);

    expect(screen.queryByRole('button', { name: /Hapus berkas/ })).toBeNull();
    expect(screen.queryByRole('button', { name: /Ganti berkas/ })).toBeNull();
    expect(screen.getByRole('button', { name: 'Penuhi RAB' })).toBeTruthy();
    expect(screen.getByText(/tidak dapat dihapus atau diganti sejak rencana aksi/)).toBeTruthy();
});

it('tanpa izin unggah menyembunyikan kolom aksi dan menampilkan alasannya', () => {
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel({ alasan_tolak_unggah: 'Tahun kegiatan sudah ditutup.' })} />);

    const tabel = screen.getByRole('table', { name: 'Persyaratan perencanaan kegiatan' });
    expect(within(tabel).queryByRole('columnheader', { name: 'Aksi' })).toBeNull();
    expect(screen.queryByRole('button', { name: /^Penuhi / })).toBeNull();
    expect(screen.getByText('Tahun kegiatan sudah ditutup.')).toBeTruthy();
});

it('tidak tampil bila tidak ada persyaratan perencanaan kegiatan', () => {
    const { container } = render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel({ persyaratan: [] })} />);
    expect(container.innerHTML).toBe('');
});

it('panel pelaksanaan memakai unduhan terpisah dan capability append-only dari server', () => {
    render(<BerkasPerencanaanPanel kegiatanId="k1" tahap="pelaksanaan" berkas={panel({
        persyaratan: [rab({ nama: 'Dokumentasi', berkas: [berkas({ nama_asli: 'foto.pdf' })], contoh: [] })],
        alasan_tolak_ganti: 'Kegiatan sudah terlaksana.', alasan_tolak_hapus: 'Kegiatan sudah terlaksana.',
    })} />);
    expect(screen.getByRole('region', { name: 'Berkas pelaksanaan kegiatan' })).toBeTruthy();
    expect(screen.getByRole('table', { name: 'Persyaratan pelaksanaan kegiatan' })).toBeTruthy();
    expect(screen.getByRole('link', { name: /Unduh file/ }).getAttribute('href')).toBe('/kegiatan/k1/pelaksanaan/berkas/b1');
    expect(screen.queryByRole('button', { name: /Ganti berkas/ })).toBeNull();
    expect(screen.queryByRole('button', { name: /Hapus berkas/ })).toBeNull();
    expect(screen.getByRole('button', { name: 'Penuhi Dokumentasi' })).toBeTruthy();
});
