import type { ActiveVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { TargetModal } from '@/Pages/TargetTahunan/TargetModal';

// Editor selalu dirender di dalam halaman Inertia; format angka membaca setelan tampilan dari props bersama.
vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { pengaturan: {} } }),
}));

const id = '11111111-1111-4111-8111-111111111111';
const pkId = '44444444-4444-4444-8444-444444444444';
const data = (changes: Record<string, unknown> = {}) => ({ indikator_id: id, tahun: 2026, baseline_year: 2025, baseline: '74.2', target_tahunan: '76.25', expected_state: 'a'.repeat(64), has_snapshot: false, can: { update: true }, read_only_reason: null, target_renstra: null, alasan_deviasi_renstra: null, status_deviasi: 'renstra_belum_ada', saran_baseline: null, indikator: { id, kode: 'IKU 3', nama: 'Contoh', satuan: 'nilai', presisi: 2, desimal_tampilan: 1, status: 'aktif', tahun_mulai_berlaku: 2025, arah: 'naik_baik' }, renstra: { id: 'r1', nama: 'Renstra contoh', tahun_mulai: 2025, tahun_selesai: 2029, status: 'aktif' }, ...changes });
const response = (changes: Record<string, unknown> = {}) => new Response(JSON.stringify(data(changes)), { status: 200 });
const fetchMock = vi.fn<typeof fetch>();
const show = () => render(<TargetModal pkId={pkId} indikatorId={id} tahun={2026} onClose={vi.fn()} onSaved={vi.fn()} />);
/** Konflik versi membuka jalur "Muat data terbaru", satu-satunya pemicu muat ulang editor. */
const conflict = async () => {
    fireEvent.click(screen.getByRole('button', { name: 'Simpan' }));
    const options = vi.mocked(router.put).mock.calls.at(-1)?.[2];
    // useForm baru menyimpan error setelah efek mount Editor berjalan, padahal Editor dipasang setelah fetch di luar act.
    await waitFor(() => {
        options?.onError?.({ expected_state: 'Data sudah berubah.' });
        expect(screen.getByRole('button', { name: 'Muat data terbaru' })).toBeTruthy();
    });
    await act(async () => { options?.onFinish?.({} as ActiveVisit); });
};
const shims = [
    ['showModal', function (this: HTMLDialogElement) { this.open = true; }],
    ['close', function (this: HTMLDialogElement) { this.open = false; }],
] as const;
const originals = shims.map(([name]) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => shims.forEach(([name, value]) => Object.defineProperty(HTMLDialogElement.prototype, name, { configurable: true, value })));
afterAll(() => shims.forEach(([name], index) => {
    const descriptor = originals[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));
beforeEach(() => { vi.stubGlobal('fetch', fetchMock); vi.spyOn(router, 'put').mockImplementation(() => undefined); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); fetchMock.mockReset(); });

it('memuat dan menyimpan lewat route target PK, lalu memakai token baru setelah muat ulang', async () => {
    fetchMock.mockResolvedValueOnce(response()).mockResolvedValueOnce(response({ target_tahunan: '80', expected_state: 'b'.repeat(64) }));
    show(); await screen.findByLabelText('Target 2026');
    expect(fetchMock.mock.calls[0][0]).toBe(`/perjanjian-kinerja/${pkId}/target/${id}/editor`);
    expect(screen.queryByLabelText('Tahun')).toBeNull();
    await conflict();
    expect(vi.mocked(router.put).mock.calls[0][0]).toBe(`/perjanjian-kinerja/${pkId}/target/${id}`);
    fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' }));
    expect(await screen.findByDisplayValue('80')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Simpan' }));
    expect(vi.mocked(router.put).mock.calls.at(-1)?.[0]).toBe(`/perjanjian-kinerja/${pkId}/target/${id}`);
    expect(vi.mocked(router.put).mock.calls.at(-1)?.[1]).toMatchObject({ expected_state: 'b'.repeat(64) });
    expect(fetchMock.mock.calls[1][0]).toBe(`/perjanjian-kinerja/${pkId}/target/${id}/editor`);
});

it('gagal memuat ulang tidak menghapus draft lama dan menyediakan coba lagi', async () => {
    fetchMock.mockResolvedValueOnce(response()).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(response({ target_tahunan: '80' }));
    show(); await screen.findByLabelText('Baseline 2025');
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75,1234' } });
    await conflict();
    fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' }));
    fireEvent.click(screen.getByRole('button', { name: 'Buang perubahan' }));
    await screen.findByText(/Data belum dapat dimuat/);
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75,1234');
    fireEvent.click(screen.getByRole('button', { name: 'Coba lagi' }));
    fireEvent.click(screen.getByRole('button', { name: 'Buang perubahan' }));
    expect(await screen.findByDisplayValue('80')).toBeTruthy();
});

it('respons tanpa kontrak pembanding target Renstra tidak dianggap editor sah', async () => {
    const { status_deviasi: _abaikan, ...tanpaStatus } = data();
    fetchMock.mockResolvedValueOnce(new Response(JSON.stringify(tanpaStatus), { status: 200 }));
    show();
    expect(await screen.findByText('Data belum dapat dimuat. Coba lagi.')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Simpan' })).toBeNull();
});

it('respons lama setelah berganti indikator tidak mengganti konteks terbaru', async () => {
    let finishOld: ((response: Response) => void) | undefined;
    fetchMock.mockImplementationOnce(() => new Promise((resolve) => { finishOld = resolve; })).mockResolvedValueOnce(new Response(JSON.stringify({ ...data(), indikator_id: 'second', indikator: { ...data().indikator, id: 'second', kode: 'IKU 4' } })));
    const view = show();
    view.rerender(<TargetModal pkId={pkId} indikatorId="second" tahun={2026} onClose={vi.fn()} onSaved={vi.fn()} />);
    await screen.findByLabelText('Target 2026');
    await act(async () => { finishOld?.(response()); });
    expect(screen.getByText('IKU 4 · Contoh')).toBeTruthy();
});

it.each([403, 401, 419])('GET %s tidak menampilkan editor kosong sebagai data sah', async (status) => {
    fetchMock.mockResolvedValueOnce(new Response('{}', { status })); show();
    await waitFor(() => expect(screen.queryByText('Memuat data…')).toBeNull());
    expect(screen.queryByRole('button', { name: 'Simpan' })).toBeNull();
    expect(screen.getByRole('alert')).toBeTruthy();
});

it.each([401, 419])('GET %s saat muat ulang menawarkan pemulihan sesi tanpa menghilangkan draft', async (status) => {
    fetchMock.mockResolvedValueOnce(response()).mockResolvedValueOnce(new Response('{}', { status }));
    show(); await screen.findByLabelText('Baseline 2025');
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75,1234' } });
    await conflict();
    fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' }));
    fireEvent.click(screen.getByRole('button', { name: 'Buang perubahan' }));
    if (status === 401) expect((await screen.findByRole('link', { name: 'Masuk ulang' })).getAttribute('href')).toBe('/login?recovery=1');
    else expect(await screen.findByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75,1234');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan' }).disabled).toBe(true);
    expect(router.put).toHaveBeenCalledOnce();
});

it.each([
    ['bukan objek', 'x'],
    ['status tidak dikenal', { status: 'lain', nilai: null, periode: null }],
    ['disahkan tanpa nilai', { status: 'disahkan', nilai: null, periode: 'Triwulan IV' }],
    ['status tanpa angka membawa nilai', { status: 'belum_disahkan', nilai: '82.5', periode: null }],
])('saran baseline malformed (%s) tidak dianggap editor sah', async (_kasus, saran_baseline) => {
    fetchMock.mockResolvedValueOnce(response({ baseline: null, saran_baseline }));
    show();
    expect(await screen.findByText('Data belum dapat dimuat. Coba lagi.')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Simpan' })).toBeNull();
});

it.each([
    { status: 'disahkan', nilai: '82.5', periode: 'Triwulan IV' },
    { status: 'belum_ada', nilai: null, periode: null },
])('saran baseline sah ($status) menampilkan editor', async (saran_baseline) => {
    fetchMock.mockResolvedValueOnce(response({ baseline: null, saran_baseline }));
    show();
    expect(await screen.findByRole('button', { name: 'Simpan' })).toBeTruthy();
});
