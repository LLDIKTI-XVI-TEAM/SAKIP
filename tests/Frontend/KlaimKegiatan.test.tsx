import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { KlaimPanel } from '@/Pages/Kegiatan/Partials/KlaimPanel';
import KegiatanPanel from '@/Pages/RencanaAksi/KegiatanPanel';
import type { RencanaAksiPeriode } from '@/Pages/RencanaAksi/types';
import type { KlaimKegiatanPanel } from '@/types/kegiatan';

// jsdom belum mengimplementasikan dialog modal native yang dipakai AuditReasonModal.
const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));

beforeEach(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => { callback(); return 0; });
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    dialogMethods.forEach((name, index) => {
        const descriptor = originalDialogMethods[index];
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    });
});

const panel: KlaimKegiatanPanel = {
    daftar: [{ id: 'k1', rencana_aksi_id: 'ra-1', rencana_aksi: 'IKU-1 — Indikator Satu', status_alur: 'draft', komponen_id: null, komponen: null,
        arah_dampak: 'menambah', catatan: null, can: { hapus: true } }],
    opsiRencanaAksi: [{ id: 'ra-1', label: 'IKU-1 — Indikator Satu', komponen: [{ id: 'komp-1', nama: 'P — Pembilang' }], tanpa_komponen: true }],
    can: { tambah: true },
};

const periode = (id: string, nama: string): RencanaAksiPeriode => ({
    id, nama, urutan: 1, efektif: true, status: 'efektif', nilai: [], skor: { nilai: null, status_perhitungan: 'belum_diisi' }, peringatan_turun: false, komponen_turun: [], tanggal: null, batas_terkunci_pic: null, boleh_tanpa_target: false,
});

const TOTAL_KOSONG = { total: '0', belum_diisi: 0, per_periode: {} };

/** Baris data tabel kegiatan (tanpa baris kepala kolom). */
const barisTabel = (): HTMLElement[] => screen.getAllByRole('row').filter((row) => row.closest('tbody') !== null);

it('panel RA menampilkan total presisi di atas batas Number dan total nol untuk periode tanpa kegiatan', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" kotor={false} periode={[periode('tw1', 'Triwulan I'), periode('tw2', 'Triwulan II')]} komponen={[]} terkunci={[]}
        kegiatan={{ daftar: [{ id: 'k1', periode_id: 'tw1', kegiatan_id: 'keg-1', nama: 'Besar', status: 'rencana', komponen_id: null, arah_dampak: 'menambah', catatan: null,
            anggaran: '999999999999999', sumber_klaim: 'rencana_aksi', kegiatan_bersama: false }],
        total: { total: '9999999999999991', belum_diisi: 0, per_periode: { tw1: { total: '9999999999999991', belum_diisi: 0 } } }, can: { tambah: false, kelola: false } }} />);

    expect(screen.getByText(/^Total Rp\s?9\.999\.999\.999\.999\.991$/)).toBeTruthy();
    const tw2 = within(screen.getByRole('region', { name: 'Triwulan II' }));
    expect(tw2.getByText(/^Total Rp\s?0$/)).toBeTruthy();
    expect(tw2.getByText('Belum ada kegiatan.')).toBeTruthy();
});

it('panel RA menampilkan anggaran per kegiatan, penanda kegiatan bersama dan klaim pengukuran, serta total yang menandai anggaran belum diisi', () => {
    const baris = { periode_id: 'tw1', status: 'rencana', komponen_id: null, arah_dampak: 'menambah' as const, catatan: null };
    render(<KegiatanPanel rencanaAksiId="ra-1" kotor={false} periode={[periode('tw1', 'Triwulan I')]} komponen={[]} terkunci={[]}
        kegiatan={{ daftar: [
            { ...baris, id: 'k1', kegiatan_id: 'keg-1', nama: 'Workshop', anggaran: '1500000', sumber_klaim: 'rencana_aksi', kegiatan_bersama: true },
            { ...baris, id: 'k2', kegiatan_id: 'keg-2', nama: 'Rapat', anggaran: null, sumber_klaim: 'pengukuran', kegiatan_bersama: false },
        ], total: { total: '1500000', belum_diisi: 1, per_periode: { tw1: { total: '1500000', belum_diisi: 1 } } }, can: { tambah: false, kelola: true } }} />);

    const [workshop, rapat] = barisTabel().map((item) => within(item));
    expect(workshop.getByText(/^Rp\s?1\.500\.000$/)).toBeTruthy();
    expect(workshop.getByText('Kegiatan bersama')).toBeTruthy();
    expect(workshop.queryByText('Klaim pengukuran')).toBeNull();
    expect(rapat.getByText('Belum diisi')).toBeTruthy();
    expect(rapat.getByText('Klaim pengukuran')).toBeTruthy();
    expect(rapat.queryByText('Kegiatan bersama')).toBeNull();
    // Klaim pengukuran tidak dapat dikelola di halaman Kegiatan, sehingga tautannya tidak ditawarkan.
    expect(workshop.getByRole('link', { name: 'Kelola kegiatan Workshop' })).toBeTruthy();
    expect(rapat.queryByRole('link', { name: /^Kelola kegiatan/ })).toBeNull();
    // Total rencana aksi tampil di kartu identitas halaman; panel hanya memuat total per periode.
    expect(screen.getByText(/^Total Rp\s?1\.500\.000 \(1 kegiatan belum diisi\)$/)).toBeTruthy();
});

it('mengirim klaim dengan komponen dari rencana aksi terpilih dan mengingatkan klaim tidak mengubah nilai', () => {
    render(<KlaimPanel kegiatanId="keg-1" klaim={panel} />);
    expect(screen.getByText(/tidak mengubah nilai komponen maupun pengukuran/i)).toBeTruthy();

    fireEvent.change(screen.getByLabelText(/Komponen/), { target: { value: 'komp-1' } });
    fireEvent.change(screen.getByLabelText(/Arah dampak/), { target: { value: 'mengurangi' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Tambah klaim' }).closest('form')!);

    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/keg-1/klaim');
    expect(data).toMatchObject({ rencana_aksi_id: 'ra-1', komponen_id: 'komp-1', arah_dampak: 'mengurangi' });
});

it('hapus klaim memerlukan alasan sebelum dikirim', async () => {
    render(<KlaimPanel kegiatanId="keg-1" klaim={panel} />);
    fireEvent.click(screen.getByRole('button', { name: /Hapus klaim ke IKU-1/ }));
    const dialog = within(screen.getByRole('dialog'));
    expect(dialog.getByRole<HTMLButtonElement>('button', { name: 'Hapus' }).disabled).toBe(true);

    fireEvent.change(dialog.getByRole('textbox'), { target: { value: 'Klaim keliru' } });
    await act(async () => { fireEvent.click(dialog.getByRole('button', { name: 'Hapus' })); });

    const [url, options] = vi.mocked(router.delete).mock.calls[0];
    expect(url).toBe('/kegiatan/keg-1/klaim/k1');
    expect(options?.data).toEqual({ alasan: 'Klaim keliru' });
});

it('meneruskan konteks rencana aksi asal saat menghapus klaim', async () => {
    render(<KlaimPanel kegiatanId="keg-1" klaim={panel} rencanaAksiKonteks="ra-1" />);
    fireEvent.click(screen.getByRole('button', { name: /Hapus klaim ke IKU-1/ }));
    const dialog = within(screen.getByRole('dialog'));
    fireEvent.change(dialog.getByRole('textbox'), { target: { value: 'Klaim keliru' } });
    await act(async () => { fireEvent.click(dialog.getByRole('button', { name: 'Hapus' })); });

    const [url, options] = vi.mocked(router.delete).mock.calls[0];
    expect(url).toBe('/kegiatan/keg-1/klaim/k1');
    // Server memakai konteks ini untuk memilih kembali ke daftar kegiatan RA, bukan halaman ubah yang sudah 404.
    expect(options?.data).toEqual({ alasan: 'Klaim keliru', rencana_aksi_konteks: 'ra-1' });
});

it('tanpa rencana aksi yang dapat diklaim tidak menampilkan form dan menjelaskan alasannya', () => {
    render(<KlaimPanel kegiatanId="keg-1" klaim={{ ...panel, opsiRencanaAksi: [], can: { tambah: false }, daftar: [] }} />);
    expect(screen.queryByRole('button', { name: 'Tambah klaim' })).toBeNull();
    expect(screen.getByText(/Belum ada rencana aksi yang dapat diklaim/)).toBeTruthy();
});

it('panel RA menawarkan tambah hanya untuk periode terbuka dan salin hanya ke periode terbuka lain', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" kotor={false} periode={[periode('tw1', 'Triwulan I'), periode('tw2', 'Triwulan II'), periode('tw3', 'Triwulan III')]}
        komponen={[]} terkunci={['tw1']}
        kegiatan={{ daftar: [{ id: 'k1', kegiatan_id: 'keg-1', periode_id: 'tw1', nama: 'Rapat lama', status: 'rencana', komponen_id: null, arah_dampak: 'menambah', catatan: null, anggaran: '0', sumber_klaim: 'rencana_aksi', kegiatan_bersama: false }],
            total: TOTAL_KOSONG, can: { tambah: true, kelola: true } }} />);

    expect(screen.queryByRole('button', { name: /Tambah kegiatan Triwulan I$/ })).toBeNull();
    expect(screen.getByRole('link', { name: 'Kelola kegiatan Rapat lama' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/kegiatan/keg-1');

    fireEvent.click(screen.getByRole('button', { name: /Tambah kegiatan Triwulan II$/ }));
    const dialog = within(screen.getByRole('dialog'));
    expect(dialog.queryByRole('checkbox', { name: 'Triwulan I' })).toBeNull();
    expect(dialog.queryByRole('checkbox', { name: 'Triwulan II' })).toBeNull();

    fireEvent.change(dialog.getByLabelText(/Nama kegiatan/), { target: { value: 'Bimtek' } });
    fireEvent.click(dialog.getByRole('checkbox', { name: 'Triwulan III' }));
    fireEvent.submit(dialog.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);

    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/rencana-aksi/ra-1/kegiatan');
    expect(data).toMatchObject({ periode_id: 'tw2', salin_ke_periode_ids: ['tw3'], nama: 'Bimtek', sasaran_peserta: null });
});

it('pembaca RA melihat komponen snapshot, arah dampak, dan catatan tanpa akses kelola', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" kotor={false} periode={[periode('tw1', 'Triwulan I')]}
        komponen={[{ komponen_id: 'komp-1', kode: 'P', label: 'Pembilang', peran: 'pembilang', bobot: '1', urutan: 1 }]}
        terkunci={['tw1']} kegiatan={{ daftar: [
            { id: 'k1', kegiatan_id: 'keg-1', periode_id: 'tw1', nama: 'Bimtek', status: 'rencana', komponen_id: 'komp-1', arah_dampak: 'mengurangi', catatan: 'Mengurangi kendala pelaksanaan', anggaran: '0', sumber_klaim: 'rencana_aksi', kegiatan_bersama: false },
        ], total: TOTAL_KOSONG, can: { tambah: false, kelola: false } }} />);

    const row = within(barisTabel()[0]);
    expect(row.getByText('Bimtek')).toBeTruthy();
    expect(row.getByText('P — Pembilang')).toBeTruthy();
    expect(row.getByText('Mengurangi')).toBeTruthy();
    expect(row.getByText('Mengurangi kendala pelaksanaan')).toBeTruthy();
    expect(row.queryByRole('link', { name: /^Kelola kegiatan/ })).toBeNull();
    expect(screen.queryByRole('button', { name: /Tambah kegiatan/ })).toBeNull();
});

const klaimKomponen = (komponenId: string | null) => ({ daftar: [{ id: 'k1', kegiatan_id: 'keg-1', periode_id: 'tw1', nama: 'Bimtek', status: 'rencana', komponen_id: komponenId,
    arah_dampak: 'menambah' as const, catatan: null, anggaran: '0', sumber_klaim: 'rencana_aksi' as const, kegiatan_bersama: false }], total: TOTAL_KOSONG, can: { tambah: false, kelola: false } });

it.each([
    [[{ komponen_id: 'komp-1', kode: 'P', label: 'Pembilang', peran: 'pembilang', bobot: '1', urutan: 1 }], null, 'Tanpa komponen tertentu'],
    [[], 'komponen-lama', 'Komponen tidak tersedia pada snapshot'],
])('menjelaskan komponen klaim (%#) tanpa menghilangkan klaim', (komponen, komponenId, label) => {
    render(<KegiatanPanel rencanaAksiId="ra-1" kotor={false} periode={[periode('tw1', 'Triwulan I')]} komponen={komponen} terkunci={[]} kegiatan={klaimKomponen(komponenId)} />);

    const row = within(barisTabel()[0]);
    expect(row.getByText(label)).toBeTruthy();
    expect(row.getByText('Menambah')).toBeTruthy();
});

it('indikator tanpa komponen tidak menampilkan kolom komponen, klaimnya tetap tampil', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" kotor={false} periode={[periode('tw1', 'Triwulan I')]} komponen={[]} terkunci={[]} kegiatan={klaimKomponen(null)} />);

    expect(screen.queryByRole('columnheader', { name: 'Komponen' })).toBeNull();
    const row = within(barisTabel()[0]);
    expect(row.getByText('Bimtek')).toBeTruthy();
    expect(row.getByText('Menambah')).toBeTruthy();
});

it('menonaktifkan tambah kegiatan selama form target berubah belum disimpan', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" kotor periode={[periode('tw1', 'Triwulan I')]} komponen={[]} terkunci={[]}
        kegiatan={{ daftar: [], total: TOTAL_KOSONG, can: { tambah: true, kelola: true } }} />);

    expect(screen.getByRole<HTMLButtonElement>('button', { name: /Tambah kegiatan Triwulan I$/ }).disabled).toBe(true);
    expect(screen.getByText('Simpan atau buang perubahan target sebelum menambah atau mengelola kegiatan.')).toBeTruthy();
});

it('menahan kelola kegiatan saat target kotor dan mengaktifkannya kembali sesudah bersih', () => {
    const props = {
        rencanaAksiId: 'ra-1', periode: [periode('tw1', 'Triwulan I')], komponen: [], terkunci: [],
        kegiatan: { daftar: [{ id: 'k1', kegiatan_id: 'keg-1', periode_id: 'tw1', nama: 'Bimtek', status: 'rencana' as const,
            komponen_id: null, arah_dampak: 'menambah' as const, catatan: null, anggaran: '0', sumber_klaim: 'rencana_aksi' as const, kegiatan_bersama: false }], total: TOTAL_KOSONG, can: { tambah: false, kelola: true } },
    };
    const { rerender } = render(<KegiatanPanel {...props} kotor />);

    expect(screen.getByRole('link', { name: 'Kelola kegiatan Bimtek' }).getAttribute('aria-disabled')).toBe('true');
    expect(screen.getByText('Simpan atau buang perubahan target sebelum menambah atau mengelola kegiatan.')).toBeTruthy();
    fireEvent.click(screen.getByRole('link', { name: 'Kelola kegiatan Bimtek' }));

    rerender(<KegiatanPanel {...props} kotor={false} />);
    expect(screen.getByRole('link', { name: 'Kelola kegiatan Bimtek' }).getAttribute('aria-disabled')).toBe('false');
    expect(screen.queryByText('Simpan atau buang perubahan target sebelum menambah atau mengelola kegiatan.')).toBeNull();
    expect(screen.getByRole('link', { name: 'Kelola kegiatan Bimtek' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/kegiatan/keg-1');
});
