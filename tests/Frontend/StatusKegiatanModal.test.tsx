import { createRef } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { StatusKegiatanModal } from '@/Pages/Kegiatan/Partials/StatusKegiatanModal';
import type { StatusTujuan } from '@/types/kegiatan';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: '/kegiatan?status=rencana', props: { auth: {}, pengaturan: {}, flash: {} } }),
}));

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => { callback(); return 0; });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

const kegiatan = { id: 'k1', nama: 'Monitoring kinerja', tahun: 2026, periode: 'Triwulan I', version_token: 'v1' };
const opsiPeriodeTujuan = [{ id: 'tw2', nama: 'Triwulan II' }, { id: 'tw3', nama: 'Triwulan III' }];

const buka = (status: StatusTujuan) => {
    const onClose = vi.fn();
    render(<StatusKegiatanModal kegiatan={kegiatan} status={status} opsiPeriodeTujuan={status === 'ditunda' ? opsiPeriodeTujuan : []}
        fokusCadangan={createRef<HTMLElement>()} onClose={onClose} />);
    return onClose;
};
const justifikasi = () => screen.getByLabelText<HTMLTextAreaElement>(/Justifikasi/);
const isi = (teks: string) => fireEvent.change(justifikasi(), { target: { value: teks } });
const klik = (nama: string) => fireEvent.click(screen.getByRole('button', { name: nama }));
const opsiKirim = () => vi.mocked(router.post).mock.calls[0][2]!;

it('meminta konfirmasi final sebelum menunda dan mengirim periode tujuan', () => {
    buka('ditunda');
    expect(screen.getByText('Monitoring kinerja · Triwulan I 2026')).toBeTruthy();
    fireEvent.change(screen.getByLabelText(/Periode tujuan/), { target: { value: 'tw2' } });
    isi('Narasumber berhalangan.');
    klik('Lanjutkan');
    expect(router.post).not.toHaveBeenCalled();

    const dialog = screen.getByRole('dialog', { name: 'Tunda kegiatan?' });
    expect(dialog.textContent).toContain('Status Ditunda final dan tidak dapat diubah.');
    expect(dialog.textContent).toContain('Kegiatan lanjutan dibuat di Triwulan II.');
    expect(document.activeElement).toBe(screen.getByText('Status Ditunda final dan tidak dapat diubah.'));

    klik('Ya, tunda');
    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/k1/status');
    expect(data).toEqual({ status: 'ditunda', justifikasi: 'Narasumber berhalangan.', periode_tujuan_id: 'tw2', expected_state: 'v1' });
});

it('menandai tidak terlaksana langsung tanpa periode tujuan', () => {
    buka('tidak_terlaksana');
    expect(screen.getByRole('dialog', { name: 'Tandai tidak terlaksana' })).toBeTruthy();
    expect(screen.queryByLabelText(/Periode tujuan/)).toBeNull();
    isi('Anggaran diblokir.');
    klik('Simpan');
    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/k1/status');
    expect(data).toMatchObject({ status: 'tidak_terlaksana', justifikasi: 'Anggaran diblokir.', expected_state: 'v1' });
});

it('kembali dari konfirmasi batal mempertahankan isian', () => {
    buka('batal');
    isi('Kebijakan berubah.');
    klik('Lanjutkan');
    expect(screen.getByRole('dialog', { name: 'Batalkan kegiatan?' }).textContent).toContain('Status Batal final dan tidak dapat diubah.');
    klik('Kembali');
    expect(justifikasi().value).toBe('Kebijakan berubah.');
    expect(document.activeElement).toBe(justifikasi());
});

it('mengabaikan klik ganda yang mendarat di tombol konfirmasi final', () => {
    buka('batal');
    isi('Kebijakan berubah.');
    klik('Lanjutkan');
    const kirim = screen.getByRole('button', { name: 'Ya, batalkan' });
    fireEvent.click(kirim, { detail: 2 });
    expect(router.post).not.toHaveBeenCalled();

    fireEvent.click(kirim, { detail: 1 });
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('klik ganda Kembali tidak menutup modal lewat tombol Tutup', () => {
    const onClose = buka('batal');
    isi('Kebijakan berubah.');
    klik('Lanjutkan');
    klik('Kembali');
    const tutup = screen.getByRole('button', { name: 'Tutup' });
    fireEvent.click(tutup, { detail: 2 });
    expect(onClose).not.toHaveBeenCalled();

    fireEvent.click(tutup, { detail: 1 });
    expect(onClose).toHaveBeenCalledTimes(1);
});

it('menampilkan galat justifikasi pada langkah isi', async () => {
    buka('batal');
    isi('Kebijakan berubah.');
    klik('Lanjutkan');
    klik('Ya, batalkan');
    await act(async () => { opsiKirim().onError?.({ justifikasi: 'Justifikasi wajib diisi.' }); });
    const textarea = justifikasi();
    expect(document.getElementById(textarea.getAttribute('aria-describedby') ?? '')?.textContent).toBe('Justifikasi wajib diisi.');
    expect(document.activeElement).toBe(textarea);
});

it.each([
    ['status', 'Status kegiatan sudah final dan tidak dapat diubah.'],
    ['expected_state', 'Kegiatan telah berubah. Muat ulang sebelum mengubah status.'],
])('menampilkan galat %s sebagai alert', async (field, pesan) => {
    buka('batal');
    isi('Draf alasan.');
    klik('Lanjutkan');
    klik('Ya, batalkan');
    await act(async () => { opsiKirim().onError?.({ [field]: pesan }); });
    expect(screen.getByRole('dialog', { name: 'Batalkan kegiatan' })).toBeTruthy();
    expect(screen.getByRole('alert').textContent).toContain(pesan);
    expect(justifikasi().value).toBe('Draf alasan.');
});

it('menahan penutupan saat request berjalan dan setelah gagal jaringan', async () => {
    const onClose = buka('tidak_terlaksana');
    isi('Anggaran diblokir.');
    klik('Simpan');
    const options = opsiKirim();
    await act(async () => { options.onStart?.({} as never); });
    fireEvent.keyDown(window, { key: 'Escape' });
    fireEvent.click(screen.getByRole('dialog'));
    expect(onClose).not.toHaveBeenCalled();
    expect(screen.queryByRole('button', { name: 'Tutup dialog' })).toBeNull();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Tutup' }).disabled).toBe(true);
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan' }).disabled).toBe(true);

    await act(async () => { options.onNetworkError?.({} as never); options.onFinish?.({} as never); });
    fireEvent.keyDown(window, { key: 'Escape' });
    fireEvent.click(screen.getByRole('dialog'));
    expect(onClose).not.toHaveBeenCalled();
    expect(justifikasi().value).toBe('Anggaran diblokir.');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan' }).disabled).toBe(true);
    expect(screen.getByRole('link', { name: 'Muat ulang data kegiatan' }).getAttribute('href')).toBe('/kegiatan?status=rencana');
});
