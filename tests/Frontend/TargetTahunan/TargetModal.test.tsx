import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { TargetModal } from '@/Pages/TargetTahunan/TargetModal';

const id = '11111111-1111-4111-8111-111111111111';
const data = (year = 2026) => ({ indikator_id: id, tahun: year, baseline_year: year - 1, baseline: year === 2026 ? '74.2' : null, target_tahunan: year === 2026 ? '76.25' : '0', expected_state: String(year).repeat(16), has_snapshot: false, can: { update: true }, read_only_reason: null, indikator: { id, kode: 'IKU 3', nama: 'Contoh', satuan: 'nilai', presisi: 2, desimal_tampilan: 1, status: 'aktif', tahun_mulai_berlaku: 2025 }, renstra: { id: 'r1', nama: 'Renstra contoh', tahun_mulai: 2025, tahun_selesai: 2029, status: 'aktif' } });
const response = (year = 2026) => new Response(JSON.stringify(data(year)), { status: 200 });
const fetchMock = vi.fn<typeof fetch>();
const show = () => render(<TargetModal indikatorId={id} initialYear={2026} onClose={vi.fn()} onSaved={vi.fn()} />);
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

it('memuat pasangan baru dengan nilai dan token bersama, tanpa token tahun lama', async () => {
    fetchMock.mockResolvedValueOnce(response()).mockResolvedValueOnce(response(2027));
    show(); await screen.findByLabelText('Target 2026');
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } });
    const target = await screen.findByLabelText<HTMLInputElement>('Target 2027');
    expect(target.value).toBe('0');
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2026').value).toBe('');
    fireEvent.click(screen.getByRole('button', { name: 'Simpan' }));
    expect(vi.mocked(router.put).mock.calls[0][0]).toBe(`/perencanaan/indikator/${id}/target-tahunan/2027`);
    expect(vi.mocked(router.put).mock.calls[0][1]).toMatchObject({ expected_state: data(2027).expected_state });
    expect(fetchMock.mock.calls[0][0]).toBe(`/perencanaan/indikator/${id}/target-tahunan/2026/editor`);
});

it('gagal memuat tahun baru tidak menghapus draft lama dan menyediakan coba lagi', async () => {
    fetchMock.mockResolvedValueOnce(response()).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(response(2027));
    show(); await screen.findByLabelText('Baseline 2025');
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75,1234' } });
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } });
    fireEvent.click(screen.getByRole('button', { name: 'Buang perubahan' }));
    await screen.findByText(/Data belum dapat dimuat/);
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75,1234');
    expect(screen.getByLabelText<HTMLSelectElement>('Tahun').value).toBe('2026');
    fireEvent.click(screen.getByRole('button', { name: 'Coba lagi' }));
    fireEvent.click(screen.getByRole('button', { name: 'Buang perubahan' }));
    await screen.findByLabelText('Target 2027');
});

it('respons lama setelah berganti indikator tidak mengganti konteks terbaru', async () => {
    let finishOld: ((response: Response) => void) | undefined;
    fetchMock.mockImplementationOnce(() => new Promise((resolve) => { finishOld = resolve; })).mockResolvedValueOnce(new Response(JSON.stringify({ ...data(), indikator_id: 'second', indikator: { ...data().indikator, id: 'second', kode: 'IKU 4' } })));
    const view = show();
    view.rerender(<TargetModal indikatorId="second" initialYear={2026} onClose={vi.fn()} onSaved={vi.fn()} />);
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

it.each([401, 419])('GET %s sesudah editor terbuka menawarkan pemulihan sesi tanpa menghilangkan draft', async (status) => {
    fetchMock.mockResolvedValueOnce(response()).mockResolvedValueOnce(new Response('{}', { status }));
    show(); await screen.findByLabelText('Baseline 2025');
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75,1234' } });
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } });
    fireEvent.click(screen.getByRole('button', { name: 'Buang perubahan' }));
    if (status === 401) expect((await screen.findByRole('link', { name: 'Masuk ulang' })).getAttribute('href')).toBe('/login?recovery=1');
    else expect(await screen.findByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75,1234');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan' }).disabled).toBe(true);
    expect(router.put).not.toHaveBeenCalled();
});
