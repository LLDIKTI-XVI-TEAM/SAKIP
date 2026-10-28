import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import KegiatanRealisasi from '@/Pages/RencanaAksi/KegiatanRealisasi';
import type { KegiatanEditProps } from '@/Pages/RencanaAksi/KegiatanEdit';
import type { RencanaKegiatan } from '@/types/kegiatan';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rencana-aksi/ra-1/kegiatan/kegiatan-1/realisasi', props: { auth: {}, pengaturan: {}, flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, breadcrumbs }: { children: ReactNode; breadcrumbs?: { label: string }[] }) => (
        <main>
            <nav aria-label="Breadcrumb">{breadcrumbs?.map((item) => <span key={item.label}>{item.label}</span>)}</nav>
            {children}
        </main>
    ),
}));

// jsdom belum mengimplementasikan dialog modal native yang dipakai AuditReasonModal di panel berkas.
const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));

beforeEach(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => { callback(); return 0; });
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    dialogMethods.forEach((name, index) => {
        const descriptor = originalDialogMethods[index];
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    });
});

const rencana: RencanaKegiatan = {
    id: 'kegiatan-1', unit_id: 'unit-a', tahun: 2026, periode_id: 'periode-4', nama: 'Fasilitasi unit', tujuan: null,
    sasaran_peserta: 30, keterangan_peserta: null, lokasi: null, tanggal_rencana: '2026-10-01', anggaran: '2500000', status: 'rencana',
    version_token: 'v1', justifikasi: null, periode_nama: 'Triwulan IV', periode_tujuan: null,
    tanggal_realisasi: null, biaya_terpakai: null, realisasi_peserta: null, uraian_pelaksanaan: null, kendala: null, strategi_tindaklanjut: null,
};
const props: KegiatanEditProps = {
    rencanaAksi: { id: 'ra-1', tahun: 2026, kode: 'IKU 1.2' },
    kegiatan: rencana,
    unitOptions: [{ id: 'unit-a', nama: 'Unit A' }],
    periodeOptions: [],
    opsiPeriodeTujuan: [],
    can: { ubah_rencana: true, ubah_status: ['ditunda', 'tidak_terlaksana', 'batal'], catat_realisasi: true, tandai_terlaksana: true },
    pelaksanaan: { perlu_koreksi: false, persyaratan_kurang: [], rentang: { mulai: '2026-10-01', selesai: '2026-12-31' } },
    // Panel berkas tidak dirender tanpa persyaratan, jadi fixture memuat satu persyaratan.
    berkasPelaksanaan: { persyaratan: [{
        id: 'jb-dok', nama: 'Dokumentasi', keterangan: null, wajib: true, semua_mode_wajib: false, izinkan_file: true, izinkan_tautan: false, izinkan_teks: false,
        format_diizinkan: 'pdf', ukuran_maks_kb: 10240, hanya_kegiatan_beranggaran: false, pemenuhan: { terpenuhi: false, mode_kurang: ['file'] }, berkas: [], contoh: [],
    }], alasan_tolak_unggah: null, alasan_tolak_ganti: null, alasan_tolak_hapus: null },
};

const formRealisasi = () => screen.getByRole('form', { name: 'Catat realisasi' });

it('memuat ringkasan rencana, status, form realisasi, dan berkas pelaksanaan tanpa form rencana', () => {
    render(<KegiatanRealisasi {...props} />);

    const ringkasan = within(screen.getByRole('region', { name: 'Rencana kegiatan' }));
    expect(ringkasan.getByText('Fasilitasi unit')).toBeTruthy();
    expect(ringkasan.getByText('Triwulan IV 2026')).toBeTruthy();
    expect(ringkasan.getByText('01 Oktober 2026')).toBeTruthy();
    expect(ringkasan.getByText(/^Rp\s?2\.500\.000$/)).toBeTruthy();
    expect(ringkasan.getByText('30')).toBeTruthy();
    expect(within(screen.getByRole('region', { name: 'Status pelaksanaan' })).getAllByRole('button').map((tombol) => tombol.textContent))
        .toEqual(['Tunda', 'Tidak terlaksana', 'Batalkan']);
    expect(formRealisasi()).toBeTruthy();
    expect(screen.getByText('Berkas pelaksanaan kegiatan')).toBeTruthy();
    expect(screen.queryByRole('form', { name: 'Catat rencana kegiatan' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Simpan kegiatan' })).toBeNull();
});

it('menautkan kembali ke kegiatan rencana aksi dan memberi breadcrumb catat realisasi', () => {
    render(<KegiatanRealisasi {...props} />);

    expect(screen.getByRole('link', { name: 'Kembali ke kegiatan rencana aksi' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/kegiatan');
    const breadcrumb = within(screen.getByRole('navigation', { name: 'Breadcrumb' }));
    expect(breadcrumb.getByText('IKU 1.2 · 2026')).toBeTruthy();
    expect(breadcrumb.getByText('Catat realisasi kegiatan')).toBeTruthy();
});

it('mengirim realisasi nol tanpa field rencana', () => {
    render(<KegiatanRealisasi {...props} />);
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '0' } });
    fireEvent.change(screen.getByLabelText(/Peserta riil/), { target: { value: '0' } });
    fireEvent.submit(formRealisasi());

    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/kegiatan-1/realisasi');
    expect(data).toMatchObject({ biaya_terpakai: '0', realisasi_peserta: 0, tanggal_realisasi: null, tandai_terlaksana: false, expected_state: 'v1' });
    expect(data).not.toHaveProperty('anggaran');
    expect(data).not.toHaveProperty('nama');
});

it('tandai terlaksana mengirim intent eksplisit beserta isian dan menampilkan error bukti', async () => {
    render(<KegiatanRealisasi {...props} />);
    fireEvent.change(screen.getByLabelText(/Tanggal realisasi/), { target: { value: '2026-10-15' } });
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '1500000' } });
    fireEvent.change(screen.getByLabelText(/Peserta riil/), { target: { value: '18' } });
    const tandai = screen.getByRole('button', { name: 'Tandai terlaksana' });
    fireEvent(formRealisasi(), new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: tandai }));

    const [, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(data).toMatchObject({ tanggal_realisasi: '2026-10-15', biaya_terpakai: '1500000', realisasi_peserta: 18, tandai_terlaksana: true });
    await act(async () => { options?.onError?.({ bukti: 'Dokumentasi belum lengkap.' }); });
    expect(screen.getByText('Dokumentasi belum lengkap.')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).value).toBe('1500000');
});

it('merender penanda koreksi dan mengikuti capability server pada status final', () => {
    render(<KegiatanRealisasi {...props} kegiatan={{ ...rencana, status: 'terlaksana', biaya_terpakai: '0', realisasi_peserta: 18, tanggal_realisasi: '2026-10-15' }}
        can={{ ...props.can, ubah_rencana: false, ubah_status: [], tandai_terlaksana: false }}
        pelaksanaan={{ ...props.pelaksanaan, perlu_koreksi: true, persyaratan_kurang: ['Surat tugas'] }} />);

    expect(screen.getByText('Perlu koreksi bukti pelaksanaan')).toBeTruthy();
    expect(screen.getByText('Surat tugas')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Tandai terlaksana' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Simpan realisasi' })).toBeTruthy();
    expect(within(screen.getByRole('region', { name: 'Status pelaksanaan' })).getByText('Terlaksana')).toBeTruthy();
});

it('menjaga draf realisasi saat props berubah dan token basi tetap terdeteksi', () => {
    const { rerender } = render(<KegiatanRealisasi {...props} />);
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '99000' } });
    rerender(<KegiatanRealisasi {...props} kegiatan={{ ...rencana, version_token: 'v2' }} />);

    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).value).toBe('99000');
    fireEvent.submit(formRealisasi());
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ expected_state: 'v1' });
});

it('form realisasi yang belum diedit memakai token terbaru setelah status berubah', () => {
    const { rerender } = render(<KegiatanRealisasi {...props} />);
    rerender(<KegiatanRealisasi {...props} kegiatan={{ ...rencana, version_token: 'v2' }} />);
    fireEvent.submit(formRealisasi());
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ expected_state: 'v2' });
});

it('sukses realisasi memperbarui baseline token dan form dapat dikirim ulang', async () => {
    const { rerender } = render(<KegiatanRealisasi {...props} />);
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '0' } });
    fireEvent.submit(formRealisasi());
    const [, , options] = vi.mocked(router.post).mock.calls[0];
    expect(options?.preserveState).toBe(true);
    await act(async () => {
        await options?.onSuccess?.({ props: { kegiatan: { ...rencana, biaya_terpakai: '0', version_token: 'v2' } } } as never);
        rerender(<KegiatanRealisasi {...props} kegiatan={{ ...rencana, biaya_terpakai: '0', version_token: 'v2' }} />);
        options?.onFinish?.({} as never);
    });

    fireEvent.submit(formRealisasi());
    expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ expected_state: 'v2', biaya_terpakai: '0' });
});

it('tautan muat ulang setelah koneksi putus kembali ke halaman realisasi', async () => {
    render(<KegiatanRealisasi {...props} />);
    fireEvent.submit(formRealisasi());
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onNetworkError?.({} as never); });

    expect(screen.getByRole('link', { name: 'Muat ulang form realisasi' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/kegiatan/kegiatan-1/realisasi');
});

it('tanpa capability realisasi hanya menampilkan nilai server tanpa tombol mutasi', () => {
    render(<KegiatanRealisasi {...props} can={{ ...props.can, catat_realisasi: false, tandai_terlaksana: false }} kegiatan={{ ...rencana, biaya_terpakai: '5000' }} />);

    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).value).toBe('5000');
    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).matches(':disabled')).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan realisasi' })).toBeNull();
});
