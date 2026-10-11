import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Form from '@/Pages/Kegiatan/Form';
import Index from '@/Pages/Kegiatan/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { kegiatan: true } }, pengaturan: {}, flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const unitOptions = [{ id: 'unit-a', nama: 'Unit A' }];
const periodeOptions = [{ id: 'periode-4', nama: 'Triwulan IV' }];
const props = { kegiatan: null, tahunDefault: 2026, unitOptions, periodeOptions };

beforeEach(() => { vi.spyOn(router, 'post').mockImplementation(() => undefined); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

it('mengirim field rencana melalui submit keyboard tanpa anggaran atau status', () => {
    render(<Form {...props} />);
    expect(screen.queryByLabelText(/anggaran/i)).toBeNull();
    expect(screen.queryByLabelText(/^status/i)).toBeNull();
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Fasilitasi unit' } });
    fireEvent.change(screen.getByLabelText(/Tujuan/), { target: { value: 'Meningkatkan layanan' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan');
    expect(data).toMatchObject({ unit_id: 'unit-a', tahun: 2026, periode_id: 'periode-4', nama: 'Fasilitasi unit', tujuan: 'Meningkatkan layanan', sasaran_peserta: null });
    expect(data).not.toHaveProperty('anggaran');
    expect(data).not.toHaveProperty('status');
});

it('menampilkan error server yang terhubung ke field dan mempertahankan koreksi pengguna', async () => {
    render(<Form {...props} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Nama yang dikoreksi' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onError?.({ tujuan: 'Tujuan wajib diisi.' }); });
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Nama yang dikoreksi');
    const tujuan = screen.getByLabelText(/Tujuan/);
    expect(tujuan.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(tujuan.getAttribute('aria-describedby') ?? '')?.textContent).toContain('Tujuan wajib diisi.');
});

it('tidak menawarkan ubah atau hapus ketika server tidak memberi capability', () => {
    render(<Index
        kegiatan={{ data: [{ id: 'kegiatan-1', nama: 'Kegiatan pembaca', tujuan: 'Layanan unit', unit: { id: 'unit-a', nama: 'Unit A' }, periode: { id: 'periode-4', nama: 'Triwulan IV' }, tahun: 2026, status: 'rencana', tanggal_rencana: null, can: { update: false } }], links: [], from: 1, to: 1, total: 1 }}
        filters={{ tahun: '', periode_id: '', unit_id: '', status: '' }} unitOptions={unitOptions} periodeOptions={periodeOptions} can={{ create: false }}
    />);
    expect(screen.getByText('Kegiatan pembaca')).toBeTruthy();
    expect(screen.queryByRole('link', { name: /Buat kegiatan/i })).toBeNull();
    expect(screen.queryByRole('button', { name: /Aksi kegiatan/i })).toBeNull();
    expect(screen.queryByText(/Hapus/)).toBeNull();
});

it('mempertahankan input dan menawarkan pemulihan sesi tanpa replay otomatis', async () => {
    render(<Form {...props} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Rencana belum dikonfirmasi' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onHttpException?.({ status: 401, data: { recovery: { reason: 'authentication_required', rejected: { method: 'POST', path: '/kegiatan', before_action: true } } }, headers: {} }); });
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Rencana belum dikonfirmasi');
    expect(screen.getByRole('link', { name: 'Masuk ulang' }).getAttribute('href')).toBe('/login?recovery=1');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan kegiatan' }).disabled).toBe(true);
    expect(router.post).toHaveBeenCalledTimes(1);
});
