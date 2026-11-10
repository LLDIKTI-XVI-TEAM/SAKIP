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
const lokasi = vi.hoisted(() => ({ url: '/verifikasi/status-capaian' }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, 'aria-label': ariaLabel, 'aria-current': ariaCurrent }: { children: ReactNode; href: string; 'aria-label'?: string; 'aria-current'?: 'page' }) => (
        <a href={href} aria-label={ariaLabel} aria-current={ariaCurrent}>{children}</a>
    ),
    router: { reload: vi.fn(), get: vi.fn() },
    usePage: () => ({ props: halaman, url: lokasi.url }),
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
    lokasi.url = '/verifikasi/status-capaian';
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

const TABEL_KANDIDAT = 'Kandidat penetapan status capaian';
const TABEL_DITETAPKAN = 'Status capaian yang sudah ditetapkan';

function tombolTetapkan() {
    return screen.getAllByRole('button', { name: 'Tetapkan status capaian IKU-UJI' });
}

/** Tab aktif dibaca dari URL, sehingga daftar yang sudah ditetapkan diuji dengan parameter `tab`. */
function bukaTabDitetapkan(query = '') {
    lokasi.url = `/verifikasi/status-capaian?tab=ditetapkan${query}`;
}

function formDialog(): HTMLFormElement {
    const element = document.querySelector('form');
    if (!element) throw new Error('Form dialog tidak ditemukan');
    return element;
}

describe('Halaman status capaian versi pengukuran', () => {
    it('menampilkan kandidat pada satu tabel ringkas berpola kartu', () => {
        render(<StatusCapaian {...props()} />);

        const tabel = screen.getByRole('table', { name: TABEL_KANDIDAT });
        expect(tabel.className).toContain('md:table');
        expect(within(tabel).getAllByRole('columnheader').map((kolom) => kolom.textContent)).toEqual([
            'KODE & INDIKATOR', 'UNIT KERJA', 'PERIODE', 'REALISASI', 'CAPAIAN', 'AKSI',
        ]);
        expect(within(tabel).getByText('IKU-UJI')).toBeTruthy();
        expect(within(tabel).getByText('Indikator uji status capaian')).toBeTruthy();
        expect(within(tabel).getByText('Unit Uji')).toBeTruthy();
        expect(within(tabel).getByText('Triwulan I 2026')).toBeTruthy();
        expect(within(tabel).getByText('Versi 2')).toBeTruthy();
        expect(within(tabel).getByText('88,00 %')).toBeTruthy();
        expect(within(tabel).getByText('Target 100,00 %')).toBeTruthy();
        expect(within(tabel).getByText('88,00%')).toBeTruthy();

        // Satu markup untuk desktop dan layar kecil: tombol tidak lagi digandakan.
        expect(tombolTetapkan()).toHaveLength(1);
        // Rincian dibaca di dialog penetapan, bukan di baris.
        expect(within(tabel).queryByText(/target PK/i)).toBeNull();
        expect(within(tabel).queryByText(/disahkan/)).toBeNull();
        expect(within(tabel).queryByText('Belum Ditetapkan')).toBeNull();
    });

    it('menampilkan dua daftar sebagai tab berjumlah dan hanya merender daftar aktif', () => {
        const { unmount } = render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);
        let tab = within(screen.getByRole('navigation', { name: 'Daftar status capaian' }));
        const belum = tab.getByRole('link', { name: 'Belum ditetapkan, 1 pengukuran' });
        const sudah = tab.getByRole('link', { name: 'Sudah ditetapkan, 1 pengukuran' });

        expect(belum.getAttribute('aria-current')).toBe('page');
        expect(sudah.getAttribute('aria-current')).toBeNull();
        expect(sudah.getAttribute('href')).toBe('/verifikasi/status-capaian?tab=ditetapkan');
        expect(screen.getByRole('table', { name: TABEL_KANDIDAT })).toBeTruthy();
        expect(screen.queryByRole('table', { name: TABEL_DITETAPKAN })).toBeNull();
        unmount();

        bukaTabDitetapkan('&status_page=2');
        render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);
        tab = within(screen.getByRole('navigation', { name: 'Daftar status capaian' }));

        expect(tab.getByRole('link', { name: 'Sudah ditetapkan, 1 pengukuran' }).getAttribute('aria-current')).toBe('page');
        // Pindah tab hanya melepas parameter tab; halaman daftar lain tetap diingat.
        expect(tab.getByRole('link', { name: 'Belum ditetapkan, 1 pengukuran' }).getAttribute('href')).toBe('/verifikasi/status-capaian?status_page=2');
        expect(screen.getByRole('table', { name: TABEL_DITETAPKAN })).toBeTruthy();
        expect(screen.queryByRole('table', { name: TABEL_KANDIDAT })).toBeNull();
    });

    it('menampilkan status berlaku pada tabel ringkas daftar yang sudah ditetapkan', () => {
        bukaTabDitetapkan();
        render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);

        const tabel = screen.getByRole('table', { name: TABEL_DITETAPKAN });
        expect(tabel.className).toContain('md:table');
        expect(within(tabel).getAllByRole('columnheader').map((kolom) => kolom.textContent)).toEqual([
            'KODE & INDIKATOR', 'UNIT KERJA', 'PERIODE', 'CAPAIAN', 'STATUS', 'AKSI',
        ]);
        expect(within(tabel).getByText('IKU-UJI')).toBeTruthy();
        expect(within(tabel).getByText('Triwulan I 2026')).toBeTruthy();
        expect(within(tabel).getByText('88,00%')).toBeTruthy();
        expect(within(tabel).getByText('Belum Tercapai')).toBeTruthy();
        // Penetap dan riwayat dibaca dari jendela riwayat, bukan dari baris.
        expect(within(tabel).queryByText(/Verifikator/)).toBeNull();
        expect(within(tabel).queryByText(/Target/)).toBeNull();
    });

    it('menampilkan kondisi kosong kandidat dan belum ada status ditetapkan', () => {
        const kosong = props({
            kandidat: [],
            pagination: { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null },
        });
        const { unmount } = render(<StatusCapaian {...kosong} />);

        expect(within(screen.getByRole('table', { name: TABEL_KANDIDAT })).getByText('Tidak ada pengukuran yang menunggu penetapan')).toBeTruthy();
        unmount();

        bukaTabDitetapkan();
        render(<StatusCapaian {...kosong} />);
        expect(within(screen.getByRole('table', { name: TABEL_DITETAPKAN })).getByText('Belum ada status capaian yang ditetapkan')).toBeTruthy();
    });

    /** P2: halaman yang melewati batas tetap memuat nav agar pengguna dapat kembali ke halaman berisi kandidat. */
    it('membedakan halaman kosong karena melewati batas dan tetap menampilkan nav kandidat', () => {
        render(<StatusCapaian {...props({
            kandidat: [],
            pagination: {
                current_page: 3,
                last_page: 2,
                total: 5,
                prev_page_url: '/verifikasi/status-capaian?page=2',
                next_page_url: null,
            },
        })} />);

        expect(screen.getByText('Tidak ada kandidat di halaman ini')).toBeTruthy();
        expect(screen.queryByText('Tidak ada pengukuran yang menunggu penetapan')).toBeNull();

        const nav = screen.getByRole('navigation', { name: 'Halaman pengukuran' });
        expect(within(nav).getByText('Halaman 3 dari 2 · 5 pengukuran')).toBeTruthy();
        expect(within(nav).getByRole('link', { name: 'Sebelumnya' }).getAttribute('href')).toBe('/verifikasi/status-capaian?page=2');
        expect(within(nav).queryByRole('link', { name: 'Berikutnya' })).toBeNull();
    });

    /** P2: total nol berarti memang tidak ada kandidat, bukan halaman yang melewati batas. */
    it('memakai kondisi kosong total nol tanpa nav paginasi kandidat', () => {
        render(<StatusCapaian {...props({
            kandidat: [],
            pagination: { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null },
        })} />);

        expect(screen.getByText('Tidak ada pengukuran yang menunggu penetapan')).toBeTruthy();
        expect(screen.queryByText('Tidak ada kandidat di halaman ini')).toBeNull();
        expect(screen.queryByRole('navigation', { name: 'Halaman pengukuran' })).toBeNull();
        expect(screen.queryByText(/Halaman 1 dari 1/)).toBeNull();
    });

    it('tetap menampilkan tabel kandidat dan nav saat halaman berisi kandidat', () => {
        render(<StatusCapaian {...props({
            pagination: {
                current_page: 1,
                last_page: 2,
                total: 5,
                prev_page_url: null,
                next_page_url: '/verifikasi/status-capaian?page=2',
            },
        })} />);

        const tabel = screen.getByRole('table', { name: TABEL_KANDIDAT });
        expect(within(tabel).getByText('IKU-UJI')).toBeTruthy();
        expect(screen.queryByText('Tidak ada kandidat di halaman ini')).toBeNull();

        const nav = screen.getByRole('navigation', { name: 'Halaman pengukuran' });
        expect(within(nav).getByText('Halaman 1 dari 2 · 5 pengukuran')).toBeTruthy();
        expect(within(nav).getByRole('link', { name: 'Berikutnya' }).getAttribute('href')).toBe('/verifikasi/status-capaian?page=2');
        expect(within(nav).queryByRole('link', { name: 'Sebelumnya' })).toBeNull();
    });

    it('menyembunyikan aksi tetapkan dan menandai tanpa izin saat can.tetapkan false', () => {
        render(<StatusCapaian {...props({ can: { tetapkan: false } })} />);

        expect(screen.getByText(/Anda tidak memiliki izin menetapkan status capaian/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Tetapkan status capaian/ })).toBeNull();
        const tabel = screen.getByRole('table', { name: TABEL_KANDIDAT });
        expect(within(tabel).getByText('Tanpa izin')).toBeTruthy();
    });

    it('mengirim status, versi pengukuran, token versi, dan alasan saat menetapkan', async () => {
        const user = userEvent.setup();
        render(<StatusCapaian {...props()} />);

        await user.click(tombolTetapkan()[0]);
        const dialog = screen.getByRole('dialog', { name: 'Tetapkan status capaian' });
        expect((within(dialog).getByRole('button', { name: 'Simpan' }) as HTMLButtonElement).disabled).toBe(true);
        // Rincian yang dikeluarkan dari baris tetap tersedia di dialog.
        expect(within(dialog).getByText('Target PK')).toBeTruthy();
        expect(within(dialog).getByText('Selisih terhadap target PK')).toBeTruthy();

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

    /** m2: textarea alasan memakai batas 1000 karakter yang sama dengan aturan server (`max:1000`). */
    it('membatasi isian alasan pada 1000 karakter', async () => {
        const user = userEvent.setup();
        render(<StatusCapaian {...props()} />);

        await user.click(tombolTetapkan()[0]);
        const dialog = screen.getByRole('dialog', { name: 'Tetapkan status capaian' });
        const alasan = within(dialog).getByLabelText('Alasan (opsional)') as HTMLTextAreaElement;

        expect(alasan.maxLength).toBe(1000);
        expect(alasan.getAttribute('maxlength')).toBe('1000');
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
        bukaTabDitetapkan();
        const { rerender } = render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);
        expect(screen.getByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeTruthy();

        rerender(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan], can: { tetapkan: false } })} />);
        expect(screen.queryByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeNull();
        // Riwayat tetap dapat dibuka pengguna yang hanya membaca.
        expect(screen.getByRole('button', { name: 'Riwayat status capaian IKU-UJI' })).toBeTruthy();

        rerender(<StatusCapaian {...props({ sudah_ditetapkan: [{ ...sudahDitetapkan, can: { tetapkan: false, revisi: false } }] })} />);
        expect(screen.queryByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeNull();
    });

    it('membuka dialog revisi dengan status berlaku terpilih dan mengirim token versi baris', async () => {
        const user = userEvent.setup();
        bukaTabDitetapkan();
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

    it('membuka riwayat per versi di jendela dan menandai hanya status berlaku', async () => {
        const user = userEvent.setup();
        bukaTabDitetapkan();
        render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);

        expect(screen.queryByText(/Verifikator Lama/)).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Riwayat status capaian IKU-UJI' }));
        const dialog = screen.getByRole('dialog', { name: 'Riwayat status capaian' });

        expect(within(dialog).getAllByRole('listitem')).toHaveLength(2);
        const berlaku = within(dialog).getAllByText('Berlaku');
        expect(berlaku).toHaveLength(1);
        const barisBerlaku = berlaku[0].closest('li');
        expect(barisBerlaku).toBeTruthy();
        expect(within(barisBerlaku as HTMLElement).getByText(/^Versi 2 · Verifikator Uji/)).toBeTruthy();
        expect(within(barisBerlaku as HTMLElement).queryByText(/^Versi 1 ·/)).toBeNull();
        expect(within(dialog).getByText(/^Versi 1 · Verifikator Lama/)).toBeTruthy();

        await user.click(within(dialog).getByRole('button', { name: 'Tutup' }));
        expect(screen.queryByRole('dialog', { name: 'Riwayat status capaian' })).toBeNull();
    });

    it('menampilkan paginasi revisi memakai total server dan tautan status_page', () => {
        bukaTabDitetapkan();
        render(<StatusCapaian {...props({
            sudah_ditetapkan: [sudahDitetapkan],
            pagination_status: {
                current_page: 1,
                last_page: 2,
                total: 25,
                prev_page_url: null,
                next_page_url: '/verifikasi/status-capaian?tab=ditetapkan&status_page=2',
            },
        })} />);

        const nav = screen.getByRole('navigation', { name: 'Halaman pengukuran' });
        expect(within(nav).getByText('Halaman 1 dari 2 · 25 pengukuran')).toBeTruthy();

        const berikutnya = within(nav).getByRole('link', { name: 'Berikutnya' });
        expect(berikutnya.getAttribute('href')).toBe('/verifikasi/status-capaian?tab=ditetapkan&status_page=2');
        expect(within(nav).queryByRole('link', { name: 'Sebelumnya' })).toBeNull();
        expect(screen.getByRole('link', { name: 'Sudah ditetapkan, 25 pengukuran' })).toBeTruthy();
    });

    it('menyembunyikan paginasi revisi saat koleksi hanya satu halaman', () => {
        bukaTabDitetapkan();
        render(<StatusCapaian {...props({ sudah_ditetapkan: [sudahDitetapkan] })} />);

        expect(screen.getByRole('button', { name: 'Revisi status capaian IKU-UJI' })).toBeTruthy();
        expect(screen.queryByRole('navigation', { name: 'Halaman pengukuran' })).toBeNull();
        expect(screen.queryByText(/Halaman 1 dari 1/)).toBeNull();
    });
});
