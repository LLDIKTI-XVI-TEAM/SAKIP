import { useState, type ReactNode } from 'react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import StatusCapaian from '@/Pages/Verifikasi/StatusCapaian';
import type { BarisStatusCapaian, StatusCapaianProps } from '@/types/statusCapaian';

type PostCall = { url: string; data: Record<string, unknown>; options: Record<string, unknown> };

const form = vi.hoisted(() => ({
    posts: [] as PostCall[],
    errors: {} as Record<string, string | undefined>,
    processing: false,
}));

const halaman = vi.hoisted(() => ({ pengaturan: {} as Record<string, string> }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>,
    router: { reload: vi.fn(), get: vi.fn() },
    usePage: () => ({ props: halaman }),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setData] = useState(initial);

        return {
            data,
            setData: (key: string | Record<string, unknown>, value?: unknown) => {
                setData((current) => (typeof key === 'string' ? { ...current, [key]: value } : key));
            },
            post: (url: string, options: Record<string, unknown>) => {
                form.posts.push({ url, data, options });
            },
            processing: form.processing,
            errors: form.errors,
        };
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, title }: { children: ReactNode; title: string }) => <main><h1>{title}</h1>{children}</main>,
}));

beforeEach(() => {
    vi.clearAllMocks();
    form.posts = [];
    form.errors = {};
    form.processing = false;
    halaman.pengaturan = {};
});
afterEach(cleanup);

function baris(overrides: Partial<BarisStatusCapaian> = {}): BarisStatusCapaian {
    return {
        id: 'pengukuran-1',
        versi: 7,
        tahun: 2026,
        versi_pengukuran: { id: 'versi-1', nomor: 2, disahkan_pada: '2026-04-04T02:00:00+08:00' },
        indikator: { kode: 'IKU-UJI', nama: 'Indikator uji status capaian', satuan: '%', presisi: 4, desimal_tampilan: 2 },
        unit: { id: 'unit-1', nama: 'Unit Uji' },
        periode: { id: 'periode-1', nama: 'Triwulan I', urutan: 1 },
        nilai: '88', status_perhitungan: 'terhitung', target: '100', target_pk: '100',
        capaian_persen: '88.00', selisih_target_pk: '-12',
        riwayat: [], can: { tetapkan: true, revisi: false }, ...overrides,
    };
}

const statusBerlaku = {
    id: 'status-2', status: 'belum_tercapai' as const, sumber: 'manual',
    ditetapkan_pada: '2026-05-01T03:00:00+08:00', penetap: 'Verifikator Uji', versi_id: 'versi-1', versi_nomor: 2,
};

const sudahDitetapkan = baris({
    id: 'pengukuran-2',
    can: { tetapkan: false, revisi: true },
    status_berlaku: statusBerlaku,
    riwayat: [
        { id: 'status-1', status: 'tercapai', sumber: 'manual', versi_id: 'versi-0', versi_nomor: 1, penetap: 'Verifikator Lama', ditetapkan_pada: '2026-03-01T03:00:00+08:00' },
        { ...statusBerlaku, versi_id: 'versi-1' },
    ],
});

function props(overrides: Partial<StatusCapaianProps> = {}): StatusCapaianProps {
    const dasar: StatusCapaianProps = {
        kandidat: [baris()],
        sudah_ditetapkan: [],
        pagination: { current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null },
        pagination_status: { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null },
        can: { tetapkan: true },
    };
    const gabungan = { ...dasar, ...overrides };

    // Total paginasi revisi mengikuti fixture koleksi bila tidak diuji dengan nilai server eksplisit.
    return {
        ...gabungan,
        pagination_status: overrides.pagination_status
            ?? { ...dasar.pagination_status, total: gabungan.sudah_ditetapkan.length },
    };
}

/** Tombol tetapkan muncul dua kali (tabel desktop + kartu mobile); keduanya harus ada. */
function tombolTetapkan() {
    return screen.getAllByRole('button', { name: 'Tetapkan status capaian IKU-UJI' });
}

function formDialog(): HTMLFormElement {
    const element = document.querySelector('form');
    if (!element) throw new Error('Form dialog tidak ditemukan');
    return element;
}

describe('Halaman status capaian versi pengukuran', () => {
    it('menampilkan kandidat pada tabel desktop dan kartu mobile', () => {
        render(<StatusCapaian {...props()} />);

        const tabel = screen.getByRole('table', { name: 'Kandidat penetapan status capaian' });
        expect(within(tabel).getByText('IKU-UJI')).toBeTruthy();
        expect(within(tabel).getByText('Unit Uji')).toBeTruthy();
        expect(within(tabel).getByText('Triwulan I 2026')).toBeTruthy();
        expect(within(tabel).getByText(/Versi 2/)).toBeTruthy();
        expect(within(tabel).getByText('88,00 %')).toBeTruthy();
        expect(within(tabel).getByText('88,00%')).toBeTruthy();

        const tombol = tombolTetapkan();
        expect(tombol).toHaveLength(2);
        const kartu = tombol.map((item) => item.closest('li')).filter((item): item is HTMLLIElement => item !== null)[0];
        expect(kartu).toBeTruthy();
        expect(within(kartu).getByText('Indikator uji status capaian')).toBeTruthy();
        expect(within(kartu).getByText('Triwulan I 2026')).toBeTruthy();
        expect(within(kartu).getByText('Belum Ditetapkan')).toBeTruthy();
    });

    it('menampilkan kondisi kosong kandidat dan belum ada status ditetapkan', () => {
        render(<StatusCapaian {...props({
            kandidat: [],
            pagination: { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null },
        })} />);

        expect(screen.getByText('Tidak ada pengukuran yang menunggu penetapan')).toBeTruthy();
        expect(screen.getByText('Belum ada status capaian yang ditetapkan pada versi pengukuran yang disahkan.')).toBeTruthy();
        expect(screen.queryByRole('table')).toBeNull();
    });

    it('menyembunyikan aksi tetapkan dan menandai tanpa izin saat can.tetapkan false', () => {
        render(<StatusCapaian {...props({ can: { tetapkan: false } })} />);

        expect(screen.getByText(/Anda tidak memiliki izin menetapkan status capaian/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Tetapkan status capaian/ })).toBeNull();
        const tabel = screen.getByRole('table', { name: 'Kandidat penetapan status capaian' });
        expect(within(tabel).getByText('Tanpa izin')).toBeTruthy();
    });

    it('mengirim status, versi pengukuran, token versi, dan alasan saat menetapkan', async () => {
        const user = userEvent.setup();
        render(<StatusCapaian {...props()} />);

        await user.click(tombolTetapkan()[0]);
        const dialog = screen.getByRole('dialog', { name: 'Tetapkan status capaian' });
        expect((within(dialog).getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);

        await user.click(within(dialog).getByRole('radio', { name: /^Belum tercapai/ }));
        await user.type(within(dialog).getByLabelText('Alasan (opsional)'), 'Kualitas data belum lengkap');
        expect((within(dialog).getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(false);

        fireEvent.submit(formDialog());

        expect(form.posts).toHaveLength(1);
        expect(form.posts[0].url).toBe('/verifikasi/pengukuran-1/status-capaian');
        expect(form.posts[0].data).toMatchObject({
            status: 'belum_tercapai',
            pengukuran_versi_id: 'versi-1',
            versi: 7,
            alasan: 'Kualitas data belum lengkap',
        });
    });

    it('menampilkan penolakan token versi sebagai pesan konflik dan mengunci simpan', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<StatusCapaian {...props()} />);

        await user.click(tombolTetapkan()[0]);
        expect(screen.getByRole('radio', { name: /^Tercapai/ })).toBeTruthy();

        form.errors = { versi: 'Versi pengukuran telah berubah. Muat ulang halaman sebelum menetapkan ulang.' };
        rerender(<StatusCapaian {...props()} />);

        expect(screen.getByText('Versi pengukuran telah berubah. Muat ulang halaman sebelum menetapkan ulang.')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);
    });

    it('mengunci penetapan dan menawarkan muat ulang saat server menolak dengan 409', async () => {
        const user = userEvent.setup();
        render(<StatusCapaian {...props()} />);

        await user.click(tombolTetapkan()[0]);
        await user.click(screen.getByRole('radio', { name: /^Tercapai/ }));
        fireEvent.submit(formDialog());
        expect(form.posts).toHaveLength(1);

        const onHttpException = form.posts[0].options.onHttpException as (response: { status: number }) => void;
        act(() => { onHttpException({ status: 409 }); });

        expect(screen.getByText('Data telah berubah. Muat ulang halaman sebelum menetapkan ulang.')).toBeTruthy();
        expect((screen.getByRole('group', { name: 'Status capaian' }) as HTMLFieldSetElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);

        await user.click(screen.getByRole('button', { name: 'Muat ulang halaman' }));
        expect(router.reload).toHaveBeenCalled();
    });

    it('menampilkan tombol revisi hanya ketika izin halaman dan izin baris tersedia', () => {
        const { rerender } = render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);
        expect(screen.getByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeTruthy();

        rerender(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan], can: { tetapkan: false } })} />);
        expect(screen.queryByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeNull();

        rerender(<StatusCapaian {...props({ sudah_ditetapkan: [{ ...sudahDitetapkan, can: { tetapkan: false, revisi: false } }] })} />);
        expect(screen.queryByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeNull();
    });

    it('membuka dialog revisi dengan status berlaku terpilih dan mengirim token versi baris', async () => {
        const user = userEvent.setup();
        render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);

        await user.click(screen.getByRole('button', { name: 'Revisi status capaian IKU-UJI' }));
        const dialog = screen.getByRole('dialog', { name: 'Revisi status capaian' });
        expect(within(dialog).getByText('Status berlaku')).toBeTruthy();
        expect((within(dialog).getByRole('radio', { name: /^Belum tercapai/ }) as HTMLInputElement).checked).toBe(true);
        expect((within(dialog).getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(false);

        fireEvent.submit(formDialog());

        expect(form.posts[0].url).toBe('/verifikasi/pengukuran-2/status-capaian');
        expect(form.posts[0].data).toMatchObject({ status: 'belum_tercapai', pengukuran_versi_id: 'versi-1', versi: 7 });
    });

    it('menandai hanya status berlaku pada riwayat per versi', () => {
        render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);

        expect(screen.getByText(/Riwayat status per versi \(2\)/)).toBeTruthy();
        const berlaku = screen.getAllByText('Berlaku');
        expect(berlaku).toHaveLength(1);
        const barisBerlaku = berlaku[0].closest('li');
        expect(barisBerlaku).toBeTruthy();
        expect(within(barisBerlaku as HTMLElement).getByText(/^Versi 2 · Verifikator Uji/)).toBeTruthy();
        expect(within(barisBerlaku as HTMLElement).queryByText(/^Versi 1 ·/)).toBeNull();
    });

    it('menampilkan paginasi revisi memakai total server dan tautan status_page', () => {
        render(<StatusCapaian {...props({
            sudah_ditetapkan: [sudahDitetapkan],
            pagination_status: {
                current_page: 1,
                last_page: 2,
                total: 25,
                prev_page_url: null,
                next_page_url: '/verifikasi/status-capaian?status_page=2',
            },
        })} />);

        const nav = screen.getByRole('navigation', { name: 'Halaman status capaian yang sudah ditetapkan' });
        expect(within(nav).getByText('Halaman 1 dari 2 · 25 baris')).toBeTruthy();
        expect(within(nav).queryByText(/1 baris/)).toBeNull();

        const berikutnya = within(nav).getByRole('link', { name: 'Berikutnya' });
        expect(berikutnya.getAttribute('href')).toBe('/verifikasi/status-capaian?status_page=2');
        expect(within(nav).queryByRole('link', { name: 'Sebelumnya' })).toBeNull();
    });

    it('menyembunyikan paginasi revisi saat koleksi hanya satu halaman', () => {
        render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);

        expect(screen.getByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeTruthy();
        expect(screen.queryByRole('navigation', { name: 'Halaman status capaian yang sudah ditetapkan' })).toBeNull();
        expect(screen.queryByText(/Halaman 1 dari 1/)).toBeNull();
    });
});
