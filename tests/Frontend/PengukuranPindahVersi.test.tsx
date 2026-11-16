import type { ComponentProps, ReactNode } from 'react';
import { http, type ActiveVisit, type Page, type PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { PindahVersiButton } from '@/Components/Pengukuran/PindahVersiButton';
import PengukuranEdit from '@/Pages/Pengukuran/Edit';
import PengukuranIndex from '@/Pages/Pengukuran/Index';
import type { BarisPengukuran, Pengukuran } from '@/Pages/Pengukuran/types';
import { installDialogPolyfill } from './viewportFixtures';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const measurement: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 1, versi_berlaku: null,
    jalur_pengajuan: 'pic', self_approval: false, reviu_terlambat: false, status: 'draft', target: 100,
    nilai: 80, status_perhitungan: 'terhitung', sumber_nilai: 'manual', catatan: '', alasan_tidak_dapat_dihitung: null,
    komponen: [], prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [],
    can: { view: true, evidence: true, uploadEvidence: true, update: true, submit: true, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'unit-1', nama: 'Unit Uji' }, pic: { id: 'pic-1', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: 'periode-1', urutan: 1, nama_periode: 'Triwulan I', tahun: 2026, is_nilai_akhir: false,
        tanggal: { bulan_mulai: 1, hari_mulai: 1, bulan_selesai: 3, hari_selesai: 31 } },
    bukti_dukungs: [], bukti_count: 0, riwayats: [], snapshot: null,
};
const tujuan = { id: 'snapshot-2', nomor: 2 };
const base: ComponentProps<typeof PindahVersiButton> = {
    pengukuranId: measurement.id, versi: 4, versiBerlaku: tujuan, labelAksesibel: 'Pindahkan IKU-UJI', size: 'sm',
};
const path = `/pengukuran/${measurement.id}/pindah-snapshot`;
const belumPasti = 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.';
type Options = NonNullable<Parameters<typeof router.post>[2]>;
const visit: PendingVisit = {
    id: 'pindah-test', url: new URL('http://localhost' + path), method: 'post', data: {},
    completed: false, cancelled: false, interrupted: false, replace: false, preserveScroll: true, preserveState: true,
    only: [], except: [], headers: {}, errorBag: null, forceFormData: false, queryStringArrayFormat: 'brackets',
    async: false, showProgress: true, prefetch: false, fresh: false, reset: [], preserveUrl: false, preserveErrors: false,
    invalidateCacheTags: [], viewTransition: false, component: null, pageProps: null, cached: false,
};
const finishedVisit: ActiveVisit = { ...visit, completed: true,
    onCancelToken: vi.fn(), onBefore: vi.fn(), onBeforeUpdate: vi.fn(), onStart: vi.fn(), onProgress: vi.fn(), onFinish: vi.fn(),
    onCancel: vi.fn(), onSuccess: vi.fn(), onError: vi.fn(), onHttpException: vi.fn(), onNetworkError: vi.fn(),
    onFlash: vi.fn(), onPrefetched: vi.fn(), onPrefetching: vi.fn(),
};
const page = (flash: Page['flash'] = {}): Page => ({
    component: 'Pengukuran/Edit', url: `/pengukuran/${measurement.id}/edit`, props: { errors: {} },
    flash, version: null, clearHistory: false, encryptHistory: false, rescuedProps: [], rememberedState: {},
});
const opsi = (index = 0): Options => {
    const result = vi.mocked(router.post).mock.calls[index]?.[2];
    if (!result) throw new Error('POST belum tersedia.');
    return result;
};
const dialog = () => screen.getByRole<HTMLDialogElement>('dialog');
const alasan = () => within(dialog()).getByRole<HTMLTextAreaElement>('textbox');
const konfirmasi = () => within(dialog()).getByRole<HTMLButtonElement>('button', { name: 'Pindahkan' });
const buka = (user: ReturnType<typeof userEvent.setup>) => user.click(screen.getByRole('button', { name: base.labelAksesibel }));

async function selesaikan(callback: (options: Options) => unknown, index = 0) {
    await act(async () => {
        await callback(opsi(index));
        opsi(index).onFinish?.(finishedVisit);
    });
}

let restoreDialog: () => void;
beforeAll(() => {
    // Fokus dan inert dialog native dibuktikan melalui browser.
    restoreDialog = installDialogPolyfill();
});
afterAll(() => restoreDialog());
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => { options?.onStart?.(visit); });
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({
        status: 200, data: JSON.stringify({ nilai: '80.00', status_perhitungan: 'terhitung' }), headers: {},
    });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

it('mengambil token tiap pembukaan dan membekukannya selama dialog terbuka', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<PindahVersiButton {...base} />);
    rerender(<PindahVersiButton {...base} versi={5} versiBerlaku={{ id: 'snapshot-3', nomor: 3 }} />);
    await buka(user);
    await user.type(alasan(), 'Alasan pengguna.');
    rerender(<PindahVersiButton {...base} versi={6} versiBerlaku={{ id: 'snapshot-4', nomor: 4 }} />);
    expect(within(dialog()).getByText('Pindahkan ke versi 3?')).toBeTruthy();
    expect(within(dialog()).getByText('Nilai yang tidak sesuai dengan versi 3 akan dikosongkan.')).toBeTruthy();

    await user.click(konfirmasi());
    expect(konfirmasi().disabled).toBe(true);
    expect(vi.mocked(router.post).mock.calls[0][0]).toBe(path);
    expect(vi.mocked(router.post).mock.calls[0][1]).toStrictEqual({
        versi: 5, expected_snapshot_id: 'snapshot-3', expected_snapshot_versi: 3, alasan: 'Alasan pengguna.',
    });
    const preserveState = opsi().preserveState;
    if (typeof preserveState !== 'function') throw new Error('preserveState harus berupa callback.');
    expect(preserveState(page())).toBe(true);
    expect(preserveState(page({ success: 'Berhasil.' }))).toBe(false);
    expect(preserveState({ ...page(), props: { errors: {}, flash: { success: 'Legacy.' } } })).toBe(true);
    await selesaikan((o) => o.onSuccess?.(page()));
    expect(dialog().open).toBe(true);
    expect(within(dialog()).getByText(belumPasti)).toBeTruthy();
    expect(alasan().value).toBe('Alasan pengguna.');

    await user.click(within(dialog()).getByRole('button', { name: 'Batal' }));
    await buka(user);
    expect(within(dialog()).getByText('Pindahkan ke versi 4?')).toBeTruthy();
    expect(alasan().value).toBe('');
    expect(alasan().getAttribute('aria-required')).toBe('false');
    expect(within(dialog()).queryByRole('alert')).toBeNull();
    expect(konfirmasi().disabled).toBe(false);
    await user.click(konfirmasi());
    expect(vi.mocked(router.post).mock.calls[1][1]).toStrictEqual({
        versi: 6, expected_snapshot_id: 'snapshot-4', expected_snapshot_versi: 4, alasan: '',
    });
    await selesaikan((o) => o.onSuccess?.(page({ success: 'Berhasil.' })), 1);
    expect(screen.queryByRole('dialog')).toBeNull();
});

it.each(['alasan', 'versi', 'expected_snapshot_id', 'pindah_snapshot'] as const)(
    'menempatkan galat %s dengan tepat dan membersihkannya saat dibuka ulang', async (field) => {
        const user = userEvent.setup();
        render(<PindahVersiButton {...base} />);
        await buka(user);
        await user.type(alasan(), 'Tetap tersedia.');
        await user.click(konfirmasi());
        await selesaikan((o) => o.onError?.({ [field]: 'Pesan server.' }));
        expect(dialog().open).toBe(true);
        expect(within(dialog()).getByRole('alert').textContent).toContain('Pesan server.');
        expect(alasan().value).toBe('Tetap tersedia.');
        expect(alasan().getAttribute('aria-invalid')).toBe(String(field === 'alasan'));
        if (field === 'alasan') {
            const errorId = alasan().getAttribute('aria-describedby');
            expect(errorId && document.getElementById(errorId)?.textContent).toBe('Pesan server.');
        }
        await user.click(within(dialog()).getByRole('button', { name: 'Batal' }));
        await buka(user);
        expect(within(dialog()).queryByRole('alert')).toBeNull();
        expect(alasan().getAttribute('aria-invalid')).toBe('false');
        expect(alasan().value).toBe('');
    },
);

const kegagalan: [string, (o: Options) => unknown, string][] = [
    ['jaringan', (o) => o.onNetworkError?.(new Error('offline')), belumPasti],
    ['batal', (o) => o.onCancel?.(), belumPasti],
    ['403', (o) => o.onHttpException?.({ status: 403, data: {}, headers: {} }), 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.'],
];
it.each(kegagalan)('%s mempertahankan dialog dan mengizinkan kirim ulang', async (_name, gagal, pesan) => {
    const user = userEvent.setup();
    render(<PindahVersiButton {...base} />);
    await buka(user);
    await user.type(alasan(), 'Alasan tetap.');
    await user.click(konfirmasi());
    await selesaikan(gagal);
    expect(dialog().open).toBe(true);
    expect(within(dialog()).getByText(pesan)).toBeTruthy();
    expect(alasan().value).toBe('Alasan tetap.');
    expect(alasan().getAttribute('aria-invalid')).toBe('false');
    expect(konfirmasi().disabled).toBe(false);
    await user.click(konfirmasi());
    expect(router.post).toHaveBeenCalledTimes(2);
    expect(vi.mocked(router.post).mock.calls[1][1]).toStrictEqual(vi.mocked(router.post).mock.calls[0][1]);
});

it.each([401, 419])('%i menempatkan recovery di dialog dan menahan konfirmasi setelah finish', async (status) => {
    const user = userEvent.setup();
    render(<PindahVersiButton {...base} />);
    await buka(user);
    await user.type(alasan(), 'Bisa disalin.');
    await user.click(konfirmasi());
    await selesaikan((o) => {
        expect(o.onHttpException?.({ status, headers: {}, data: { recovery: {
            reason: status === 401 ? 'authentication_required' : 'csrf_mismatch',
            rejected: { method: 'POST', path, before_action: true },
        } } })).toBe(false);
    });
    const judul = status === 401 ? 'Autentikasi diperlukan' : 'Verifikasi keamanan diperlukan';
    expect(dialog().open).toBe(true);
    expect(screen.getAllByText(judul)).toHaveLength(1);
    expect(within(dialog()).getByText(judul)).toBeTruthy();
    expect(within(dialog()).getByText(/Permintaan perubahan ini ditolak karena/)).toBeTruthy();
    expect(alasan().value).toBe('Bisa disalin.');
    expect(konfirmasi().disabled).toBe(true);
    await user.click(konfirmasi());
    expect(router.post).toHaveBeenCalledTimes(1);
});

const row: BarisPengukuran = {
    indikator_id: 'ind-1', jadwal_id: 'jad-1', jadwal_snapshot_id: 'snap-1', tahun: 2026,
    periode: measurement.periode_jadwal, pic: measurement.penugasan_indikator.pic,
    indikator: measurement.penugasan_indikator.indikator_kinerja, unit: measurement.penugasan_indikator.unit_kerja,
    jenis: 'existing', pengukuran: measurement, versi_berlaku: tujuan, can: { create: false, pindahSnapshot: false },
};
const indexProps = (pengukurans: BarisPengukuran[]) => ({
    konteks: [{ jadwal_id: 'jad-1', tahun: 2026, periode: measurement.periode_jadwal }],
    filters: { jadwal_id: null, periode_id: null }, can: { selectPeriod: false },
    pengukurans, pagination: { links: [], from: 1, to: pengukurans.length, total: pengukurans.length },
});

it('Index memperlihatkan label bagi PIC dan tombol hanya dengan capability', () => {
    const { rerender } = render(<PengukuranIndex {...indexProps([row])} />);
    expect(screen.getAllByText('Versi lama').length).toBeGreaterThan(0);
    expect(screen.queryByRole('button', { name: /^Pindahkan/ })).toBeNull();
    rerender(<PengukuranIndex {...indexProps([{ ...row, can: { create: false, pindahSnapshot: true } }])} />);
    expect(screen.getByRole('button', { name: /^Pindahkan/ })).toBeTruthy();
    rerender(<PengukuranIndex {...indexProps([{ ...row, versi_berlaku: null }])} />);
    expect(screen.queryByText('Versi lama')).toBeNull();
    expect(screen.queryByRole('button', { name: /^Pindahkan/ })).toBeNull();
});

it('Edit memakai isDirty nyata dan tetap menampilkan banner bagi PIC', async () => {
    const user = userEvent.setup();
    const data: Pengukuran = { ...measurement, versi_berlaku: tujuan, can: { ...measurement.can, pindahSnapshot: true } };
    const { rerender } = render(<PengukuranEdit pengukuran={data} />);
    expect(screen.getByText('Memakai versi lama. Versi 2 berlaku untuk periode ini.')).toBeTruthy();
    const pindah = () => screen.getByRole<HTMLButtonElement>('button', { name: /^Pindahkan/ });
    expect(pindah().disabled).toBe(false);
    const catatan = screen.getByRole('textbox', { name: 'Catatan' });
    await user.type(catatan, 'Belum disimpan');
    expect(pindah().disabled).toBe(true);
    expect(screen.getByText('Simpan perubahan dulu')).toBeTruthy();
    expect(screen.getByRole('button', { name: /^Pindahkan/, description: 'Simpan perubahan dulu' })).toBe(pindah());
    await user.clear(catatan);
    expect(pindah().disabled).toBe(false);
    expect(screen.queryByText('Simpan perubahan dulu')).toBeNull();
    rerender(<PengukuranEdit pengukuran={{ ...data, can: { ...data.can, pindahSnapshot: false } }} />);
    expect(screen.getByText('Memakai versi lama. Versi 2 berlaku untuk periode ini.')).toBeTruthy();
    expect(screen.queryByRole('button', { name: /^Pindahkan/ })).toBeNull();
});

