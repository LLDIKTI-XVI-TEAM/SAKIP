import type { Page, PendingVisit } from '@inertiajs/core';
import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderToString } from 'react-dom/server';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import Editor from '@/Pages/Jadwal/Editor';
import Index from '@/Pages/Jadwal/Index';
import type { JadwalDetail, JadwalEditorProps } from '@/types/periode-jadwal';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { periode: true, jadwal: true } } } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

const jadwal: JadwalDetail = {
    id: 'jadwal-1',
    renstra_id: 'renstra-1',
    tahun: 2026,
    status: 'draft',
    revisi: 4,
    renstra: { id: 'renstra-1', nama: 'Renstra pengujian', status: 'aktif', tahun_mulai: 2025, tahun_selesai: 2029 },
    penutupan: '2027-01-19',
    periode: [
        {
            periode_id: 'periode-1',
            periode_revisi: 2,
            nama: 'Triwulan I',
            urutan: 1,
            aktif: true,
            is_nilai_akhir: false,
            bulan_mulai: 1,
            hari_mulai: 1,
            bulan_selesai: 3,
            hari_selesai: 31,
            pengisian_mulai: '2026-04-01',
            pengisian_selesai: '2026-04-07',
        },
        {
            periode_id: 'periode-4',
            periode_revisi: 7,
            nama: 'Triwulan IV',
            urutan: 4,
            aktif: true,
            is_nilai_akhir: true,
            bulan_mulai: 10,
            hari_mulai: 1,
            bulan_selesai: 12,
            hari_selesai: 31,
            pengisian_mulai: '2027-01-04',
            pengisian_selesai: '2027-01-11',
        },
    ],
};
const tanggal = (bulanMulai: number, bulanSelesai: number, hariSelesai: number) => ({
    bulan_mulai: bulanMulai,
    hari_mulai: 1,
    bulan_selesai: bulanSelesai,
    hari_selesai: hariSelesai,
});
const periodeOptions = [
    { id: 'periode-1', nama: 'Triwulan I', urutan: 1, aktif: true, is_nilai_akhir: false, revisi: 2, ...tanggal(1, 3, 31) },
    { id: 'periode-2', nama: 'Triwulan II', urutan: 2, aktif: true, is_nilai_akhir: false, revisi: 3, ...tanggal(4, 6, 30) },
    { id: 'periode-3', nama: 'Triwulan III', urutan: 3, aktif: true, is_nilai_akhir: false, revisi: 6, ...tanggal(7, 9, 30) },
    { id: 'periode-4', nama: 'Triwulan IV', urutan: 4, aktif: true, is_nilai_akhir: true, revisi: 7, ...tanggal(10, 12, 31) },
];
const props: JadwalEditorProps = {
    jadwal,
    can: { create: true, update: true, activate: false, close: false, revise_windows: false, tambah_indikator: false },
    read_only_reason: null,
    renstra_options: [jadwal.renstra],
    periode_options: periodeOptions,
    indikator_belum_terjadwal: [],
    periode_mulai_default: null,
    perjanjian_kinerja_id: null,
};
const createProps: JadwalEditorProps = {
    jadwal: null,
    can: { create: true, update: false, activate: false, close: false, revise_windows: false, tambah_indikator: false },
    read_only_reason: null,
    renstra_options: [jadwal.renstra],
    periode_options: periodeOptions,
    indikator_belum_terjadwal: [],
    periode_mulai_default: null,
    perjanjian_kinerja_id: null,
};
const page = (flash: Page['flash'] = {}): Page => ({
    component: 'Jadwal/Editor',
    props: { errors: {}, jadwal },
    url: '/jadwal/jadwal-1',
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rescuedProps: [],
    rememberedState: {},
    flash,
});

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
    vi.spyOn(router, 'put').mockImplementation(() => undefined);
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

it('menampilkan jendela pengisian saja tanpa isian review (Q70)', () => {
    render(<Editor {...props} />);
    expect(screen.getByRole('columnheader', { name: 'Pengisian' })).toBeTruthy();
    expect(screen.queryByRole('columnheader', { name: /review/i })).toBeNull();
    expect(screen.queryAllByLabelText(/review/i)).toHaveLength(0);
    for (const item of jadwal.periode) {
        expect(screen.getByLabelText<HTMLInputElement>(`${item.nama}: Mulai pengisian`).value).toBe(item.pengisian_mulai);
        expect(screen.getByLabelText<HTMLInputElement>(`${item.nama}: Selesai pengisian`).value).toBe(
            item.pengisian_selesai,
        );
    }
    expect(screen.queryByText('Periode berikutnya')).toBeNull();
});

it('mempertahankan tanggal dan revisi asal setelah error bersarang, dengan ringkasan fokus dan ARIA', async () => {
    const { rerender } = render(<Editor {...props} />);
    const field = screen.getByLabelText<HTMLInputElement>('Triwulan IV: Selesai pengisian');
    fireEvent.change(field, { target: { value: '2027-01-20' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    const options = vi.mocked(router.put).mock.calls[0][2];
    await act(async () => {
        options?.onError?.({ 'periode.1.pengisian_selesai': 'Tanggal harus sama dengan atau setelah tanggal jendela sebelumnya.' });
    });
    rerender(
        <Editor
            {...props}
            jadwal={{ ...jadwal, revisi: 9, periode: jadwal.periode.map((item) => ({ ...item, periode_revisi: 10 })) }}
        />,
    );
    expect(field.value).toBe('2027-01-20');
    expect(field.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(field.getAttribute('aria-describedby') ?? '')?.textContent).toContain(
        'Tanggal harus sama dengan atau setelah tanggal jendela sebelumnya.',
    );
    expect(document.activeElement?.textContent).toContain('Periksa data kalender');
    const preserveState = options?.preserveState;
    expect(typeof preserveState === 'function' && preserveState(page())).toBe(true);
    expect(typeof preserveState === 'function' && preserveState(page({ success: 'Draft tersimpan.' }))).toBe(false);
});

it('rentang periode tampil pada tahun jadwal, termasuk periode yang baru ditambah dan saat revisi jendela', async () => {
    const { unmount } = render(<Editor {...props} />);
    expect(screen.getByText('1 Jan–31 Mar 2026')).toBeTruthy();
    expect(screen.getByText('1 Okt–31 Des 2026')).toBeTruthy();
    fireEvent.change(screen.getByRole('combobox', { name: 'Pilih periode' }), { target: { value: 'periode-2' } });
    fireEvent.click(screen.getByRole('button', { name: 'Tambah' }));
    expect(screen.getByText('1 Apr–30 Jun 2026')).toBeTruthy();
    unmount();

    render(<Editor {...createProps} />);
    fireEvent.change(screen.getByRole('combobox', { name: 'Pilih periode' }), { target: { value: 'periode-3' } });
    fireEvent.click(screen.getByRole('button', { name: 'Tambah' }));
    // Tahun belum diisi: rentang tampil tanpa tahun, lalu mengikuti tahun yang diketik.
    expect(screen.getByText('1 Jul–30 Sep')).toBeTruthy();
    fireEvent.change(screen.getByLabelText(/^Tahun/), { target: { value: '2027' } });
    expect(screen.getByText('1 Jul–30 Sep 2027')).toBeTruthy();
    cleanup();

    const izinRevisi = { create: false, update: false, activate: false, close: false, revise_windows: true, tambah_indikator: false };
    render(<Editor {...props} jadwal={{ ...jadwal, status: 'aktif' }} read_only_reason="Jadwal aktif." can={izinRevisi} />);
    await userEvent.click(screen.getByRole('button', { name: 'Revisi Jendela PIC' }));
    expect(screen.getByRole('button', { name: 'Simpan revisi' })).toBeTruthy();
    expect(screen.getByText('1 Okt–31 Des 2026')).toBeTruthy();
});

it('mengirim agregat pilihan sekali tanpa metadata tampilan atau status', () => {
    render(<Editor {...props} />);
    const button = screen.getByRole('button', { name: 'Simpan draft' });
    act(() => {
        fireEvent.click(button);
        fireEvent.click(button);
    });
    expect(router.put).toHaveBeenCalledTimes(1);
    const payload = vi.mocked(router.put).mock.calls[0][1] as { periode: Record<string, unknown>[] };
    expect(payload).toEqual({
        renstra_id: 'renstra-1',
        tahun: '2026',
        revisi: 4,
        penutupan: '2027-01-19',
        periode: jadwal.periode.map(({ periode_id, periode_revisi, pengisian_mulai, pengisian_selesai }) => ({
            periode_id,
            periode_revisi,
            pengisian_mulai,
            pengisian_selesai,
        })),
    });
    // toEqual mengabaikan key bernilai undefined, jadi daftar key diperiksa langsung.
    for (const item of payload.periode) {
        expect(Object.keys(item).sort()).toEqual(['pengisian_mulai', 'pengisian_selesai', 'periode_id', 'periode_revisi']);
    }
});

it('penghapusan hubungan periode menjaga fokus pada baris berikutnya dan lalu tombol tambah', async () => {
    const user = userEvent.setup();
    render(<Editor {...props} />);
    await user.click(screen.getByRole('button', { name: 'Hapus Triwulan I dari draft' }));
    expect(document.activeElement).toBe(screen.getByLabelText('Triwulan IV: Mulai pengisian'));
    await user.click(screen.getByRole('button', { name: 'Hapus Triwulan IV dari draft' }));
    expect(document.activeElement).toBe(screen.getByRole('combobox', { name: 'Pilih periode' }));
});

it('menonaktifkan input dan aksi selama penyimpanan berjalan', async () => {
    render(<Editor {...props} />);
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    const visit: PendingVisit = {
        id: 'pending-jadwal',
        url: new URL('http://localhost/jadwal/jadwal-1'),
        method: 'put',
        data: {},
        replace: false,
        preserveScroll: true,
        preserveState: true,
        only: [],
        except: [],
        headers: {},
        errorBag: null,
        forceFormData: false,
        queryStringArrayFormat: 'brackets',
        async: false,
        showProgress: true,
        prefetch: false,
        fresh: false,
        reset: [],
        preserveUrl: false,
        preserveErrors: false,
        invalidateCacheTags: [],
        viewTransition: false,
        component: null,
        pageProps: null,
        cached: false,
        completed: false,
        cancelled: false,
        interrupted: false,
    };
    await act(async () => {
        vi.mocked(router.put).mock.calls[0][2]?.onStart?.(visit);
    });
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai pengisian').disabled).toBe(true);
    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'Pilih periode' }).disabled).toBe(true);
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Tambah' }).disabled).toBe(true);
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan draft' }).disabled).toBe(true);
    expect(router.put).toHaveBeenCalledTimes(1);
});

it('periode nonaktif tetap terlihat dan boleh dihapus dari draft', () => {
    render(
        <Editor
            {...props}
            jadwal={{ ...jadwal, periode: [{ ...jadwal.periode[0], aktif: false }, jadwal.periode[1]] }}
        />,
    );
    expect(screen.getByText('Ada periode nonaktif pada draft')).toBeTruthy();
    expect(screen.getByText('Nonaktif')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai pengisian').disabled).toBe(false);
});

it.each(['nonaktif', 'diarsipkan'] as const)('Renstra %s hanya menampilkan kalender baca saja', (status) => {
    render(
        <Editor
            {...props}
            jadwal={{ ...jadwal, renstra: { ...jadwal.renstra, status } }}
            can={{ create: true, update: false, activate: false, close: false, revise_windows: false, tambah_indikator: false }}
            read_only_reason={`Renstra ${status}.`}
            renstra_options={[]}
        />,
    );
    expect(screen.getByText('Kalender hanya dapat dibaca')).toBeTruthy();
    const renstra = screen.getByLabelText<HTMLSelectElement>(/^Renstra/);
    expect(renstra.value).toBe('renstra-1');
    expect(renstra.disabled).toBe(true);
    expect(screen.getByRole('option', { name: /^Renstra pengujian \(2025–2029\) · (Nonaktif|Diarsipkan)$/ })).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan IV: Selesai pengisian').disabled).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan draft' })).toBeNull();
    expect(screen.queryByRole('combobox', { name: 'Pilih periode' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Tambah' })).toBeNull();
    expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/jadwal');
    expect(screen.queryByRole('link', { name: 'Batal' })).toBeNull();
});

it('hasil jaringan unknown mempertahankan input dan memberi pemeriksaan otoritatif tanpa replay', async () => {
    render(<Editor {...props} />);
    fireEvent.change(screen.getByLabelText('Triwulan I: Mulai pengisian'), { target: { value: '2026-04-02' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    const options = vi.mocked(router.put).mock.calls[0][2];
    await act(async () => {
        expect(options?.onNetworkError?.(new Error('Disconnected'))).toBe(false);
    });
    expect(screen.getByText(/Hasil penyimpanan belum terkonfirmasi/)).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai pengisian').value).toBe('2026-04-02');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan draft' }).disabled).toBe(true);
    expect(screen.getByRole('link', { name: 'Periksa kalender terbaru' }).getAttribute('href')).toBe(
        '/jadwal/jadwal-1',
    );
    expect(router.put).toHaveBeenCalledTimes(1);
    expect(router.get).not.toHaveBeenCalled();
});

it('pemulihan sesi mempertahankan draft dan tidak mengulang mutasi', async () => {
    render(<Editor {...props} />);
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    const options = vi.mocked(router.put).mock.calls[0][2];
    await act(async () => {
        options?.onHttpException?.({ status: 401, data: {}, headers: {} });
    });
    expect(screen.getByRole('link', { name: 'Masuk ulang' }).getAttribute('href')).toBe('/login?recovery=1');
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan IV: Selesai pengisian').value).toBe('2027-01-11');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan draft' }).disabled).toBe(true);
    expect(router.put).toHaveBeenCalledTimes(1);
});

it('403 respons akhir tanpa bukti penolakan sebelum mutasi mempertahankan draft sebagai hasil unknown', async () => {
    render(<Editor {...props} />);
    const field = screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai pengisian');
    fireEvent.change(field, { target: { value: '2026-04-02' } });
    const button = screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan draft' });
    fireEvent.click(button);
    await act(async () => {
        expect(vi.mocked(router.put).mock.calls[0][2]?.onHttpException?.({ status: 403, data: {}, headers: {} })).toBe(false);
    });
    expect(screen.getByText(/Hasil penyimpanan belum terkonfirmasi karena akses ditolak/)).toBeTruthy();
    expect(field.value).toBe('2026-04-02');
    expect(button.disabled).toBe(true);
    expect(screen.getByRole('link', { name: 'Periksa kalender terbaru' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
    fireEvent.click(button);
    expect(router.put).toHaveBeenCalledTimes(1);
    expect(router.get).not.toHaveBeenCalled();
});

it('mengubah pilihan periode (panah/type-ahead) tidak menambah baris; hanya tombol Tambah yang menambah dan revisi ikut terkirim', async () => {
    const fetchMock = vi.fn<typeof fetch>();
    vi.stubGlobal('fetch', fetchMock);
    render(<Editor {...props} />);
    const pilih = document.getElementById('tambah-periode') as HTMLSelectElement;
    fireEvent.change(pilih, { target: { value: 'periode-2' } });
    fireEvent.change(pilih, { target: { value: 'periode-3' } });
    expect(screen.queryByLabelText('Triwulan II: Mulai pengisian')).toBeNull();
    expect(screen.queryByLabelText('Triwulan III: Mulai pengisian')).toBeNull();
    expect(pilih).toBe(screen.getByRole('combobox', { name: 'Pilih periode' }));
    const pilihan = () => Array.from(pilih.options).map((option) => option.textContent);
    expect(pilihan()).toEqual(['Pilih periode', 'Triwulan II', 'Triwulan III']);
    fireEvent.click(screen.getByRole('button', { name: 'Tambah' }));
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan III: Mulai pengisian').value).toBe('');
    expect(screen.queryByLabelText('Triwulan II: Mulai pengisian')).toBeNull();
    expect(pilih.value).toBe('');
    expect(pilihan()).toEqual(['Pilih periode', 'Triwulan II']);
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Tambah' }).disabled).toBe(true);
    await waitFor(() => expect(document.activeElement).toBe(pilih));
    expect(fetchMock).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    expect(vi.mocked(router.put).mock.calls[0][1]).toHaveProperty('periode.1.periode_revisi', 6);
});

it('katalog periode kosong dijelaskan berbeda dari semua periode aktif sudah dipilih', () => {
    const { unmount } = render(<Editor {...createProps} periode_options={[]} />);
    expect(screen.getByText('Belum ada periode aktif. Tambahkan atau aktifkan di Master Periode.')).toBeTruthy();
    const pilih = screen.getByRole<HTMLSelectElement>('combobox', { name: 'Pilih periode' });
    expect(pilih.disabled).toBe(true);
    expect(document.getElementById(pilih.getAttribute('aria-describedby') ?? '')?.textContent).toBe(
        'Belum ada periode aktif. Tambahkan atau aktifkan di Master Periode.',
    );
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Tambah' }).disabled).toBe(true);
    unmount();
    render(<Editor {...props} periode_options={[periodeOptions[0], periodeOptions[3]]} />);
    expect(screen.getByText('Semua periode aktif sudah dipilih.')).toBeTruthy();
    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'Pilih periode' }).disabled).toBe(true);
});

it('pilihan periode menandai periode nilai akhir', () => {
    render(<Editor {...createProps} />);
    expect(screen.getByRole('option', { name: 'Triwulan IV · Periode nilai akhir' })).toBeTruthy();
});

it('mode create aman dirender server tanpa hardcode tahun atau tanggal', () => {
    const html = renderToString(<Editor {...createProps} />);
    expect(html).toContain('Informasi tahunan');
    render(<Editor {...createProps} />);
    expect(screen.queryByText('Draft')).toBeNull();
    expect(screen.getByLabelText<HTMLInputElement>(/^Tahun/).value).toBe('');
    expect(screen.getByLabelText<HTMLInputElement>(/^Penutupan/).value).toBe('');
    expect(screen.queryAllByLabelText(/Rencana Aksi/)).toHaveLength(0);
    expect(screen.getByText(/Belum ada periode dipilih/)).toBeTruthy();
});

it('dua Renstra bernama dan berstatus sama dibedakan oleh rentang tahun pada pilihan', () => {
    const kembar = { ...jadwal.renstra, id: 'renstra-2', tahun_mulai: 2030, tahun_selesai: 2034 };
    render(<Editor {...createProps} renstra_options={[jadwal.renstra, kembar]} />);
    const teks = Array.from(screen.getByLabelText<HTMLSelectElement>(/^Renstra/).options)
        .slice(1)
        .map((option) => option.textContent);
    expect(teks).toEqual(['Renstra pengujian (2025–2029) · Aktif', 'Renstra pengujian (2030–2034) · Aktif']);
});

it('pilihan Renstra pada create memperbarui identitas tanpa mengisi tanggal atau membuat jadwal otomatis', () => {
    const fetchMock = vi.fn<typeof fetch>();
    vi.stubGlobal('fetch', fetchMock);
    render(<Editor {...createProps} />);
    const renstra = screen.getByLabelText<HTMLSelectElement>(/^Renstra/);
    expect(renstra.value).toBe('');
    expect(screen.getByRole('option', { name: 'Renstra pengujian (2025–2029) · Aktif' })).toBeTruthy();
    fireEvent.change(renstra, { target: { value: 'renstra-1' } });
    expect(screen.getByText('Rentang Renstra 2025–2029')).toBeTruthy();
    expect(fetchMock).not.toHaveBeenCalled();
    fireEvent.change(screen.getByLabelText(/^Tahun/), { target: { value: '2028' } });
    expect(screen.getByRole('link', { name: 'Periksa jadwal Renstra dan tahun ini' }).getAttribute('href')).toBe(
        '/jadwal?renstra_id=renstra-1&tahun=2028',
    );
    expect(router.post).not.toHaveBeenCalled();
    expect(screen.getByLabelText<HTMLInputElement>(/^Penutupan/).value).toBe('');
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({
        renstra_id: 'renstra-1',
        tahun: '2028',
        periode: [],
    });
    expect(vi.mocked(router.post).mock.calls[0][1]).not.toHaveProperty('revisi');
});

it('pasangan Renstra dan tahun existing ditautkan ke kalender tanpa menawarkan create kedua', () => {
    render(
        <Index
            jadwal={{
                data: [{ ...jadwal, can_update: true }],
                current_page: 1,
                last_page: 1,
                total: 1,
                from: 1,
                to: 1,
                links: [],
            }}
            filters={{ q: '', status: null, renstra_id: 'renstra-1', tahun: 2026, sort: 'tahun_desc' }}
            can={{ create: true }}
        />,
    );
    expect(screen.getByText('Jadwal tahun ini sudah tersedia')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Edit jadwal Renstra pengujian 2026' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
    expect(screen.queryByRole('link', { name: 'Susun jadwal' })).toBeNull();
});

it('panel aktivasi hanya untuk can.activate dan kalender dirty memblokir aktivasi', async () => {
    const readiness = {
        jadwal_id: 'jadwal-1', checked_revisi: 4, checked_at: '2026-10-07T02:00:00Z', allowed: true, blockers: [],
        gates: (['G1', 'G2', 'G3', 'G4'] as const).map((key) => ({ key, status: 'lolos', message: 'Lolos.', count: null })),
        counts: { indikator_berlaku: 1, target_belum_terisi: 0, snapshot_existing: 0, snapshot_baru: 1, komponen_baru: 0 }, periode_lampau_ids: [],
    };
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response(JSON.stringify(readiness), { status: 200 }))));
    const { unmount } = render(<Editor {...props} />);
    expect(screen.queryByText('Kesiapan aktivasi')).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
    unmount();

    render(<Editor {...props} can={{ create: false, update: false, activate: true, close: false, revise_windows: false, tambah_indikator: false }} read_only_reason="Anda tidak memiliki izin mengubah jadwal." />);
    expect(await screen.findByText('Siap diaktifkan')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Simpan draft' })).toBeNull();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(false);
    cleanup();

    render(<Editor {...props} can={{ create: true, update: true, activate: true, close: false, revise_windows: false, tambah_indikator: false }} />);
    await screen.findByText('Siap diaktifkan');
    fireEvent.change(screen.getByLabelText<HTMLInputElement>('Triwulan IV: Selesai pengisian'), { target: { value: '2027-01-12' } });
    expect(screen.getByText(/Simpan atau batalkan perubahan kalender/)).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(true);
});

it('revisi props baru dengan kalender tampil lama tetap memakai revisi tampil dan dianggap stale', async () => {
    const readiness = (revisi: number) => ({
        jadwal_id: 'jadwal-1', checked_revisi: revisi, checked_at: '2026-10-07T02:00:00Z', allowed: true, blockers: [],
        gates: (['G1', 'G2', 'G3', 'G4'] as const).map((key) => ({ key, status: 'lolos', message: 'Lolos.', count: null })),
        counts: { indikator_berlaku: 1, target_belum_terisi: 0, snapshot_existing: 0, snapshot_baru: 1, komponen_baru: 0 }, periode_lampau_ids: [],
    });
    const fetchMock = vi.fn(() => Promise.resolve(new Response(JSON.stringify(readiness(4)), { status: 200 })));
    vi.stubGlobal('fetch', fetchMock);
    const activator = { ...props, can: { create: false, update: false, activate: true, close: false, revise_windows: false, tambah_indikator: false }, read_only_reason: 'Anda tidak memiliki izin mengubah jadwal.' };
    const { rerender } = render(<Editor {...activator} />);
    await screen.findByText('Siap diaktifkan');
    // preserveState sesudah POST gagal: props revisi naik, kalender yang tampil tetap revisi 4.
    fetchMock.mockImplementation(() => Promise.resolve(new Response(JSON.stringify(readiness(5)), { status: 200 })));
    rerender(<Editor {...activator} jadwal={{ ...jadwal, revisi: 5, penutupan: '2027-01-20' }} />);
    fireEvent.click(screen.getByRole('button', { name: 'Periksa ulang' }));
    expect(await screen.findByText(/Kalender sudah berubah/)).toBeTruthy();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(true);
});
it('panel penutupan hanya untuk can.close dan mengirim revisi tampil serta alasan', async () => {
    const aktif = { ...jadwal, status: 'aktif' as const };
    const readOnly = 'Jadwal aktif, ditutup, atau pernah diaktifkan hanya dapat dibaca.';
    const { unmount } = render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={{ create: false, update: false, activate: false, close: false, revise_windows: false, tambah_indikator: false }} />);
    expect(screen.queryByRole('button', { name: 'Tutup Jadwal' })).toBeNull();
    unmount();

    render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={{ create: false, update: false, activate: false, close: true, revise_windows: false, tambah_indikator: false }} />);
    await userEvent.click(screen.getByRole('button', { name: 'Tutup Jadwal' }));
    const dialog = screen.getByRole('dialog');
    const confirm = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Tutup Jadwal' });
    expect(confirm.disabled).toBe(true);
    await userEvent.type(within(dialog).getByRole('textbox'), 'Tahun pelaporan 2026 selesai.');
    await userEvent.click(confirm);
    expect(router.post).toHaveBeenCalledWith('/jadwal/jadwal-1/tutup', { expected_revisi: 4, alasan: 'Tahun pelaporan 2026 selesai.' }, expect.anything());
});
it('panel tambah indikator hanya untuk can.tambah_indikator yang memiliki kandidat', () => {
    const aktif = { ...jadwal, status: 'aktif' as const };
    const readOnly = 'Jadwal aktif, ditutup, atau pernah diaktifkan hanya dapat dibaca.';
    const kandidat = [{ id: 'indikator-9', kode: 'IKU-9', nama: 'Indikator tengah tahun', target_terisi: true, dapat_dibekukan: true }];
    const tanpaIzin = { create: false, update: false, activate: false, close: false, revise_windows: false, tambah_indikator: false };
    const tampil = (can: JadwalEditorProps['can'], daftar: JadwalEditorProps['indikator_belum_terjadwal']) => {
        const { unmount } = render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={can} indikator_belum_terjadwal={daftar} perjanjian_kinerja_id="pk-1" />);
        const ada = screen.queryByText('Indikator belum masuk jadwal') !== null;
        unmount();
        return ada;
    };

    expect(tampil(tanpaIzin, kandidat)).toBe(false);
    expect(tampil({ ...tanpaIzin, tambah_indikator: true }, [])).toBe(false);
    expect(tampil({ ...tanpaIzin, tambah_indikator: true }, kandidat)).toBe(true);
});
it('revisi jendela PIC hanya untuk can.revise_windows, mengubah tanggal saja, dan mengirim revisi tampil serta alasan', async () => {
    const aktif = { ...jadwal, status: 'aktif' as const };
    const readOnly = 'Jadwal aktif, ditutup, atau pernah diaktifkan hanya dapat dibaca.';
    const tanpaIzin = { create: false, update: false, activate: false, close: false, revise_windows: false, tambah_indikator: false };
    const { unmount } = render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={tanpaIzin} />);
    expect(screen.queryByRole('button', { name: 'Revisi Jendela PIC' })).toBeNull();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Selesai pengisian').disabled).toBe(true);
    unmount();

    render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={{ ...tanpaIzin, revise_windows: true }} />);
    await userEvent.click(screen.getByRole('button', { name: 'Revisi Jendela PIC' }));
    const simpan = screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' });
    expect(simpan.disabled).toBe(true);
    expect(screen.queryByRole('button', { name: /dari draft/ })).toBeNull();
    expect(screen.queryByLabelText('Pilih periode')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Revisi Jendela PIC' })).toBeNull();
    const selesai = screen.getByLabelText<HTMLInputElement>('Triwulan I: Selesai pengisian');
    expect(selesai.disabled).toBe(false);
    expect(screen.queryAllByLabelText(/review/i)).toHaveLength(0);
    fireEvent.change(selesai, { target: { value: '2026-04-12' } });
    await userEvent.click(simpan);
    const dialog = screen.getByRole('dialog');
    const confirm = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' });
    expect(confirm.disabled).toBe(true);
    await userEvent.type(within(dialog).getByRole('textbox'), 'Gangguan jaringan unit.');
    await userEvent.click(confirm);
    expect(router.post).toHaveBeenCalledWith('/jadwal/jadwal-1/revisi-jendela', {
        expected_revisi: 4,
        alasan: 'Gangguan jaringan unit.',
        periode: [
            { periode_id: 'periode-1', pengisian_mulai: '2026-04-01', pengisian_selesai: '2026-04-12' },
            { periode_id: 'periode-4', pengisian_mulai: '2027-01-04', pengisian_selesai: '2027-01-11' },
        ],
    }, expect.anything());
    const terkirim = vi.mocked(router.post).mock.calls[0][1] as { periode: Record<string, unknown>[] };
    for (const item of terkirim.periode) {
        expect(Object.keys(item).sort()).toEqual(['pengisian_mulai', 'pengisian_selesai', 'periode_id']);
    }

    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => {
        options?.onError?.({ 'periode.0.pengisian_selesai': 'Tanggal harus sama dengan atau setelah tanggal jendela sebelumnya.', penutupan: 'Penutupan paling cepat hari berikutnya setelah jendela pengisian terakhir selesai.' });
    });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(selesai.getAttribute('aria-invalid')).toBe('true');
    expect(screen.getAllByRole('alert').some((alert) => alert.textContent?.includes('Penutupan paling cepat'))).toBe(true);
    const preserveState = options?.preserveState;
    expect(typeof preserveState === 'function' && preserveState(page())).toBe(true);
    expect(typeof preserveState === 'function' && preserveState(page({ success: 'Jendela PIC berhasil direvisi.' }))).toBe(false);

    await userEvent.click(screen.getByRole('button', { name: 'Batal' }));
    expect(screen.getByRole('button', { name: 'Revisi Jendela PIC' })).toBeTruthy();
});
it('revisi jendela PIC tetap mengirim token baseline form setelah ditolak basi dan props berganti', async () => {
    const aktif = { ...jadwal, status: 'aktif' as const };
    const readOnly = 'Jadwal aktif, ditutup, atau pernah diaktifkan hanya dapat dibaca.';
    const can = { create: false, update: false, activate: false, close: false, revise_windows: true, tambah_indikator: false };
    const { rerender } = render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={can} />);
    await userEvent.click(screen.getByRole('button', { name: 'Revisi Jendela PIC' }));
    fireEvent.change(screen.getByLabelText('Triwulan I: Selesai pengisian'), { target: { value: '2026-04-12' } });
    const kirim = async () => {
        await userEvent.click(screen.getByRole('button', { name: 'Simpan revisi' }));
        const dialog = screen.getByRole('dialog');
        const alasan = within(dialog).getByRole('textbox');
        if ((alasan as HTMLTextAreaElement).value === '') await userEvent.type(alasan, 'Gangguan jaringan unit.');
        await userEvent.click(within(dialog).getByRole('button', { name: 'Simpan revisi' }));
    };
    await kirim();
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => {
        options?.onError?.({ jadwal: 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum merevisi jendela.' });
    });

    // Redirect validasi memuat revisi jadwal terbaru, sedangkan tanggal draf lama dipertahankan.
    rerender(<Editor {...props} jadwal={{ ...aktif, revisi: 5 }} read_only_reason={readOnly} can={can} />);
    expect(screen.getAllByRole('alert').some((alert) => alert.textContent?.includes('Jadwal sudah berubah'))).toBe(true);
    await kirim();

    const kedua = vi.mocked(router.post).mock.calls[1][1] as { expected_revisi: number };
    expect(kedua.expected_revisi).toBe(4);
});
