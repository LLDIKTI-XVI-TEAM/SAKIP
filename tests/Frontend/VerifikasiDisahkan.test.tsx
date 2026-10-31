import { useState, type ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PengukuranDisahkan, { type BarisDisahkan } from '@/Pages/Verifikasi/Disahkan';
import type { PengukuranPagination } from '@/Pages/Pengukuran/types';

type GetCall = { url: string; data: Record<string, unknown>; options: Record<string, unknown> };

const form = vi.hoisted(() => ({
    gets: [] as GetCall[],
    errors: {} as Record<string, string | undefined>,
    processing: false,
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, 'aria-label': ariaLabel }: { children: ReactNode; href: string; 'aria-label'?: string }) => <a href={href} aria-label={ariaLabel}>{children}</a>,
    usePage: () => ({ props: { pengaturan: {} } }),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setData] = useState(initial);

        return {
            data,
            setData: (key: string | Record<string, unknown>, value?: unknown) => {
                setData((current) => (typeof key === 'string' ? { ...current, [key]: value } : key));
            },
            get: (url: string, options: Record<string, unknown>) => {
                form.gets.push({ url, data, options });
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
    form.gets = [];
    form.errors = {};
    form.processing = false;
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

const filters = { renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const selectedFilters = { renstra_id: null, periode_id: null, unit_id: null };
const pagination: PengukuranPagination = { current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null };

const dasar: BarisDisahkan = {
    id: 'pengukuran-1', versi: 7, nomor_pengajuan: 2, jalur_pengajuan: 'pic', self_approval: false, reviu_terlambat: false,
    status: 'disahkan', target: '100', nilai: '88', status_perhitungan: 'terhitung', sumber_nilai: 'manual',
    catatan: null, alasan_tidak_dapat_dihitung: null, komponen: [], prasyarat: { siap: true, alasan: [] },
    persyaratan_bukti: [],
    can: { view: true, evidence: true, uploadEvidence: false, update: false, submit: false, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator uji disahkan', satuan: '%', tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'unit-uji', nama: 'Unit Uji' },
        pic: { id: 'pic-1', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: 'periode-1', urutan: 1, nama_periode: 'Triwulan I', tahun: 2026, is_nilai_akhir: false, tanggal: { bulan_mulai: 1, hari_mulai: 1, bulan_selesai: 3, hari_selesai: 31 } },
    bukti_dukungs: [], bukti_count: 0,
    versi_resmi: { id: 'versi-3', nomor: 3, disahkan_pada: '2026-05-02T03:00:00+08:00' },
};

function tampilan(overrides: {
    pengukurans?: BarisDisahkan[];
    pagination?: typeof pagination;
    can?: { buka_kembali: boolean };
} = {}) {
    return <PengukuranDisahkan
        pengukurans={overrides.pengukurans ?? [dasar]}
        pagination={overrides.pagination ?? pagination}
        filters={filters}
        selected_filters={selectedFilters}
        can={overrides.can ?? { buka_kembali: true }}
    />;
}

function tabelDisahkan(): HTMLElement {
    return screen.getByRole('table', { name: 'Daftar pengukuran yang telah disahkan' });
}

function kartuDisahkan(): HTMLElement {
    return screen.getByRole('list', { name: 'Daftar pengukuran disahkan' });
}

describe('Antrean pengukuran disahkan', () => {
    it('merender baris antrean: indikator, unit, periode, versi resmi, nilai, status, dan tautan aksi', () => {
        render(tampilan());

        const tabel = tabelDisahkan();
        expect(within(tabel).getByText('IKU-UJI')).toBeTruthy();
        expect(within(tabel).getByText('Indikator uji disahkan')).toBeTruthy();
        expect(within(tabel).getByText('Unit Uji')).toBeTruthy();
        expect(within(tabel).getByText('Triwulan I 2026')).toBeTruthy();
        expect(within(tabel).getByText('Versi 3')).toBeTruthy();
        expect(within(tabel).getByText('disahkan 02 Mei 2026')).toBeTruthy();
        expect(within(tabel).getByText('88,00 %')).toBeTruthy();
        expect(within(tabel).getByText('Disahkan')).toBeTruthy();

        const aksi = within(tabel).getByRole('link', { name: 'Buka kembali pengukuran IKU-UJI periode Triwulan I 2026' });
        expect(aksi.getAttribute('href')).toBe('/verifikasi/pengukuran-1');

        const mobile = screen.getByRole('list', { name: 'Daftar pengukuran disahkan' });
        expect(within(mobile).getByText('Indikator uji disahkan')).toBeTruthy();
        expect(within(mobile).getByText('Versi 3')).toBeTruthy();
        expect(within(mobile).getByRole('link', { name: 'Buka kembali pengukuran IKU-UJI periode Triwulan I 2026' }).getAttribute('href')).toBe('/verifikasi/pengukuran-1');
    });

    it('menandai baris tanpa versi resmi sebagai Belum ada versi sah', () => {
        render(tampilan({ pengukurans: [{ ...dasar, versi_resmi: null }] }));

        const tabel = tabelDisahkan();
        expect(within(tabel).getByText('Belum ada versi sah')).toBeTruthy();
        expect(within(tabel).queryByText(/^Versi /)).toBeNull();
        expect(within(tabel).queryByText(/^disahkan /)).toBeNull();
    });

    it('memakai snapshot lama sebagai fallback versi resmi pada payload tanpa versi_resmi', () => {
        render(tampilan({
            pengukurans: [{ ...dasar, versi_resmi: undefined, snapshot: { snapshot_hash: 'hash-lama', disahkan_pada: '2026-05-02T03:00:00+08:00', nomor_pengajuan: 3 } }],
        }));

        const tabel = tabelDisahkan();
        expect(within(tabel).getByText('Versi 3')).toBeTruthy();
        expect(within(tabel).queryByText('Belum ada versi sah')).toBeNull();
    });

    it('membedakan kondisi kosong total nol dari halaman yang melewati batas', () => {
        const { unmount } = render(tampilan({
            pengukurans: [],
            pagination: { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null },
        }));

        expect(screen.getByText('Tidak ada pengukuran disahkan')).toBeTruthy();
        expect(screen.getByText('Belum ada pengukuran yang disahkan dan tersedia untuk koreksi.')).toBeTruthy();
        expect(screen.queryByRole('table')).toBeNull();
        unmount();

        render(tampilan({
            pengukurans: [],
            pagination: { current_page: 3, last_page: 3, total: 5, prev_page_url: '/verifikasi/disahkan?page=2', next_page_url: null },
        }));

        expect(screen.getByText('Tidak ada pengukuran di halaman ini')).toBeTruthy();
        expect(screen.getByText(/masih ada 5 pengukuran disahkan pada halaman lain/)).toBeTruthy();
        expect(screen.queryByText('Tidak ada pengukuran disahkan')).toBeNull();
    });

    it('merender tautan paginasi sesuai prev_page_url dan next_page_url', () => {
        const { unmount } = render(tampilan({
            pagination: { current_page: 2, last_page: 3, total: 87, prev_page_url: '/verifikasi/disahkan?page=1', next_page_url: '/verifikasi/disahkan?page=3' },
        }));

        const nav = screen.getByRole('navigation', { name: 'Halaman pengukuran disahkan' });
        expect(within(nav).getByText('Halaman 2 dari 3 · 87 baris')).toBeTruthy();
        expect(within(nav).getByRole('link', { name: 'Sebelumnya' }).getAttribute('href')).toBe('/verifikasi/disahkan?page=1');
        expect(within(nav).getByRole('link', { name: 'Berikutnya' }).getAttribute('href')).toBe('/verifikasi/disahkan?page=3');
        unmount();

        render(tampilan({
            pagination: { current_page: 3, last_page: 3, total: 87, prev_page_url: '/verifikasi/disahkan?page=2', next_page_url: null },
        }));

        const navTerakhir = screen.getByRole('navigation', { name: 'Halaman pengukuran disahkan' });
        expect(within(navTerakhir).getByRole('link', { name: 'Sebelumnya' }).getAttribute('href')).toBe('/verifikasi/disahkan?page=2');
        expect(within(navTerakhir).queryByRole('link', { name: 'Berikutnya' })).toBeNull();
    });

    it('menampilkan notice hanya baca dan menyembunyikan tautan aksi saat can.buka_kembali false atau absen', () => {
        const { unmount } = render(tampilan({ can: { buka_kembali: false } }));

        expect(screen.getByRole('status').textContent).toContain('Anda tidak memiliki izin membuka kembali pengukuran. Halaman ini hanya dapat dibaca.');
        expect(screen.queryByRole('link', { name: /Buka kembali pengukuran/ })).toBeNull();
        expect(within(tabelDisahkan()).getByText('Hanya baca')).toBeTruthy();
        expect(within(kartuDisahkan()).getByText('Hanya baca')).toBeTruthy();
        unmount();

        render(<PengukuranDisahkan pengukurans={[dasar]} pagination={pagination} filters={filters} selected_filters={selectedFilters} />);

        expect(screen.getByRole('status').textContent).toContain('Halaman ini hanya dapat dibaca');
        expect(screen.queryByRole('link', { name: /Buka kembali pengukuran/ })).toBeNull();
    });

    it('memakai lookupUrl antrean disahkan dan menyembunyikan filter status', async () => {
        const user = userEvent.setup();
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ data: [{ id: 'unit-1', label: 'Unit Uji' }], page: 1, has_more: false }),
        });
        vi.stubGlobal('fetch', fetchMock);
        render(tampilan());

        expect(screen.getByRole('form', { name: 'Filter antrean pengukuran disahkan' })).toBeTruthy();
        expect(screen.queryByRole('combobox', { name: 'Status antrean' })).toBeNull();

        await user.type(screen.getByRole('textbox', { name: 'Cari Unit' }), 'Unit');
        expect(await screen.findByRole('button', { name: 'Unit Uji' })).toBeTruthy();

        expect(fetchMock.mock.calls[0][0]).toBe('/verifikasi/disahkan/pilihan?jenis=unit&q=Unit&page=1');
        expect(fetchMock.mock.calls.every(([url]) => !String(url).startsWith('/verifikasi/pilihan'))).toBe(true);
    });
});
