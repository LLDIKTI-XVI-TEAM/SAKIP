import { useState, type ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import VerifikasiShow from '@/Pages/Verifikasi/Show';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

type PostCall = { url: string; data: Record<string, unknown>; options: Record<string, unknown> };

const form = vi.hoisted(() => ({
    posts: [] as PostCall[],
    errors: {} as Record<string, string | string[] | undefined>,
    processing: false,
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, 'aria-label': ariaLabel }: { children: ReactNode; href: string; 'aria-label'?: string }) => <a href={href} aria-label={ariaLabel}>{children}</a>,
    router: { reload: vi.fn(), get: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: { pengaturan: {} } }),
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
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => {
    for (const [name, descriptor] of [['showModal', originalShowModal], ['close', originalClose]] as const) {
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    }
});
beforeEach(() => {
    vi.clearAllMocks();
    form.posts = [];
    form.errors = {};
    form.processing = false;
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const PESAN_JADWAL_TUTUP = 'Tahun sudah ditutup; buka kembali jadwal tahunan lalu ulangi di dalam sesi koreksi.';

const dasar: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 2,
    jalur_pengajuan: 'perencanaan', self_approval: false, reviu_terlambat: false, status: 'disahkan',
    target: 100, nilai: 80, status_perhitungan: 'terhitung', sumber_nilai: 'manual',
    catatan: null, alasan_tidak_dapat_dihitung: null, komponen: [], prasyarat: { siap: true, alasan: [] },
    persyaratan_bukti: [],
    can: { view: true, evidence: true, uploadEvidence: false, update: false, submit: false, verify: false, ratify: false, return: false, reopen: true },
    businessErrors: { verifikasi: [], sahkan: [], kembalikan: [], buka_kembali: [] },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'ed27dabf-8d26-4e48-935b-07d913d8a49a', nama: 'Unit Uji' },
        pic: { id: 'ae1c7f4c-85b9-4db6-b89c-9951f91ab58e', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', urutan: 1, nama_periode: 'Semester I', tahun: 2026, is_nilai_akhir: false, tanggal: { bulan_mulai: 1, hari_mulai: 1, bulan_selesai: 6, hari_selesai: 30 } },
    bukti_dukungs: [], bukti_count: 0, riwayats: [],
    snapshot: { snapshot_hash: 'hash-uji', disahkan_pada: '2026-04-06T03:30:00+08:00', nomor_pengajuan: 2 },
    histori_versi: [
        { id: 'versi-1', nomor: 1, diajukan_at: '2026-01-05T01:00:00+08:00', disahkan_at: null, jalur_pengajuan: 'pic', resmi: false, status_capaian: null },
        { id: 'versi-2', nomor: 2, diajukan_at: '2026-04-04T02:00:00+08:00', disahkan_at: '2026-04-06T03:30:00+08:00', jalur_pengajuan: 'perencanaan', resmi: true,
            status_capaian: { status: 'tercapai', sumber: 'manual', penetap: { id: 'user-9', nama: 'Verifikator Uji' }, ditetapkan_pada: '2026-05-01T04:00:00+08:00' } },
        { id: 'versi-3', nomor: 3, diajukan_at: '2026-07-01T01:00:00+08:00', disahkan_at: null, jalur_pengajuan: null, resmi: false,
            status_capaian: { status: 'belum_tercapai', sumber: 'manual', penetap: null, ditetapkan_pada: null } },
    ],
};

const tampilan = (override: Partial<Pengukuran> = {}) => <VerifikasiShow pengukuran={{ ...dasar, ...override }} />;

function tombolPemicu(): HTMLButtonElement {
    return screen.getByRole<HTMLButtonElement>('button', { name: 'Buka Kembali' });
}

function dialogBukaKembali(): HTMLElement {
    return screen.getByRole('dialog', { name: 'Buka kembali pengukuran' });
}

function barisHistori(nomor: number): HTMLTableRowElement {
    const tabel = screen.getByRole('table', { name: 'Histori versi pengukuran' });
    const baris = within(tabel).getByText(`Versi ${nomor}`).closest('tr');
    if (!baris) throw new Error(`Baris histori versi ${nomor} tidak ditemukan`);

    return baris;
}

describe('Buka kembali pengukuran disahkan', () => {
    it('tombol Buka Kembali hanya tampil bila server mengizinkan can.reopen', () => {
        const { unmount } = render(tampilan());
        expect(tombolPemicu()).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Verifikasi pengukuran' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Sahkan kinerja resmi' })).toBeNull();
        unmount();

        render(tampilan({ can: { ...dasar.can, reopen: false } }));
        expect(screen.queryByRole('button', { name: 'Buka Kembali' })).toBeNull();
        expect(screen.queryByText('Keputusan pengukuran')).toBeNull();
    });

    it('dialog alasan wajib menonaktifkan submit saat alasan kosong atau hanya spasi', async () => {
        const user = userEvent.setup();
        render(tampilan());

        await user.click(tombolPemicu());
        const dialog = dialogBukaKembali();
        expect(within(dialog).getByText(/Versi resmi nomor 2 akan dibuka kembali untuk dikoreksi/)).toBeTruthy();

        const konfirmasi = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Buka Kembali' });
        expect(konfirmasi.disabled).toBe(true);

        const alasan = within(dialog).getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan perubahan/ });
        await user.type(alasan, '   ');
        expect(konfirmasi.disabled).toBe(true);

        await user.clear(alasan);
        await user.type(alasan, 'Koreksi resmi nilai TW I.');
        expect(konfirmasi.disabled).toBe(false);
    });

    it('mengirim token versi dan alasan ke endpoint buka kembali pengukuran', async () => {
        const user = userEvent.setup();
        render(tampilan());

        await user.click(tombolPemicu());
        const dialog = dialogBukaKembali();
        await user.type(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ }), 'Koreksi resmi nilai TW I.');
        await user.click(within(dialog).getByRole('button', { name: 'Buka Kembali' }));

        expect(form.posts).toHaveLength(1);
        expect(form.posts[0].url).toBe(`/verifikasi/${dasar.id}/buka-kembali`);
        expect(form.posts[0].data).toEqual({ versi: 4, alasan: 'Koreksi resmi nilai TW I.' });
    });

    it('validasi server field alasan tampil sebagai alert dan dialog tetap terbuka', async () => {
        const user = userEvent.setup();
        const { rerender } = render(tampilan());

        await user.click(tombolPemicu());
        await user.type(within(dialogBukaKembali()).getByRole('textbox', { name: /Alasan perubahan/ }), 'Koreksi resmi');

        form.errors = { alasan: 'Alasan wajib diisi minimal 10 karakter.' };
        rerender(tampilan());

        expect(screen.getByRole('alert').textContent).toBe('Alasan wajib diisi minimal 10 karakter.');
        expect(screen.getByRole<HTMLTextAreaElement>('textbox').value).toBe('Koreksi resmi');
        expect(dialogBukaKembali()).toBeTruthy();
    });

    it('konflik versi mengunci submit, menampilkan alert, dan menawarkan muat ulang', async () => {
        const user = userEvent.setup();
        render(tampilan());

        await user.click(tombolPemicu());
        await user.type(within(dialogBukaKembali()).getByRole('textbox', { name: /Alasan perubahan/ }), 'Koreksi resmi');
        await user.click(within(dialogBukaKembali()).getByRole('button', { name: 'Buka Kembali' }));

        const galatVersi = 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.';
        form.errors = { versi: galatVersi };
        const onError = form.posts[0].options.onError as (errors: Record<string, string>) => void;
        act(() => { onError({ versi: galatVersi }); });

        const dialog = dialogBukaKembali();
        expect(within(dialog).getByRole('alert').textContent).toContain(galatVersi);
        expect(within(dialog).getByRole('button', { name: 'Muat ulang halaman' })).toBeTruthy();
        expect(within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Buka Kembali' }).disabled).toBe(true);
    });

    it('alasan bisnis buka kembali tampil sebagai notice saat can.reopen false', () => {
        render(tampilan({
            can: { ...dasar.can, reopen: false },
            businessErrors: { verifikasi: [], sahkan: [], kembalikan: [], buka_kembali: [PESAN_JADWAL_TUTUP] },
        }));

        const notice = screen.getByRole('status');
        expect(notice.textContent).toContain('Tindakan koreksi tidak tersedia');
        expect(notice.textContent).toContain(PESAN_JADWAL_TUTUP);
        expect(screen.queryByRole('button', { name: 'Buka Kembali' })).toBeNull();
    });

    it('judul notice mengikuti status pengukuran dan visibilitas panel keputusan', () => {
        const tanpaPanel = { ...dasar.can, reopen: false };

        const { unmount } = render(tampilan({
            can: tanpaPanel,
            businessErrors: { verifikasi: [], sahkan: [], kembalikan: [], buka_kembali: [PESAN_JADWAL_TUTUP] },
        }));
        expect(screen.getByRole('status').textContent).toContain('Tindakan koreksi tidak tersedia');
        unmount();

        render(tampilan({
            businessErrors: { verifikasi: ['Verifikasi belum dapat dilakukan.'], sahkan: [], kembalikan: [], buka_kembali: [] },
        }));
        expect(screen.getByRole('status').textContent).toContain('Sebagian tindakan tidak tersedia');
        cleanup();

        render(tampilan({
            status: 'diajukan',
            can: tanpaPanel,
            businessErrors: { verifikasi: ['Verifikasi belum dapat dilakukan.'], sahkan: [], kembalikan: [], buka_kembali: [] },
        }));
        expect(screen.getByRole('status').textContent).toContain('Tindakan reviu tidak tersedia');
    });

    it('merender histori versi: nomor, jalur, tanggal, badge Resmi, dan status capaian per versi', () => {
        render(tampilan());

        const versiSatu = barisHistori(1);
        expect(versiSatu.textContent).toContain('05 Januari 2026 01:00');
        expect(versiSatu.textContent).toContain('Belum disahkan');
        expect(versiSatu.textContent).toContain('PIC');
        expect(versiSatu.textContent).toContain('Belum ditetapkan');
        expect(within(versiSatu).queryByText('Resmi')).toBeNull();

        const versiDua = barisHistori(2);
        expect(within(versiDua).getByText('Resmi')).toBeTruthy();
        expect(versiDua.textContent).toContain('04 April 2026 02:00');
        expect(versiDua.textContent).toContain('06 April 2026 03:30');
        expect(versiDua.textContent).toContain('Perencanaan');
        expect(within(versiDua).getByText('Tercapai')).toBeTruthy();
        expect(versiDua.textContent).toContain('Verifikator Uji · 01 Mei 2026 04:00');

        const versiTiga = barisHistori(3);
        expect(within(versiTiga).getByText('Belum Tercapai')).toBeTruthy();
        expect(versiTiga.textContent).toContain('Belum disahkan');
        expect(within(versiTiga).queryByText('Resmi')).toBeNull();
    });

    it('dialog dan tombol pemicu memiliki nama aksesibel', async () => {
        const user = userEvent.setup();
        render(tampilan());

        await user.click(tombolPemicu());
        const dialog = dialogBukaKembali();
        expect(within(dialog).getByRole('button', { name: 'Tutup modal alasan audit' })).toBeTruthy();
        expect(within(dialog).getByRole('textbox', { name: /Alasan perubahan/ })).toBeTruthy();
        expect(within(dialog).getByRole('button', { name: 'Batal' })).toBeTruthy();
    });
});
