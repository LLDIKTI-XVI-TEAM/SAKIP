import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { ActivationPanel } from '@/Components/Jadwal/ActivationPanel';
import type { JadwalActivationReadiness } from '@/types/periode-jadwal';

const readiness = (overrides: Partial<JadwalActivationReadiness> = {}): JadwalActivationReadiness => ({
    jadwal_id: 'jadwal-1',
    checked_revisi: 4,
    checked_at: '2026-10-07T02:00:00Z',
    allowed: true,
    blockers: [],
    gates: [
        { key: 'G1', status: 'lolos', message: 'Perjanjian Kinerja tahun ini tersedia.', count: null },
        { key: 'G2', status: 'lolos', message: 'Seluruh indikator yang berlaku memiliki target tahunan.', count: 12 },
        { key: 'G3', status: 'lolos', message: 'Tahun jadwal berada dalam rentang Renstra.', count: null },
        { key: 'G4', status: 'pengecualian', message: 'Unggahan berkas sedang dinonaktifkan; lampiran PK dikecualikan dan akan ditandai.', count: 0 },
    ],
    counts: { indikator_berlaku: 12, target_belum_terisi: 0, snapshot_existing: 0, snapshot_baru: 12, komponen_baru: 9 },
    periode_lampau_ids: ['periode-1'],
    ...overrides,
});
const periodeNames = { 'periode-1': 'Triwulan I', 'periode-4': 'Triwulan IV' };
const props = { jadwalId: 'jadwal-1', tahun: 2026, revisi: 4, status: 'draft' as const, dirtyCalendar: false, periodeNames };
const page = (flash: Page['flash'] = {}): Page => ({
    component: 'Jadwal/Editor',
    props: { errors: {} },
    url: '/jadwal/jadwal-1',
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rescuedProps: [],
    rememberedState: {},
    flash,
});
const respond = (body: JadwalActivationReadiness) => Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native; fokus trap tetap diverifikasi lewat browser.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    vi.stubGlobal('fetch', vi.fn(() => respond(readiness())));
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const openAndConfirm = async (alasan = 'PK 2026 sudah ditandatangani') => {
    const user = userEvent.setup();
    await screen.findByText('Siap diaktifkan');
    await user.click(screen.getByRole('button', { name: 'Aktifkan jadwal' }));
    await user.type(screen.getByLabelText(/^Alasan/), alasan);
    await user.click(screen.getByRole('button', { name: 'Aktifkan' }));
    return vi.mocked(router.post).mock.calls[0];
};

it('menampilkan empat gerbang, jumlah agregat, dan periode lampau dari server', async () => {
    render(<ActivationPanel {...props} />);
    expect(await screen.findByText('Siap diaktifkan')).toBeTruthy();
    for (const label of ['Perjanjian Kinerja', 'Target tahunan', 'Tahun dalam Renstra', 'Lampiran PK']) {
        expect(screen.getByText(label)).toBeTruthy();
    }
    expect(screen.getByText('Pengecualian')).toBeTruthy();
    expect(screen.getAllByText('Lolos')).toHaveLength(3);
    // text-success (#16A34A) hanya 3.3:1 di atas surface; teks status wajib memakai token gelap ≥4.5:1.
    for (const el of [...screen.getAllByText('Lolos'), screen.getByText('Siap diaktifkan')]) {
        expect(el.className).toContain('text-success-dark');
    }
    expect(screen.getByText(/Triwulan I sudah lewat/)).toBeTruthy();
    expect(screen.getByText(/snapshot baru/).closest('p')?.textContent).toContain('12 indikator12 snapshot baru9 komponen');
    expect(fetch).toHaveBeenCalledWith('/jadwal/jadwal-1/kesiapan-aktivasi', expect.objectContaining({ headers: { Accept: 'application/json' } }));
});

it('membuka modal alasan tanpa request mutasi dan batal tidak mengirim apa pun', async () => {
    const user = userEvent.setup();
    render(<ActivationPanel {...props} />);
    await screen.findByText('Siap diaktifkan');
    await user.click(screen.getByRole('button', { name: 'Aktifkan jadwal' }));
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByText('Target dan formula untuk pengukuran dikunci. Data yang sudah dikunci tetap digunakan.')).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan' }).disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: 'Batal' }));
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(router.post).not.toHaveBeenCalled();
});

it('mengirim satu request berisi revisi tampil, operation_id, dan alasan setelah konfirmasi', async () => {
    render(<ActivationPanel {...props} />);
    const [url, payload] = await openAndConfirm();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Aktifkan' }));
    expect(router.post).toHaveBeenCalledTimes(1);
    expect(url).toBe('/jadwal/jadwal-1/aktivasi');
    expect(payload).toEqual({ expected_revisi: 4, operation_id: expect.stringMatching(/^[0-9a-f-]{36}$/), alasan: 'PK 2026 sudah ditandatangani' });
});

it('hanya menerima sukses dari flash yang cocok dengan operasi dan jadwal', async () => {
    render(<ActivationPanel {...props} />);
    const [, payload, options] = await openAndConfirm();
    const outcome = { operation_id: (payload as { operation_id: string }).operation_id, jadwal_id: 'jadwal-1', status: 'aktif', activated_at: '2026-10-07T02:00:00Z', revisi: 5, changed: true, snapshot_created_count: 12 };
    expect(typeof options?.preserveState === 'function' ? options.preserveState(page()) : options?.preserveState).toBe(true);
    await act(async () => options?.onSuccess?.(page({ jadwal_aktivasi: outcome })));
    expect(screen.getByRole('status').textContent).toContain('Jadwal berhasil diaktifkan.');
});

it('replay menampilkan sudah aktif tanpa mengklaim pengaktif', async () => {
    render(<ActivationPanel {...props} />);
    const [, payload, options] = await openAndConfirm();
    const outcome = { operation_id: (payload as { operation_id: string }).operation_id, jadwal_id: 'jadwal-1', status: 'aktif', activated_at: '2026-10-07T02:00:00Z', revisi: 5, changed: false, snapshot_created_count: 0 };
    await act(async () => options?.onSuccess?.(page({ jadwal_aktivasi: outcome })));
    expect(screen.getByRole('status').textContent).toContain('Jadwal sudah aktif.');
});

it.each([
    ['flash absen', {}],
    ['operasi lain', { jadwal_aktivasi: { operation_id: '00000000-0000-4000-8000-000000000000', jadwal_id: 'jadwal-1', status: 'aktif', activated_at: 'x', revisi: 5, changed: true, snapshot_created_count: 1 } }],
    ['jadwal lain', { jadwal_aktivasi: { operation_id: 'pakai-request', jadwal_id: 'jadwal-2', status: 'aktif', activated_at: 'x', revisi: 5, changed: true, snapshot_created_count: 1 } }],
])('hasil tidak cocok (%s) menjadi belum terkonfirmasi', async (_, flash) => {
    render(<ActivationPanel {...props} />);
    const [, payload, options] = await openAndConfirm();
    const bound = JSON.parse(JSON.stringify(flash).replace('pakai-request', (payload as { operation_id: string }).operation_id));
    await act(async () => options?.onSuccess?.(page(bound)));
    expect(screen.getByRole('alert').textContent).toContain('Hasil aktivasi belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.');
    expect(screen.queryByText('Jadwal berhasil diaktifkan.')).toBeNull();
    expect(screen.getByRole('link', { name: 'Periksa jadwal terbaru' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
});

it.each([
    ['network', 'onNetworkError'],
    ['cancel', 'onCancel'],
] as const)('%s menjadi belum terkonfirmasi dan tidak mengulang mutasi', async (_, handler) => {
    render(<ActivationPanel {...props} />);
    const [, , options] = await openAndConfirm();
    await act(async () => {
        (options?.[handler] as (() => void) | undefined)?.();
        options?.onFinish?.({} as never);
    });
    expect(screen.getByRole('alert').textContent).toContain('Hasil aktivasi belum terkonfirmasi.');
    expect(router.post).toHaveBeenCalledTimes(1);
});

it.each([
    ['network', (options: Record<string, unknown>) => (options.onNetworkError as () => void)()],
    ['cancel', (options: Record<string, unknown>) => (options.onCancel as () => void)()],
    ['419', (options: Record<string, unknown>) => (options.onHttpException as (response: unknown) => void)({ status: 419, data: {}, headers: {} })],
] as const)('alasan tetap dapat disalin sesudah %s tanpa membuka kembali submit', async (_, fail) => {
    render(<ActivationPanel {...props} />);
    const [, , options] = await openAndConfirm('Alasan untuk disalin');
    await act(async () => {
        fail(options as Record<string, unknown>);
        options?.onFinish?.({} as never);
    });
    expect(screen.queryByRole('dialog')).toBeNull();
    const copy = screen.getByLabelText<HTMLTextAreaElement>(/Alasan belum terkonfirmasi/);
    expect([copy.value, copy.readOnly]).toEqual(['Alasan untuk disalin', true]);
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(true);
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('error validasi mempertahankan alasan dan menampilkan pesan server', async () => {
    render(<ActivationPanel {...props} />);
    const [, , options] = await openAndConfirm('Alasan awal');
    await act(async () => {
        options?.onError?.({ aktivasi: 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum mengaktifkan.' });
        options?.onFinish?.({} as never);
    });
    expect(screen.getByRole('dialog').textContent).toContain('Jadwal sudah berubah.');
    expect(screen.getByLabelText<HTMLTextAreaElement>(/^Alasan/).value).toBe('Alasan awal');
});

it('revisi pratinjau berbeda memblokir aktivasi dan meminta muat ulang, bukan mengadopsi revisi', async () => {
    vi.mocked(fetch).mockImplementation(() => respond(readiness({ checked_revisi: 5 })));
    render(<ActivationPanel {...props} />);
    expect(await screen.findByText(/Kalender sudah berubah/)).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(true);
    expect(screen.getByRole('link', { name: 'Muat ulang detail' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
});

it('kalender yang belum disimpan memblokir aktivasi', async () => {
    render(<ActivationPanel {...props} dirtyCalendar />);
    expect(await screen.findByText(/Simpan atau batalkan perubahan kalender/)).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(true);
});

it('mengabaikan respons lama yang tiba sesudah pemeriksaan ulang', async () => {
    let releaseOld: (value: Response) => void = () => undefined;
    vi.mocked(fetch)
        .mockImplementationOnce(() => new Promise<Response>((resolve) => (releaseOld = resolve)))
        .mockImplementationOnce(() => respond(readiness({ allowed: false, blockers: [{ code: 'indikator_kosong', message: 'Belum ada indikator aktif.' }] })));
    const user = userEvent.setup();
    render(<ActivationPanel {...props} />);
    await user.click(screen.getByRole('button', { name: 'Periksa ulang' }));
    expect(await screen.findByText('Belum ada indikator aktif.')).toBeTruthy();
    await act(async () => releaseOld(new Response(JSON.stringify(readiness()), { status: 200 })));
    expect(screen.getByText('Belum ada indikator aktif.')).toBeTruthy();
    expect(screen.queryByText('Siap diaktifkan')).toBeNull();
});

it('menemukan jadwal sudah aktif lewat pratinjau memuat ulang detail kanonis', async () => {
    vi.mocked(fetch).mockImplementation(() => respond(readiness({ allowed: false, blockers: [{ code: 'sudah_aktif', message: 'Jadwal sudah aktif.' }] })));
    render(<ActivationPanel {...props} />);
    await waitFor(() => expect(router.reload).toHaveBeenCalledTimes(1));
});

it('kegagalan memuat pratinjau tidak menampilkan siap dan tombol tetap nonaktif', async () => {
    vi.mocked(fetch).mockImplementation(() => Promise.resolve(new Response('', { status: 500 })));
    render(<ActivationPanel {...props} />);
    expect(await screen.findByText('Kesiapan belum dapat dimuat. Coba periksa ulang.')).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(true);
});
