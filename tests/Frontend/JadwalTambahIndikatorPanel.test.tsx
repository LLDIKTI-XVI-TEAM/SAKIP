import { router } from '@inertiajs/react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { TambahIndikatorPanel } from '@/Components/Jadwal/TambahIndikatorPanel';
import type { JadwalPeriode } from '@/types/periode-jadwal';

const jendela = (periodeId: string, nama: string, urutan: number, bulanMulai: number, bulanSelesai: number, hariSelesai: number): JadwalPeriode => ({
    periode_id: periodeId,
    periode_revisi: 1,
    nama,
    urutan,
    aktif: true,
    is_nilai_akhir: false,
    bulan_mulai: bulanMulai,
    hari_mulai: 1,
    bulan_selesai: bulanSelesai,
    hari_selesai: hariSelesai,
    pengisian_mulai: '2026-03-01',
    pengisian_selesai: '2026-03-15',
});
const props = {
    jadwalId: 'jadwal-1',
    tahun: 2026,
    periode: [jendela('periode-2', 'Triwulan II', 2, 4, 6, 30), jendela('periode-1', 'Triwulan I', 1, 1, 3, 31)],
    kandidat: [
        { id: 'indikator-a', kode: 'IKU-A', nama: 'Indikator A', target_terisi: true, dapat_dibekukan: true },
        { id: 'indikator-b', kode: 'IKU-B', nama: 'Indikator B', target_terisi: true, dapat_dibekukan: true },
        { id: 'indikator-c', kode: 'IKU-C', nama: 'Indikator C', target_terisi: false, dapat_dibekukan: true },
        { id: 'indikator-d', kode: 'IKU-D', nama: 'Indikator D', target_terisi: true, dapat_dibekukan: false },
    ],
    periodeMulaiDefault: 'periode-2',
    perjanjianKinerjaId: 'pk-1',
};
const KALIMAT_TW2 = 'Periode sebelum Triwulan II tampil Tidak berlaku. Pilihan tidak dapat diubah setelah disimpan. Jendela pengisian PIC tidak berubah.';

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native; fokus trap tetap diverifikasi lewat browser.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

/** Opsi yang diteruskan useForm asli ke router; callback-nya mengisi state form seperti respons server. */
const opsiKirim = (index = 0) => vi.mocked(router.post).mock.calls[index][2] as {
    onSuccess: (page: unknown) => Promise<unknown>;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (response: { status: number; data: unknown; headers: Record<string, string> }) => boolean | void;
    onNetworkError: () => void;
};

it('menampilkan aksi sesuai kelengkapan tiap indikator', () => {
    const { unmount } = render(<TambahIndikatorPanel {...props} />);

    expect(screen.getByText('Indikator belum masuk jadwal')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Tambahkan IKU-A' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Tambahkan IKU-B' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Tambahkan IKU-C' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Tambahkan IKU-D' })).toBeNull();
    expect(screen.getByText('Target PK belum diisi')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Isi target' }).getAttribute('href')).toBe('/perjanjian-kinerja/pk-1');
    expect(screen.getByText('Perbaiki data indikator di Sasaran & Indikator')).toBeTruthy();
    unmount();

    render(<TambahIndikatorPanel {...props} perjanjianKinerjaId={null} />);
    expect(screen.getByText('Target PK belum diisi')).toBeTruthy();
    expect(screen.queryByRole('link', { name: 'Isi target' })).toBeNull();
});

it('membuka modal dengan periode default dan mengirim tepat tiga field', async () => {
    const user = userEvent.setup();
    render(<TambahIndikatorPanel {...props} />);

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-A' }));
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByText('Tambahkan IKU-A ke jadwal 2026')).toBeTruthy();
    const select = within(dialog).getByLabelText<HTMLSelectElement>(/Periode mulai berlaku/);
    expect(select.value).toBe('periode-2');
    // Opsi mengikuti urutan periode, bukan urutan props.
    expect(within(select).getAllByRole<HTMLOptionElement>('option').filter((option) => option.value !== '').map((option) => option.value)).toEqual(['periode-1', 'periode-2']);
    expect(within(dialog).getByText(KALIMAT_TW2)).toBeTruthy();

    await user.selectOptions(select, 'periode-1');
    expect(within(dialog).getByText(/^Periode sebelum Triwulan I tampil Tidak berlaku\./)).toBeTruthy();
    await user.type(within(dialog).getByRole('textbox'), 'Indikator baru ditetapkan resmi.');
    await user.click(within(dialog).getByRole('button', { name: 'Tambahkan' }));

    expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/jadwal/jadwal-1/indikator');
    expect(vi.mocked(router.post).mock.calls[0][1]).toStrictEqual({ indikator_id: 'indikator-a', periode_mulai_id: 'periode-1', alasan: 'Indikator baru ditetapkan resmi.' });
});

it('konfirmasi nonaktif saat alasan kosong atau periode belum dipilih', async () => {
    const user = userEvent.setup();
    render(<TambahIndikatorPanel {...props} periodeMulaiDefault={null} />);

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-A' }));
    const dialog = screen.getByRole('dialog');
    const konfirmasi = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Tambahkan' });
    expect(konfirmasi.disabled).toBe(true);
    await user.type(within(dialog).getByRole('textbox'), 'Alasan resmi.');
    expect(konfirmasi.disabled).toBe(true);
    await user.selectOptions(within(dialog).getByLabelText(/Periode mulai berlaku/), 'periode-2');
    expect(konfirmasi.disabled).toBe(false);
    await user.clear(within(dialog).getByRole('textbox'));
    expect(konfirmasi.disabled).toBe(true);
});

it('error server tampil di modal dan error periode terpasang di Select', async () => {
    const user = userEvent.setup();
    render(<TambahIndikatorPanel {...props} />);

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-A' }));
    const dialog = screen.getByRole('dialog');
    await user.type(within(dialog).getByRole('textbox'), 'Alasan resmi.');
    await user.click(within(dialog).getByRole('button', { name: 'Tambahkan' }));
    act(() => opsiKirim().onError({
        indikator_id: 'Indikator sudah masuk jadwal ini.',
        jadwal: 'Data sedang diubah pengguna lain. Muat ulang detail jadwal lalu coba lagi.',
        periode_mulai_id: 'Periode mulai berlaku harus salah satu periode jadwal ini.',
    }));

    expect(within(dialog).getByText('Indikator sudah masuk jadwal ini.')).toBeTruthy();
    expect(within(dialog).getByText('Data sedang diubah pengguna lain. Muat ulang detail jadwal lalu coba lagi.')).toBeTruthy();
    expect(within(dialog).getByLabelText(/Periode mulai berlaku/).getAttribute('aria-invalid')).toBe('true');
    expect(within(dialog).getByText('Periode mulai berlaku harus salah satu periode jadwal ini.')).toBeTruthy();
});

it('membuka baris lain mereset alasan, error, dan indikator terpilih', async () => {
    const user = userEvent.setup();
    render(<TambahIndikatorPanel {...props} />);

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-A' }));
    let dialog = screen.getByRole('dialog');
    await user.selectOptions(within(dialog).getByLabelText(/Periode mulai berlaku/), 'periode-1');
    await user.type(within(dialog).getByRole('textbox'), 'Alasan untuk A.');
    await user.click(within(dialog).getByRole('button', { name: 'Tambahkan' }));
    act(() => opsiKirim().onError({ indikator_id: 'Indikator sudah masuk jadwal ini.' }));
    expect(within(dialog).getByText('Indikator sudah masuk jadwal ini.')).toBeTruthy();
    await user.click(within(dialog).getByRole('button', { name: 'Batal' }));

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-B' }));
    dialog = screen.getByRole('dialog');
    expect(within(dialog).getByText('Tambahkan IKU-B ke jadwal 2026')).toBeTruthy();
    expect(within(dialog).getByRole<HTMLTextAreaElement>('textbox').value).toBe('');
    expect(within(dialog).getByLabelText<HTMLSelectElement>(/Periode mulai berlaku/).value).toBe('periode-2');
    expect(within(dialog).queryByText('Indikator sudah masuk jadwal ini.')).toBeNull();
    await user.type(within(dialog).getByRole('textbox'), 'Alasan untuk B.');
    await user.click(within(dialog).getByRole('button', { name: 'Tambahkan' }));

    expect(vi.mocked(router.post).mock.calls[1][1]).toStrictEqual({ indikator_id: 'indikator-b', periode_mulai_id: 'periode-2', alasan: 'Alasan untuk B.' });
});

it('gangguan jaringan menandai hasil belum terkonfirmasi dan menahan penambahan berikutnya', async () => {
    const user = userEvent.setup();
    render(<TambahIndikatorPanel {...props} />);

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-A' }));
    const dialog = screen.getByRole('dialog');
    await user.type(within(dialog).getByRole('textbox'), 'Alasan resmi.');
    await user.click(within(dialog).getByRole('button', { name: 'Tambahkan' }));
    act(() => opsiKirim().onNetworkError());

    expect(screen.getByRole('alert').textContent).toContain('Hasil penambahan belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.');
    expect(screen.getByRole('link', { name: 'Periksa jadwal terbaru' }).getAttribute('href')).toBe('/jadwal/jadwal-1');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Tambahkan IKU-B' }).disabled).toBe(true);
});

it('alasan penambahan yang sukses tidak terbawa ke baris berikutnya', async () => {
    const user = userEvent.setup();
    render(<TambahIndikatorPanel {...props} />);

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-A' }));
    let dialog = screen.getByRole('dialog');
    await user.type(within(dialog).getByRole('textbox'), 'Alasan untuk A.');
    await user.click(within(dialog).getByRole('button', { name: 'Tambahkan' }));
    // Setelah sukses, useForm menjadikan data terkirim sebagai default baru; panel tetap ter-mount karena state dipertahankan.
    await act(async () => {
        await opsiKirim().onSuccess({});
    });

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-B' }));
    dialog = screen.getByRole('dialog');
    expect(within(dialog).getByRole<HTMLTextAreaElement>('textbox').value).toBe('');
    expect(within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Tambahkan' }).disabled).toBe(true);
});

it.each([
    [401, 'Autentikasi diperlukan'],
    [419, 'Verifikasi keamanan diperlukan'],
])('respons %i menjaga modal terbuka agar input dapat disalin dan menahan konfirmasi', async (status, judul) => {
    const user = userEvent.setup();
    render(<TambahIndikatorPanel {...props} />);

    await user.click(screen.getByRole('button', { name: 'Tambahkan IKU-A' }));
    const dialog = screen.getByRole<HTMLDialogElement>('dialog');
    await user.type(within(dialog).getByRole('textbox'), 'Alasan resmi.');
    await user.click(within(dialog).getByRole('button', { name: 'Tambahkan' }));
    act(() => {
        opsiKirim().onHttpException({ status, data: {}, headers: {} });
    });

    expect(dialog.open).toBe(true);
    expect(screen.getAllByText(judul)).toHaveLength(1);
    expect(within(dialog).getByText(judul)).toBeTruthy();
    expect(within(dialog).getByRole<HTMLTextAreaElement>('textbox').value).toBe('Alasan resmi.');
    expect(within(dialog).getByLabelText<HTMLSelectElement>(/Periode mulai berlaku/).value).toBe('periode-2');
    expect(within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Tambahkan' }).disabled).toBe(true);
    expect(screen.queryByText('Hasil penambahan belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.')).toBeNull();
});
