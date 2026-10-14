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

it('memakai opsi update yang berbeda dari create ketika edit lewat tombol aksi', () => {
    render(<Index {...indexProps} />);
    fireEvent.click(screen.getByRole('button', { name: 'Edit kegiatan Kegiatan audit' }));
    const dialog = within(screen.getByRole('dialog', { name: 'Edit rencana kegiatan' }));
    expect(dialog.queryByRole('option', { name: 'Unit B' })).not.toBeNull();
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
    render(<KegiatanModal isOpen onClose={onClose} kegiatan={null} tahunDefault={2026} unitOptions={[a]} periodeOptions={[period]} />);
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
    render(<KegiatanModal isOpen onClose={onClose} kegiatan={null} tahunDefault={2026} unitOptions={[a]} periodeOptions={[period]} />);
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

it('edit mempertahankan token awal saat props record yang sama berubah dan reset saat identitas berganti', () => {
    const props = { isOpen: true, onClose: vi.fn(), kegiatan: item, tahunDefault: 2026, unitOptions: [a, b], periodeOptions: [period] };
    const { rerender } = render(<KegiatanModal {...props} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Koreksi lokal' } });
    fireEvent.change(screen.getByLabelText(/Unit/), { target: { value: b.id } });
    rerender(<KegiatanModal {...props} kegiatan={{ ...item, nama: 'Perubahan pengguna lain', version_token: 'v2' }} />);
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan perubahan' }).closest('form')!);
    expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual([
        '/kegiatan/k1', expect.objectContaining({ nama: 'Koreksi lokal', unit_id: b.id, expected_state: 'v1' }),
    ]);
    rerender(<KegiatanModal {...props} kegiatan={{ ...item, id: 'k2', nama: 'Record kedua', version_token: 'v3' }} />);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Record kedua');
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
    render(<KegiatanModal isOpen onClose={vi.fn()} kegiatan={{ ...item, tujuan: null }} tahunDefault={2026} periodeOptions={[period]} unitOptions={[a]} />);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).maxLength).toBe(500);
    const tujuan = screen.getByLabelText<HTMLTextAreaElement>(/Tujuan/);
    expect(tujuan.required).toBe(false);
    expect(tujuan.value).toBe('');
});
