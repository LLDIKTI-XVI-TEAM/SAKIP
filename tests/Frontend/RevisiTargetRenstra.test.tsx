import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import { TargetRenstraTable } from '@/Pages/Perencanaan/SasaranIndikator/TargetRenstraTable';
import type { IndikatorKinerjaItem, RenstraOption, RevisiTargetRenstra, SasaranStrategisItem } from '@/types/sasaran-indikator';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { pengaturan: {} } }),
}));

let put: ReturnType<typeof vi.spyOn>;
let post: ReturnType<typeof vi.spyOn>;
let hapus: ReturnType<typeof vi.spyOn>;
let reload: ReturnType<typeof vi.spyOn>;
beforeEach(() => {
    put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    hapus = vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const KONFLIK = 'Draf revisi sudah berubah oleh pengguna lain atau tidak lagi terbuka. Muat ulang untuk melanjutkan.';

function indikator(overrides: Partial<IndikatorKinerjaItem> = {}): IndikatorKinerjaItem {
    return {
        id: 'ind-1', sasaran_strategis_id: 'sas-1', regulasi_id: null, kode: 'IKU-1', nama: 'Persentase lulusan',
        definisi_operasional: null, satuan: '%', unit_id: 'unit-a', unit_nama: 'Unit A', arah: 'naik_baik',
        tipe_perhitungan: 'manual', presisi: 2, desimal_tampilan: 2, wajib_catatan: false, dinilai_tahunan: false,
        status: 'aktif', tahun_mulai_berlaku: 2026,
        target_renstra: [{ tahun: 2026, nilai: '10' }, { tahun: 2028, nilai: '30' }],
        target_renstra_state: 'a'.repeat(64),
        ...overrides,
    };
}

const renstra = (status: RenstraOption['status'] = 'aktif'): RenstraOption =>
    ({ id: 'r-1', kode: 'REN-1', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029, is_aktif: status === 'aktif', status });

const sasarans = (inds = [indikator()]): SasaranStrategisItem[] =>
    [{ id: 'sas-1', renstra_id: 'r-1', kode: 'SS-01', deskripsi: 'Tata kelola akuntabel', urutan: 1, indikator_kinerjas: inds }];

const draf = (overrides: Partial<RevisiTargetRenstra> = {}): RevisiTargetRenstra => ({
    id: 'rev-1', versi: 2, justifikasi: 'Draf alasan', dibuat_oleh: 'Rina Perencana', diubah_pada: '2026-10-25T08:00:00+08:00',
    nilai: { 'ind-1': { 2026: null, 2027: '5' } }, ...overrides,
});

function tampilkan({ status = 'aktif' as RenstraOption['status'], canEdit = true, revisi = draf() as RevisiTargetRenstra | null, inds = [indikator()] } = {}) {
    return render(<TargetRenstraTable renstra={renstra(status)} sasarans={sasarans(inds)} canEdit={canEdit} onSaved={vi.fn()} revisi={revisi} />);
}

const input = (tahun: number, kode = 'IKU-1') => screen.queryByRole('textbox', { name: `Target ${tahun} ${kode}` }) as HTMLInputElement | null;
const opsi = (spy: ReturnType<typeof vi.spyOn>, index = 0) => spy.mock.calls[index][2] as {
    preserveState?: boolean; only?: string[]; onError: (errors: Record<string, string>) => void; onSuccess: () => void;
};

it('Renstra aktif tanpa draf menawarkan Mode revisi hanya bagi pemegang izin; histori terbuka bagi pembaca', () => {
    const view = tampilkan({ revisi: null });
    expect(input(2026)).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Mode revisi' }));
    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/perencanaan/renstra/r-1/revisi-target');
    expect(screen.getByRole('button', { name: 'Histori revisi' })).toBeTruthy();
    view.unmount();

    const pembaca = tampilkan({ revisi: null, canEdit: false });
    expect(screen.queryByRole('button', { name: 'Mode revisi' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Histori revisi' })).toBeTruthy();
    pembaca.unmount();

    tampilkan({ revisi: null, status: 'draft' });
    expect(screen.queryByRole('button', { name: 'Mode revisi' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Histori revisi' })).toBeNull();
});

it('draf terbuka membuat seluruh sel berlaku dapat diisi, dengan isian draf di atas nilai berlaku', () => {
    tampilkan({ inds: [indikator(), indikator({ id: 'ind-2', kode: 'IKU-2', status: 'arsip' })] });
    expect(screen.getByText(/Rina Perencana/)).toBeTruthy();
    // Sel draf null berarti dikosongkan; sel tanpa draf memakai nilai berlaku.
    expect(input(2026)!.value).toBe('');
    expect(input(2027)!.value).toBe('5');
    expect(input(2028)!.value).toBe('30');
    expect(input(2029)!.value).toBe('');
    expect(input(2025)).toBeNull();
    expect(input(2026, 'IKU-2')).toBeNull();
    expect((screen.getByLabelText(/Justifikasi revisi/) as HTMLTextAreaElement).value).toBe('Draf alasan');
    expect(screen.queryByRole('button', { name: 'Ubah target Renstra IKU-1' })).toBeNull();
});

it('draf yang baru dibuka tanpa isian tidak diumumkan sebagai tersimpan', () => {
    tampilkan({ revisi: draf({ nilai: {}, justifikasi: null }) });
    expect(screen.getByRole('status').textContent).toBe('Draf belum memiliki isian.');
});

it('autosave mengirim sel yang diubah dan justifikasi setelah jeda, dengan versi draf dan partial reload', () => {
    vi.useFakeTimers();
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: '12,5' } });
    fireEvent.change(input(2028)!, { target: { value: '' } });
    fireEvent.change(screen.getByLabelText(/Justifikasi revisi/), { target: { value: 'Target terlampaui.' } });
    act(() => { vi.advanceTimersByTime(700); });
    expect(put).not.toHaveBeenCalled();
    act(() => { vi.advanceTimersByTime(200); });

    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0][0]).toBe('/perencanaan/renstra/r-1/revisi-target');
    expect(put.mock.calls[0][1]).toEqual({ versi: 2, nilai: { 'ind-1': { 2026: '12,5', 2028: null } }, justifikasi: 'Target terlampaui.' });
    expect(opsi(put)).toMatchObject({ preserveState: true, only: ['revisi_target'] });
    expect(screen.getByRole('button', { name: 'Simpan revisi' })).toBeTruthy();
});

it('galat sel dari server tampil di selnya, dan 409 menghentikan autosave serta menawarkan muat ulang', () => {
    vi.useFakeTimers();
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: 'abc' } });
    act(() => { vi.advanceTimersByTime(900); });
    act(() => { opsi(put).onError({ 'nilai.ind-1.2026': 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.' }); });
    expect(screen.getByText('Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.')).toBeTruthy();

    fireEvent.change(input(2026)!, { target: { value: '12' } });
    act(() => { vi.advanceTimersByTime(900); });
    expect(put).toHaveBeenCalledTimes(2);
    act(() => { opsi(put, 1).onError({ versi: 'Draf revisi sudah berubah atau tidak lagi terbuka. Muat ulang untuk melanjutkan.' }); });
    expect(screen.getByRole('alert').textContent).toContain(KONFLIK);
    // Status tidak boleh mengklaim tersimpan saat perubahan terakhir ditolak.
    expect(screen.getByRole('status').textContent).toBe('Perubahan terakhir belum tersimpan.');
    fireEvent.change(input(2026)!, { target: { value: '13' } });
    act(() => { vi.advanceTimersByTime(2000); });
    expect(put).toHaveBeenCalledTimes(2);
    expect((screen.getByRole('button', { name: 'Simpan revisi' }) as HTMLButtonElement).disabled).toBe(true);

    fireEvent.click(screen.getByRole('button', { name: 'Muat ulang' }));
    expect(reload).toHaveBeenCalledTimes(1);
    const muat = reload.mock.calls[0][0] as { only: string[]; onSuccess: () => void };
    expect(muat.only).toEqual(['revisi_target', 'sasarans']);
    // Setelah data terbaru dimuat, isian lokal yang basi dibuang dan autosave dapat berjalan lagi.
    act(() => { muat.onSuccess(); });
    expect(screen.queryByRole('alert')).toBeNull();
    expect(input(2026)!.value).toBe('');
});

it('Simpan revisi ditahan selama ada perubahan belum terkirim, lalu mengirim versi dan justifikasi', () => {
    vi.useFakeTimers();
    tampilkan();
    const simpan = () => screen.getByRole('button', { name: 'Simpan revisi' }) as HTMLButtonElement;
    fireEvent.change(input(2026)!, { target: { value: '12' } });
    expect(simpan().disabled).toBe(true);
    act(() => { vi.advanceTimersByTime(900); });
    expect(simpan().disabled).toBe(false);

    fireEvent.click(simpan());
    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/perencanaan/renstra/r-1/revisi-target/simpan');
    expect(post.mock.calls[0][1]).toEqual({ versi: 2, justifikasi: 'Draf alasan' });
    // Tab Target Renstra tetap terbuka setelah revisi disimpan.
    expect(opsi(post)).toMatchObject({ preserveState: true });
    act(() => { opsi(post).onError({ justifikasi: 'Justifikasi revisi wajib diisi.', nilai: 'Tidak ada nilai yang berubah, revisi tidak dapat disimpan.' }); });
    expect(screen.getByText('Justifikasi revisi wajib diisi.')).toBeTruthy();
    expect(screen.getByText('Tidak ada nilai yang berubah, revisi tidak dapat disimpan.')).toBeTruthy();
});

it('Batal revisi meminta konfirmasi lalu menghapus draf dengan versinya', () => {
    tampilkan();
    fireEvent.click(screen.getByRole('button', { name: 'Batal revisi' }));
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByText(/target Renstra tidak berubah/)).toBeTruthy();
    expect(hapus).not.toHaveBeenCalled();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Ya, batalkan revisi' }));

    expect(hapus).toHaveBeenCalledTimes(1);
    expect(hapus.mock.calls[0][0]).toBe('/perencanaan/renstra/r-1/revisi-target');
    expect((hapus.mock.calls[0][1] as { data: unknown; preserveState?: boolean })).toMatchObject({ data: { versi: 2 }, preserveState: true });
});

it('histori revisi tampil sebagai daftar tertutup dan setiap revisi dibuka untuk melihat justifikasi dan perubahannya', async () => {
    const ubah = (tahun: number, lama: string | null, baru: string) => ({ indikator: { kode: 'IKU-1', nama: 'Persentase lulusan', desimal_tampilan: 2 }, tahun, nilai_lama: lama, nilai_baru: baru });
    const fetchMock = vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ terpotong: false, items: [
        { nomor: 2, waktu: '2026-10-25T09:00:00+08:00', aktor: 'Rina Perencana', justifikasi: 'Target terlampaui.', perubahan: [ubah(2026, '10', '12.5')] },
        { nomor: 1, waktu: '2026-10-20T09:00:00+08:00', aktor: null, justifikasi: 'Pengisian awal.', perubahan: [ubah(2027, null, '5'), ubah(2028, null, '6')] },
    ] }) }));
    vi.stubGlobal('fetch', fetchMock);
    tampilkan({ revisi: null, canEdit: false });
    fireEvent.click(screen.getByRole('button', { name: 'Histori revisi' }));

    const dialog = await screen.findByRole('dialog');
    expect((fetchMock.mock.calls[0] as unknown[])[0]).toBe('/perencanaan/renstra/r-1/revisi-target/histori');
    await waitFor(() => expect(within(dialog).getByText('Revisi ke-2')).toBeTruthy());
    const ringkasan = (nomor: number) => within(dialog).getByText(`Revisi ke-${nomor}`).closest('summary');
    const butir = (nomor: number) => ringkasan(nomor)?.closest('details') as HTMLDetailsElement | null | undefined;
    // jsdom tetap merender isi details yang tertutup, jadi status open yang membuktikan isi terlipat.
    expect([butir(2)?.open, butir(1)?.open]).toEqual([false, false]);
    expect(ringkasan(2)?.textContent).toContain('Rina Perencana');
    expect(ringkasan(2)?.textContent).toContain('1 perubahan');
    expect(ringkasan(2)?.textContent).not.toContain('Target terlampaui.');
    expect(ringkasan(1)?.textContent).toContain('—');
    expect(ringkasan(1)?.textContent).toContain('2 perubahan');

    fireEvent.click(ringkasan(2)!);
    expect([butir(2)?.open, butir(1)?.open]).toEqual([true, false]);
    expect(within(butir(2)!).getByText('Target terlampaui.')).toBeTruthy();
    expect(within(butir(2)!).getByText('12,50')).toBeTruthy();
    expect(within(butir(2)!).getByText('10,00')).toBeTruthy();
});

it('histori kosong dan gagal memberi pesan jelas', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ items: [], terpotong: false }) })));
    const view = tampilkan({ revisi: null });
    fireEvent.click(screen.getByRole('button', { name: 'Histori revisi' }));
    expect(await screen.findByText('Belum ada revisi.')).toBeTruthy();
    view.unmount();

    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 403, json: async () => ({}) })));
    tampilkan({ revisi: null });
    fireEvent.click(screen.getByRole('button', { name: 'Histori revisi' }));
    expect(await screen.findByText('Anda tidak memiliki akses membaca indikator ini.')).toBeTruthy();
});
