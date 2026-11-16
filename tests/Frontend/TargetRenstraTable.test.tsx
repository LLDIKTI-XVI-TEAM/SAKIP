import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import type { HttpExceptionResponse, Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { TargetRenstraTable } from '@/Pages/Perencanaan/SasaranIndikator/TargetRenstraTable';
import type { IndikatorKinerjaItem, RenstraOption, SasaranStrategisItem } from '@/types/sasaran-indikator';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { pengaturan: {} } }),
}));

let put: ReturnType<typeof vi.spyOn>;
beforeEach(() => {
    put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

const STATE_A = 'a'.repeat(64);
const GAGAL = 'Hasil simpan belum dapat dipastikan. Muat data terbaru sebelum mencoba lagi.';

function indikator(overrides: Partial<IndikatorKinerjaItem> = {}): IndikatorKinerjaItem {
    return {
        id: 'ind-1', sasaran_strategis_id: 'sas-1', regulasi_id: null, kode: 'IKU-1', nama: 'Persentase lulusan',
        definisi_operasional: null, satuan: '%', unit_id: 'unit-a', unit_nama: 'Unit A', arah: 'naik_baik',
        tipe_perhitungan: 'manual', presisi: 2, desimal_tampilan: 2, wajib_catatan: false, dinilai_tahunan: false,
        status: 'aktif', tahun_mulai_berlaku: 2026,
        target_renstra: [{ tahun: 2026, nilai: '0' }, { tahun: 2027, nilai: null }],
        target_renstra_state: STATE_A,
        ...overrides,
    };
}

function renstra(status: RenstraOption['status'] = 'draft'): RenstraOption {
    return { id: 'r-1', kode: 'REN-1', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029, is_aktif: status === 'aktif', status };
}

function sasarans(ind = indikator()): SasaranStrategisItem[] {
    return [{ id: 'sas-1', renstra_id: 'r-1', kode: 'SS-01', deskripsi: 'Tata kelola akuntabel', urutan: 1, indikator_kinerjas: [ind] }];
}

function tampilkan({ ind = indikator(), status = 'draft' as RenstraOption['status'], canEdit = true, onSaved = vi.fn() } = {}) {
    const view = render(<TargetRenstraTable renstra={renstra(status)} sasarans={sasarans(ind)} canEdit={canEdit} onSaved={onSaved} />);
    return { ...view, onSaved };
}

const input = (tahun: number) => screen.queryByRole('textbox', { name: `Target ${tahun} IKU-1` }) as HTMLInputElement | null;
const ubah = () => fireEvent.click(screen.getByRole('button', { name: 'Ubah target Renstra IKU-1' }));
const simpan = () => fireEvent.click(screen.getByRole('button', { name: 'Simpan' }));
const callbacks = (index = 0) => put.mock.calls[index][2] as {
    onStart: (visit: unknown) => void;
    onFinish: (visit: unknown) => void;
    onSuccess: (page: Page) => Promise<void> | void;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (response: HttpExceptionResponse) => boolean | void;
    onNetworkError: () => boolean | void;
};
const halaman = (hasil: unknown) => ({ flash: { target_renstra: hasil } }) as unknown as Page;

it('menampilkan nol, kosong, dan tahun belum berlaku tanpa tombol ubah bagi pembaca', () => {
    tampilkan({ canEdit: false });

    expect(screen.getByText('0,00')).toBeTruthy();
    // 2027 null serta 2028–2029 absen sama-sama kosong; 2025 sebelum indikator berlaku.
    expect(screen.getAllByText('—')).toHaveLength(3);
    expect(screen.getAllByText('Target Renstra belum ada')).toHaveLength(3);
    expect(screen.getAllByText('Belum berlaku')).toHaveLength(1);
    expect(screen.queryByRole('textbox')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Ubah target Renstra IKU-1' })).toBeNull();
    // Di HP header tahun disembunyikan; label tahun per sel harus tetap terbaca pembaca layar.
    const labelTahun = screen.getAllByText('2027');
    expect(labelTahun).toHaveLength(2);
    expect(labelTahun.filter((el) => el.closest('[aria-hidden="true"]'))).toHaveLength(0);
});

it('isian terkunci selama simpan berjalan agar ketikan tidak hilang saat edit ditutup', () => {
    tampilkan();
    ubah();
    simpan();
    act(() => { callbacks().onStart({}); });
    expect(input(2026)!.readOnly).toBe(true);
    expect(input(2027)!.readOnly).toBe(true);
});

it('navigasi Inertia lain ditahan selama simpan berjalan', () => {
    // PUT yang dibatalkan klien tetap bisa berhasil di server; tabel tidak boleh dilepas sebelum hasilnya diketahui.
    const navigasi = (prefetch = false) => {
        const event = new CustomEvent('inertia:before', { cancelable: true, detail: { visit: { method: 'get', url: new URL('/monitoring', window.location.href), prefetch } } });
        document.dispatchEvent(event);
        return event.defaultPrevented;
    };
    tampilkan();
    ubah();
    simpan();
    act(() => { callbacks().onStart({}); });
    expect(navigasi()).toBe(true);
    expect(navigasi(true)).toBe(false);
    act(() => { callbacks().onFinish({}); });
    expect(navigasi()).toBe(false);
});

it('hasil simpan ambigu memblokir simpan ulang dan menawarkan muat ulang', () => {
    const pertama = tampilkan();
    ubah();
    simpan();
    act(() => { callbacks().onNetworkError(); });
    expect(screen.getByText(GAGAL)).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);
    expect(screen.getByRole('button', { name: 'Muat ulang' })).toBeTruthy();
    simpan();
    expect(put).toHaveBeenCalledTimes(1);
    pertama.unmount();

    tampilkan();
    ubah();
    simpan();
    act(() => { callbacks(1).onHttpException({ status: 403 } as HttpExceptionResponse); });
    expect((screen.getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);
    expect(screen.getByRole('button', { name: 'Muat ulang' })).toBeTruthy();
    cleanup();

    // Sesi berakhir: tindak lanjutnya masuk ulang lewat pemberitahuan pemulihan, bukan muat ulang baris.
    tampilkan();
    ubah();
    fireEvent.change(input(2026)!, { target: { value: '9' } });
    simpan();
    act(() => { callbacks(2).onHttpException({ status: 401, headers: {}, data: '' } as unknown as HttpExceptionResponse); });
    expect(screen.getByText('Autentikasi diperlukan')).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);
    expect(screen.queryByRole('button', { name: 'Muat ulang' })).toBeNull();
    expect(input(2026)!.value).toBe('9');
    // Edit baru tidak dapat disimpan sampai masuk ulang, jadi tombol Ubah tidak ditawarkan.
    const batal = screen.getByRole('button', { name: 'Batal' });
    batal.focus();
    fireEvent.click(batal);
    expect(screen.queryByRole('button', { name: 'Ubah target Renstra IKU-1' })).toBeNull();
    // Tanpa tombol Ubah, fokus tetap di baris yang sama lewat tombol Riwayat.
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Riwayat target Renstra IKU-1' }));
});

it('membuka ulang edit setelah hasil ambigu tetap terblokir sampai data dimuat ulang', () => {
    tampilkan();
    ubah();
    fireEvent.change(input(2026)!, { target: { value: '5' } });
    simpan();
    act(() => { callbacks().onNetworkError(); });
    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
    ubah();
    expect(screen.getByText(GAGAL)).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);
    simpan();
    expect(put).toHaveBeenCalledTimes(1);

    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    fireEvent.click(screen.getByRole('button', { name: 'Muat ulang' }));
    act(() => { reload.mock.calls[0][0]?.onSuccess?.({} as Page); });
    ubah();
    expect(screen.queryByText(GAGAL)).toBeNull();
    expect((screen.getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(false);
});

it('Renstra draft menyimpan seluruh tahun berlaku langsung tanpa dialog', () => {
    tampilkan();
    ubah();
    expect(input(2025)).toBeNull();
    // Sel "Belum berlaku" saat edit tidak berisi isian berlabel; label tahunnya harus tetap terbaca.
    expect(screen.getAllByText('2025').filter((el) => el.closest('[aria-hidden="true"]'))).toHaveLength(0);
    fireEvent.change(input(2026)!, { target: { value: '5' } });
    fireEvent.change(input(2027)!, { target: { value: '75,5' } });
    simpan();

    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0][0]).toBe('/perencanaan/indikator/ind-1/target-renstra');
    expect(put.mock.calls[0][1]).toEqual({ nilai: { 2026: '5', 2027: '75,5', 2028: null, 2029: null }, expected_state: STATE_A });
    expect(screen.queryByRole('dialog')).toBeNull();
});

it('flash cocok menutup edit dengan pesan, flash kosong memberi "Tidak ada perubahan."', async () => {
    const pertama = tampilkan();
    ubah();
    simpan();
    await act(async () => { await callbacks().onSuccess(halaman({ indikator_id: 'ind-1', renstra_id: 'r-1', tahun: [2026] })); });
    expect(pertama.onSaved).toHaveBeenCalledWith('Target Renstra IKU-1 tersimpan.');
    expect(input(2026)).toBeNull();
    pertama.unmount();

    const kedua = tampilkan();
    ubah();
    simpan();
    await act(async () => { await callbacks(1).onSuccess(halaman({ indikator_id: 'ind-1', renstra_id: 'r-1', tahun: [] })); });
    expect(kedua.onSaved).toHaveBeenCalledWith('Tidak ada perubahan.');
    kedua.unmount();

    const ketiga = tampilkan();
    ubah();
    simpan();
    await act(async () => { await callbacks(2).onSuccess(halaman({ indikator_id: 'ind-lain', renstra_id: 'r-1', tahun: [2026] })); });
    expect(ketiga.onSaved).not.toHaveBeenCalled();
    expect(input(2026)).not.toBeNull();
    expect(screen.getByText(GAGAL)).toBeTruthy();
});

it('edit kedua memakai nilai dan state terbaru dari props', async () => {
    const onSaved = vi.fn();
    const { rerender } = tampilkan({ onSaved });
    ubah();
    fireEvent.change(input(2026)!, { target: { value: '5' } });
    simpan();
    await act(async () => { await callbacks().onSuccess(halaman({ indikator_id: 'ind-1', renstra_id: 'r-1', tahun: [2026] })); });

    const terbaru = indikator({ target_renstra: [{ tahun: 2026, nilai: '5' }], target_renstra_state: 'b'.repeat(64) });
    rerender(<TargetRenstraTable renstra={renstra()} sasarans={sasarans(terbaru)} canEdit onSaved={onSaved} />);
    ubah();
    expect(input(2026)!.value).toBe('5');
    simpan();
    expect((put.mock.calls[1][1] as { expected_state: string }).expected_state).toBe('b'.repeat(64));
});

it('kegagalan simpan mempertahankan isian baris', () => {
    const pertama = tampilkan();
    ubah();
    fireEvent.change(input(2026)!, { target: { value: '5' } });
    simpan();
    act(() => { callbacks().onHttpException({ status: 403 } as HttpExceptionResponse); });
    expect(screen.getByText('Akses ditolak. Hasil simpan belum dapat dipastikan. Periksa data setelah akses pulih.')).toBeTruthy();
    expect(input(2026)!.value).toBe('5');
    pertama.unmount();

    tampilkan();
    ubah();
    fireEvent.change(input(2026)!, { target: { value: '7' } });
    simpan();
    act(() => { callbacks(1).onNetworkError(); });
    expect(screen.getByText(GAGAL)).toBeTruthy();
    expect(input(2026)!.value).toBe('7');
});

it('error server tampil di sel dan baris, token basi memuat ulang lalu keluar edit setelah berhasil', () => {
    const pertama = tampilkan();
    ubah();
    simpan();
    act(() => { callbacks().onError({ 'nilai.2027': 'Target maksimal 2 angka desimal bermakna.', nilai: 'Indikator arsip hanya dapat dibaca.' }); });
    expect(screen.getByText('Target maksimal 2 angka desimal bermakna.')).toBeTruthy();
    expect(screen.getByText('Indikator arsip hanya dapat dibaca.')).toBeTruthy();
    pertama.unmount();

    tampilkan();
    ubah();
    simpan();
    act(() => { callbacks(1).onError({ expected_state: 'Data sudah berubah. Muat kembali data terbaru sebelum menyimpan.' }); });
    expect(screen.getByText('Data sudah berubah. Muat kembali data terbaru sebelum menyimpan.')).toBeTruthy();
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    fireEvent.click(screen.getByRole('button', { name: 'Muat ulang' }));
    expect(reload).toHaveBeenCalledTimes(1);
    expect(input(2026)).not.toBeNull();
    act(() => { reload.mock.calls[0][0]?.onSuccess?.({} as Page); });
    expect(input(2026)).toBeNull();
});

it('batal dan simpan berhasil mengembalikan fokus ke tombol ubah', async () => {
    tampilkan();
    ubah();
    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Ubah target Renstra IKU-1' }));

    ubah();
    simpan();
    await act(async () => { await callbacks().onSuccess(halaman({ indikator_id: 'ind-1', renstra_id: 'r-1', tahun: [] })); });
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Ubah target Renstra IKU-1' }));
});

it('indikator arsip, Renstra aktif atau nonaktif, atau status absen tidak menampilkan tombol ubah', () => {
    const tombol = () => screen.queryByRole('button', { name: 'Ubah target Renstra IKU-1' });
    const arsip = tampilkan({ ind: indikator({ status: 'arsip' }) });
    expect(tombol()).toBeNull();
    arsip.unmount();
    for (const status of ['aktif', 'nonaktif'] as const) {
        const view = tampilkan({ status });
        expect(tombol()).toBeNull();
        expect(screen.getByRole('button', { name: 'Riwayat target Renstra IKU-1' })).toBeTruthy();
        view.unmount();
    }
    // Default parameter tidak bisa mewakili status absen, jadi render langsung.
    render(<TargetRenstraTable renstra={{ ...renstra(), status: undefined }} sasarans={sasarans()} canEdit onSaved={vi.fn()} />);
    expect(screen.getByText('0,00')).toBeTruthy();
    expect(tombol()).toBeNull();
});

function stubRiwayat(response: { ok: boolean; status: number; json: () => Promise<unknown> }) {
    const fetchMock = vi.fn().mockResolvedValue(response);
    vi.stubGlobal('fetch', fetchMock);
    return fetchMock;
}
const sukses = (body: unknown) => ({ ok: true, status: 200, json: async () => body });
const bukaRiwayat = () => fireEvent.click(screen.getByRole('button', { name: 'Riwayat target Renstra IKU-1' }));

it('riwayat dimuat saat ikon diklik dan menampilkan entri', async () => {
    const fetchMock = stubRiwayat(sukses({
        items: [
            { waktu: '2026-10-17T02:00:00+00:00', aktor: 'Penulis', tahun: 2026, nilai_lama: '10', nilai_baru: '12' },
            { waktu: '2026-10-16T02:00:00+00:00', aktor: 'Penulis', tahun: 2026, nilai_lama: null, nilai_baru: '10' },
        ],
        terpotong: false,
    }));
    tampilkan({ canEdit: false });
    expect(fetchMock).not.toHaveBeenCalled();

    bukaRiwayat();
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
    expect(fetchMock.mock.calls[0][0]).toBe('/perencanaan/indikator/ind-1/target-renstra/riwayat');
    expect(fetchMock.mock.calls[0][1].headers.Accept).toBe('application/json');

    const dialog = await screen.findByRole('dialog');
    expect(await within(dialog).findAllByText('Penulis')).toHaveLength(2);
    expect(within(dialog).getByText('12,00')).toBeTruthy();
    expect(within(dialog).getAllByText('10,00')).toHaveLength(2);
    expect(within(dialog).getAllByText('—')).toHaveLength(1);
    expect(within(dialog).queryByText(/Alasan/)).toBeNull();
});

it('riwayat kosong, terpotong, dan gagal memberi pesan jelas', async () => {
    const kasus: [{ ok: boolean; status: number; json: () => Promise<unknown> }, string][] = [
        [sukses({ items: [], terpotong: false }), 'Belum ada riwayat.'],
        [sukses({ items: [{ waktu: '2026-10-17T02:00:00+00:00', aktor: 'Penulis', tahun: 2026, nilai_lama: null, nilai_baru: '1' }], terpotong: true }), 'Menampilkan 100 perubahan terbaru.'],
        [{ ok: false, status: 403, json: async () => ({}) }, 'Anda tidak memiliki akses membaca indikator ini.'],
        [{ ok: false, status: 401, json: async () => ({}) }, 'Sesi berakhir. Masuk ulang untuk melihat data.'],
        [sukses({ items: 'rusak', terpotong: false }), 'Riwayat belum dapat dimuat. Coba lagi.'],
    ];
    for (const [response, pesan] of kasus) {
        stubRiwayat(response);
        const view = tampilkan({ canEdit: false });
        bukaRiwayat();
        const dialog = await screen.findByRole('dialog');
        expect(await within(dialog).findByText(pesan)).toBeTruthy();
        // Satu kejadian gagal hanya diumumkan sekali, termasuk saat pemulihan sesi tampil.
        if (!response.ok) expect(within(dialog).getAllByRole('alert')).toHaveLength(1);
        view.unmount();
    }
});
