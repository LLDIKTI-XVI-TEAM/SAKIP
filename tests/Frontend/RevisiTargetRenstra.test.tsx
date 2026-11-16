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
const BELUM_PASTI = 'Hasil penyimpanan otomatis terakhir belum dapat dipastikan. Muat ulang untuk memeriksa draf terbaru sebelum melanjutkan.';

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
    preserveState?: boolean; only?: string[]; onError: (errors: Record<string, string>) => void; onSuccess: (page?: unknown) => void;
    onNetworkError?: (error: Error) => unknown; onHttpException?: (response: unknown) => unknown; onCancel?: () => unknown;
};
/** Halaman hasil reload setelah autosave; Inertia memberikannya ke onSuccess. */
const halaman = (revisi: Partial<RevisiTargetRenstra>) => ({ props: { revisi_target: draf(revisi) } });

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
    expect(put.mock.calls[0][1]).toEqual({ revisi_id: 'rev-1', versi: 2, nilai: { 'ind-1': { 2026: '12,5', 2028: null } }, justifikasi: 'Target terlampaui.' });
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
    expect(post.mock.calls[0][1]).toEqual({ revisi_id: 'rev-1', versi: 2, justifikasi: 'Draf alasan' });
    // Tab Target Renstra tetap terbuka setelah revisi disimpan.
    expect(opsi(post)).toMatchObject({ preserveState: true });
    act(() => { opsi(post).onError({ justifikasi: 'Justifikasi revisi wajib diisi.', nilai: 'Tidak ada nilai yang berubah, revisi tidak dapat disimpan.' }); });
    expect(screen.getByText('Justifikasi revisi wajib diisi.')).toBeTruthy();
    expect(screen.getByText('Tidak ada nilai yang berubah, revisi tidak dapat disimpan.')).toBeTruthy();
});

it('Simpan revisi terkunci selama autosave terakhir ditolak, lalu terbuka lagi setelah isian tersimpan', () => {
    vi.useFakeTimers();
    tampilkan();
    const simpan = () => screen.getByRole('button', { name: 'Simpan revisi' }) as HTMLButtonElement;
    fireEvent.change(input(2026)!, { target: { value: '12,5' } });
    act(() => { vi.advanceTimersByTime(900); });
    act(() => { opsi(put).onError({ 'nilai.ind-1.2026': 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.' }); });
    expect(screen.getByRole('status').textContent).toBe('Draf belum tersimpan. Periksa isian yang ditandai.');
    // Simpan hanya mengirim versi dan justifikasi, jadi menyimpan sekarang akan memakai isian lama di server.
    fireEvent.click(simpan());
    expect([simpan().disabled, post.mock.calls.length]).toEqual([true, 0]);

    fireEvent.change(input(2026)!, { target: { value: '12.5' } });
    act(() => { vi.advanceTimersByTime(900); });
    expect(put).toHaveBeenCalledTimes(2);
    act(() => { opsi(put, 1).onSuccess(halaman({ versi: 3 })); });
    expect([simpan().disabled, screen.queryByRole('alert')]).toEqual([false, null]);
});

it('reload setelah autosave yang membawa versi lebih baru dari hasil PUT ini menghentikan autosave untuk rekonsiliasi', () => {
    vi.useFakeTimers();
    tampilkan();
    const simpan = () => screen.getByRole('button', { name: 'Simpan revisi' }) as HTMLButtonElement;
    fireEvent.change(input(2026)!, { target: { value: '12' } });
    act(() => { vi.advanceTimersByTime(900); });
    // Pengguna lain menyimpan di antara PUT ini (versi 2 → 3) dan reload, sehingga reload membawa versi 4.
    act(() => { opsi(put).onSuccess(halaman({ versi: 4, nilai: { 'ind-1': { 2026: '99' } } })); });

    expect(screen.getByRole('alert').textContent).toContain(KONFLIK);
    expect(simpan().disabled).toBe(true);
    fireEvent.change(input(2026)!, { target: { value: '13' } });
    act(() => { vi.advanceTimersByTime(2000); });
    expect(put).toHaveBeenCalledTimes(1);
});

// false mencegah dialog atau error bawaan Inertia; pembatalan tidak memiliki perilaku bawaan untuk dicegah.
it.each([
    ['gagal jaringan', (o: ReturnType<typeof opsi>) => o.onNetworkError?.(new Error('offline')), false],
    ['mendapat respons HTTP 500', (o: ReturnType<typeof opsi>) => o.onHttpException?.({ status: 500, data: '<html></html>', headers: {} }), false],
    ['dibatalkan', (o: ReturnType<typeof opsi>) => o.onCancel?.(), undefined],
])('autosave yang %s menahan Simpan revisi sampai draf dimuat ulang', (_, gagal, kembalian) => {
    vi.useFakeTimers();
    tampilkan();
    const tombol = (name: string) => screen.getByRole('button', { name }) as HTMLButtonElement;
    fireEvent.change(input(2026)!, { target: { value: '12' } });
    act(() => { vi.advanceTimersByTime(900); });
    let hasil: unknown = 'tidak dipanggil';
    act(() => { hasil = gagal(opsi(put)); });

    // Hasil PUT tidak diketahui: Simpan memakai isian di server yang mungkin belum memuat isian terakhir di layar.
    expect(hasil).toBe(kembalian);
    expect(screen.getByRole('alert').textContent).toContain(BELUM_PASTI);
    expect(screen.getByRole('status').textContent).toBe('Penyimpanan terakhir belum terkonfirmasi.');
    expect([tombol('Simpan revisi').disabled, tombol('Batal revisi').disabled, input(2026)!.readOnly]).toEqual([true, true, true]);
    fireEvent.change(input(2026)!, { target: { value: '13' } });
    act(() => { vi.advanceTimersByTime(2000); });
    expect(put).toHaveBeenCalledTimes(1);
    fireEvent.click(tombol('Muat ulang'));
    expect(reload).toHaveBeenCalledTimes(1);
});

it('sesi atau token CSRF kedaluwarsa saat autosave memakai pemulihan sesi dan tetap menahan Simpan revisi', () => {
    vi.useFakeTimers();
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: '12' } });
    act(() => { vi.advanceTimersByTime(900); });
    let hasil: unknown;
    act(() => { hasil = opsi(put).onHttpException?.({ status: 419, headers: {},
        data: { recovery: { reason: 'csrf_mismatch', rejected: { method: 'PUT', path: '/perencanaan/renstra/r-1/revisi-target', before_action: true } } } }); });

    expect(hasil).toBe(false);
    // Penolakan cocok dengan method dan path autosave ini, jadi dinyatakan ditolak sebelum Action.
    expect(screen.getByRole('heading', { name: 'Verifikasi keamanan diperlukan' })).toBeTruthy();
    expect(screen.getByText(/Permintaan perubahan ini ditolak/)).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Simpan revisi' }) as HTMLButtonElement).disabled).toBe(true);
    // Pemulihan sesi menyediakan Muat ulang halaman sendiri; Muat ulang draf tidak ditawarkan bersamaan.
    expect(screen.queryByRole('button', { name: 'Muat ulang' })).toBeNull();
});

const kunjungan = (path: string, method = 'get', prefetch = false, tambahan: Record<string, unknown> = {}) => {
    const event = new CustomEvent('inertia:before', {
        cancelable: true,
        detail: { visit: { method, url: new URL(path, window.location.href), prefetch, ...tambahan } },
    });
    document.dispatchEvent(event);
    return event;
};

it('navigasi dengan perubahan yang belum tersimpan meminta konfirmasi dan tidak meninggalkan halaman', () => {
    vi.useFakeTimers();
    tampilkan();
    const pemicu = screen.getByRole('button', { name: 'Batal revisi' });
    pemicu.focus();
    fireEvent.change(input(2026)!, { target: { value: '12' } });

    let event!: CustomEvent;
    act(() => { event = kunjungan('/dashboard'); });
    expect(event.defaultPrevented).toBe(true);
    const dialog = screen.getByRole('dialog', { name: 'Tinggalkan halaman?' });
    expect(within(dialog).getByText('Perubahan yang belum tersimpan akan hilang.')).toBeTruthy();
    expect(put).not.toHaveBeenCalled();

    // Fokus pindah ke tombol pertama dialog, lalu kembali ke elemen pembuka saat ditutup.
    act(() => { vi.advanceTimersByTime(50); });
    expect(document.activeElement).toBe(within(dialog).getByRole('button', { name: 'Tetap di halaman' }));

    // Tetap di halaman tidak mengirim apa pun dan autosave tetap berjalan seperti biasa.
    fireEvent.click(within(dialog).getByRole('button', { name: 'Tetap di halaman' }));
    expect(screen.queryByRole('dialog', { name: 'Tinggalkan halaman?' })).toBeNull();
    expect(document.activeElement).toBe(pemicu);
    expect(input(2026)!.value).toBe('12');
    act(() => { vi.advanceTimersByTime(900); });
    expect(put).toHaveBeenCalledTimes(1);
});

it('guard navigasi tetap aktif setelah autosave ditolak validasi', async () => {
    vi.useFakeTimers();
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: 'abc' } });
    act(() => { vi.advanceTimersByTime(900); });
    // Server menolak seluruh payload; belum ada satu pun perubahan dalam request ini yang tersimpan.
    await act(async () => { opsi(put).onError({ 'nilai.ind-1.2026': 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.' }); });

    let event!: CustomEvent;
    await act(async () => { event = kunjungan('/dashboard'); });
    expect(event.defaultPrevented).toBe(true);
    expect(screen.getByRole('dialog', { name: 'Tinggalkan halaman?' })).toBeTruthy();
});

it('ketikan baru selama autosave berjalan tidak mengosongkan status belum tersimpan', async () => {
    vi.useFakeTimers();
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: '12' } });
    act(() => { vi.advanceTimersByTime(900); });
    // Ketikan ini terjadi setelah PUT dikirim, jadi hasil PUT tersebut belum memuatnya.
    fireEvent.change(input(2028)!, { target: { value: '31' } });
    await act(async () => { opsi(put).onSuccess(halaman({ versi: 3 })); });

    let event!: CustomEvent;
    await act(async () => { event = kunjungan('/dashboard'); });
    expect(event.defaultPrevented).toBe(true);
    expect(screen.getByRole('dialog', { name: 'Tinggalkan halaman?' })).toBeTruthy();
});

it('dialog konfirmasi keluar tetap berada di document.body saat tabel berada di tab tersembunyi', () => {
    const view = render(
        <div hidden>
            <TargetRenstraTable renstra={renstra()} sasarans={sasarans()} canEdit onSaved={vi.fn()} revisi={draf()} />
        </div>,
    );
    fireEvent.change(within(view.container).getByLabelText('Target 2026 IKU-1'), { target: { value: '12' } });

    act(() => { kunjungan('/dashboard'); });

    const dialog = screen.getByRole('dialog', { name: 'Tinggalkan halaman?' });
    // Tanpa portal dialog ikut tersembunyi bersama induknya, padahal scroll body sudah dikunci.
    expect(dialog.parentElement).toBe(document.body);
    expect(dialog.closest('[hidden]')).toBeNull();
    expect(view.container.contains(dialog)).toBe(false);
    expect(within(dialog).getByText('Perubahan yang belum tersimpan akan hilang.')).toBeTruthy();
});

it('beforeunload hanya menahan refresh saat ada perubahan yang belum tersimpan', () => {
    tampilkan();
    const bersih = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(bersih);
    expect(bersih.defaultPrevented).toBe(false);

    fireEvent.change(input(2026)!, { target: { value: '12' } });
    const kotor = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(kotor);
    expect(kotor.defaultPrevented).toBe(true);
});

it('beforeunload tetap aktif selama autosave masih berjalan', () => {
    vi.useFakeTimers();
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: '12' } });
    act(() => { vi.advanceTimersByTime(900); });
    act(() => { (put.mock.calls[0][2] as { onStart?: (visit: unknown) => void }).onStart?.({}); });

    const berjalan = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(berjalan);
    expect(berjalan.defaultPrevented).toBe(true);
});

it('navigasi tanpa perubahan tidak meminta konfirmasi', () => {
    tampilkan();
    const event = kunjungan('/dashboard');
    expect(event.defaultPrevented).toBe(false);
    expect(screen.queryByRole('dialog', { name: 'Tinggalkan halaman?' })).toBeNull();
});

it('mutasi draf, simpan revisi, dan batal revisi bukan navigasi yang perlu dikonfirmasi', () => {
    vi.useFakeTimers();
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: '12' } });
    const url = '/perencanaan/renstra/r-1/revisi-target';
    for (const [method, path] of [['put', url], ['post', `${url}/simpan`], ['delete', url]] as const) {
        expect(kunjungan(path, method).defaultPrevented).toBe(false);
    }
    // Prefetch juga bukan navigasi yang dipilih pengguna.
    expect(kunjungan('/dashboard', 'get', true).defaultPrevented).toBe(false);
    expect(screen.queryByRole('dialog', { name: 'Tinggalkan halaman?' })).toBeNull();
});

it('setelah "Tinggalkan halaman" visit yang sama diteruskan sekali tanpa ditahan lagi', () => {
    vi.useFakeTimers();
    let diteruskan: Event | null = null;
    const onSuccess = vi.fn();
    const visit = vi.spyOn(router, 'visit').mockImplementation((path, options) => {
        diteruskan = kunjungan(String(path), options?.method ?? 'get');
        return undefined;
    });
    tampilkan();
    fireEvent.change(input(2026)!, { target: { value: '12' } });

    act(() => { kunjungan('/dashboard', 'get', false, { onSuccess }); });
    expect(screen.getByRole('button', { name: 'Tinggalkan halaman' })).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Tinggalkan halaman' }));

    expect(visit).toHaveBeenCalledTimes(1);
    expect(String(visit.mock.calls[0][0])).toContain('/dashboard');
    // Hanya opsi sah yang diteruskan; field internal PendingVisit tidak boleh ikut dikirim ulang.
    const opsi = visit.mock.calls[0][1] as Record<string, unknown>;
    expect(opsi.method).toBe('get');
    expect(opsi.onSuccess).toBe(onSuccess);
    for (const internal of ['id', 'url', 'completed', 'cancelled', 'interrupted', 'prefetch']) {
        expect(opsi).not.toHaveProperty(internal);
    }
    expect(diteruskan).not.toBeNull();
    expect((diteruskan as unknown as Event).defaultPrevented).toBe(false);
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
    expect((hapus.mock.calls[0][1] as { data: unknown; preserveState?: boolean })).toMatchObject({ data: { revisi_id: 'rev-1', versi: 2 }, preserveState: true });
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
