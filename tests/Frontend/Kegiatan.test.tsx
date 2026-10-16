import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Form from '@/Pages/Kegiatan/Form';
import Index from '@/Pages/Kegiatan/Index';
import type { KegiatanIndexProps } from '@/types/kegiatan';

let mockPengaturan: Record<string, string> = {};

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { kegiatan: true } }, pengaturan: mockPengaturan, flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

const unitOptions = [{ id: 'unit-a', nama: 'Unit A' }];
const periodeOptions = [{ id: 'periode-4', nama: 'Triwulan IV' }];
const props = { kegiatan: null, tahunDefault: 2026, unitOptions, periodeOptions };

beforeEach(() => { mockPengaturan = {}; vi.spyOn(router, 'post').mockImplementation(() => undefined); });
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

it('tidak menawarkan edit atau hapus ketika server tidak memberi capability', () => {
    render(<Index
        kegiatan={{ data: [{ id: 'kegiatan-1', nama: 'Kegiatan pembaca', tujuan: 'Layanan unit', unit: { id: 'unit-a', nama: 'Unit A' }, periode: { id: 'periode-4', nama: 'Triwulan IV' }, tahun: 2026, status: 'rencana', tanggal_rencana: null, can: { update: false } }], links: [], from: 1, to: 1, total: 1 }}
        filters={{ tahun: '', periode_id: '', unit_id: '', status: '' }} unitOptions={unitOptions} periodeOptions={periodeOptions} can={{ create: false }}
    />);
    expect(screen.getByText('Kegiatan pembaca')).toBeTruthy();
    expect(screen.queryByRole('button', { name: /Buat kegiatan/i })).toBeNull();
    expect(screen.queryByRole('button', { name: /Edit kegiatan/i })).toBeNull();
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

it('menghubungkan error unit dan periode ke pesan terlihat tanpa duplikasi', async () => {
    render(<Form {...props} />);
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onError?.({ unit_id: 'Unit sudah nonaktif.', periode_id: 'Periode sudah nonaktif.' }); });

    for (const [label, message] of [['Unit', 'Unit sudah nonaktif.'], ['Periode', 'Periode sudah nonaktif.']]) {
        const field = screen.getByLabelText(new RegExp(`^${label}`));
        const description = document.getElementById(field.getAttribute('aria-describedby') ?? '');
        expect(field.getAttribute('aria-invalid')).toBe('true');
        expect(description?.getAttribute('role')).toBe('alert');
        expect(description?.textContent).toBe(message);
        expect(screen.getAllByText(message)).toHaveLength(1);
    }
});

const indexProps: KegiatanIndexProps = {
    kegiatan: {
        data: [{ id: 'kegiatan-1', unit_id: 'unit-a', periode_id: 'periode-4', nama: 'Fasilitasi unit', tujuan: 'Meningkatkan layanan', sasaran_peserta: null, keterangan_peserta: null, lokasi: null, unit: unitOptions[0], periode: periodeOptions[0], tahun: 2026, status: 'rencana', tanggal_rencana: '2026-10-11', version_token: 'token-1', can: { update: true } }],
        links: [], from: 1, to: 1, total: 1,
    },
    filters: { tahun: '', periode_id: '', unit_id: '', status: '' },
    unitOptions, periodeOptions, can: { create: true },
};

it('menampilkan tanggal rencana sesuai preferensi tampilan pada tabel berlabel', () => {
    mockPengaturan = { 'tampilan.format_tanggal': 'd/m/Y', 'tampilan.zona_waktu': 'Asia/Makassar' };
    render(<Index {...indexProps} />);
    expect(screen.getByRole('table', { name: 'Daftar kegiatan' })).toBeTruthy();
    expect(screen.getByText('11/10/2026')).toBeTruthy();
    expect(screen.queryByText('2026-10-11')).toBeNull();
});

it('menyediakan tombol buat dan edit sesuai capability dengan modal pop-up', () => {
    render(<Index {...indexProps} />);
    const createBtn = screen.getByRole('button', { name: 'Buat kegiatan' });
    expect(createBtn).toBeTruthy();
    fireEvent.click(createBtn);
    expect(screen.getByRole('dialog', { name: 'Buat rencana kegiatan' })).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));

    fireEvent.click(screen.getByRole('button', { name: 'Edit kegiatan Fasilitasi unit' }));
    expect(screen.getByRole('dialog', { name: 'Edit rencana kegiatan' })).toBeTruthy();
});
it('menginisialisasi filter tahun dengan nilai default serta menyediakan placeholder', () => {
    const getSpy = vi.spyOn(router, 'get').mockImplementation(() => undefined);
    render(<Index {...indexProps} tahunDefault={2026} />);
    const inputTahun = screen.getByLabelText<HTMLInputElement>('Tahun');
    expect(inputTahun.value).toBe('2026');
    expect(inputTahun.placeholder).toBe('Contoh: 2026');

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));
    expect(getSpy).toHaveBeenCalledWith('/kegiatan', { tahun: 2026 }, expect.any(Object));
});

it('menyinkronkan input filter dengan filters server setelah redirect simpan yang mempertahankan state', () => {
    const daftar = { data: [], links: [], from: null, to: null, total: 0 };
    const { rerender } = render(<Index kegiatan={daftar} filters={{ tahun: '2026', unit_id: '', periode_id: '', status: '' }} unitOptions={unitOptions} periodeOptions={periodeOptions} tahunDefault={2026} can={{ create: false }} />);
    // Mutasi Inertia default preserveState: komponen tidak di-mount ulang, hanya props yang berubah.
    rerender(<Index kegiatan={daftar} filters={{ tahun: '2027', unit_id: '', periode_id: '', status: '' }} unitOptions={unitOptions} periodeOptions={periodeOptions} tahunDefault={2026} can={{ create: false }} />);
    expect(screen.getByLabelText<HTMLInputElement>('Tahun').value).toBe('2027');
});

it('menjelaskan daftar kosong mengikuti unit yang dapat diakses', () => {
    render(<Index {...indexProps} kegiatan={{ data: [], links: [], from: null, to: null, total: 0 }} />);
    expect(screen.getByText('Belum ada rencana kegiatan pada unit yang dapat Anda akses.')).toBeTruthy();
});
