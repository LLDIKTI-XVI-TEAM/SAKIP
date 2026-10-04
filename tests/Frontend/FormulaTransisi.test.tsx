import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http } from '@inertiajs/core';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FormulaModal } from '@/Pages/Perencanaan/SasaranIndikator/FormulaModal';
import Index from '@/Pages/Perencanaan/SasaranIndikator/Index';
import type { DefinitionEditor } from '@/Pages/Indikator/Komponen/definition';
import { COMPONENTS, definition, indicator, REVISION } from './indikatorFixtures';

vi.mock('@inertiajs/react', async (original) => ({ ...(await original<typeof import('@inertiajs/react')>()), Head: () => null }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
const reason = 'Perbaikan formula sesuai kebutuhan indikator.';
function open(editor: DefinitionEditor = definition()) {
    const close = vi.fn();
    render(<FormulaModal isOpen editor={editor} onClose={close} />);
    return { user: userEvent.setup(), close, patch: vi.spyOn(router, 'patch').mockImplementation(() => undefined) };
}
type Callbacks = { onSuccess: (page: { props: Record<string, unknown>; flash: Record<string, unknown> }) => void; onError: (errors: Record<string, string>) => void; onFinish: () => void; onNetworkError: () => void };
async function submit(user: ReturnType<typeof userEvent.setup>) { await user.click(screen.getByRole('button', { name: 'Simpan Formula' })); }

describe('Editor formula atomik dengan intent eksplisit', () => {
    it.each(['rasio_persen', 'penjumlahan'] as const)('memulihkan ID nonaktif secara eksplisit saat manual kembali ke %s', async (tipe) => {
        const rows = COMPONENTS.map((row) => ({ ...row, aktif: false, ...(tipe === 'penjumlahan' ? { peran: 'penjumlah' as const } : {}) }));
        const { user, patch } = open(definition({ indikator: indicator({ tipe_perhitungan: 'manual' }), komponen: rows }));
        expect((screen.getByLabelText('Komponen 1 aktif') as HTMLInputElement).checked).toBe(false);
        await user.selectOptions(screen.getByLabelText('Tipe Perhitungan Target'), tipe);
        await user.click(screen.getByLabelText('Komponen 1 aktif')); await user.click(screen.getByLabelText('Komponen 2 aktif'));
        await user.type(screen.getByLabelText('Alasan perubahan formula'), reason); await submit(user);
        expect(patch.mock.calls[0]?.[1]).toMatchObject({ tipe_perhitungan: tipe, komponen: rows.map((row) => ({ ...row, aktif: true })), expected_updated_at: REVISION });
    });
    it('menampilkan aktif dan nonaktif tanpa mengirim ulang baris yang tidak berubah', async () => {
        const { user, patch } = open(definition({ komponen: [COMPONENTS[0], { ...COMPONENTS[1], aktif: false }] }));
        expect(screen.getByLabelText('Kode komponen 2')).toBeTruthy(); await submit(user);
        expect(patch.mock.calls[0]?.[1]).toMatchObject({ komponen: [], hapus_komponen_ids: [] });
    });
    it('tidak mengizinkan penyimpanan saat child tidak dapat diakses', () => {
        open(definition({ komponen: null }));
        expect(screen.getByText('Data komponen formula tidak dapat diakses.')).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Simpan Formula' }) as HTMLButtonElement).disabled).toBe(true);
    });
    it('tidak mengizinkan penyimpanan baseline halaman parsial', () => {
        open(definition({ pagination: { page: 1, per_page: 50, total: 51, next_page: 2, complete: false } }));
        expect((screen.getByRole('button', { name: 'Simpan Formula' }) as HTMLButtonElement).disabled).toBe(true);
    });
    it('mengirim satu mutation dan menahan penutupan selama request belum selesai', async () => {
        const { user, patch, close } = open(); await user.dblClick(screen.getByRole('button', { name: 'Simpan Formula' })); await user.click(screen.getByRole('button', { name: 'Batal' }));
        expect(patch).toHaveBeenCalledTimes(1); expect(close).not.toHaveBeenCalled();
    });
    it.each(['rasio_persen', 'penjumlahan'] as const)('mempertahankan ID dan decimal string pada edit tipe %s', async (tipe) => {
        const { user, patch } = open(definition({ indikator: indicator({ tipe_perhitungan: tipe }) }));
        await user.type(screen.getByLabelText('Label komponen 1'), ' terkoreksi'); await user.type(screen.getByLabelText('Alasan perubahan formula'), reason); await submit(user);
        expect(patch.mock.calls[0]?.[1]).toMatchObject({ komponen: [{ ...COMPONENTS[0], label: 'Capaian aktual terkoreksi' }], alasan: reason });
    });
    it('memisahkan hapus eksplisit dari omission saat transisi rasio ke penjumlahan', async () => {
        const { user, patch } = open(); await user.selectOptions(screen.getByLabelText('Tipe Perhitungan Target'), 'penjumlahan');
        await user.selectOptions(screen.getByLabelText('Peran komponen 1'), 'penjumlah'); await user.click(screen.getByRole('button', { name: 'Hapus komponen 2' })); await submit(user);
        expect(patch.mock.calls[0]?.[1]).toMatchObject({ tipe_perhitungan: 'penjumlahan', komponen: [{ ...COMPONENTS[0], peran: 'penjumlah' }], hapus_komponen_ids: [COMPONENTS[1].id] });
    });
    it('target manual mempertahankan baris dan mengirim deaktivasi eksplisit', async () => {
        const { user, patch } = open(); await user.selectOptions(screen.getByLabelText('Tipe Perhitungan Target'), 'manual');
        expect(screen.getByLabelText('Kode komponen 1')).toBeTruthy();
        await user.click(screen.getByLabelText('Komponen 1 aktif')); await user.click(screen.getByLabelText('Komponen 2 aktif')); await submit(user);
        expect(patch.mock.calls[0]?.[1]).toMatchObject({ komponen: COMPONENTS.map((row) => ({ ...row, aktif: false })) });
    });
    it('memetakan error intent ke baris kedua dan mempertahankan draft/token saat stale', async () => {
        const { user, patch } = open(); await user.type(screen.getByLabelText('Label komponen 2'), ' draft'); await submit(user);
        const options = patch.mock.calls[0]?.[2] as unknown as Callbacks;
        await act(async () => { options.onError({ konflik: 'Versi formula berubah.', 'komponen.0.label': 'Label ditolak.' }); options.onFinish(); });
        expect(screen.getByText('Versi formula berubah.')).toBeTruthy();
        expect(screen.getByLabelText('Label komponen 2').getAttribute('aria-invalid')).toBe('true');
        expect((screen.getByLabelText('Label komponen 2') as HTMLInputElement).value).toBe('Total target draft');
    });
    it.each(['unchanged', 'saved', 'missing', 'mismatch', 'network'] as const)('memeriksa outcome %s sebelum menutup/reset', async (status) => {
        const { user, patch, close } = open(); await user.type(screen.getByLabelText('Alasan perubahan formula'), reason); await submit(user);
        const data = patch.mock.calls[0]?.[1] as Record<string, unknown>;
        const options = patch.mock.calls[0]?.[2] as unknown as Callbacks;
        await act(async () => {
            if (status === 'network') options.onNetworkError();
            else options.onSuccess({ props: {}, flash: status === 'missing' ? {} : { indikatorMutation: { request_id: status === 'mismatch' ? 'other' : data.request_id, indikator_id: 'ind-1', revision: REVISION, status } } });
            options.onFinish();
        });
        expect(close).toHaveBeenCalledTimes(status === 'saved' ? 1 : 0);
        if (status === 'unchanged') { expect(screen.getByText('Tidak ada perubahan.')).toBeTruthy(); expect((screen.getByLabelText('Alasan perubahan formula') as HTMLTextAreaElement).value).toBe(reason); }
        if (['missing', 'mismatch', 'network'].includes(status)) { expect(screen.getByText(/Hasil penyimpanan belum terkonfirmasi/)).toBeTruthy(); expect((screen.getByRole('button', { name: 'Simpan Formula' }) as HTMLButtonElement).disabled).toBe(true); }
    });
    it('manual kosong tidak membuat baris palsu', () => { open(definition({ indikator: indicator({ tipe_perhitungan: 'manual' }), komponen: [], pagination: { page: 1, per_page: 50, total: 0, next_page: null, complete: true } })); expect(screen.queryByLabelText('Kode komponen 1')).toBeNull(); });
    it('capability komponen update tidak membuka perubahan tipe atau membuat/menghapus baris', () => {
        open(definition({ can: { create: false, update: true, delete: false, update_indikator: false } }));
        expect((screen.getByLabelText('Tipe Perhitungan Target') as HTMLSelectElement).disabled).toBe(true);
        expect(screen.queryByRole('button', { name: 'Tambah Komponen' })).toBeNull(); expect(screen.queryByRole('button', { name: 'Hapus komponen 1' })).toBeNull();
        expect((screen.getByLabelText('Label komponen 1') as HTMLInputElement).disabled).toBe(false);
    });
    it.each(['komponen.0.kode', 'komponen.0.bobot', 'alasan', 'tipe_perhitungan'])('menampilkan validasi server %s tanpa menghapus draft', async (field) => {
        const { user, patch } = open(); await user.type(screen.getByLabelText('Label komponen 1'), ' draft'); await submit(user);
        await act(async () => { (patch.mock.calls[0]?.[2] as unknown as Callbacks).onError({ [field]: 'Validasi domain ditolak server.' }); });
        expect(screen.getAllByText('Validasi domain ditolak server.').length).toBeGreaterThan(0);
        if (field === 'alasan') {
            const alasan = screen.getByRole('textbox', { name: 'Alasan perubahan formula' });
            expect(alasan.getAttribute('aria-invalid')).toBe('true');
            expect(document.getElementById(alasan.getAttribute('aria-describedby') ?? '')?.textContent).toBe('Validasi domain ditolak server.');
        }
        expect((screen.getByLabelText('Label komponen 1') as HTMLInputElement).value).toBe('Capaian aktual draft');
    });
});

it('daftar membuka editor dari aggregate server, bukan child/token dalam listing', async () => {
    const user = userEvent.setup();
    const request = vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, data: JSON.stringify(definition({ revision: 'server-revision' })), headers: {} });
    const patch = vi.spyOn(router, 'patch').mockImplementation(() => undefined);
    render(<Index renstras={[]} selectedRenstraId="ren-1" sasarans={[{ id: 'sas-1', renstra_id: 'ren-1', kode: 'SS-01', deskripsi: 'Sasaran', urutan: 1, indikator_kinerjas: [indicator({ updated_at: 'list-old' })] }]} units={[]} regulasis={[]} can={{ sasaran_create: false, sasaran_update: false, sasaran_delete: false, indikator_create: false, indikator_read: true, indikator_update: true, indikator_delete: false, komponen_read: true, komponen_update: true }} />);
    await user.click(screen.getByRole('button', { name: 'Atur formula indikator IKU-01' }));
    await screen.findByLabelText('Tipe Perhitungan Target'); await submit(user);
    expect(request).toHaveBeenCalledWith(expect.objectContaining({ url: '/perencanaan/indikator/ind-1/editor' }));
    expect(patch.mock.calls[0]?.[1]).toHaveProperty('expected_updated_at', 'server-revision');
});
it.each([401, 419, 403])('status %s tidak menutup draft atau memicu replay', async (status) => {
    const { user, patch, close } = open(); await submit(user);
    const options = patch.mock.calls[0]?.[2] as unknown as { onHttpException: (response: unknown) => void; onFinish: () => void };
    await act(async () => { options.onHttpException({ status, data: '{}', headers: {} }); options.onFinish(); });
    expect(close).not.toHaveBeenCalled(); expect(patch).toHaveBeenCalledTimes(1);
    expect((screen.getByRole('button', { name: 'Simpan Formula' }) as HTMLButtonElement).disabled).toBe(true);
    if (status === 401) expect(screen.getByRole('link', { name: 'Masuk ulang' })).toBeTruthy();
    if (status === 419) expect(screen.getByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
    if (status === 403) expect(screen.getByText(/Hasil penyimpanan belum terkonfirmasi/)).toBeTruthy();
});
it('mempertahankan kedua error ketika intent 0 dan 1 berasal dari baris editor 2 dan 3', async () => {
    const third = { ...COMPONENTS[0], id: '00000000-0000-4000-8000-000000000003', kode: 'X', label: 'Tambahan', urutan: 3 };
    const { user, patch } = open(definition({ komponen: [...COMPONENTS, third] }));
    await user.type(screen.getByLabelText('Label komponen 2'), ' revisi');
    await user.type(screen.getByLabelText('Label komponen 3'), ' revisi');
    await submit(user);
    const options = patch.mock.calls[0]?.[2] as unknown as Callbacks;
    await act(async () => {
        options.onError({ 'komponen.0.label': 'Label kedua ditolak.', 'komponen.1.label': 'Label ketiga ditolak.' });
        options.onFinish();
    });
    expect(screen.getByLabelText('Label komponen 2').getAttribute('aria-invalid')).toBe('true');
    expect(screen.getByLabelText('Label komponen 3').getAttribute('aria-invalid')).toBe('true');
    expect(screen.getByLabelText('Label komponen 1').getAttribute('aria-invalid')).not.toBe('true');
});
