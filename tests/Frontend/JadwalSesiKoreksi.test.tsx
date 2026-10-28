import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { SesiKoreksiPanel } from '@/Components/Jadwal/SesiKoreksiPanel';

const options = {
    jadwalId: 'jadwal-1', revisi: 7, canReopen: true, koreksi: null,
    indikator: [{ id: 'indikator-1', nama: 'Indikator uji' }], periode: [{ periode_id: 'periode-1', nama: 'Triwulan I' }],
};

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
    vi.spyOn(HTMLDialogElement.prototype, 'showModal').mockImplementation(function (this: HTMLDialogElement) { this.open = true; });
    vi.spyOn(HTMLDialogElement.prototype, 'close').mockImplementation(function (this: HTMLDialogElement) { this.open = false; });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

async function kirim() {
    render(<SesiKoreksiPanel {...options} />);
    fireEvent.change(screen.getByLabelText(/Awal sesi/), { target: { value: '2026-11-01T08:00' } });
    fireEvent.change(screen.getByLabelText(/Akhir sesi/), { target: { value: '2026-11-02T17:00' } });
    fireEvent.click(screen.getByRole('checkbox', { name: 'Rencana Aksi' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Indikator uji' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Triwulan I' }));
    fireEvent.click(screen.getByRole('button', { name: 'Buka kembali untuk koreksi' }));
    const modal = within(screen.getByRole('dialog'));
    expect(modal.getByRole<HTMLButtonElement>('button', { name: 'Buka sesi koreksi' }).disabled).toBe(true);
    fireEvent.change(modal.getByLabelText(/Alasan/), { target: { value: 'Koreksi salah input' } });
    await act(async () => { fireEvent.click(modal.getByRole('button', { name: 'Buka sesi koreksi' })); });
    return modal;
}

it('mengirim waktu WITA dan scope eksplisit setelah alasan dikonfirmasi tanpa submit ganda', async () => {
    const modal = await kirim();
    fireEvent.click(modal.getByRole('button', { name: 'Buka sesi koreksi' }));
    expect(router.post).toHaveBeenCalledTimes(1);
    const [url, payload] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/jadwal/jadwal-1/buka-kembali');
    expect(payload).toEqual({ expected_revisi: 7, alasan: 'Koreksi salah input', koreksi_mulai: '2026-11-01T08:00', koreksi_sampai: '2026-11-02T17:00',
        lingkup_koreksi: { jenis_objek: ['rencana_aksi'], indikator_ids: ['indikator-1'], periode_ids: ['periode-1'] } });
});

it('mempertahankan isian serta alasan saat validasi server gagal', async () => {
    await kirim();
    const visit = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { visit?.onError?.({ 'lingkup_koreksi.periode_ids': 'Periode bukan anggota jadwal.' }); });
    expect(screen.getByText('Periode bukan anggota jadwal.')).toBeTruthy();
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Alasan/).value).toBe('Koreksi salah input');
    expect(screen.getByLabelText<HTMLInputElement>(/Awal sesi/).value).toBe('2026-11-01T08:00');
    expect(screen.getByRole<HTMLInputElement>('checkbox', { name: 'Triwulan I' }).checked).toBe(true);
});

it('meminta pembacaan terbaru setelah hasil jaringan ambigu tanpa mengulang mutasi', async () => {
    await kirim();
    const visit = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { visit?.onNetworkError?.(new Error('Koneksi terputus')); });
    expect(screen.getByText(/Hasil pembukaan belum terkonfirmasi/)).toBeTruthy();
    expect(screen.getByRole<HTMLAnchorElement>('link', { name: 'Periksa jadwal terbaru' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
    fireEvent.click(screen.getByRole('button', { name: 'Buka kembali untuk koreksi' }));
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('menampilkan sesi berakhir tanpa menawarkan pembukaan tanpa capability', () => {
    render(<SesiKoreksiPanel {...options} canReopen={false} koreksi={{ status: 'berakhir', mulai: '2026-11-01T00:00:00Z', sampai: '2026-11-02T09:00:00Z',
        lingkup: { jenis_objek: ['rencana_aksi'], indikator_ids: ['indikator-1'], periode_ids: ['periode-1'] } }} />);
    expect(screen.getByText('Sesi koreksi berakhir')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Buka kembali untuk koreksi' })).toBeNull();
    expect(screen.getByText(/PIC tidak otomatis mendapat akses/)).toBeTruthy();
});
