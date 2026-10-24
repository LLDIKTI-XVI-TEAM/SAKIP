import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Form from '@/Pages/Kegiatan/Form';
import Index from '@/Pages/Kegiatan/Index';
import type { KegiatanFormProps, KegiatanIndexItem, KegiatanIndexProps, StatusTujuan } from '@/types/kegiatan';

let mockPengaturan: Record<string, string> = {};

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { kegiatan: true } }, pengaturan: mockPengaturan, flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

const unitOptions = [{ id: 'unit-a', nama: 'Unit A' }];
const periodeOptions = [{ id: 'periode-4', nama: 'Triwulan IV' }];
const rencana = { id: 'kegiatan-1', unit_id: 'unit-a', tahun: 2026, periode_id: 'periode-4', nama: '', tujuan: null, sasaran_peserta: null, keterangan_peserta: null, lokasi: null, tanggal_rencana: null, anggaran: null, status: 'rencana', version_token: 'v1', justifikasi: null, periode_nama: 'Triwulan IV', periode_tujuan: null };
const props: KegiatanFormProps = { kegiatan: rencana, unitOptions, periodeOptions, opsiPeriodeTujuan: [], can: { ubah_rencana: true, ubah_status: [] } };
const tigaStatus: StatusTujuan[] = ['ditunda', 'tidak_terlaksana', 'batal'];
const opsiPeriodeTujuan = [{ id: 'periode-tambahan', nama: 'Periode Tambahan' }];
const formRencana: KegiatanFormProps = { ...props, opsiPeriodeTujuan, can: { ubah_rencana: true, ubah_status: tigaStatus } };

beforeEach(() => {
    mockPengaturan = {};
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => { callback(); return 0; });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

const klik = (nama: string) => fireEvent.click(screen.getByRole('button', { name: nama }));
const isiJustifikasi = (teks: string) => fireEvent.change(screen.getByLabelText(/Justifikasi/), { target: { value: teks } });
const sukses = async () => {
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { await options?.onSuccess?.({ props: { errors: {} } } as never); });
};

it('mengirim field rencana beserta anggaran rupiah melalui submit keyboard tanpa status', () => {
    render(<Form {...props} />);
    expect(within(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!).queryByLabelText(/^status/i)).toBeNull();
    fireEvent.change(screen.getByLabelText(/Anggaran/), { target: { value: '5000000' } });
    expect(screen.getByText(/Rp\s5\.000\.000/)).toBeTruthy();
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Fasilitasi unit' } });
    fireEvent.change(screen.getByLabelText(/Tujuan/), { target: { value: 'Meningkatkan layanan' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/kegiatan-1');
    expect(data).toMatchObject({ unit_id: 'unit-a', tahun: 2026, periode_id: 'periode-4', nama: 'Fasilitasi unit', tujuan: 'Meningkatkan layanan', sasaran_peserta: null, expected_state: 'v1', anggaran: '5000000' });
    expect(data).not.toHaveProperty('status');
});

it('menampilkan error server yang terhubung ke field dan mempertahankan koreksi pengguna', async () => {
    render(<Form {...props} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Nama yang dikoreksi' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onError?.({ tujuan: 'Tujuan wajib diisi.' }); });
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Nama yang dikoreksi');
    const tujuan = screen.getByLabelText(/Tujuan/);
    expect(tujuan.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(tujuan.getAttribute('aria-describedby') ?? '')?.textContent).toContain('Tujuan wajib diisi.');
});

it('tidak menawarkan edit atau hapus ketika server tidak memberi capability', () => {
    render(<Index
        kegiatan={{ data: [{ id: 'kegiatan-1', nama: 'Kegiatan pembaca', tujuan: 'Layanan unit', unit: { id: 'unit-a', nama: 'Unit A' }, periode: { id: 'periode-4', nama: 'Triwulan IV' }, tahun: 2026, status: 'rencana', tanggal_rencana: null, version_token: 'v1', can: { update: false, ubah_rencana: false, ubah_status: [] }, opsi_periode_tujuan: [] }], links: [], from: 1, to: 1, total: 1 }}
        filters={{ tahun: '', periode_id: '', unit_id: '', status: '' }} unitOptions={unitOptions} periodeOptions={periodeOptions} can={{ create: false }}
    />);
    expect(screen.getByText('Kegiatan pembaca')).toBeTruthy();
    expect(screen.queryByRole('button', { name: /Buat kegiatan/i })).toBeNull();
    expect(screen.queryByRole('button', { name: /Edit kegiatan/i })).toBeNull();
    expect(screen.queryByText(/Hapus/)).toBeNull();
});

it('mempertahankan input dan menawarkan pemulihan sesi tanpa replay otomatis', async () => {
    render(<Form {...props} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Rencana belum dikonfirmasi' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onHttpException?.({ status: 401, data: { recovery: { reason: 'authentication_required', rejected: { method: 'POST', path: '/kegiatan/kegiatan-1', before_action: true } } }, headers: {} }); });
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Rencana belum dikonfirmasi');
    expect(screen.getByRole('link', { name: 'Masuk ulang' }).getAttribute('href')).toBe('/login?recovery=1');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan kegiatan' }).disabled).toBe(true);
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('menghubungkan error unit dan periode ke pesan terlihat tanpa duplikasi', async () => {
    render(<Form {...props} />);
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onError?.({ unit_id: 'Unit sudah nonaktif.', periode_id: 'Periode sudah nonaktif.' }); });

    for (const [label, message] of [['Unit', 'Unit sudah nonaktif.'], ['Periode', 'Periode sudah nonaktif.']]) {
        const field = screen.getByLabelText(new RegExp(`^${label}`));
        const description = document.getElementById(field.getAttribute('aria-describedby') ?? '');
        expect(field.getAttribute('aria-invalid')).toBe('true');
        expect(description?.getAttribute('role')).toBe('alert');
        expect(description?.textContent).toBe(message);
        expect(screen.getAllByText(message)).toHaveLength(1);
    }
});

const baris: KegiatanIndexItem = { id: 'kegiatan-1', unit_id: 'unit-a', periode_id: 'periode-4', nama: 'Fasilitasi unit', tujuan: 'Meningkatkan layanan', sasaran_peserta: null, keterangan_peserta: null, lokasi: null, unit: unitOptions[0], periode: periodeOptions[0], tahun: 2026, status: 'rencana', tanggal_rencana: '2026-10-11', version_token: 'token-1', can: { update: true, ubah_rencana: true, ubah_status: tigaStatus }, opsi_periode_tujuan: opsiPeriodeTujuan };
const daftar = (data: KegiatanIndexItem[]) => ({ data, links: [], from: data.length ? 1 : null, to: data.length || null, total: data.length });
const indexProps: KegiatanIndexProps = {
    kegiatan: daftar([baris]),
    filters: { tahun: '', periode_id: '', unit_id: '', status: '' },
    unitOptions, periodeOptions, can: { create: true },
};

it('menampilkan tanggal rencana sesuai preferensi tampilan pada tabel berlabel', () => {
    mockPengaturan = { 'tampilan.format_tanggal': 'd/m/Y', 'tampilan.zona_waktu': 'Asia/Makassar' };
    render(<Index {...indexProps} />);
    expect(screen.getByRole('table', { name: 'Daftar kegiatan' })).toBeTruthy();
    expect(screen.getByText('11/10/2026')).toBeTruthy();
    expect(screen.queryByText('2026-10-11')).toBeNull();
});

it('menyediakan buat lewat modal pop-up dan ubah lewat halaman ubah sesuai capability', () => {
    render(<Index {...indexProps} />);
    const createBtn = screen.getByRole('button', { name: 'Buat kegiatan' });
    expect(createBtn).toBeTruthy();
    fireEvent.click(createBtn);
    expect(screen.getByRole('dialog', { name: 'Buat rencana kegiatan' })).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));

    klik('Aksi kegiatan Fasilitasi unit');
    expect(screen.getByRole('menuitem', { name: 'Ubah rencana kegiatan dan klaim rencana aksi Fasilitasi unit' }).getAttribute('href')).toBe('/kegiatan/kegiatan-1/edit');
    expect(screen.getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['Ubah', 'Tunda', 'Tidak terlaksana', 'Batalkan']);
});
it('menginisialisasi filter tahun dengan nilai default serta menyediakan placeholder', () => {
    const getSpy = vi.spyOn(router, 'get').mockImplementation(() => undefined);
    render(<Index {...indexProps} tahunDefault={2026} />);
    const inputTahun = screen.getByLabelText<HTMLInputElement>('Tahun');
    expect(inputTahun.value).toBe('2026');
    expect(inputTahun.placeholder).toBe('Contoh: 2026');

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));
    expect(getSpy).toHaveBeenCalledWith('/kegiatan', { tahun: 2026 }, expect.any(Object));
});

it('menyinkronkan input filter dengan filters server setelah redirect simpan yang mempertahankan state', () => {
    const daftar = { data: [], links: [], from: null, to: null, total: 0 };
    const { rerender } = render(<Index kegiatan={daftar} filters={{ tahun: '2026', unit_id: '', periode_id: '', status: '' }} unitOptions={unitOptions} periodeOptions={periodeOptions} tahunDefault={2026} can={{ create: false }} />);
    // Mutasi Inertia default preserveState: komponen tidak di-mount ulang, hanya props yang berubah.
    rerender(<Index kegiatan={daftar} filters={{ tahun: '2027', unit_id: '', periode_id: '', status: '' }} unitOptions={unitOptions} periodeOptions={periodeOptions} tahunDefault={2026} can={{ create: false }} />);
    expect(screen.getByLabelText<HTMLInputElement>('Tahun').value).toBe('2027');
});

it('menjelaskan daftar kosong mengikuti unit yang dapat diakses', () => {
    render(<Index {...indexProps} kegiatan={{ data: [], links: [], from: null, to: null, total: 0 }} />);
    expect(screen.getByText('Belum ada rencana kegiatan pada unit yang dapat Anda akses.')).toBeTruthy();
});

it('menonaktifkan tooltip bawaan unit dengan durasi dipercepat', () => {
    render(<Index {...indexProps} />);
    const unitCell = screen.getByRole('cell', { name: 'Unit A' });
    const scrollContainer = unitCell.querySelector('.hover-scroll-text');
    expect(scrollContainer?.getAttribute('title')).toBeNull();
    expect(scrollContainer?.getAttribute('style')).toContain('--hover-scroll-duration: 0.8s');
});

it('menu baris final hanya berisi Buka', () => {
    render(<Index {...indexProps} kegiatan={daftar([{ ...baris, status: 'batal', can: { update: true, ubah_rencana: false, ubah_status: [] }, opsi_periode_tujuan: [] }])} />);
    klik('Aksi kegiatan Fasilitasi unit');
    expect(screen.getByRole('menuitem', { name: 'Buka kegiatan Fasilitasi unit' }).getAttribute('href')).toBe('/kegiatan/kegiatan-1/edit');
    expect(screen.getAllByRole('menuitem')).toHaveLength(1);
});

it('tanpa capability tidak merender menu', () => {
    render(<Index {...indexProps} kegiatan={daftar([{ ...baris, can: { update: false, ubah_rencana: false, ubah_status: [] }, opsi_periode_tujuan: [] }])} />);
    expect(screen.queryByRole('button', { name: /^Aksi kegiatan/ })).toBeNull();
});

it('tidak membawa isian antarbaris dan mengirim ke kegiatan yang dipilih', () => {
    render(<Index {...indexProps} kegiatan={daftar([baris, { ...baris, id: 'kegiatan-2', nama: 'Rapat evaluasi', version_token: 'token-2' }])} />);
    klik('Aksi kegiatan Fasilitasi unit');
    fireEvent.click(screen.getByRole('menuitem', { name: 'Tunda' }));
    isiJustifikasi('Draf baris A');
    klik('Tutup');
    expect(screen.queryByRole('dialog')).toBeNull();

    klik('Aksi kegiatan Rapat evaluasi');
    fireEvent.click(screen.getByRole('menuitem', { name: 'Batalkan' }));
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Justifikasi/).value).toBe('');
    isiJustifikasi('Alasan baris B');
    klik('Lanjutkan');
    klik('Ya, batalkan');
    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/kegiatan-2/status');
    expect(data).toMatchObject({ status: 'batal', justifikasi: 'Alasan baris B', expected_state: 'token-2' });
});

it('aksi kedua setelah sukses memakai status dan token baru', async () => {
    const { rerender } = render(<Form {...formRencana} />);
    screen.getByRole('button', { name: 'Tidak terlaksana' }).focus();
    klik('Tidak terlaksana');
    expect(screen.getByRole('dialog', { name: 'Tandai tidak terlaksana' })).toBeTruthy();
    isiJustifikasi('Anggaran diblokir.');
    klik('Simpan');

    // Urutan Inertia: props halaman diganti sebelum onSuccess dipanggil.
    rerender(<Form {...formRencana} kegiatan={{ ...rencana, status: 'tidak_terlaksana', justifikasi: 'Anggaran diblokir.', version_token: 'v2' }} can={{ ubah_rencana: false, ubah_status: ['ditunda', 'batal'] }} />);
    await sukses();
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(screen.getByText('Status pelaksanaan'));

    klik('Batalkan');
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Justifikasi/).value).toBe('');
    isiJustifikasi('Anggaran dialihkan.');
    klik('Lanjutkan');
    klik('Ya, batalkan');
    expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ status: 'batal', justifikasi: 'Anggaran dialihkan.', expected_state: 'v2' });
});

it('form terkunci menampilkan nilai server, bukan draf', async () => {
    const awal = { ...rencana, nama: 'Rencana awal', anggaran: '5000000' };
    const { rerender } = render(<Form {...props} kegiatan={awal} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draf belum disimpan' } });
    fireEvent.change(screen.getByLabelText(/Anggaran/), { target: { value: '7500000' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onNetworkError?.({} as never); });
    expect(screen.getByText(/Koneksi terputus/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Muat ulang form' })).toBeTruthy();

    rerender(<Form {...props} kegiatan={{ ...awal, status: 'tidak_terlaksana', version_token: 'v2' }} can={{ ubah_rencana: false, ubah_status: ['ditunda', 'batal'] }} />);
    const nama = screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/);
    expect(nama.value).toBe('Rencana awal');
    expect(nama.matches(':disabled')).toBe(true);
    expect(screen.getByLabelText<HTMLInputElement>(/Anggaran/).value).toBe('5000000');
    expect(screen.getByText(/5\.000\.000/)).toBeTruthy();
    expect(screen.queryByText(/7\.500\.000/)).toBeNull();
    expect(screen.queryByText(/Koneksi terputus/)).toBeNull();
    expect(screen.queryByRole('link', { name: 'Muat ulang form' })).toBeNull();
});

it.each([true, false])('fokus pindah ke judul kartu status setelah transisi final (pemicu difokus: %s)', async (difokus) => {
    const { rerender } = render(<Form {...formRencana} />);
    const batalkan = screen.getByRole('button', { name: 'Batalkan' });
    if (difokus) batalkan.focus();
    else expect(document.activeElement).toBe(document.body);
    fireEvent.click(batalkan);
    isiJustifikasi('Kebijakan berubah.');
    klik('Lanjutkan');
    klik('Ya, batalkan');

    rerender(<Form {...formRencana} kegiatan={{ ...rencana, status: 'batal', justifikasi: 'Kebijakan berubah.', version_token: 'v2' }} can={{ ubah_rencana: false, ubah_status: [] }} />);
    await sukses();
    expect(document.activeElement).toBe(screen.getByText('Status pelaksanaan'));
});

it('fokus pindah ke daftar bila baris hilang dari filter', async () => {
    const filters = { ...indexProps.filters, status: 'rencana' };
    const { rerender } = render(<Index {...indexProps} filters={filters} />);
    const pemicu = screen.getByRole('button', { name: 'Aksi kegiatan Fasilitasi unit' });
    pemicu.focus();
    fireEvent.click(pemicu);
    fireEvent.click(screen.getByRole('menuitem', { name: 'Batalkan' }));
    isiJustifikasi('Kebijakan berubah.');
    klik('Lanjutkan');
    klik('Ya, batalkan');

    rerender(<Index {...indexProps} filters={filters} kegiatan={daftar([])} />);
    await sukses();
    expect(document.activeElement).toBe(screen.getByRole('region', { name: 'Daftar kegiatan' }));
});

it('form status final terkunci dengan justifikasi baca-saja', () => {
    render(<Form {...props} kegiatan={{ ...rencana, status: 'ditunda', justifikasi: 'Narasumber berhalangan.', periode_nama: 'Triwulan I', periode_tujuan: 'Triwulan II' }} can={{ ubah_rencana: false, ubah_status: [] }} />);
    const kartu = within(screen.getByRole('region', { name: 'Status pelaksanaan' }));
    expect(kartu.getByText('Ditunda')).toBeTruthy();
    expect(kartu.getByText('Periode tujuan')).toBeTruthy();
    expect(kartu.getByText('Triwulan II 2026')).toBeTruthy();
    expect(kartu.getByText('Narasumber berhalangan.')).toBeTruthy();
    expect(kartu.queryByRole('button')).toBeNull();
    expect(screen.getByText('Data rencana terkunci karena status sudah berubah.')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).closest('fieldset')!.disabled).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan kegiatan' })).toBeNull();
});

it('form rencana menawarkan aksi status dari server', () => {
    render(<Form {...formRencana} />);
    const kartu = within(screen.getByRole('region', { name: 'Status pelaksanaan' }));
    expect(kartu.getAllByRole('button').map((tombol) => tombol.textContent)).toEqual(['Tunda', 'Tidak terlaksana', 'Batalkan']);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).closest('fieldset')!.disabled).toBe(false);
    expect(screen.queryByText('Data rencana terkunci karena status sudah berubah.')).toBeNull();
});
