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
    opsiRencanaAksi: [{ id: 'ra-1', label: 'IKU-1 — Indikator Satu', komponen: [{ id: 'komp-1', nama: 'P — Pembilang' }] }],
    can: { tambah: true },
};

const periode = (id: string, nama: string): RencanaAksiPeriode => ({
    id, nama, urutan: 1, efektif: true, status: 'efektif', nilai: [], skor: { nilai: null, status_perhitungan: 'belum_diisi' }, peringatan_turun: false, komponen_turun: [],
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

it('tanpa rencana aksi yang dapat diklaim tidak menampilkan form dan menjelaskan alasannya', () => {
    render(<KlaimPanel kegiatanId="keg-1" klaim={{ ...panel, opsiRencanaAksi: [], can: { tambah: false }, daftar: [] }} />);
    expect(screen.queryByRole('button', { name: 'Tambah klaim' })).toBeNull();
    expect(screen.getByText(/Belum ada rencana aksi yang dapat diklaim/)).toBeTruthy();
});

it('panel RA menawarkan tambah hanya untuk periode terbuka dan salin hanya ke periode terbuka lain', () => {
    render(<KegiatanPanel rencanaAksiId="ra-1" periode={[periode('tw1', 'Triwulan I'), periode('tw2', 'Triwulan II'), periode('tw3', 'Triwulan III')]}
        komponen={[]} terkunci={['tw1']}
        kegiatan={{ daftar: [{ id: 'k1', kegiatan_id: 'keg-1', periode_id: 'tw1', nama: 'Rapat lama', status: 'rencana', komponen_id: null, arah_dampak: 'menambah', catatan: null }],
            can: { tambah: true, kelola: true } }} />);

    expect(screen.queryByRole('button', { name: /Tambah kegiatan Triwulan I$/ })).toBeNull();
    expect(screen.getByRole('link', { name: 'Kelola klaim' }).getAttribute('href')).toBe('/kegiatan/keg-1/edit');

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
