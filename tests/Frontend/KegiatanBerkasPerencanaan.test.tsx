import { router } from '@inertiajs/react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { BerkasPerencanaanPanel } from '@/Pages/Kegiatan/Partials/BerkasPerencanaanPanel';
import type { BerkasKegiatanItem, BerkasPerencanaanKegiatan, PersyaratanPerencanaanKegiatan } from '@/types/kegiatan';

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

it('menampilkan pemenuhan dan contoh, lalu mengunggah berkas sesuai mode persyaratan', async () => {
    const user = userEvent.setup();
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel()} />);

    expect(screen.getByText('Kurang: File, Tautan')).toBeTruthy();
    expect(screen.getByText('Kegiatan beranggaran')).toBeTruthy();
    expect(screen.getByRole('link', { name: /contoh-rab.xlsx/ }).getAttribute('href')).toBe('/jenis-berkas/jb-rab/contoh/c1');

    await user.click(screen.getByRole('button', { name: 'Unggah' }));
    expect(screen.queryByRole('button', { name: 'Teks' })).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Tautan' }));
    await user.type(screen.getByLabelText(/^Tautan/), 'https://contoh.go.id/rab');
    await user.click(screen.getByRole('button', { name: 'Simpan berkas' }));
    const [url, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/k1/berkas');
    expect(data).toMatchObject({ jenis_berkas_id: 'jb-rab', mode: 'tautan', tautan: 'https://contoh.go.id/rab', menggantikan_id: '' });
    expect(options).toMatchObject({ forceFormData: true });
});

it('mengganti berkas dengan menautkan berkas lama dan menghapus dengan alasan', async () => {
    const user = userEvent.setup();
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel({ persyaratan: [rab({ berkas: [berkas()], pemenuhan: { terpenuhi: true, mode_kurang: [] } })] })} />);

    expect(screen.getByText('Terpenuhi')).toBeTruthy();
    expect(screen.getByRole('link', { name: /Unduh file/ }).getAttribute('href')).toBe('/kegiatan/k1/berkas/b1');
    await user.click(screen.getByRole('button', { name: 'Ganti berkas rab.xlsx' }));
    const file = new File(['isi'], 'rab-revisi.xlsx');
    await user.upload(screen.getByLabelText('Berkas'), file);
    expect((screen.getByLabelText(/Alasan penggantian/) as HTMLTextAreaElement).required).toBe(true);
    await user.type(screen.getByLabelText(/Alasan penggantian/), 'Nilai satuan diperbaiki');
    // jsdom tidak menganggap input file hasil upload memenuhi `required`; submit form langsung.
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan berkas' }).closest('form')!);
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ mode: 'file', file, menggantikan_id: 'b1', alasan_koreksi: 'Nilai satuan diperbaiki' });
    await user.click(screen.getByRole('button', { name: 'Batal' }));

    await user.click(screen.getByRole('button', { name: 'Hapus berkas rab.xlsx' }));
    await user.type(screen.getByRole('textbox'), 'Salah unggah');
    await user.click(screen.getByRole('button', { name: 'Hapus' }));
    expect(router.delete).toHaveBeenCalledWith('/kegiatan/k1/berkas/b1', expect.objectContaining({ data: { alasan: 'Salah unggah' } }));
});

it('menyembunyikan ganti dan hapus tetapi tetap menawarkan unggah saat RA pengklaim sudah diajukan', () => {
    const alasan = 'Berkas perencanaan kegiatan tidak dapat dihapus atau diganti sejak rencana aksi yang mengklaimnya diajukan; koreksi lewat pengembalian rencana aksi.';
    render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel({ persyaratan: [rab({ berkas: [berkas({ mode: 'teks', nama_asli: null, isi_teks: 'Rincian' })] })],
        alasan_tolak_ganti: alasan, alasan_tolak_hapus: alasan })} />);

    expect(screen.queryByRole('button', { name: /Hapus berkas/ })).toBeNull();
    expect(screen.queryByRole('button', { name: /Ganti berkas/ })).toBeNull();
    expect(screen.getByRole('button', { name: 'Unggah' })).toBeTruthy();
    expect(screen.getByText(/tidak dapat dihapus atau diganti sejak rencana aksi/)).toBeTruthy();
});

it('tidak tampil bila tidak ada persyaratan perencanaan kegiatan', () => {
    const { container } = render(<BerkasPerencanaanPanel kegiatanId="k1" berkas={panel({ persyaratan: [] })} />);
    expect(container.innerHTML).toBe('');
});
