import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import BuktiPanel from '@/Pages/RencanaAksi/BuktiPanel';
import type { RencanaAksiBukti } from '@/Pages/RencanaAksi/types';

const inertia = vi.hoisted(() => ({
    post: vi.fn(),
    del: vi.fn(),
    state: { processing: false, errors: {} as Record<string, string> },
}));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    const React = await import('react');
    return {
        ...original,
        usePage: () => ({ props: {} }),
        // useForm berstate agar pergantian mode/persyaratan benar-benar me-render ulang.
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setDataState] = React.useState(initial);
            return {
                data,
                setData: (key: keyof T | T, value?: unknown) =>
                    typeof key === 'object' ? setDataState(key) : setDataState((prev) => ({ ...prev, [key]: value })),
                post: inertia.post,
                processing: inertia.state.processing,
                errors: inertia.state.errors,
                reset: () => setDataState(initial),
                clearErrors: vi.fn(),
            };
        },
        router: { ...original.router, delete: inertia.del },
    };
});

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));

beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});

afterAll(() => {
    dialogMethods.forEach((name, index) => {
        const descriptor = originalDialogMethods[index];
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    });
});

beforeEach(() => {
    inertia.state.processing = false;
    inertia.state.errors = {};
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

const bukti: RencanaAksiBukti = {
    persyaratan: [
        {
            id: 'jb-1',
            nama: 'Dokumen Kerangka Acuan Kerja',
            keterangan: 'KAK yang telah ditandatangani pimpinan.',
            wajib: true,
            semua_mode_wajib: false,
            izinkan_file: true,
            izinkan_tautan: true,
            izinkan_teks: true,
            format_diizinkan: 'pdf,docx',
            ukuran_maks_kb: 5120,
            pemenuhan: { terpenuhi: true, mode_terpenuhi: ['file'], mode_kurang: [], mode_dikecualikan: [], tidak_dapat_dipenuhi: false, alasan_pengecualian: null },
        },
        {
            id: 'jb-2',
            nama: 'Surat Keputusan Tim Pelaksana',
            keterangan: null,
            wajib: true,
            semua_mode_wajib: true,
            izinkan_file: true,
            izinkan_tautan: false,
            izinkan_teks: true,
            format_diizinkan: 'pdf',
            ukuran_maks_kb: 5120,
            pemenuhan: { terpenuhi: false, mode_terpenuhi: [], mode_kurang: ['file', 'teks'], mode_dikecualikan: [], tidak_dapat_dipenuhi: false, alasan_pengecualian: null },
        },
    ],
    ringkasan: { lengkap: false, total_wajib: 2, terpenuhi_wajib: 1 },
    daftar: [
        {
            id: 'bukti-1',
            jenis_berkas_id: 'jb-1',
            nama_persyaratan: 'Dokumen Kerangka Acuan Kerja',
            mode: 'file',
            nama_asli: 'kak-final.pdf',
            mime: 'application/pdf',
            ukuran_bytes: 1048576,
            tautan: null,
            isi_teks: null,
            download_url: '/rencana-aksi/ra-1/bukti/bukti-1/unduh',
            pengunggah: 'Adriel',
            created_at: '2026-03-10T10:00:00Z',
        },
    ],
    unggahan: { unggahan_aktif: true, ukuran_maks_kb: 10240, format_diizinkan: 'pdf,docx,xlsx,jpg,jpeg,png' },
    can: { upload: true, delete: true },
};

const renderPanel = (override: Partial<RencanaAksiBukti> = {}) => render(<BuktiPanel rencanaAksiId="ra-1" bukti={{ ...bukti, ...override }} />);

describe('BuktiPanel rencana aksi', () => {
    it('merender ringkasan, status tiap persyaratan, dan tautan unduh bukti file', () => {
        renderPanel();

        expect(screen.getByText(/Belum lengkap \(1\/2 wajib\)/)).toBeTruthy();
        expect(screen.getByText('Terpenuhi')).toBeTruthy();
        expect(screen.getByText('Kurang: File, Teks')).toBeTruthy();
        expect(screen.getByText('kak-final.pdf')).toBeTruthy();
        expect(screen.getByRole('link', { name: /Unduh file/ }).getAttribute('href')).toBe('/rencana-aksi/ra-1/bukti/bukti-1/unduh');
    });

    it('menyembunyikan aksi mutasi bila capability server tidak mengizinkan', () => {
        renderPanel({ can: { upload: false, delete: false } });

        expect(screen.queryByRole('button', { name: /Tambah bukti/ })).toBeNull();
        expect(screen.queryByRole('button', { name: /^Penuhi$/ })).toBeNull();
        expect(screen.queryByRole('button', { name: /Hapus bukti/ })).toBeNull();
    });

    it('membuka modal dari tombol Penuhi dengan mode yang mengikuti persyaratan', async () => {
        const user = userEvent.setup();
        renderPanel();

        await user.click(screen.getAllByRole('button', { name: /^Penuhi$/ })[1]);

        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByText('Tambah bukti dukung')).toBeTruthy();
        expect((within(dialog).getByLabelText('Persyaratan') as HTMLSelectElement).value).toBe('jb-2');
        const tautan = within(dialog).getByRole('button', { name: 'Tautan' }) as HTMLButtonElement;
        expect(tautan.disabled).toBe(true);
        expect(within(dialog).getByRole('button', { name: 'File' }).getAttribute('aria-pressed')).toBe('true');

        await user.click(within(dialog).getByRole('button', { name: 'Teks' }));
        expect(within(dialog).getByLabelText(/^Keterangan/)).toBeTruthy();
    });

    it('menonaktifkan mode file saat unggahan dimatikan dan memakai tautan sebagai bawaan', async () => {
        const user = userEvent.setup();
        renderPanel({ unggahan: { ...bukti.unggahan, unggahan_aktif: false } });

        await user.click(screen.getByRole('button', { name: /Tambah bukti/ }));

        const dialog = screen.getByRole('dialog');
        expect((within(dialog).getByRole('button', { name: 'File' }) as HTMLButtonElement).disabled).toBe(true);
        expect(within(dialog).getByRole('button', { name: 'Tautan' }).getAttribute('aria-pressed')).toBe('true');
        expect(within(dialog).getByText(/Unggahan file sedang dinonaktifkan/)).toBeTruthy();
    });

    it('menyembunyikan tombol Penuhi bila persyaratan file-only tidak dapat dipenuhi karena unggahan nonaktif', () => {
        renderPanel({
            unggahan: { ...bukti.unggahan, unggahan_aktif: false },
            persyaratan: [
                {
                    ...bukti.persyaratan[0],
                    id: 'jb-file',
                    nama: 'Scan Dokumen Asli',
                    izinkan_tautan: false,
                    izinkan_teks: false,
                    pemenuhan: { terpenuhi: true, mode_terpenuhi: [], mode_kurang: [], mode_dikecualikan: ['file'], tidak_dapat_dipenuhi: true, alasan_pengecualian: 'Unggahan file dinonaktifkan pada setelan aplikasi.' },
                },
                bukti.persyaratan[1],
            ],
        });

        expect(screen.getByText('Dikecualikan')).toBeTruthy();
        // Hanya persyaratan kedua (mengizinkan teks) yang masih menawarkan tombol Penuhi.
        expect(screen.getAllByRole('button', { name: /^Penuhi$/ })).toHaveLength(1);
    });

    it('batal menutup modal tanpa mengirim request', async () => {
        const user = userEvent.setup();
        renderPanel();

        await user.click(screen.getByRole('button', { name: /Tambah bukti/ }));
        await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Batal' }));

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(inertia.post).not.toHaveBeenCalled();
    });

    it('mengirim bukti teks sebagai form data ke route store rencana aksi', async () => {
        const user = userEvent.setup();
        renderPanel();

        await user.click(screen.getByRole('button', { name: /Tambah bukti/ }));
        const dialog = screen.getByRole('dialog');
        await user.click(within(dialog).getByRole('button', { name: 'Teks' }));
        await user.type(within(dialog).getByLabelText(/^Keterangan/), 'Keterangan bukti');
        await user.click(within(dialog).getByRole('button', { name: 'Simpan bukti' }));

        expect(inertia.post).toHaveBeenCalledTimes(1);
        expect(inertia.post.mock.calls[0][0]).toBe('/rencana-aksi/ra-1/bukti');
        expect(inertia.post.mock.calls[0][1]).toMatchObject({ forceFormData: true, preserveScroll: true });
    });

    it('menonaktifkan tombol simpan saat request berjalan dan menampilkan galat server pada field', async () => {
        const user = userEvent.setup();
        inertia.state.processing = true;
        inertia.state.errors = { file: 'Ukuran berkas melebihi batas 100 KB.' };
        renderPanel();

        await user.click(screen.getByRole('button', { name: /Tambah bukti/ }));
        const dialog = screen.getByRole('dialog');
        expect((within(dialog).getByRole('button', { name: 'Simpan bukti' }) as HTMLButtonElement).disabled).toBe(true);
        expect((within(dialog).getByRole('button', { name: 'Batal' }) as HTMLButtonElement).disabled).toBe(true);
        expect(within(dialog).getByText('Ukuran berkas melebihi batas 100 KB.')).toBeTruthy();
        expect(within(dialog).getByLabelText(/^Berkas/).getAttribute('aria-invalid')).toBe('true');
    });

    it('tidak menutup modal tambah lewat Escape, backdrop, atau tombol tutup selama unggahan berjalan', async () => {
        const user = userEvent.setup();
        inertia.state.processing = true;
        const { rerender } = renderPanel();

        await user.click(screen.getByRole('button', { name: /Tambah bukti/ }));
        const dialog = screen.getByRole('dialog', { name: 'Tambah bukti dukung' });
        await user.keyboard('{Escape}');
        await user.click(dialog);
        await user.click(within(dialog).getByRole('button', { name: 'Tutup dialog' }));
        expect(screen.getByRole('dialog', { name: 'Tambah bukti dukung' })).toBeTruthy();

        // Kontrol: setelah request selesai, Escape kembali menutup modal.
        inertia.state.processing = false;
        rerender(<BuktiPanel rencanaAksiId="ra-1" bukti={bukti} />);
        await user.keyboard('{Escape}');
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('menonaktifkan opsi persyaratan tanpa mode tersedia saat unggahan nonaktif', async () => {
        const user = userEvent.setup();
        renderPanel({
            unggahan: { ...bukti.unggahan, unggahan_aktif: false },
            persyaratan: [
                { ...bukti.persyaratan[0], id: 'jb-file', nama: 'Scan Dokumen Asli', izinkan_tautan: false, izinkan_teks: false },
                bukti.persyaratan[1],
            ],
        });

        await user.click(screen.getByRole('button', { name: /Tambah bukti/ }));
        const dialog = screen.getByRole('dialog');
        expect((within(dialog).getByRole('option', { name: /Scan Dokumen Asli/ }) as HTMLOptionElement).disabled).toBe(true);
        expect((within(dialog).getByRole('option', { name: /Surat Keputusan Tim Pelaksana/ }) as HTMLOptionElement).disabled).toBe(false);
    });

    it('menghubungkan galat field file ke input lewat aria-describedby', async () => {
        const user = userEvent.setup();
        inertia.state.errors = { file: 'Ukuran berkas melebihi batas 100 KB.' };
        renderPanel();

        await user.click(screen.getByRole('button', { name: /Tambah bukti/ }));
        const input = within(screen.getByRole('dialog')).getByLabelText(/^Berkas/);
        expect(input.getAttribute('aria-invalid')).toBe('true');
        const deskripsi = (input.getAttribute('aria-describedby') ?? '').split(' ').map((id) => document.getElementById(id)?.textContent);
        expect(deskripsi).toContain('Ukuran berkas melebihi batas 100 KB.');
    });

    it('hapus memakai modal alasan audit dan baru mengirim setelah alasan diisi', async () => {
        const user = userEvent.setup();
        renderPanel();

        await user.click(screen.getByRole('button', { name: /Hapus bukti kak-final.pdf/ }));
        expect(screen.getByText('Hapus bukti dukung')).toBeTruthy();
        const konfirmasi = screen.getByRole('button', { name: 'Hapus' }) as HTMLButtonElement;
        expect(konfirmasi.disabled).toBe(true);

        await user.type(screen.getByLabelText(/Alasan perubahan/), 'Dokumen salah unggah');
        await user.click(screen.getByRole('button', { name: 'Hapus' }));

        expect(inertia.del).toHaveBeenCalledTimes(1);
        expect(inertia.del.mock.calls[0][0]).toBe('/rencana-aksi/ra-1/bukti/bukti-1');
        expect(inertia.del.mock.calls[0][1]).toMatchObject({ data: { alasan: 'Dokumen salah unggah' }, preserveScroll: true });
    });

    it('menampilkan galat di modal hapus saat server menjawab 403 atau galat status', async () => {
        const user = userEvent.setup();
        renderPanel();

        await user.click(screen.getByRole('button', { name: /Hapus bukti kak-final.pdf/ }));
        await user.type(screen.getByLabelText(/Alasan perubahan/), 'Dokumen salah unggah');
        await user.click(screen.getByRole('button', { name: 'Hapus' }));
        const opsi = inertia.del.mock.calls[0][1];

        let ditangani: unknown;
        act(() => { ditangani = opsi.onHttpException({ status: 403 }); });
        // false mencegah modal error bawaan Inertia; pesan tampil di modal alasan.
        expect(ditangani).toBe(false);
        expect(screen.getByText(/Izin penghapusan bukti ditolak/)).toBeTruthy();

        act(() => { opsi.onError({ status_alur: 'Bukti hanya dapat diubah pada rencana aksi yang masih dapat disunting.' }); });
        expect(screen.getByText('Bukti hanya dapat diubah pada rencana aksi yang masih dapat disunting.')).toBeTruthy();
    });
});
