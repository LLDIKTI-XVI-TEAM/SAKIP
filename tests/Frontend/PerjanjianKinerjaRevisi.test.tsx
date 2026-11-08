import type { ComponentProps, ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Show from '@/Pages/PerjanjianKinerja/Show';
import type { BerkasPk, RenstraPkSummary, RevisiPk, VersiPk } from '@/types/perjanjian-kinerja';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

const pk: RenstraPkSummary = {
    id: 'pk-1', renstra_id: 'ren-1', tahun: 2026, nomor_pk: 'PK/2026/001', tanggal_pk: '2026-01-15',
    created_at: '2026-01-15T02:00:00Z', updated_at: '2026-01-15T02:00:00Z', berkas: [],
    renstra: { id: 'ren-1', kode: 'REN-2025', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029 },
    jadwal_tahunan: { id: 'jadwal-1', status: 'aktif' },
};
const berkas = (id: string, nama: string): BerkasPk => ({ id, mode: 'file', nama_asli: nama, mime: 'application/pdf', ukuran_bytes: 2048, tautan: null, isi_teks: null });
const v1: VersiPk = { id: 'v1', nomor_versi: 1, nomor_surat: null, tanggal_surat: null, periode_mulai: null, alasan: null, disetujui_oleh: null,
    disetujui_pada: null, berkas: [berkas('berkas-awal', 'pk-awal.pdf')], perubahan_target: [] };
const revisiVersi = (nomor: number, perubahan: VersiPk['perubahan_target'], tambahan: Partial<VersiPk> = {}): VersiPk => ({
    id: `v${nomor}`, nomor_versi: nomor, nomor_surat: `B-${nomor}/2026`, tanggal_surat: '2026-11-01', periode_mulai: { id: 'tw3', nama: 'Triwulan III' },
    alasan: 'Revisi sesuai surat persetujuan.', disetujui_oleh: 'Perencana Uji', disetujui_pada: '2026-11-02T03:00:00Z', berkas: [], perubahan_target: perubahan, ...tambahan,
});
const revisi: RevisiPk = {
    expected_nomor_versi: 0, expected_dasar: 'a'.repeat(64), periode_mulai: { id: 'tw3', nama: 'Triwulan III' }, dapat_ubah_target: true, alasan_tidak_dapat_ubah_target: null,
    target: [
        { indikator_id: 'ind-1', kode: 'IKU-1', nama: 'Lulusan bekerja', satuan: 'persen', nilai: '85', desimal_tampilan: 2 },
        { indikator_id: 'ind-2', kode: 'IKU-2', nama: 'Dosen tersertifikasi', satuan: 'persen', nilai: '60', desimal_tampilan: 2 },
    ],
    pengukuran_belum_disahkan: { 'ind-2': [{ periode_id: 'tw2', periode_nama: 'Triwulan II', status_alur: 'draft', nomor_versi: 1 }] },
};

type ShowProps = ComponentProps<typeof Show>;
const props = (tambahan: Partial<ShowProps> = {}): ShowProps => ({
    pk, jadwal_status: null, is_jadwal_aktif: true, versi: [v1], revisi,
    can: { update: false, delete_berkas: false, read_berkas: true, revisi: true }, ...tambahan,
});

/** Opsi yang diteruskan useForm asli ke router; callback-nya mengisi state form seperti respons server. */
const opsiKirim = (index = 0) => vi.mocked(router.post).mock.calls[index][2] as unknown as {
    onStart: (visit: unknown) => void;
    onSuccess: (page: unknown) => Promise<unknown>;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (response: { status: number; data: unknown; headers: Record<string, string> }) => boolean | void;
    onNetworkError: () => boolean | void;
    onFinish: (visit: unknown) => void;
};
const dataKirim = (index = 0) => vi.mocked(router.post).mock.calls[index][1] as Record<string, unknown>;
const tombolRevisi = () => document.getElementById('revisi-pk-tombol') as HTMLButtonElement;

const bukaPanel = async (user: ReturnType<typeof userEvent.setup>) => {
    await user.click(tombolRevisi());
    return screen.getByRole('heading', { name: /Revisi baru/ }).parentElement!.parentElement!;
};
const tambahTarget = async (user: ReturnType<typeof userEvent.setup>, indikatorId: string, nilai?: string) => {
    await user.selectOptions(screen.getByLabelText('Pilih indikator'), indikatorId);
    await user.click(screen.getByRole('button', { name: 'Tambah' }));
    if (nilai !== undefined) {
        const input = screen.getAllByLabelText('Target baru').at(-1)!;
        fireEvent.change(input, { target: { value: nilai } });
    }
};
const bukaModal = async (user: ReturnType<typeof userEvent.setup>, alasan = 'Revisi sesuai surat Eselon I.') => {
    await user.click(screen.getByRole('button', { name: 'Simpan revisi' }));
    const dialog = screen.getByRole('dialog');
    if (alasan) await user.type(within(dialog).getByLabelText(/Alasan perubahan/), alasan);
    return dialog;
};
const kirim = async (user: ReturnType<typeof userEvent.setup>, alasan?: string) => {
    const dialog = await bukaModal(user, alasan);
    await user.click(within(dialog).getByRole('button', { name: 'Simpan revisi' }));
    return dialog;
};
const baris = (kode: string) => screen.getByText(kode, { selector: 'span.font-mono' }).closest('li') as HTMLElement;

it('FE-1 linimasa menampilkan versi terbaru di atas, lencana, chip perubahan, dokumen, dan unduhan route PK', () => {
    const versi = [
        v1,
        revisiVersi(2, [{ indikator_id: 'ind-1', kode: 'IKU-1', lama: '80', baru: '85', desimal_tampilan: 2 }], { berkas: [berkas('berkas-v2', 'pk-akhir.pdf')] }),
        revisiVersi(3, [
            { indikator_id: 'ind-2', kode: 'IKU-2', lama: '60', baru: '65', desimal_tampilan: 2 },
            { indikator_id: 'ind-3', kode: 'IKU-3', lama: null, baru: '50', desimal_tampilan: 0 },
            { indikator_id: 'ind-4', kode: 'IKU-4', lama: '70', baru: null, desimal_tampilan: 0 },
        ]),
        revisiVersi(4, []),
    ];
    render(<Show {...props({ versi })} />);

    expect(screen.getAllByText(/^Versi \d$/).map((item) => item.textContent)).toEqual(['Versi 4', 'Versi 3', 'Versi 2', 'Versi 1']);
    expect(screen.getAllByText('PK akhir')).toHaveLength(1);
    expect(screen.getByText('PK akhir').closest('li')!.textContent).toContain('Versi 4');
    expect(screen.getByText('PK awal').closest('li')!.textContent).toContain('Versi 1');
    expect(screen.getByText('IKU-1 80,00 → 85,00')).toBeTruthy();
    // Revisi dokumen yang menyerap koreksi snapshot tetap menampilkan selisihnya, bukan label dokumen saja.
    expect(screen.getByText('IKU-2 60,00 → 65,00')).toBeTruthy();
    expect(screen.getByText('IKU-3 masuk PK, target 50')).toBeTruthy();
    expect(screen.getByText('IKU-4 tidak lagi aktif')).toBeTruthy();
    expect(screen.getByText('Target sama dengan versi PK sebelumnya').closest('li')!.textContent).toContain('Versi 4');
    expect(screen.getByText(/^PK\/2026\/001 · /)).toBeTruthy();
    expect(screen.getAllByText(/^Surat B-2\/2026 · /)).toHaveLength(1);
    expect(screen.getAllByText('Berlaku mulai Triwulan III')).toHaveLength(3);
    expect(screen.getAllByRole('link', { name: 'Unduh file' }).map((link) => link.getAttribute('href'))).toEqual([
        '/perjanjian-kinerja/pk-1/berkas/berkas-v2/unduh', '/perjanjian-kinerja/pk-1/berkas/berkas-awal/unduh',
    ]);
});

it('FE-1 versi 1 tanpa nomor dan tanggal PK memakai nama dokumen tahunan; tanpa izin baca tidak ada tautan unduh', () => {
    render(<Show {...props({ pk: { ...pk, nomor_pk: null, tanggal_pk: null }, can: { update: false, delete_berkas: false, read_berkas: false } })} />);
    expect(screen.getByText('Dokumen PK 2026')).toBeTruthy();
    expect(screen.getByText('pk-awal.pdf')).toBeTruthy();
    expect(screen.queryByRole('link', { name: 'Unduh file' })).toBeNull();
});

it('FE-2 tanpa izin revisi tombol tidak ada dan kartu kosong tidak dirender', () => {
    const { rerender } = render(<Show {...props({ can: { update: false, delete_berkas: false } })} />);
    expect(screen.getByRole('heading', { name: 'Versi PK' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Revisi PK' })).toBeNull();

    rerender(<Show {...props({ versi: [], can: { update: false, delete_berkas: false } })} />);
    expect(screen.queryByRole('heading', { name: 'Versi PK' })).toBeNull();

    rerender(<Show {...props({ versi: [] })} />);
    expect(screen.getByText('Belum ada revisi.')).toBeTruthy();
});

it('FE-2 tombol nonaktif selama panel terbuka dan Batal mengembalikan fokus ke tombol', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    expect(tombolRevisi().disabled).toBe(true);
    expect(tombolRevisi().hasAttribute('aria-expanded')).toBe(false);

    await user.click(screen.getByRole('button', { name: 'Batal' }));
    expect(screen.queryByRole('heading', { name: /Revisi baru/ })).toBeNull();
    expect(tombolRevisi().disabled).toBe(false);
    expect(document.activeElement).toBe(tombolRevisi());
});

it('FE-2 fokus pindah ke isian baris baru setelah Tambah dan ke pilihan indikator setelah baris dibatalkan', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-1');
    expect(document.activeElement).toBe(within(baris('IKU-1')).getByLabelText('Target baru'));
    // Nama aksesibel memuat kode agar pembaca layar dapat membedakan baris.
    await tambahTarget(user, 'ind-2');
    expect(screen.getAllByRole('textbox', { name: /^Target baru/ }).map((input) => input.getAttribute('aria-label'))).toEqual(['Target baru IKU-1', 'Target baru IKU-2']);

    await user.click(screen.getByRole('button', { name: 'Batalkan perubahan IKU-1' }));
    expect(document.activeElement).toBe(screen.getByLabelText('Pilih indikator'));
});

it('FE-3 mengirim revisi target dengan payload persis lalu menutup panel saat sukses', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    expect(screen.getByRole('heading', { name: 'Revisi baru (versi 2)' })).toBeTruthy();
    expect(screen.getByText('Berlaku mulai Triwulan III (perkiraan)')).toBeTruthy();
    expect(document.activeElement).toBe(screen.getByLabelText(/Nomor surat Eselon I/));

    await user.type(screen.getByLabelText(/Nomor surat Eselon I/), 'B-12/2026');
    fireEvent.change(screen.getByLabelText(/Tanggal surat/), { target: { value: '2026-11-01' } });
    await tambahTarget(user, 'ind-1', '90');
    expect(within(baris('IKU-1')).getByText('Target berlaku 85,00 persen')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
    const file = new File(['%PDF-1.4'], 'pk-akhir.pdf', { type: 'application/pdf' });
    await user.upload(screen.getByLabelText('File lampiran 1'), file);
    await user.type(screen.getByLabelText('Keterangan (opsional)'), 'PK akhir');

    await user.click(screen.getByRole('button', { name: 'Simpan revisi' }));
    expect(screen.getByRole('dialog', { name: 'Simpan revisi PK versi 2' })).toBeTruthy();
    const dialog = screen.getByRole('dialog');
    await user.type(within(dialog).getByLabelText(/Alasan perubahan/), 'Revisi sesuai surat Eselon I.');
    await user.click(within(dialog).getByRole('button', { name: 'Simpan revisi' }));

    expect(router.post).toHaveBeenCalledTimes(1);
    expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/perjanjian-kinerja/pk-1/versi');
    expect(opsiKirim()).toMatchObject({ forceFormData: true, preserveScroll: true });
    expect(dataKirim()).toStrictEqual({
        nomor_surat: 'B-12/2026', tanggal_surat: '2026-11-01', alasan: 'Revisi sesuai surat Eselon I.',
        expected_nomor_versi: 0, expected_dasar: 'a'.repeat(64),
        target: [{ indikator_id: 'ind-1', nilai: '90' }], lampiran: [{ file, keterangan: 'PK akhir' }],
    });

    await act(async () => {
        await opsiKirim().onSuccess({ component: 'PerjanjianKinerja/Show', props: { errors: {} }, url: '/perjanjian-kinerja/pk-1', version: null });
    });
    expect(screen.queryByRole('heading', { name: /Revisi baru/ })).toBeNull();
    expect(document.activeElement).toBe(tombolRevisi());
});

it('FE-3 nomor versi baru mengikuti token versi dari server', async () => {
    const user = userEvent.setup();
    render(<Show {...props({ versi: [v1, revisiVersi(2, [])], revisi: { ...revisi, expected_nomor_versi: 2 } })} />);
    await bukaPanel(user);
    expect(screen.getByRole('heading', { name: 'Revisi baru (versi 3)' })).toBeTruthy();
});

it('FE-4 tanpa baris target payload tidak membawa key target', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    const dialog = await kirim(user);
    expect(within(dialog).getByText('Revisi dokumen saja')).toBeTruthy();
    expect(dataKirim()).not.toHaveProperty('target');
    expect(dataKirim()).not.toHaveProperty('konfirmasi_pengukuran_sebelumnya');
});

it('FE-4 baris yang kembali sama dengan target berlaku tetap terkirim dan dihitung revisi dokumen', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-1', '85,0');
    expect(screen.queryByText(/Dampak mulai/)).toBeNull();
    const dialog = await bukaModal(user);
    expect(within(dialog).getByText('Revisi dokumen saja')).toBeTruthy();
    await user.click(within(dialog).getByRole('button', { name: 'Simpan revisi' }));
    expect(dataKirim().target).toEqual([{ indikator_id: 'ind-1', nilai: '85,0' }]);
});

it('FE-4 hanya baris yang berbeda dihitung berubah, termasuk nilai tak terbaca dan kosong', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-1', '90');
    await tambahTarget(user, 'ind-2');
    expect(screen.getByText('Dampak mulai Triwulan III')).toBeTruthy();
    let dialog = await bukaModal(user, '');
    expect(within(dialog).getByText('1 target berubah · berlaku mulai Triwulan III')).toBeTruthy();
    await user.click(within(dialog).getByRole('button', { name: 'Batal' }));

    fireEvent.change(within(baris('IKU-1')).getByLabelText('Target baru'), { target: { value: '8x' } });
    fireEvent.change(within(baris('IKU-2')).getByLabelText('Target baru'), { target: { value: '' } });
    dialog = await bukaModal(user, '');
    expect(within(dialog).getByText('2 target berubah · berlaku mulai Triwulan III')).toBeTruthy();
});

it('FE-4 nol di depan dan di belakang pecahan dinormalisasi sebelum dibandingkan', async () => {
    const user = userEvent.setup();
    render(<Show {...props({ revisi: { ...revisi, target: [{ ...revisi.target[0], nilai: '85.5' }] } })} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-1', '085,50');
    const dialog = await bukaModal(user, '');
    expect(within(dialog).getByText('Revisi dokumen saja')).toBeTruthy();
});

it('FE-5 target terkunci hanya menampilkan alasan dan revisi dokumen tetap dapat dikirim', async () => {
    const user = userEvent.setup();
    render(<Show {...props({ revisi: { ...revisi, dapat_ubah_target: false, alasan_tidak_dapat_ubah_target: 'Renstra nonaktif atau diarsipkan.' } })} />);
    await bukaPanel(user);
    expect(screen.queryByLabelText('Pilih indikator')).toBeNull();
    expect(screen.getByText('Renstra nonaktif atau diarsipkan.')).toBeTruthy();
    await kirim(user);
    expect(router.post).toHaveBeenCalledTimes(1);
    expect(dataKirim()).not.toHaveProperty('target');
});

it('FE-5 target terkunci setelah panel terbuka: baris yang sudah ada tetap tampil dan dapat dibatalkan', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-1', '90');

    // Respons penolakan membawa props terbaru: jadwal ditutup aktor lain sehingga target tidak lagi dapat diubah.
    rerender(<Show {...props({ revisi: { ...revisi, dapat_ubah_target: false, alasan_tidak_dapat_ubah_target: 'Jadwal tahunan sudah ditutup.' } })} />);
    expect(screen.getByText('Jadwal tahunan sudah ditutup.')).toBeTruthy();
    expect(screen.queryByLabelText('Pilih indikator')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Tambah' })).toBeNull();
    expect(within(baris('IKU-1')).getByLabelText<HTMLInputElement>('Target baru').value).toBe('90');

    await user.click(screen.getByRole('button', { name: 'Batalkan perubahan IKU-1' }));
    expect(screen.queryByText('IKU-1', { selector: 'span.font-mono' })).toBeNull();
    expect(document.activeElement).toBe(screen.getByRole('heading', { name: 'Target yang berubah (opsional)' }));
    await kirim(user);
    expect(dataKirim()).not.toHaveProperty('target');
});

it('FE-6 konfirmasi pengukuran sebelumnya hanya muncul di modal untuk baris berubah yang terdampak', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    const panel = await bukaPanel(user);
    await tambahTarget(user, 'ind-2', '65');
    expect(within(panel).queryByText(/Saya mengerti/)).toBeNull();

    const dialog = await bukaModal(user);
    expect(within(dialog).getByText('IKU-2: Triwulan II')).toBeTruthy();
    expect(within(dialog).getByText('Versi 1')).toBeTruthy();
    const konfirmasi = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' });
    expect(konfirmasi.disabled).toBe(true);
    await user.click(within(dialog).getByLabelText(/Saya mengerti/));
    expect(konfirmasi.disabled).toBe(false);
    await user.click(konfirmasi);
    expect(dataKirim().konfirmasi_pengukuran_sebelumnya).toBe(true);
});

it('FE-6 konfirmasi direset setiap modal dibuka sehingga daftar harus dikonfirmasi ulang', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-2', '65');
    let dialog = await bukaModal(user);
    await user.click(within(dialog).getByLabelText(/Saya mengerti/));
    await user.click(within(dialog).getByRole('button', { name: 'Batal' }));

    dialog = await bukaModal(user, '');
    expect(within(dialog).getByLabelText<HTMLInputElement>(/Saya mengerti/).checked).toBe(false);
    expect(within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' }).disabled).toBe(true);
});

it('FE-6 baris yang sama atau indikator lain tidak meminta konfirmasi', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-2');
    await tambahTarget(user, 'ind-1', '90');
    const dialog = await kirim(user);
    expect(within(dialog).queryByText(/Saya mengerti/)).toBeNull();
    expect(dataKirim()).not.toHaveProperty('konfirmasi_pengukuran_sebelumnya');
});

it('FE-6 nilai panjang dibandingkan tanpa kehilangan presisi', async () => {
    const user = userEvent.setup();
    render(<Show {...props({ revisi: { ...revisi, target: [revisi.target[0], { ...revisi.target[1], nilai: '9007199254740992' }] } })} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-2', '9007199254740993');
    const dialog = await bukaModal(user);
    await user.click(within(dialog).getByLabelText(/Saya mengerti/));
    await user.click(within(dialog).getByRole('button', { name: 'Simpan revisi' }));
    expect(dataKirim().konfirmasi_pengukuran_sebelumnya).toBe(true);
});

it('FE-7 galat indeks target tampil di baris yang sesuai', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    // Baris ke-0 sama dengan target berlaku (dibuang server); galat baris ke-1 tetap berindeks 1.
    await tambahTarget(user, 'ind-2');
    await tambahTarget(user, 'ind-1', '8x');
    await kirim(user);
    act(() => { opsiKirim().onError({ 'target.1.nilai': 'Gunakan angka desimal nonnegatif.' }); opsiKirim().onFinish({}); });

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(within(baris('IKU-1')).getByText('Gunakan angka desimal nonnegatif.')).toBeTruthy();
    expect(within(baris('IKU-2')).queryByText('Gunakan angka desimal nonnegatif.')).toBeNull();
    expect(screen.getByText('Periksa isian yang ditandai.')).toBeTruthy();
});

it('FE-7 membatalkan baris setelah penolakan tidak memindahkan galat ke baris lain', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-1', '8x');
    await tambahTarget(user, 'ind-2');
    await kirim(user);
    act(() => { opsiKirim().onError({ 'target.0.nilai': 'Gunakan angka desimal nonnegatif.' }); opsiKirim().onFinish({}); });
    expect(within(baris('IKU-1')).getByText('Gunakan angka desimal nonnegatif.')).toBeTruthy();

    // IKU-2 bergeser ke indeks 0; galat milik IKU-1 tidak boleh ikut berpindah ke baris itu.
    await user.click(screen.getByRole('button', { name: 'Batalkan perubahan IKU-1' }));
    expect(screen.queryByText('Gunakan angka desimal nonnegatif.')).toBeNull();
    expect(screen.queryByText('Periksa isian yang ditandai.')).toBeNull();
});

it('FE-7 membatalkan baris tanpa galat target tidak menghapus galat field lain', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await tambahTarget(user, 'ind-1', '90');
    await kirim(user);
    act(() => { opsiKirim().onError({ lampiran: 'Lampiran dokumen PK bertanda tangan wajib diunggah.' }); opsiKirim().onFinish({}); });
    expect(screen.getByText('Lampiran dokumen PK bertanda tangan wajib diunggah.')).toBeTruthy();

    await user.click(screen.getByRole('button', { name: 'Batalkan perubahan IKU-1' }));
    expect(screen.getByText('Lampiran dokumen PK bertanda tangan wajib diunggah.')).toBeTruthy();
    expect(screen.getByText('Periksa isian yang ditandai.')).toBeTruthy();
});

it('FE-7 penolakan token memfokuskan alert panel, mempertahankan isian, lalu mengirim ulang dengan token terbaru', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<Show {...props()} />);
    await bukaPanel(user);
    await user.type(screen.getByLabelText(/Nomor surat Eselon I/), 'B-12/2026');
    await tambahTarget(user, 'ind-1', '90');
    await kirim(user);
    act(() => {
        opsiKirim().onError({ expected_dasar: 'Target dasar PK telah berubah sejak halaman dibuka. Muat ulang halaman sebelum menyimpan revisi.' });
        opsiKirim().onFinish({});
    });

    expect(screen.queryByRole('dialog')).toBeNull();
    const judul = screen.getByRole('heading', { name: 'Revisi belum tersimpan' });
    expect(document.activeElement).toBe(judul);
    expect(judul.closest('[role=alert]')!.textContent).toContain('Target dasar PK telah berubah');
    expect(screen.getByLabelText<HTMLInputElement>(/Nomor surat Eselon I/).value).toBe('B-12/2026');

    // Redirect penolakan membawa props terbaru: target berlaku dan kedua token sudah berganti.
    const segar: RevisiPk = { ...revisi, expected_nomor_versi: 2, expected_dasar: 'b'.repeat(64), target: [{ ...revisi.target[0], nilai: '88' }, revisi.target[1]] };
    rerender(<Show {...props({ versi: [v1, revisiVersi(2, [])], revisi: segar })} />);
    expect(within(baris('IKU-1')).getByText('Target berlaku 88,00 persen')).toBeTruthy();
    expect(within(baris('IKU-1')).getByLabelText<HTMLInputElement>('Target baru').value).toBe('90');

    await user.click(screen.getByRole('button', { name: 'Simpan revisi' }));
    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Simpan revisi' }));
    expect(dataKirim(1)).toMatchObject({ expected_nomor_versi: 2, expected_dasar: 'b'.repeat(64), alasan: 'Revisi sesuai surat Eselon I.' });
});

it('FE-7 respons 403 menampilkan izin ditolak tanpa mengunci simpan dan tanpa galat field lama', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await kirim(user);
    act(() => { opsiKirim(0).onError({ nomor_surat: 'Nomor surat persetujuan wajib diisi.' }); opsiKirim(0).onFinish({}); });
    expect(screen.getByText('Periksa isian yang ditandai.')).toBeTruthy();

    await kirim(user, '');
    act(() => { opsiKirim(1).onHttpException({ status: 403, data: {}, headers: {} }); opsiKirim(1).onFinish({}); });

    expect(screen.queryByRole('dialog')).toBeNull();
    const alert = screen.getByRole('heading', { name: 'Revisi belum tersimpan' }).closest('[role=alert]')!;
    expect(alert.textContent).toContain('Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.');
    expect(alert.textContent).not.toContain('belum dapat dipastikan');
    expect(alert.textContent).not.toContain('Periksa isian yang ditandai.');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' }).disabled).toBe(false);
});

it.each([
    ['respons 500', (opsi: ReturnType<typeof opsiKirim>) => opsi.onHttpException({ status: 500, data: {}, headers: {} })],
    ['galat jaringan', (opsi: ReturnType<typeof opsiKirim>) => opsi.onNetworkError()],
])('FE-7 %s menampilkan hasil belum dapat dipastikan dan mengunci simpan', async (_, gagal) => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await kirim(user);
    act(() => { gagal(opsiKirim()); opsiKirim().onFinish({}); });

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByRole('heading', { name: 'Revisi belum tersimpan' }).closest('[role=alert]')!.textContent)
        .toContain('Hasil penyimpanan belum dapat dipastikan. Muat ulang halaman PK sebelum mencoba lagi.');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' }).disabled).toBe(true);
});

it('FE-7 galat field menutup modal, galat alasan saja tetap di modal, respons campuran menampilkan keduanya', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    await user.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
    await kirim(user);
    act(() => { opsiKirim(0).onError({ nomor_surat: 'Nomor surat persetujuan wajib diisi.' }); opsiKirim(0).onFinish({}); });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByLabelText(/Nomor surat Eselon I/).getAttribute('aria-describedby')).toBeTruthy();
    expect(screen.getByText('Nomor surat persetujuan wajib diisi.')).toBeTruthy();
    expect(screen.getByRole('heading', { name: 'Revisi belum tersimpan' }).closest('[role=alert]')!.textContent).toContain('Periksa isian yang ditandai.');

    let dialog = await bukaModal(user, '');
    await user.click(within(dialog).getByRole('button', { name: 'Simpan revisi' }));
    act(() => { opsiKirim(1).onError({ alasan: 'Alasan revisi wajib diisi.' }); opsiKirim(1).onFinish({}); });
    dialog = screen.getByRole('dialog');
    expect(within(dialog).getByText('Alasan revisi wajib diisi.')).toBeTruthy();

    await user.click(within(dialog).getByRole('button', { name: 'Simpan revisi' }));
    act(() => {
        opsiKirim(2).onError({ alasan: 'Alasan revisi wajib diisi.', 'lampiran.0.file': 'Pilih file lampiran.' });
        opsiKirim(2).onFinish({});
    });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByText('Pilih file lampiran.')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Simpan revisi' }));
    expect(within(screen.getByRole('dialog')).getByText('Alasan revisi wajib diisi.')).toBeTruthy();
});

it('FE-8 peringatan tabel target menautkan ke panel revisi hanya bila boleh merevisi', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<Show {...props({ jadwal_status: 'aktif' })} />);
    const peringatan = screen.getByText(/Perubahan resmi dari surat Eselon I:/);
    await user.click(within(peringatan).getByRole('button', { name: 'Revisi PK' }));
    expect(screen.getByRole('heading', { name: /Revisi baru/ })).toBeTruthy();
    expect(document.activeElement).toBe(screen.getByLabelText(/Nomor surat Eselon I/));

    rerender(<Show {...props({ jadwal_status: 'aktif', can: { update: false, delete_berkas: false } })} />);
    expect(screen.queryByText(/Perubahan resmi dari surat Eselon I:/)).toBeNull();
    expect(screen.queryByRole('button', { name: 'Revisi PK' })).toBeNull();
});

it('FE-9 klik ganda hanya mengirim sekali dan tombol nonaktif selama diproses', async () => {
    const user = userEvent.setup();
    render(<Show {...props()} />);
    await bukaPanel(user);
    const dialog = await bukaModal(user);
    const konfirmasi = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' });
    await user.dblClick(konfirmasi);
    expect(router.post).toHaveBeenCalledTimes(1);

    act(() => opsiKirim().onStart({}));
    expect(within(screen.getByRole('dialog')).getByRole<HTMLButtonElement>('button', { name: 'Simpan revisi' }).disabled).toBe(true);
    expect(screen.getAllByRole<HTMLButtonElement>('button', { name: 'Simpan revisi', hidden: true }).every((tombol) => tombol.disabled)).toBe(true);
});
