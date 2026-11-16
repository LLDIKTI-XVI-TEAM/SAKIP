import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Create from '@/Pages/Regulasi/Create';
import Index from '@/Pages/Regulasi/Index';
import Edit from '@/Pages/Regulasi/Edit';
import type { BerkasRegulasi, RegulasiSummary } from '@/types/regulasi';
vi.mock('@inertiajs/react', async (original) => ({ ...await original<typeof import('@inertiajs/react')>(), Head: () => null }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;
afterAll(() => { HTMLDialogElement.prototype.showModal = originalShowModal; HTMLDialogElement.prototype.close = originalClose; });
beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
it.each(['network', 'auth', 'permission-after-redirect'] as const)('menjaga alasan/file dan mencegah submit ulang ketika %s', async (failure) => {
    const user = userEvent.setup();
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<Create />);
    await user.type(screen.getByLabelText(/Catatan internal/), 'Draft regulasi recovery');
    await user.click(screen.getByRole('button', { name: /Tambah lampiran/i }));
    const file = new File(['fixture'], 'fixture.pdf', { type: 'application/pdf' });
    const input = document.querySelector<HTMLInputElement>('input[type=file]')!;
    await user.upload(input, file);
    await user.click(screen.getByRole('button', { name: /Simpan dasar aturan/i }));
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { if (failure === 'network') options?.onNetworkError?.(new Error('offline')); else options?.onHttpException?.({ status: failure === 'auth' ? 401 : 403, data: {}, headers: {} }); });
    expect(screen.getByRole('alert').textContent).toContain('belum dapat dipastikan');
    expect((screen.getByLabelText(/Catatan internal/) as HTMLTextAreaElement).value).toBe('Draft regulasi recovery');
    expect(input.files?.[0]).toBe(file);
    const submit = screen.getByRole<HTMLButtonElement>('button', { name: /Simpan dasar aturan/i });
    expect(submit.disabled).toBe(true);
    await user.click(submit);
    expect(router.post).toHaveBeenCalledTimes(1);
});


it('Edit tidak membuka submit ulang setelah modal alasan ditutup/dibuka pada unknown', async () => {
    const { default: Edit } = await import('@/Pages/Regulasi/Edit');
    const user = userEvent.setup();
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<Edit regulasi={{ id: 1, jenis: 'kepmen', nomor: 'QA', tahun: 2026, tentang: 'Fixture', tanggal: null, tautan_sumber: null, aktif: true, catatan: null, versi: 1, berkas: [] }} can={{ 'regulasi:update': true }} />);
    await user.click(screen.getByRole('button', { name: /Tinjau dan simpan/ }));
    await user.type(screen.getByLabelText(/Alasan perubahan/), 'Alasan edit recovery');
    await user.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onNetworkError?.(new Error('offline')); });
    expect(screen.getByRole('alert').textContent).toContain('belum dapat dipastikan');
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    await user.click(screen.getByRole('button', { name: /Tinjau dan simpan/ }));
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' }).disabled).toBe(true);
    expect((screen.getByLabelText(/Alasan perubahan/) as HTMLTextAreaElement).value).toBe('Alasan edit recovery');
    await user.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    expect(router.post).toHaveBeenCalledTimes(1);
});


it('recovery simpan tetap terlihat saat membuka modal operasi lampiran lain', async () => {
    const { default: Edit } = await import('@/Pages/Regulasi/Edit');
    const user = userEvent.setup();
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<Edit regulasi={{ id: 1, jenis: 'kepmen', nomor: 'QA', tahun: 2026, tentang: 'Fixture', tanggal: null, tautan_sumber: null, aktif: true, catatan: null, versi: 1, berkas: [{ id: 2, mode: 'teks', isi_teks: 'Lampiran QA', nama_asli: null, mime: null, ukuran_bytes: null, tautan: null, download_url: null }] }} can={{ 'regulasi:update': true, 'berkas:delete': true }} />);
    await user.click(screen.getByRole('button', { name: /Tinjau dan simpan/ }));
    await user.type(screen.getByLabelText(/Alasan perubahan/), 'Alasan edit recovery');
    await user.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onHttpException?.({ status: 401, data: { recovery: { reason: 'authentication_required', rejected: { method: 'PUT', path: '/regulasi/1', before_action: true } } }, headers: {} }); });
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    await user.click(screen.getByRole('button', { name: /^Hapus$/ }));
    const reason = screen.getByLabelText<HTMLTextAreaElement>(/Alasan perubahan/);
    await user.type(reason, 'Draft lintas operasi tetap bisa disalin');
    expect(reason.value).toBe('Draft lintas operasi tetap bisa disalin');
    expect(document.activeElement).toBe(reason);
    expect(screen.getByRole('alert').textContent).toContain('belum dapat dipastikan');
    expect(screen.getByRole('link', { name: 'Masuk ulang' })).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: /^Hapus lampiran$/ }).disabled).toBe(true);
});

const rejectedDelete = (path: string) => ({
    status: 401,
    data: { recovery: { reason: 'authentication_required', rejected: { method: 'DELETE', path, before_action: true } } },
    headers: {},
});

it('recovery hapus regulasi mempertahankan target dan alasan asal saat operator memilih target lain', async () => {
    const user = userEvent.setup();
    vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    const items: RegulasiSummary[] = [1, 2].map((id) => ({
        id, jenis: 'kepmen', nomor: `QA-${id}`, tahun: 2026, tentang: `Target ${id}`,
        aktif: true, berkas_count: 0, tanggal: null, tautan_sumber: null, pembuat: null, updated_at: null,
    }));
    render(<Index regulasi={{ data: items, current_page: 1, last_page: 1, total: 2, from: 1, to: 2, links: [] }} filters={{ q: '', status: null }} can={{ 'regulasi:delete': true }} />);
    await user.click(screen.getByRole('button', { name: 'Hapus regulasi QA-2' }));
    await user.type(screen.getByLabelText(/Alasan perubahan/), 'Draft belum dikirim');
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    await user.click(screen.getByRole('button', { name: 'Hapus regulasi QA-1' }));
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Alasan perubahan/).value).toBe('');
    await user.type(screen.getByLabelText(/Alasan perubahan/), 'Alasan penghapusan target A');
    await user.click(screen.getByRole('button', { name: 'Hapus dasar aturan' }));
    expect(vi.mocked(router.delete).mock.calls[0][0]).toBe('/regulasi/1');
    await act(async () => { vi.mocked(router.delete).mock.calls[0][1]?.onHttpException?.(rejectedDelete('/regulasi/1')); });
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    await user.click(screen.getByRole('button', { name: 'Hapus regulasi QA-2' }));
    expect(screen.getByRole('dialog').textContent).toContain('QA-1/2026');
    expect(screen.getByRole('dialog').textContent).not.toContain('QA-2/2026');
    expect(screen.getByRole('alert').textContent).toContain('Permintaan perubahan ini ditolak');
    const reason = screen.getByLabelText<HTMLTextAreaElement>(/Alasan perubahan/);
    expect(reason.value).toBe('Alasan penghapusan target A');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Hapus dasar aturan' }).disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: 'Hapus dasar aturan' }));
    await user.click(reason);
    await user.keyboard('{Enter}');
    expect(router.delete).toHaveBeenCalledTimes(1);
});

it('recovery hapus lampiran mempertahankan nama dan alasan asal saat operator memilih lampiran lain', async () => {
    const user = userEvent.setup();
    vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    const berkas: BerkasRegulasi[] = [2, 3].map((id) => ({
        id, mode: 'file', nama_asli: `Lampiran-${id}.pdf`, mime: 'application/pdf', ukuran_bytes: 100,
        isi_teks: null, tautan: null, download_url: null,
    }));
    render(<Edit regulasi={{ id: 1, jenis: 'kepmen', nomor: 'QA', tahun: 2026, tentang: 'Fixture', tanggal: null, tautan_sumber: null, aktif: true, catatan: null, versi: 1, berkas }} can={{ 'regulasi:update': true, 'berkas:delete': true }} />);
    await user.click(screen.getAllByRole('button', { name: /^Hapus$/ })[0]);
    await user.type(screen.getByLabelText(/Alasan perubahan/), 'Alasan penghapusan lampiran A');
    await user.click(screen.getByRole('button', { name: 'Hapus lampiran' }));
    expect(vi.mocked(router.delete).mock.calls[0][0]).toBe('/regulasi/1/berkas/2');
    await act(async () => { vi.mocked(router.delete).mock.calls[0][1]?.onHttpException?.(rejectedDelete('/regulasi/1/berkas/2')); });
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    await user.click(screen.getAllByRole('button', { name: /^Hapus$/ })[1]);
    expect(screen.getByRole('dialog').textContent).toContain('Lampiran-2.pdf');
    expect(screen.getByRole('dialog').textContent).not.toContain('Lampiran-3.pdf');
    expect(screen.getByRole('alert').textContent).toContain('Permintaan perubahan ini ditolak');
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Alasan perubahan/).value).toBe('Alasan penghapusan lampiran A');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Hapus lampiran' }).disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: 'Hapus lampiran' }));
    expect(router.delete).toHaveBeenCalledTimes(1);
});
