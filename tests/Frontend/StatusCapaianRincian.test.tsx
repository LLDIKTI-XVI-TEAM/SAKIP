import { useState, type ReactNode } from 'react';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import StatusCapaianRincian from '@/Pages/Verifikasi/StatusCapaianRincian';
import type { BarisStatusCapaian } from '@/types/statusCapaian';

type PostCall = { url: string; data: Record<string, unknown> };

const form = vi.hoisted(() => ({ posts: [] as PostCall[] }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, 'aria-label': ariaLabel }: { children: ReactNode; href: string; 'aria-label'?: string }) => (
        <a href={href} aria-label={ariaLabel}>{children}</a>
    ),
    router: { reload: vi.fn(), get: vi.fn() },
    usePage: () => ({ props: { pengaturan: {} }, url: '/verifikasi/status-capaian/pengukuran-1' }),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setData] = useState(initial);

        return {
            data,
            setData: (key: string, value: unknown) => setData((current) => ({ ...current, [key]: value })),
            post: (url: string) => { form.posts.push({ url, data }); },
            processing: false,
            errors: {},
        };
    },
}));
// Layout tiruan hanya menyediakan h1; tautan kembali dan tombol aksi berada di isi halaman sehingga tetap teruji.
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, title }: { children: ReactNode; title: string }) => <main><h1>{title}</h1>{children}</main>,
}));

beforeEach(() => { form.posts = []; });
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
        { ...statusBerlaku },
        { id: 'status-1', status: 'tercapai', sumber: 'manual', versi_id: 'versi-0', versi_nomor: 1, penetap: 'Verifikator Lama', ditetapkan_pada: '2026-03-01T03:00:00+08:00' },
    ],
});

const nilai = (wadah: HTMLElement, label: string) => within(wadah).getByText(label).nextElementSibling?.textContent;
const kartu = (judul: string) => screen.getByRole('region', { name: judul });

describe('Halaman rincian status capaian', () => {
    it('menampilkan identitas, angka pengukuran, dan riwayat dalam tiga kartu', () => {
        render(<StatusCapaianRincian pengukuran={baris()} can={{ tetapkan: true }} />);

        expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
        expect(screen.getAllByRole('region')).toHaveLength(3);

        const identitas = kartu('IKU-UJI · Indikator uji status capaian');
        expect(within(identitas).getByText('Belum Ditetapkan')).toBeTruthy();
        expect(nilai(identitas, 'Unit Kerja')).toBe('Unit Uji');
        expect(nilai(identitas, 'Periode')).toBe('Triwulan I 2026');
        expect(nilai(identitas, 'Versi pengukuran')).toBe('Versi 2');
        expect(nilai(identitas, 'Tanggal disahkan')).toContain('2026');
        // Penetap dan waktu penetapan hanya tampil bila sudah ada status berlaku.
        expect(within(identitas).queryByText('Ditetapkan oleh')).toBeNull();

        const angka = kartu('Angka pengukuran');
        expect(nilai(angka, 'Realisasi')).toBe('88,00 %');
        expect(nilai(angka, 'Target periode')).toBe('100,00 %');
        expect(nilai(angka, 'Capaian terhadap target')).toBe('88,00%');
        expect(nilai(angka, 'Target PK')).toBe('100,00 %');
        expect(nilai(angka, 'Selisih terhadap target PK')).toContain('di bawah target PK');
        // Versi hanya tampil sekali, di kartu identitas.
        expect(within(angka).queryByText('Versi pengukuran')).toBeNull();

        const riwayat = within(kartu('Riwayat status')).getByRole('table', { name: 'Riwayat status capaian' });
        expect(riwayat.className).toContain('md:table');
        expect(within(riwayat).getAllByRole('columnheader').map((kolom) => kolom.textContent)).toEqual([
            'STATUS', 'VERSI', 'DITETAPKAN OLEH', 'WAKTU',
        ]);
        expect(within(riwayat).getByText('Belum ada riwayat status')).toBeTruthy();
        expect(within(riwayat).queryByText('Berlaku')).toBeNull();
    });

    it('menampilkan status berlaku beserta penetap dan menandai satu baris riwayat sebagai berlaku', () => {
        render(<StatusCapaianRincian pengukuran={sudahDitetapkan} can={{ tetapkan: true }} />);

        const identitas = kartu('IKU-UJI · Indikator uji status capaian');
        expect(within(identitas).getByText('Belum Tercapai')).toBeTruthy();
        expect(within(identitas).queryByText('Belum Ditetapkan')).toBeNull();
        expect(nilai(identitas, 'Ditetapkan oleh')).toBe('Verifikator Uji');
        expect(nilai(identitas, 'Waktu penetapan')).toContain('2026');

        const riwayat = screen.getByRole('table', { name: 'Riwayat status capaian' });
        const barisRiwayat = within(riwayat).getAllByRole('row').slice(1);
        expect(barisRiwayat).toHaveLength(2);
        expect(within(riwayat).getAllByText('Berlaku')).toHaveLength(1);
        expect(within(barisRiwayat[0]).getByText('Berlaku')).toBeTruthy();
        expect(within(barisRiwayat[0]).getByText('Belum Tercapai')).toBeTruthy();
        expect(within(barisRiwayat[0]).getByText('Versi 2')).toBeTruthy();
        expect(within(barisRiwayat[0]).getByText('Verifikator Uji')).toBeTruthy();
        expect(within(barisRiwayat[1]).queryByText('Berlaku')).toBeNull();
        expect(within(barisRiwayat[1]).getByText('Tercapai')).toBeTruthy();
        expect(within(barisRiwayat[1]).getByText('Versi 1')).toBeTruthy();
        expect(within(barisRiwayat[1]).getByText('Verifikator Lama')).toBeTruthy();
        expect(within(riwayat).queryByText('Belum ada riwayat status')).toBeNull();
    });

    it('mengarahkan tautan kembali ke tab daftar yang sesuai', () => {
        const { unmount } = render(<StatusCapaianRincian pengukuran={baris()} can={{ tetapkan: true }} />);
        expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/verifikasi/status-capaian');
        unmount();

        render(<StatusCapaianRincian pengukuran={sudahDitetapkan} can={{ tetapkan: true }} />);
        expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/verifikasi/status-capaian?tab=ditetapkan');
    });

    it('menampilkan tombol tetapkan mengikuti can server dan membuka dialog penetapan yang sama', async () => {
        const user = userEvent.setup();
        render(<StatusCapaianRincian pengukuran={baris()} can={{ tetapkan: true }} />);

        expect(screen.queryByRole('button', { name: /Revisi status capaian/ })).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Tetapkan status capaian IKU-UJI' }));
        const dialog = screen.getByRole('dialog', { name: 'Tetapkan status capaian' });
        expect(within(dialog).getByText('Selisih terhadap target PK')).toBeTruthy();

        await user.click(within(dialog).getByRole('radio', { name: /^Tercapai/ }));
        fireEvent.submit(dialog.querySelector('form') as HTMLFormElement);

        expect(form.posts).toHaveLength(1);
        expect(form.posts[0].url).toBe('/verifikasi/pengukuran-1/status-capaian');
        expect(form.posts[0].data).toMatchObject({ status: 'tercapai', pengukuran_versi_id: 'versi-1', versi: 7 });
    });

    it('menampilkan tombol revisi mengikuti can server dan membuka dialog revisi yang sama', async () => {
        const user = userEvent.setup();
        render(<StatusCapaianRincian pengukuran={sudahDitetapkan} can={{ tetapkan: true }} />);

        expect(screen.queryByRole('button', { name: /Tetapkan status capaian/ })).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Revisi status capaian IKU-UJI' }));
        const dialog = screen.getByRole('dialog', { name: 'Revisi status capaian' });
        expect((within(dialog).getByRole('radio', { name: /^Belum tercapai/ }) as HTMLInputElement).checked).toBe(true);

        fireEvent.submit(dialog.querySelector('form') as HTMLFormElement);
        expect(form.posts[0].url).toBe('/verifikasi/pengukuran-2/status-capaian');
        expect(form.posts[0].data).toMatchObject({ status: 'belum_tercapai', pengukuran_versi_id: 'versi-1', versi: 7 });
    });

    it('tidak menampilkan tombol aksi tanpa izin baris atau izin halaman, tetapi rincian tetap terbaca', () => {
        const tombolAksi = () => screen.queryByRole('button', { name: /(Tetapkan|Revisi) status capaian/ });

        // Kontrol positif: dengan izin lengkap tombol tampil.
        const { rerender } = render(<StatusCapaianRincian pengukuran={baris()} can={{ tetapkan: true }} />);
        expect(tombolAksi()).toBeTruthy();

        rerender(<StatusCapaianRincian pengukuran={baris({ can: { tetapkan: false, revisi: false } })} can={{ tetapkan: true }} />);
        expect(tombolAksi()).toBeNull();
        expect(nilai(kartu('Angka pengukuran'), 'Target PK')).toBe('100,00 %');

        rerender(<StatusCapaianRincian pengukuran={{ ...sudahDitetapkan, can: { tetapkan: false, revisi: false } }} can={{ tetapkan: true }} />);
        expect(tombolAksi()).toBeNull();

        rerender(<StatusCapaianRincian pengukuran={sudahDitetapkan} can={{ tetapkan: false }} />);
        expect(tombolAksi()).toBeNull();
        expect(screen.getByRole('table', { name: 'Riwayat status capaian' })).toBeTruthy();
    });

    it('menulis tanda hubung untuk nilai yang tidak tersedia', () => {
        render(<StatusCapaianRincian
            pengukuran={baris({ unit: { id: 'unit-1', nama: null }, target_pk: null, selisih_target_pk: null, capaian_persen: null })}
            can={{ tetapkan: true }}
        />);

        expect(nilai(kartu('IKU-UJI · Indikator uji status capaian'), 'Unit Kerja')).toBe('-');
        const angka = kartu('Angka pengukuran');
        expect(nilai(angka, 'Target PK')).toBe('-');
        expect(nilai(angka, 'Selisih terhadap target PK')).toBe('-');
        expect(nilai(angka, 'Capaian terhadap target')).toBe('-');
    });
});
