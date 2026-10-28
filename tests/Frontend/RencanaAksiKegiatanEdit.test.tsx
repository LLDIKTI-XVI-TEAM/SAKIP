import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import KegiatanEdit from '@/Pages/RencanaAksi/KegiatanEdit';
import type { KegiatanEditProps } from '@/Pages/RencanaAksi/KegiatanEdit';
import type { RencanaKegiatan, StatusTujuan } from '@/types/kegiatan';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rencana-aksi/ra-1/kegiatan/kegiatan-1', props: { auth: {}, pengaturan: {}, flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, breadcrumbs }: { children: ReactNode; breadcrumbs?: { label: string }[] }) => (
        <main>
            <nav aria-label="Breadcrumb">{breadcrumbs?.map((item) => <span key={item.label}>{item.label}</span>)}</nav>
            {children}
        </main>
    ),
}));

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => { callback(); return 0; });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

const unitOptions = [{ id: 'unit-a', nama: 'Unit A' }];
const periodeOptions = [{ id: 'periode-4', nama: 'Triwulan IV', tanggal: { bulan_mulai: 10, hari_mulai: 1, bulan_selesai: 12, hari_selesai: 31 } }];
const rencana: RencanaKegiatan = {
    id: 'kegiatan-1', unit_id: 'unit-a', tahun: 2026, periode_id: 'periode-4', nama: 'Fasilitasi unit', tujuan: null,
    sasaran_peserta: null, keterangan_peserta: null, lokasi: null, tanggal_rencana: null, anggaran: null, status: 'rencana',
    version_token: 'v1', justifikasi: null, periode_nama: 'Triwulan IV', periode_tujuan: null,
    tanggal_realisasi: null, biaya_terpakai: null, realisasi_peserta: null, uraian_pelaksanaan: null, kendala: null, strategi_tindaklanjut: null,
};
const tigaStatus: StatusTujuan[] = ['ditunda', 'tidak_terlaksana', 'batal'];
const props: KegiatanEditProps = {
    rencanaAksi: { id: 'ra-1', tahun: 2026 },
    kegiatan: rencana,
    unitOptions,
    periodeOptions,
    opsiPeriodeTujuan: [],
    can: { ubah_rencana: true, ubah_status: tigaStatus, catat_realisasi: true, tandai_terlaksana: true },
    pelaksanaan: { perlu_koreksi: false, persyaratan_kurang: [], rentang: { mulai: '2026-10-01', selesai: '2026-12-31' } },
};

const kirim = () => fireEvent.submit(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);

it('menyediakan form realisasi terpisah dan mengirim nol tanpa mengubah rencana', () => {
    render(<KegiatanEdit {...props} />);
    const simpan = screen.getByRole('button', { name: 'Simpan realisasi' });
    expect(simpan.closest('form')).not.toBe(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form'));
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '0' } });
    fireEvent.change(screen.getByLabelText(/Peserta riil/), { target: { value: '0' } });
    fireEvent.submit(simpan.closest('form')!);
    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/kegiatan-1/realisasi');
    expect(data).toMatchObject({ biaya_terpakai: '0', realisasi_peserta: 0, tanggal_realisasi: null, tandai_terlaksana: false, expected_state: 'v1' });
    expect(data).not.toHaveProperty('anggaran');
    expect(data).not.toHaveProperty('nama');
});

it('tandai terlaksana mengirim intent eksplisit beserta isian dan menampilkan error bukti', async () => {
    render(<KegiatanEdit {...props} />);
    fireEvent.change(screen.getByLabelText(/Tanggal realisasi/), { target: { value: '2026-10-15' } });
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '1500000' } });
    fireEvent.change(screen.getByLabelText(/Peserta riil/), { target: { value: '18' } });
    const tandai = screen.getByRole('button', { name: 'Tandai terlaksana' });
    fireEvent(tandai.closest('form')!, new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: tandai }));
    const [, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(data).toMatchObject({ tanggal_realisasi: '2026-10-15', biaya_terpakai: '1500000', realisasi_peserta: 18, tandai_terlaksana: true });
    await act(async () => { options?.onError?.({ bukti: 'Dokumentasi belum lengkap.' }); });
    expect(screen.getByText('Dokumentasi belum lengkap.')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).value).toBe('1500000');
});

it('merender penanda koreksi dan mengikuti capability server pada status final', () => {
    render(<KegiatanEdit {...props} kegiatan={{ ...rencana, status: 'terlaksana', biaya_terpakai: '0', realisasi_peserta: 18, tanggal_realisasi: '2026-10-15' }}
        can={{ ...props.can, ubah_rencana: false, ubah_status: [], tandai_terlaksana: false }}
        pelaksanaan={{ ...props.pelaksanaan, perlu_koreksi: true, persyaratan_kurang: ['Surat tugas'] }} />);
    expect(screen.getByText('Perlu koreksi bukti pelaksanaan')).toBeTruthy();
    expect(screen.getByText('Surat tugas')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Tandai terlaksana' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Simpan realisasi' })).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).matches(':disabled')).toBe(true);
});

it('menjaga draf realisasi saat props berubah dari form lain dan token basi tetap terdeteksi', () => {
    const { rerender } = render(<KegiatanEdit {...props} />);
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '99000' } });
    rerender(<KegiatanEdit {...props} kegiatan={{ ...rencana, version_token: 'v2' }} />);
    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).value).toBe('99000');
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan realisasi' }).closest('form')!);
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ expected_state: 'v1' });
});

it('form realisasi yang belum diedit memakai token terbaru setelah rencana disimpan', () => {
    const { rerender } = render(<KegiatanEdit {...props} />);
    rerender(<KegiatanEdit {...props} kegiatan={{ ...rencana, version_token: 'v2' }} />);
    fireEvent.submit(screen.getByRole('form', { name: 'Catat realisasi' }));
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ expected_state: 'v2' });
});

it('form rencana yang belum diedit memakai token terbaru setelah realisasi disimpan', () => {
    const { rerender } = render(<KegiatanEdit {...props} />);
    rerender(<KegiatanEdit {...props} kegiatan={{ ...rencana, version_token: 'v2', biaya_terpakai: '0' }} />);
    kirim();
    expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ expected_state: 'v2' });
});

it('simpan rencana mempertahankan draf realisasi dan menyegarkan hanya form yang berhasil disimpan', async () => {
    const { rerender } = render(<KegiatanEdit {...props} />);
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '99000' } });
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Nama tersimpan' } });
    kirim();
    const [, , options] = vi.mocked(router.post).mock.calls[0];
    expect(options?.preserveState).toBe(true);
    await act(async () => { await options?.onSuccess?.({ props: { kegiatan: { ...rencana, nama: 'Nama tersimpan', version_token: 'v2' } } } as never); rerender(<KegiatanEdit {...props} kegiatan={{ ...rencana, nama: 'Nama tersimpan', version_token: 'v2' }} />); options?.onFinish?.({} as never); });
    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).value).toBe('99000');
    kirim();
    expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ expected_state: 'v2', nama: 'Nama tersimpan' });
});

it('sukses realisasi memperbarui baseline token tanpa menghapus draf rencana', async () => {
    const { rerender } = render(<KegiatanEdit {...props} />);
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draf rencana lain' } });
    fireEvent.change(screen.getByLabelText(/Biaya terpakai/), { target: { value: '0' } });
    const form = screen.getByRole('form', { name: 'Catat realisasi' });
    fireEvent.submit(form);
    const [, , options] = vi.mocked(router.post).mock.calls[0];
    await act(async () => { await options?.onSuccess?.({ props: { kegiatan: { ...rencana, biaya_terpakai: '0', version_token: 'v2' } } } as never); rerender(<KegiatanEdit {...props} kegiatan={{ ...rencana, biaya_terpakai: '0', version_token: 'v2' }} />); options?.onFinish?.({} as never); });
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Draf rencana lain');
    fireEvent.submit(form);
    expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ expected_state: 'v2', biaya_terpakai: '0' });
});

it('tanpa capability realisasi hanya menampilkan nilai server tanpa tombol mutasi', () => {
    render(<KegiatanEdit {...props} can={{ ...props.can, catat_realisasi: false, tandai_terlaksana: false }} kegiatan={{ ...rencana, biaya_terpakai: '5000' }} />);
    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).value).toBe('5000');
    expect(screen.getByLabelText<HTMLInputElement>(/Biaya terpakai/).matches(':disabled')).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan realisasi' })).toBeNull();
});

it('merender halaman ubah dengan tautan kembali ke halaman kegiatan rencana aksi', () => {
    render(<KegiatanEdit {...props} />);

    expect(screen.getByText('Status pelaksanaan')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Fasilitasi unit');
    expect(screen.getByRole('link', { name: 'Kembali ke kegiatan rencana aksi' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/kegiatan');
});

it('breadcrumb memakai kode indikator bila tersedia dan jatuh ke tahun bila tidak', () => {
    const { rerender } = render(<KegiatanEdit {...props} rencanaAksi={{ id: 'ra-1', tahun: 2026 }} />);

    const breadcrumb = () => within(screen.getByRole('navigation', { name: 'Breadcrumb' }));
    // Payload tanpa kode (bentuk lama) tetap terbaca sebagai tahun.
    expect(breadcrumb().getByText('Tahun 2026')).toBeTruthy();

    rerender(<KegiatanEdit {...props} rencanaAksi={{ id: 'ra-1', tahun: 2026, kode: 'IKU 1.3' }} />);

    expect(breadcrumb().getByText('IKU 1.3 · 2026')).toBeTruthy();
    expect(breadcrumb().queryByText('Tahun 2026')).toBeNull();
});

it('mengirim field rencana beserta anggaran rupiah tanpa status', () => {
    render(<KegiatanEdit {...props} />);

    expect(within(screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!).queryByLabelText(/^status/i)).toBeNull();
    fireEvent.change(screen.getByLabelText(/Anggaran/), { target: { value: '5000000' } });
    expect(screen.getByText(/Rp\s5\.000\.000/)).toBeTruthy();
    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Rapat koordinasi' } });
    fireEvent.change(screen.getByLabelText(/Tujuan/), { target: { value: 'Meningkatkan layanan' } });
    kirim();

    const [url, data] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/kegiatan-1');
    expect(data).toMatchObject({
        unit_id: 'unit-a', tahun: 2026, periode_id: 'periode-4', nama: 'Rapat koordinasi', tujuan: 'Meningkatkan layanan',
        sasaran_peserta: null, expected_state: 'v1', anggaran: '5000000',
    });
    expect(data).not.toHaveProperty('status');
});

it('mempertahankan state halaman agar draf kedua form tidak dibuang', () => {
    render(<KegiatanEdit {...props} />);

    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Rapat koordinasi' } });
    kirim();

    const [url, , options] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/kegiatan-1');
    expect(options?.preserveState).toBe(true);
});

it('mempertahankan draf dan galat expected_state saat server mengirim token versi baru', async () => {
    const awal: RencanaKegiatan = { ...rencana, nama: 'Rencana awal', tujuan: 'Tujuan awal', version_token: 'v1' };
    const { rerender } = render(<KegiatanEdit {...props} kegiatan={awal} />);
    const pesanKonflik = 'Data sudah berubah. Muat ulang form sebelum menyimpan lagi.';

    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draf belum disimpan' } });
    kirim();
    const [, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(data).toMatchObject({ nama: 'Draf belum disimpan', expected_state: 'v1' });

    await act(async () => { options?.onError?.({ expected_state: pesanKonflik }); });
    expect(screen.getByText(pesanKonflik)).toBeTruthy();

    // Konflik versi: server mengirim ulang halaman dengan token baru, bukan isian pengguna.
    rerender(<KegiatanEdit {...props} kegiatan={{ ...awal, nama: 'Nama hasil bentrok', version_token: 'v2' }} />);

    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Draf belum disimpan');
    expect(screen.getByText(pesanKonflik)).toBeTruthy();
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('menjalankan onSuccess dan onFinish setelah simpan berhasil tanpa field rencana_aksi_id', async () => {
    const { rerender } = render(<KegiatanEdit {...props} rencanaAksi={{ id: 'ra-7', tahun: 2026 }} />);

    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Nama hasil koreksi' } });
    kirim();

    const [url, data, options] = vi.mocked(router.post).mock.calls[0];
    // URL mutasi lama dipertahankan; konteks RA tidak lagi menumpang di payload.
    expect(url).toBe('/kegiatan/kegiatan-1');
    expect(data).toMatchObject({
        nama: 'Nama hasil koreksi', unit_id: 'unit-a', periode_id: 'periode-4', tahun: 2026, expected_state: 'v1',
    });
    expect(data).not.toHaveProperty('rencana_aksi_id');
    expect(data).not.toHaveProperty('status');
    expect(typeof options?.onSuccess).toBe('function');
    expect(typeof options?.onFinish).toBe('function');

    await act(async () => {
        await options?.onSuccess?.({ props: { kegiatan: { ...rencana, nama: 'Nama hasil koreksi', version_token: 'v2' } } } as never);
        rerender(<KegiatanEdit {...props} rencanaAksi={{ id: 'ra-7', tahun: 2026 }} kegiatan={{ ...rencana, nama: 'Nama hasil koreksi', version_token: 'v2' }} />);
        options?.onFinish?.({} as never);
    });

    // Pengunci submit dilepas oleh onFinish sehingga form tetap dapat dikirim ulang setelah sukses.
    kirim();
    expect(router.post).toHaveBeenCalledTimes(2);
    expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ nama: 'Nama hasil koreksi', expected_state: 'v2' });
});

it('menampilkan galat server yang terhubung ke field dan mempertahankan koreksi pengguna', async () => {
    render(<KegiatanEdit {...props} />);

    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Nama yang dikoreksi' } });
    kirim();
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ tujuan: 'Tujuan wajib diisi.' }); });

    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Nama yang dikoreksi');
    const tujuan = screen.getByLabelText(/Tujuan/);
    expect(tujuan.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(tujuan.getAttribute('aria-describedby') ?? '')?.textContent).toContain('Tujuan wajib diisi.');
});

it('menghubungkan galat unit dan periode ke pesan terlihat tanpa duplikasi', async () => {
    render(<KegiatanEdit {...props} />);

    kirim();
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ unit_id: 'Unit sudah nonaktif.', periode_id: 'Periode sudah nonaktif.' }); });

    for (const [label, message] of [['Unit', 'Unit sudah nonaktif.'], ['Periode', 'Periode sudah nonaktif.']]) {
        const field = screen.getByLabelText(new RegExp(`^${label}`));
        const description = document.getElementById(field.getAttribute('aria-describedby') ?? '');
        expect(field.getAttribute('aria-invalid')).toBe('true');
        expect(description?.getAttribute('role')).toBe('alert');
        expect(description?.textContent).toBe(message);
        expect(screen.getAllByText(message)).toHaveLength(1);
    }
});

it('mempertahankan input dan menawarkan pemulihan sesi tanpa replay otomatis', async () => {
    render(<KegiatanEdit {...props} />);

    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Rencana belum dikonfirmasi' } });
    kirim();
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => {
        options?.onHttpException?.({
            status: 401,
            data: { recovery: { reason: 'authentication_required', rejected: { method: 'POST', path: '/kegiatan/kegiatan-1', before_action: true } } },
            headers: {},
        } as never);
    });

    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Rencana belum dikonfirmasi');
    expect(screen.getByRole('link', { name: 'Masuk ulang' }).getAttribute('href')).toBe('/login?recovery=1');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan kegiatan' }).disabled).toBe(true);
    expect(router.post).toHaveBeenCalledTimes(1);
});

it('menahan pengiriman ulang setelah pembatalan dan menyediakan tautan muat ulang form', async () => {
    render(<KegiatanEdit {...props} />);

    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draft pembatalan' } });
    const form = screen.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!;
    fireEvent.submit(form);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onCancel?.(); options?.onFinish?.({} as never); });
    fireEvent.submit(form);

    expect(router.post).toHaveBeenCalledTimes(1);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Draft pembatalan');
    expect(screen.getByRole('link', { name: 'Muat ulang form' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/kegiatan/kegiatan-1');
});

it('menampilkan rentang periode pilihan sesuai tahun form', () => {
    render(<KegiatanEdit {...props} />);

    expect(screen.getByText('1 Okt–31 Des 2026')).toBeTruthy();
    fireEvent.change(screen.getByLabelText(/^Tahun/), { target: { value: '2027' } });
    expect(screen.getByText('1 Okt–31 Des 2027')).toBeTruthy();
});

it('form terkunci menampilkan nilai server, bukan draf', async () => {
    const awal: RencanaKegiatan = { ...rencana, nama: 'Rencana awal', anggaran: '5000000' };
    const { rerender } = render(<KegiatanEdit {...props} kegiatan={awal} />);

    fireEvent.change(screen.getByLabelText(/Nama kegiatan/), { target: { value: 'Draf belum disimpan' } });
    fireEvent.change(screen.getByLabelText(/Anggaran/), { target: { value: '7500000' } });
    kirim();
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onNetworkError?.({} as never); });
    expect(screen.getByText(/Koneksi terputus/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Muat ulang form' })).toBeTruthy();

    rerender(<KegiatanEdit {...props} kegiatan={{ ...awal, status: 'tidak_terlaksana', version_token: 'v2' }} can={{ ...props.can, ubah_rencana: false, ubah_status: ['ditunda', 'batal'] }} />);

    const nama = screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/);
    expect(nama.value).toBe('Rencana awal');
    expect(nama.matches(':disabled')).toBe(true);
    expect(screen.getByLabelText<HTMLInputElement>(/Anggaran/).value).toBe('5000000');
    expect(screen.queryByText(/7\.500\.000/)).toBeNull();
    expect(screen.queryByText(/Koneksi terputus/)).toBeNull();
    expect(screen.queryByRole('link', { name: 'Muat ulang form' })).toBeNull();
    expect(screen.getByText('Data rencana terkunci karena status sudah berubah.')).toBeTruthy();
});

it('menawarkan aksi status dari capability server dan mengunci form setelah status final', () => {
    const { rerender } = render(<KegiatanEdit {...props} />);

    const kartu = within(screen.getByRole('region', { name: 'Status pelaksanaan' }));
    expect(kartu.getAllByRole('button').map((tombol) => tombol.textContent)).toEqual(['Tunda', 'Tidak terlaksana', 'Batalkan']);
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).closest('fieldset')!.disabled).toBe(false);
    expect(screen.queryByText('Data rencana terkunci karena status sudah berubah.')).toBeNull();

    rerender(
        <KegiatanEdit
            {...props}
            kegiatan={{ ...rencana, status: 'ditunda', justifikasi: 'Narasumber berhalangan.', periode_tujuan: 'Triwulan II' }}
            can={{ ubah_rencana: false, ubah_status: [], catat_realisasi: false, tandai_terlaksana: false }}
        />,
    );

    const terkunci = within(screen.getByRole('region', { name: 'Status pelaksanaan' }));
    expect(terkunci.getByText('Ditunda')).toBeTruthy();
    expect(terkunci.getByText('Periode tujuan')).toBeTruthy();
    expect(terkunci.getByText('Triwulan II 2026')).toBeTruthy();
    expect(terkunci.getByText('Narasumber berhalangan.')).toBeTruthy();
    expect(terkunci.queryByRole('button')).toBeNull();
    expect(screen.getByText('Data rencana terkunci karena status sudah berubah.')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).closest('fieldset')!.disabled).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan kegiatan' })).toBeNull();
});

it('menerima nama hingga 500 karakter dan tujuan opsional', () => {
    render(<KegiatanEdit {...props} />);

    expect(screen.getByLabelText<HTMLInputElement>(/Nama kegiatan/).maxLength).toBe(500);
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Tujuan/).required).toBe(false);
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Tujuan/).value).toBe('');
});
