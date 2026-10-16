import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { KegiatanModal } from '@/Pages/Kegiatan/Partials/KegiatanModal';
import Index from '@/Pages/Kegiatan/Index';
import Form from '@/Pages/Kegiatan/Form';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { kegiatan: true } }, pengaturan: {}, flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => { callback(); return 0; });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

const a = { id: 'unit-a', nama: 'Unit A' };
const b = { id: 'unit-b', nama: 'Unit B' };
const period = { id: 'p1', nama: 'Triwulan I' };
const item = { id: 'k1', unit_id: a.id, periode_id: period.id, nama: 'Kegiatan audit', tujuan: 'Sintetis', tahun: 2026, status: 'rencana', tanggal_rencana: null, unit: a, periode: period, can: { update: true }, version_token: 'v1' };
const indexProps = { kegiatan: { data: [item], links: [], from: 1, to: 1, total: 1 }, filters: { tahun: '2026', unit_id: '', periode_id: '', status: '' }, unitOptions: [a, b], periodeOptions: [period], creatableUnitOptions: [a], editableUnitOptions: [a, b], activePeriodeOptions: [period], tahunDefault: 2026, can: { create: true } };

it('mempertahankan draft dan error ketika opsi server berubah setelah validasi', async () => {
    const props = { isOpen: true, onClose: vi.fn(), kegiatan: null, tahunDefault: 2026, periodeOptions: [period], unitOptions: [a, b] };
    const { rerender } = render(<KegiatanModal {...props} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draft yang harus dipertahankan' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ tujuan: 'Tujuan wajib diisi.' }); });
    rerender(<KegiatanModal {...props} unitOptions={[b]} />);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Draft yang harus dipertahankan');
    expect(screen.getByText('Tujuan wajib diisi.')).toBeTruthy();
});

it('aksi ubah membuka halaman ubah kegiatan beserta klaim RA, bukan modal', () => {
    render(<Index {...indexProps} />);
    const ubah = screen.getByRole('link', { name: 'Ubah rencana kegiatan dan klaim rencana aksi Kegiatan audit' });
    expect(ubah.getAttribute('href')).toBe('/kegiatan/k1/edit');
    fireEvent.click(ubah);
    expect(screen.queryByRole('dialog')).toBeNull();
});

it('tidak mengganti opsi periode aktif kosong dengan opsi baca nonaktif', () => {
    render(<Index {...indexProps} activePeriodeOptions={[]} />);
    fireEvent.click(screen.getByRole('button', { name: 'Buat kegiatan' }));
    expect(within(screen.getByRole('dialog')).queryByRole('option', { name: 'Triwulan I' })).toBeNull();
});

it('mempertahankan draft dan menahan dismissal setelah hasil network belum pasti', async () => {
    render(<Index {...indexProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Buat kegiatan' }));
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Simpan mungkin sudah terjadi' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onNetworkError?.({} as never); });
    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Simpan mungkin sudah terjadi');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan kegiatan' }).disabled).toBe(true);
    expect(screen.getByRole('link', { name: 'Muat ulang data kegiatan' }).getAttribute('href')).toBe('/kegiatan');
});

it('mengirim field rencana tanpa anggaran, menahan pending dan menutup sesudah sukses', async () => {
    const onClose = vi.fn();
    render(<KegiatanModal isOpen onClose={onClose} tahunDefault={2026} unitOptions={[a]} periodeOptions={[period]} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Rencana baru' } });
    fireEvent.change(screen.getByLabelText(/Tujuan/), { target: { value: 'Layanan unit' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const [url, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan');
    expect(data).toMatchObject({ unit_id: a.id, periode_id: period.id, tahun: 2026, nama: 'Rencana baru', sasaran_peserta: null });
    expect(data).not.toHaveProperty('anggaran');
    expect(data).not.toHaveProperty('status');
    await act(async () => { options?.onStart?.({} as never); });
    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
    expect(onClose).not.toHaveBeenCalled();
    await act(async () => { await options?.onSuccess?.({ props: { errors: {} } } as never); });
    expect(onClose).toHaveBeenCalledTimes(1);
});

it.each([401, 403, 419, 500])('menahan dismissal dan menyediakan recovery eksplisit sesudah HTTP %s', async (status) => {
    const onClose = vi.fn();
    render(<KegiatanModal isOpen onClose={onClose} tahunDefault={2026} unitOptions={[a]} periodeOptions={[period]} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draft sesi' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onHttpException?.({ status, data: {} } as never); });
    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(onClose).not.toHaveBeenCalled();
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Draft sesi');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan kegiatan' }).disabled).toBe(true);
    if (status === 401) expect(screen.getByRole('link', { name: 'Masuk ulang' })).toBeTruthy();
    else if (status === 419) expect(screen.getByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
    else expect(screen.getByRole('link', { name: 'Muat ulang data kegiatan' })).toBeTruthy();
});

it.each(['page', 'modal'])('tidak membuka replay %s setelah request dibatalkan tanpa konfirmasi', async (mode) => {
    const props = { kegiatan: null, tahunDefault: 2026, unitOptions: [a], periodeOptions: [period] };
    render(mode === 'page' ? <Form {...props} /> : <KegiatanModal {...props} isOpen onClose={vi.fn()} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draft pembatalan' } });
    const form = screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!;
    fireEvent.submit(form);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onCancel?.(); options?.onFinish?.({} as never); });
    fireEvent.submit(form);
    expect(router.post).toHaveBeenCalledTimes(1);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Draft pembatalan');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan kegiatan' }).disabled).toBe(true);
    expect(screen.getByRole('link', { name: mode === 'page' ? 'Muat ulang form' : 'Muat ulang data kegiatan' })).toBeTruthy();
});

it('menerima nama hingga 500 karakter dan tujuan opsional, termasuk saat mengedit tujuan kosong', () => {
    render(<KegiatanModal isOpen onClose={vi.fn()} tahunDefault={2026} periodeOptions={[period]} unitOptions={[a]} />);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).maxLength).toBe(500);
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Tujuan/).required).toBe(false);
    cleanup();

    const rencana = { id: 'k1', unit_id: a.id, tahun: 2026, periode_id: period.id, nama: 'Kegiatan audit', tujuan: null, sasaran_peserta: null,
        keterangan_peserta: null, lokasi: null, tanggal_rencana: null, status: 'rencana', version_token: 'v1' };
    render(<Form kegiatan={rencana} tahunDefault={2026} unitOptions={[a]} periodeOptions={[period]} />);
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Tujuan/).value).toBe('');
    expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/kegiatan');
});
