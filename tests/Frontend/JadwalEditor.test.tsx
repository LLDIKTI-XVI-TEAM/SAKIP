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
    rencana_aksi_mulai: '2026-01-05',
    rencana_aksi_selesai: '2026-01-30',
    penutupan: '2027-01-19',
    periode: [
        {
            periode_id: 'periode-1',
            periode_revisi: 2,
            nama: 'Triwulan I',
            urutan: 1,
            aktif: true,
            is_nilai_akhir: false,
            pengisian_mulai: '2026-04-01',
            pengisian_selesai: '2026-04-07',
            reviu_mulai: '2026-04-08',
            reviu_selesai: '2026-04-15',
        },
        {
            periode_id: 'periode-4',
            periode_revisi: 7,
            nama: 'Triwulan IV',
            urutan: 4,
            aktif: true,
            is_nilai_akhir: true,
            pengisian_mulai: '2027-01-04',
            pengisian_selesai: '2027-01-11',
            reviu_mulai: '2027-01-12',
            reviu_selesai: '2027-01-18',
        },
    ],
};
const periodeOptions = [
    { id: 'periode-1', nama: 'Triwulan I', urutan: 1, aktif: true, is_nilai_akhir: false, revisi: 2 },
    { id: 'periode-2', nama: 'Triwulan II', urutan: 2, aktif: true, is_nilai_akhir: false, revisi: 3 },
    { id: 'periode-3', nama: 'Triwulan III', urutan: 3, aktif: true, is_nilai_akhir: false, revisi: 6 },
    { id: 'periode-4', nama: 'Triwulan IV', urutan: 4, aktif: true, is_nilai_akhir: true, revisi: 7 },
];
const props: JadwalEditorProps = {
    jadwal,
    can: { create: true, update: true, activate: false, close: false, revise_windows: false },
    read_only_reason: null,
    renstra_options: [jadwal.renstra],
    periode_options: periodeOptions,
};
const createProps: JadwalEditorProps = {
    jadwal: null,
    can: { create: true, update: false, activate: false, close: false, revise_windows: false },
    read_only_reason: null,
    renstra_options: [jadwal.renstra],
    periode_options: periodeOptions,
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

it('menampilkan seluruh jendela dalam desain A dan label Review yang disetujui', () => {
    render(<Editor {...props} />);
    expect(screen.getByRole('columnheader', { name: 'Review' })).toBeTruthy();
    expect(screen.getByRole('columnheader', { name: 'Target selesai' })).toBeTruthy();
    for (const item of jadwal.periode) {
        expect(screen.getByLabelText<HTMLInputElement>(`${item.nama}: Mulai Review`).value).toBe(item.reviu_mulai);
        expect(screen.getByLabelText<HTMLInputElement>(`${item.nama}: Target Selesai Review`).value).toBe(
            item.reviu_selesai,
        );
    }
    expect(screen.queryByText('Periode berikutnya')).toBeNull();
});

it('mempertahankan tanggal dan revisi asal setelah error bersarang, dengan ringkasan fokus dan ARIA', async () => {
    const { rerender } = render(<Editor {...props} />);
    const field = screen.getByLabelText<HTMLInputElement>('Triwulan IV: Target Selesai Review');
    fireEvent.change(field, { target: { value: '2027-01-20' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    const options = vi.mocked(router.put).mock.calls[0][2];
    await act(async () => {
        options?.onError?.({ 'periode.1.reviu_selesai': 'Target review harus sebelum penutupan.' });
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
        'Target review harus sebelum penutupan.',
    );
    expect(document.activeElement?.textContent).toContain('Periksa data kalender');
    const preserveState = options?.preserveState;
    expect(typeof preserveState === 'function' && preserveState(page())).toBe(true);
    expect(typeof preserveState === 'function' && preserveState(page({ success: 'Draft tersimpan.' }))).toBe(false);
});

it('mengirim agregat pilihan sekali tanpa metadata tampilan atau status', () => {
    render(<Editor {...props} />);
    const button = screen.getByRole('button', { name: 'Simpan draft' });
    act(() => {
        fireEvent.click(button);
        fireEvent.click(button);
    });
    expect(router.put).toHaveBeenCalledTimes(1);
    const payload = vi.mocked(router.put).mock.calls[0][1];
    expect(payload).toEqual({
        renstra_id: 'renstra-1',
        tahun: '2026',
        revisi: 4,
        rencana_aksi_mulai: '2026-01-05',
        rencana_aksi_selesai: '2026-01-30',
        penutupan: '2027-01-19',
        periode: jadwal.periode.map(
            ({ periode_id, periode_revisi, pengisian_mulai, pengisian_selesai, reviu_mulai, reviu_selesai }) => ({
                periode_id,
                periode_revisi,
                pengisian_mulai,
                pengisian_selesai,
                reviu_mulai,
                reviu_selesai,
            }),
        ),
    });
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
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai Review').disabled).toBe(true);
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
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai Review').disabled).toBe(false);
});

it.each(['nonaktif', 'diarsipkan'] as const)('Renstra %s hanya menampilkan kalender baca saja', (status) => {
    render(
        <Editor
            {...props}
            jadwal={{ ...jadwal, renstra: { ...jadwal.renstra, status } }}
            can={{ create: true, update: false, activate: false, close: false, revise_windows: false }}
            read_only_reason={`Renstra ${status}.`}
            renstra_options={[]}
        />,
    );
    expect(screen.getByText('Kalender hanya dapat dibaca')).toBeTruthy();
    const renstra = screen.getByLabelText<HTMLSelectElement>(/^Renstra/);
    expect(renstra.value).toBe('renstra-1');
    expect(renstra.disabled).toBe(true);
    expect(screen.getByRole('option', { name: /^Renstra pengujian \(2025–2029\) · (Nonaktif|Diarsipkan)$/ })).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan IV: Target Selesai Review').disabled).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan draft' })).toBeNull();
    expect(screen.queryByRole('combobox', { name: 'Pilih periode' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Tambah' })).toBeNull();
    expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/jadwal');
    expect(screen.queryByRole('link', { name: 'Batal' })).toBeNull();
});

it('hasil jaringan unknown mempertahankan input dan memberi pemeriksaan otoritatif tanpa replay', async () => {
    render(<Editor {...props} />);
    fireEvent.change(screen.getByLabelText('Triwulan I: Mulai Review'), { target: { value: '2026-04-09' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    const options = vi.mocked(router.put).mock.calls[0][2];
    await act(async () => {
        expect(options?.onNetworkError?.(new Error('Disconnected'))).toBe(false);
    });
    expect(screen.getByText(/Hasil penyimpanan belum terkonfirmasi/)).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai Review').value).toBe('2026-04-09');
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
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan IV: Target Selesai Review').value).toBe('2027-01-18');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan draft' }).disabled).toBe(true);
    expect(router.put).toHaveBeenCalledTimes(1);
});

it('403 respons akhir tanpa bukti penolakan sebelum mutasi mempertahankan draft sebagai hasil unknown', async () => {
    render(<Editor {...props} />);
    const field = screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai Review');
    fireEvent.change(field, { target: { value: '2026-04-09' } });
    const button = screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan draft' });
    fireEvent.click(button);
    await act(async () => {
        expect(vi.mocked(router.put).mock.calls[0][2]?.onHttpException?.({ status: 403, data: {}, headers: {} })).toBe(false);
    });
    expect(screen.getByText(/Hasil penyimpanan belum terkonfirmasi karena akses ditolak/)).toBeTruthy();
    expect(field.value).toBe('2026-04-09');
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
    expect(screen.queryByLabelText('Triwulan II: Mulai Review')).toBeNull();
    expect(screen.queryByLabelText('Triwulan III: Mulai Review')).toBeNull();
    expect(pilih).toBe(screen.getByRole('combobox', { name: 'Pilih periode' }));
    const pilihan = () => Array.from(pilih.options).map((option) => option.textContent);
    expect(pilihan()).toEqual(['Pilih periode', 'Triwulan II', 'Triwulan III']);
    fireEvent.click(screen.getByRole('button', { name: 'Tambah' }));
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan III: Mulai Review').value).toBe('');
    expect(screen.queryByLabelText('Triwulan II: Mulai Review')).toBeNull();
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

    render(<Editor {...props} can={{ create: false, update: false, activate: true, close: false, revise_windows: false }} read_only_reason="Anda tidak memiliki izin mengubah jadwal." />);
    expect(await screen.findByText('Siap diaktifkan')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Simpan draft' })).toBeNull();
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Aktifkan jadwal' }).disabled).toBe(false);
    cleanup();

    render(<Editor {...props} can={{ create: true, update: true, activate: true, close: false, revise_windows: false }} />);
    await screen.findByText('Siap diaktifkan');
    fireEvent.change(screen.getByLabelText<HTMLInputElement>('Triwulan IV: Target Selesai Review'), { target: { value: '2027-01-17' } });
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
    const activator = { ...props, can: { create: false, update: false, activate: true, close: false, revise_windows: false }, read_only_reason: 'Anda tidak memiliki izin mengubah jadwal.' };
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
    const { unmount } = render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={{ create: false, update: false, activate: false, close: false, revise_windows: false }} />);
    expect(screen.queryByRole('button', { name: 'Tutup Jadwal' })).toBeNull();
    unmount();

    render(<Editor {...props} jadwal={aktif} read_only_reason={readOnly} can={{ create: false, update: false, activate: false, close: true, revise_windows: false }} />);
    await userEvent.click(screen.getByRole('button', { name: 'Tutup Jadwal' }));
    const dialog = screen.getByRole('dialog');
    const confirm = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Tutup Jadwal' });
    expect(confirm.disabled).toBe(true);
    await userEvent.type(within(dialog).getByRole('textbox'), 'Tahun pelaporan 2026 selesai.');
    await userEvent.click(confirm);
    expect(router.post).toHaveBeenCalledWith('/jadwal/jadwal-1/tutup', { expected_revisi: 4, alasan: 'Tahun pelaporan 2026 selesai.' }, expect.anything());
});
it('revisi jendela PIC hanya untuk can.revise_windows, mengubah tanggal saja, dan mengirim revisi tampil serta alasan', async () => {
    const aktif = { ...jadwal, status: 'aktif' as const };
    const readOnly = 'Jadwal aktif, ditutup, atau pernah diaktifkan hanya dapat dibaca.';
    const tanpaIzin = { create: false, update: false, activate: false, close: false, revise_windows: false };
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
    fireEvent.change(selesai, { target: { value: '2026-04-12' } });
    fireEvent.change(screen.getByLabelText('Triwulan I: Mulai Review'), { target: { value: '2026-04-13' } });
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
            { periode_id: 'periode-1', pengisian_mulai: '2026-04-01', pengisian_selesai: '2026-04-12', reviu_mulai: '2026-04-13', reviu_selesai: '2026-04-15' },
            { periode_id: 'periode-4', pengisian_mulai: '2027-01-04', pengisian_selesai: '2027-01-11', reviu_mulai: '2027-01-12', reviu_selesai: '2027-01-18' },
        ],
    }, expect.anything());

    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => {
        options?.onError?.({ 'periode.0.pengisian_selesai': 'Tanggal harus sama dengan atau setelah tanggal jendela sebelumnya.', penutupan: 'Penutupan paling cepat hari berikutnya setelah seluruh target selesai review.' });
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
