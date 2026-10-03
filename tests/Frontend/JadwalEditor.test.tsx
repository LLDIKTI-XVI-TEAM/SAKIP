import type { Page, PendingVisit } from '@inertiajs/core';
import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { renderToString } from 'react-dom/server';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Editor from '@/Pages/Jadwal/Editor';
import Index from '@/Pages/Jadwal/Index';
import type { JadwalDetail, JadwalEditorProps } from '@/types/periode-jadwal';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { periode: true, jadwal: true } } } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
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
const props: JadwalEditorProps = { jadwal, can: { create: true, update: true }, read_only_reason: null };
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
    expect(screen.getByRole('columnheader', { name: 'Jendela Review' })).toBeTruthy();
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
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Tambah periode' }));
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
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Tambah periode' }).disabled).toBe(true);
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
            can={{ create: true, update: false }}
            read_only_reason={`Renstra ${status}.`}
        />,
    );
    expect(screen.getByText('Kalender hanya dapat dibaca')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan IV: Target Selesai Review').disabled).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan draft' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Tambah periode' })).toBeNull();
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

it('pilihan periode dipaginasi, dipilih lewat keyboard, dan fokus kembali ke pemicu', async () => {
    const user = userEvent.setup();
    const fetchMock = vi
        .fn<typeof fetch>()
        .mockResolvedValueOnce(
            new Response(
                JSON.stringify({
                    data: [
                        {
                            id: 'periode-2',
                            nama: 'Triwulan II',
                            urutan: 2,
                            aktif: true,
                            is_nilai_akhir: false,
                            revisi: 3,
                        },
                    ],
                    has_more: true,
                }),
            ),
        )
        .mockResolvedValueOnce(
            new Response(
                JSON.stringify({
                    data: [
                        {
                            id: 'periode-3',
                            nama: 'Triwulan III',
                            urutan: 3,
                            aktif: true,
                            is_nilai_akhir: false,
                            revisi: 6,
                        },
                    ],
                    has_more: false,
                }),
            ),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(<Editor {...props} />);
    const trigger = screen.getByRole('button', { name: 'Tambah periode' });
    await user.click(trigger);
    await screen.findByRole('button', { name: /Triwulan II/ });
    await user.click(screen.getByRole('button', { name: 'Berikutnya' }));
    const option = await screen.findByRole('button', { name: /Triwulan III/ });
    option.focus();
    await user.keyboard('{Enter}');
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(trigger);
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan III: Mulai Review').value).toBe('');
    expect(fetchMock.mock.calls[1][0]).toBe('/jadwal/opsi/periode?q=&page=2');
    fireEvent.click(screen.getByRole('button', { name: 'Simpan draft' }));
    expect(vi.mocked(router.put).mock.calls[0][1]).toHaveProperty('periode.1.periode_revisi', 6);
});

it('kegagalan lookup dapat ditutup dengan keyboard tanpa menghapus draft', async () => {
    const user = userEvent.setup();
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(new Response('', { status: 403 })));
    render(<Editor {...props} />);
    await user.click(screen.getByRole('button', { name: 'Tambah periode' }));
    await screen.findByText('Pilihan belum dapat dimuat. Coba lagi.');
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByLabelText<HTMLInputElement>('Triwulan I: Mulai Review').value).toBe('2026-04-08');
});

it('mode create aman dirender server tanpa hardcode tahun atau tanggal', () => {
    const html = renderToString(<Editor jadwal={null} can={{ create: true, update: false }} read_only_reason={null} />);
    expect(html).toContain('Informasi tahunan');
    render(<Editor jadwal={null} can={{ create: true, update: false }} read_only_reason={null} />);
    expect(screen.getByLabelText<HTMLInputElement>(/^Tahun/).value).toBe('');
    expect(screen.getByLabelText<HTMLInputElement>(/^Penutupan/).value).toBe('');
    expect(screen.getByText(/Belum ada periode dipilih/)).toBeTruthy();
});

it('pilihan Renstra pada create memperbarui identitas tanpa mengisi tanggal atau membuat jadwal otomatis', async () => {
    const user = userEvent.setup();
    vi.stubGlobal(
        'fetch',
        vi
            .fn<typeof fetch>()
            .mockResolvedValue(new Response(JSON.stringify({ data: [jadwal.renstra], has_more: false }))),
    );
    render(<Editor jadwal={null} can={{ create: true, update: false }} read_only_reason={null} />);
    await user.click(screen.getByRole('button', { name: 'Pilih Renstra' }));
    await user.click(await screen.findByRole('button', { name: /Renstra pengujian/ }));
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
    expect(screen.getByRole('link', { name: 'Edit jadwal' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
    expect(screen.queryByRole('link', { name: 'Susun jadwal' })).toBeNull();
});
